<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreEvaluationRequest;
use App\Models\Evaluation;
use App\Models\EvaluationResponse;
use App\Models\Training;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EvaluationController extends Controller
{
    public function index(Request $request): View
    {
        $query = Evaluation::query()->with(['training:id,title', 'session:id,name', 'creator:id,name'])->withCount('attempts');

        if ($request->user()->hasRole('formateur') && ! $request->user()->hasRole('administrateur', 'gestionnaire-administratif', 'responsable-pedagogique')) {
            $query->where('created_by', $request->user()->id);
        }

        $evaluations = $query->orderByDesc('created_at')->paginate(15);

        return view('management.pedagogy.evaluations.index', compact('evaluations'));
    }

    public function create(Request $request): View
    {
        $trainingsQuery = Training::query()->orderBy('title');

        if ($request->user()->hasRole('formateur') && ! $request->user()->hasRole('administrateur', 'gestionnaire-administratif', 'responsable-pedagogique')) {
            $trainingsQuery->whereHas('sessions', fn (Builder $query): Builder => $query->where('instructor_id', $request->user()->id));
        }

        $trainings = $trainingsQuery->get(['id', 'title']);
        $sessions = TrainingSession::query()->with('training:id,title')->when(
            $request->user()->hasRole('formateur') && ! $request->user()->hasRole('administrateur', 'gestionnaire-administratif', 'responsable-pedagogique'),
            fn (Builder $query): Builder => $query->where('instructor_id', $request->user()->id),
        )->where('status', 'scheduled')->orderBy('starts_at')->get(['id', 'training_id', 'name', 'instructor_id']);

        return view('management.pedagogy.evaluations.form', compact('trainings', 'sessions'));
    }

    public function store(StoreEvaluationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $questions = $data['questions'];
        unset($data['questions']);

        $training = Training::query()->findOrFail($data['training_id']);
        $session = isset($data['training_session_id']) ? TrainingSession::query()->findOrFail($data['training_session_id']) : null;

        if ($session !== null && (int) $session->training_id !== (int) $training->id) {
            abort(422, 'La session choisie ne correspond pas à la formation.');
        }

        if ($request->user()->hasRole('formateur') && ! $request->user()->hasRole('administrateur', 'gestionnaire-administratif', 'responsable-pedagogique')) {
            abort_unless($session !== null && (int) $session->instructor_id === (int) $request->user()->id, 403);
        }

        $totalPoints = collect($questions)->sum(fn (array $question): float => (float) $question['points']);
        $data['total_points'] = round($totalPoints, 2);
        $data['created_by'] = $request->user()->id;
        $data['is_published'] = (bool) ($data['is_published'] ?? false);

        $evaluation = DB::transaction(function () use ($data, $questions): Evaluation {
            $evaluation = Evaluation::create($data);

            foreach ($questions as $index => $question) {
                $evaluation->questions()->create([
                    'prompt' => $question['prompt'],
                    'question_type' => $question['question_type'],
                    'options' => $question['question_type'] === 'multiple_choice' ? $question['options'] : null,
                    'answer_key' => ['answer' => trim($question['answer'])],
                    'points' => $question['points'],
                    'sort_order' => $index,
                ]);
            }

            return $evaluation;
        });

        return redirect()->route('management.pedagogy.evaluations.index')->with('status', 'L’évaluation a été créée.');
    }

    public function show(Request $request, Evaluation $evaluation): View
    {
        $this->authorizeEvaluationManagement($request, $evaluation);
        $evaluation->load(['training:id,title', 'session:id,name']);
        $attempts = $evaluation->attempts()
            ->with(['user:id,name,email', 'responses.question:id,prompt,question_type,points'])
            ->orderByDesc('submitted_at')
            ->paginate(20);

        return view('management.pedagogy.evaluations.show', compact('evaluation', 'attempts'));
    }

    public function gradeResponse(Request $request, EvaluationResponse $response): RedirectResponse
    {
        $response->load(['attempt.evaluation', 'question']);
        $this->authorizeEvaluationManagement($request, $response->attempt->evaluation);
        abort_unless($response->attempt->status === 'submitted', 422);
        $data = $request->validate([
            'awarded_points' => ['required', 'numeric', 'min:0', 'max:'.$response->question->points],
            'instructor_feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($response, $data): void {
            $response->update($data);
            $attempt = $response->attempt()->lockForUpdate()->firstOrFail();
            $responses = $attempt->responses()->get();

            if ($responses->every(fn (EvaluationResponse $item): bool => $item->awarded_points !== null)) {
                $attempt->update(['score' => round((float) $responses->sum('awarded_points'), 2)]);
            }
        });

        return back()->with('status', 'La réponse a été corrigée.');
    }

    private function authorizeEvaluationManagement(Request $request, Evaluation $evaluation): void
    {
        $hasFullAccess = $request->user()->hasRole('administrateur', 'gestionnaire-administratif', 'responsable-pedagogique');
        abort_unless($hasFullAccess || ($request->user()->hasRole('formateur') && (int) $evaluation->created_by === (int) $request->user()->id), 403);
    }
}
