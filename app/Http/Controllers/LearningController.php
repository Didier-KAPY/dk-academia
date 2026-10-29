<?php

namespace App\Http\Controllers;

use App\Http\Requests\Learning\SubmitEvaluationRequest;
use App\Models\Enrollment;
use App\Models\Evaluation;
use App\Models\EvaluationAttempt;
use App\Models\EvaluationQuestion;
use App\Models\Lesson;
use App\Models\Progression;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LearningController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $enrollments = $user->enrollments()
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->with([
                'training',
                'training.modules.lessons' => fn (Relation $query): Relation => $query->where('is_published', true)->orderBy('sort_order'),
                'training.modules.lessons.progressions' => fn (Relation $query): Relation => $query->where('user_id', $user->id),
                'training.evaluations' => fn (Relation $query): Relation => $query->where('is_published', true)->orderBy('opens_at'),
                'session.evaluations' => fn (Relation $query): Relation => $query->where('is_published', true)->orderBy('opens_at'),
            ])
            ->orderByDesc('registered_at')
            ->get();

        return view('learning.index', compact('enrollments'));
    }

    public function showLesson(Request $request, Lesson $lesson): View
    {
        $enrollment = $this->authorizedEnrollmentForLesson($request->user(), $lesson);
        $lesson->load('module.training');
        $progression = $request->user()->progressions()->where('lesson_id', $lesson->id)->first();

        return view('learning.lesson', compact('lesson', 'enrollment', 'progression'));
    }

    public function completeLesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $enrollment = $this->authorizedEnrollmentForLesson($request->user(), $lesson);
        abort_unless(in_array($enrollment->status, ['confirmed', 'in_progress'], true), 403);
        $data = $request->validate(['seconds_watched' => ['nullable', 'integer', 'min:0', 'max:86400']]);

        DB::transaction(function () use ($request, $lesson, $enrollment, $data): void {
            Progression::updateOrCreate(
                ['user_id' => $request->user()->id, 'lesson_id' => $lesson->id],
                [
                    'completion_percent' => 100,
                    'seconds_watched' => $data['seconds_watched'] ?? 0,
                    'completed_at' => now(),
                ],
            );

            if ($enrollment->status === 'confirmed') {
                $enrollment->update(['status' => 'in_progress']);
            }
        });

        return redirect()->route('learning.lessons.show', $lesson)->with('status', 'Votre progression a été enregistrée.');
    }

    public function showEvaluation(Request $request, Evaluation $evaluation): View
    {
        $this->authorizeEvaluation($request->user(), $evaluation);
        $attempt = EvaluationAttempt::query()->firstOrCreate(
            ['evaluation_id' => $evaluation->id, 'user_id' => $request->user()->id, 'attempt_number' => 1],
            ['status' => 'in_progress', 'started_at' => now()],
        );
        $questions = $evaluation->questions()
            ->get(['id', 'evaluation_id', 'prompt', 'question_type', 'options', 'points', 'sort_order']);

        return view('learning.evaluation', compact('evaluation', 'attempt', 'questions'));
    }

    public function submitEvaluation(SubmitEvaluationRequest $request, Evaluation $evaluation): RedirectResponse
    {
        $this->authorizeEvaluation($request->user(), $evaluation);
        $attempt = EvaluationAttempt::query()
            ->where('evaluation_id', $evaluation->id)
            ->where('user_id', $request->user()->id)
            ->where('attempt_number', 1)
            ->firstOrFail();
        $questions = $evaluation->questions()->get();
        $answers = $request->validated('answers');
        $expectedIds = $questions->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $submittedIds = array_map('strval', array_keys($answers));

        if (array_diff($expectedIds, $submittedIds) !== [] || array_diff($submittedIds, $expectedIds) !== []) {
            throw ValidationException::withMessages(['answers' => 'Veuillez répondre uniquement aux questions de cette évaluation.']);
        }

        DB::transaction(function () use ($attempt, $questions, $answers): void {
            $lockedAttempt = EvaluationAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($lockedAttempt->status === 'submitted') {
                throw ValidationException::withMessages(['answers' => 'Cette évaluation a déjà été remise.']);
            }

            $hasManualAnswers = $questions->contains(fn (EvaluationQuestion $question): bool => $question->question_type === 'short_answer');
            $score = 0.0;

            foreach ($questions as $question) {
                $answer = trim($answers[$question->id]);
                $awardedPoints = null;

                if ($question->question_type === 'multiple_choice') {
                    if (! in_array($answer, $question->options ?? [], true)) {
                        throw ValidationException::withMessages(["answers.{$question->id}" => 'Choisissez une des réponses proposées.']);
                    }

                    $correctAnswer = (string) ($question->answer_key['answer'] ?? '');
                    $awardedPoints = mb_strtolower($answer) === mb_strtolower($correctAnswer) ? (float) $question->points : 0.0;
                    $score += $awardedPoints;
                }

                $lockedAttempt->responses()->updateOrCreate(
                    ['evaluation_question_id' => $question->id],
                    ['answer' => $answer, 'awarded_points' => $awardedPoints],
                );
            }

            $lockedAttempt->update([
                'status' => 'submitted',
                'score' => $hasManualAnswers ? null : round($score, 2),
                'submitted_at' => now(),
            ]);
        });

        return redirect()->route('learning.evaluations.show', $evaluation)->with('status', 'Vos réponses ont été remises.');
    }

    public function certificates(Request $request): View
    {
        $certificates = $request->user()->certificates()
            ->whereNull('revoked_at')
            ->with(['enrollment.training:id,title,slug'])
            ->orderByDesc('issued_at')
            ->paginate(15);

        return view('learning.certificates', compact('certificates'));
    }

    private function authorizedEnrollmentForLesson(User $user, Lesson $lesson): Enrollment
    {
        abort_unless($lesson->is_published, 404);
        $lesson->loadMissing('module.training');

        return $user->enrollments()
            ->where('training_id', $lesson->module->training_id)
            ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
            ->firstOrFail();
    }

    private function authorizeEvaluation(User $user, Evaluation $evaluation): void
    {
        abort_unless($evaluation->is_published, 404);
        abort_if($evaluation->opens_at?->isFuture(), 404);
        abort_if($evaluation->closes_at?->isPast(), 404);
        $evaluation->loadMissing('session:id,training_id');
        $trainingId = $evaluation->training_id ?? $evaluation->session?->training_id;
        abort_unless($trainingId !== null, 404);

        $enrollments = $user->enrollments()
            ->where('training_id', $trainingId)
            ->whereIn('status', ['confirmed', 'in_progress', 'completed']);

        if ($evaluation->training_session_id !== null) {
            $enrollments->where('training_session_id', $evaluation->training_session_id);
        }

        abort_unless($enrollments->exists(), 403);
    }
}
