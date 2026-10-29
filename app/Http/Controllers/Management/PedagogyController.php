<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\MarkAttendanceRequest;
use App\Http\Requests\Management\ReviewEnrollmentRequest;
use App\Http\Requests\Management\StoreLessonRequest;
use App\Http\Requests\Management\StoreModuleRequest;
use App\Http\Requests\Management\StoreSessionRequest;
use App\Http\Requests\Management\StoreTrainingRequest;
use App\Http\Requests\Management\UpdateLessonRequest;
use App\Http\Requests\Management\UpdateTrainingRequest;
use App\Models\AttendanceRecord;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\DeliveryMode;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Training;
use App\Models\TrainingModule;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PedagogyController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isTrainer = $user->hasRole('formateur') && ! $user->hasRole('administrateur', 'gestionnaire-administratif', 'responsable-pedagogique');
        $sessionsQuery = TrainingSession::query()->with(['training:id,title,slug', 'instructor:id,name', 'deliveryMode:id,name']);

        if ($isTrainer) {
            $sessionsQuery->where('instructor_id', $user->id);
        }

        $sessions = $sessionsQuery->where('status', 'scheduled')->orderBy('starts_at')->paginate(8);
        $pendingEnrollments = $isTrainer ? 0 : Enrollment::query()->where('status', 'pending')->count();

        return view('management.pedagogy.index', [
            'activeTrainings' => Training::query()->where('is_published', true)->count(),
            'pendingEnrollments' => $pendingEnrollments,
            'sessions' => $sessions,
            'canManageTrainings' => $user->hasPermissionTo('trainings.manage'),
            'canReviewEnrollments' => ! $isTrainer,
        ]);
    }

    public function trainings(): View
    {
        $trainings = Training::query()
            ->with('category:id,name')
            ->withCount(['modules', 'sessions', 'enrollments'])
            ->orderByDesc('updated_at')
            ->paginate(15);
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('management.pedagogy.trainings.index', compact('trainings', 'categories'));
    }

    public function createTraining(): View
    {
        $training = new Training(['level' => 'debutant', 'currency' => 'USD']);
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $deliveryModes = DeliveryMode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('management.pedagogy.trainings.form', compact('training', 'categories', 'deliveryModes'));
    }

    public function storeTraining(StoreTrainingRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('delivery_modes');
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? null) ?: $data['title']);
        $data['created_by'] = $request->user()->id;
        $data['is_published'] = (bool) ($data['is_published'] ?? false);
        $data['published_at'] = $data['is_published'] ? now() : null;

        $training = DB::transaction(function () use ($data, $request): Training {
            $training = Training::create($data);
            $training->deliveryModes()->sync($request->validated('delivery_modes'));

            return $training;
        });

        return redirect()->route('management.trainings.show', $training)->with('status', 'La formation a été créée.');
    }

    public function showTraining(Training $training): View
    {
        $training->load(['category', 'deliveryModes', 'modules.lessons', 'sessions.instructor', 'sessions.deliveryMode']);

        return view('management.pedagogy.trainings.show', compact('training'));
    }

    public function editTraining(Training $training): View
    {
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $deliveryModes = DeliveryMode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $training->load('deliveryModes:id,name');

        return view('management.pedagogy.trainings.form', compact('training', 'categories', 'deliveryModes'));
    }

    public function updateTraining(UpdateTrainingRequest $request, Training $training): RedirectResponse
    {
        $data = $request->safe()->except('delivery_modes');
        $data['slug'] = ($data['slug'] ?? null) ? Str::slug($data['slug']) : $training->slug;
        $data['is_published'] = (bool) ($data['is_published'] ?? false);
        $data['published_at'] = $data['is_published'] ? ($training->published_at ?? now()) : null;

        DB::transaction(function () use ($training, $data, $request): void {
            $training->update($data);
            $training->deliveryModes()->sync($request->validated('delivery_modes'));
        });

        return redirect()->route('management.trainings.show', $training)->with('status', 'La formation a été mise à jour.');
    }

    public function storeModule(StoreModuleRequest $request, Training $training): RedirectResponse
    {
        $data = $request->validated();
        $data['sort_order'] ??= ((int) $training->modules()->max('sort_order')) + 1;
        $training->modules()->create($data);

        return back()->with('status', 'Le module a été ajouté au programme.');
    }

    public function storeLesson(StoreLessonRequest $request, TrainingModule $module): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueLessonSlug($module, ($data['slug'] ?? null) ?: $data['title']);
        $data['sort_order'] ??= ((int) $module->lessons()->max('sort_order')) + 1;
        $module->lessons()->create($data);

        return back()->with('status', 'La leçon a été ajoutée.');
    }

    public function updateModule(StoreModuleRequest $request, TrainingModule $module): RedirectResponse
    {
        $module->update($request->validated());

        return back()->with('status', 'Le module a été mis à jour.');
    }

    public function updateLesson(UpdateLessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = ($data['slug'] ?? null) ? Str::slug($data['slug']) : $lesson->slug;
        $data['is_published'] = (bool) ($data['is_published'] ?? false);
        $lesson->update($data);

        return back()->with('status', 'La leçon a été mise à jour.');
    }

    public function sessions(Request $request): View
    {
        $query = TrainingSession::query()->with(['training:id,title,slug', 'instructor:id,name', 'deliveryMode:id,name']);

        if ($request->user()->hasRole('formateur') && ! $request->user()->hasRole('administrateur', 'gestionnaire-administratif', 'responsable-pedagogique')) {
            $query->where('instructor_id', $request->user()->id);
        }

        if ($request->filled('training') && ctype_digit($request->string('training')->toString())) {
            $query->where('training_id', $request->integer('training'));
        }

        $sessions = $query->orderByDesc('starts_at')->paginate(15);
        $trainings = Training::query()->orderBy('title')->get(['id', 'title']);
        $deliveryModes = DeliveryMode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $instructors = User::query()->whereHas('roles', fn (Builder $roles): Builder => $roles->where('slug', 'formateur'))->orderBy('name')->get(['id', 'name']);

        return view('management.pedagogy.sessions.index', compact('sessions', 'trainings', 'deliveryModes', 'instructors'));
    }

    public function storeSession(StoreSessionRequest $request): RedirectResponse
    {
        $this->authorizePedagogyManagement($request->user());
        $data = $request->validated();
        $this->validateSessionRelations($data);
        TrainingSession::create($data);

        return back()->with('status', 'La session a été créée.');
    }

    public function updateSession(StoreSessionRequest $request, TrainingSession $session): RedirectResponse
    {
        $this->authorizePedagogyManagement($request->user());
        $data = $request->validated();
        $this->validateSessionRelations($data);
        $session->update($data);

        return back()->with('status', 'La session a été mise à jour.');
    }

    public function setSessionStatus(Request $request, TrainingSession $session): RedirectResponse
    {
        $this->authorizePedagogyManagement($request->user());
        $data = $request->validate(['status' => ['required', 'in:scheduled,completed,cancelled']]);

        if ($data['status'] === 'completed' && $session->ends_at?->isFuture()) {
            throw ValidationException::withMessages(['status' => 'Une session ne peut être terminée avant sa date de fin.']);
        }

        $session->update(['status' => $data['status']]);

        return back()->with('status', 'Le statut de la session a été mis à jour.');
    }

    public function enrollments(Request $request): View
    {
        abort_unless($request->user()->hasRole('administrateur', 'gestionnaire-administratif', 'responsable-pedagogique'), 403);

        $enrollments = Enrollment::query()
            ->with(['user:id,name,email', 'training:id,title,slug', 'session:id,name,starts_at', 'deliveryMode:id,name'])
            ->withCount('certificates')
            ->when($request->string('status')->isNotEmpty(), fn (Builder $query): Builder => $query->where('status', $request->string('status')->toString()))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('registered_at')
            ->paginate(20)
            ->withQueryString();

        return view('management.pedagogy.enrollments.index', compact('enrollments'));
    }

    public function reviewEnrollment(ReviewEnrollmentRequest $request, Enrollment $enrollment): RedirectResponse
    {
        abort_unless($request->user()->hasRole('administrateur', 'gestionnaire-administratif', 'responsable-pedagogique'), 403);
        $targetStatus = $request->validated('status');
        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];

        if ($targetStatus !== $enrollment->status && ! in_array($targetStatus, $allowedTransitions[$enrollment->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'Cette transition de statut n’est pas autorisée.']);
        }

        DB::transaction(function () use ($enrollment, $request, $targetStatus): void {
            $lockedEnrollment = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $allowedTransitions = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['in_progress', 'cancelled'],
                'in_progress' => ['completed', 'cancelled'],
                'completed' => [],
                'cancelled' => [],
            ];

            if ($targetStatus !== $lockedEnrollment->status && ! in_array($targetStatus, $allowedTransitions[$lockedEnrollment->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Cette transition de statut n’est plus autorisée.']);
            }

            if ($targetStatus === 'confirmed' && $lockedEnrollment->training_session_id !== null) {
                $session = TrainingSession::query()->lockForUpdate()->findOrFail($lockedEnrollment->training_session_id);

                if ($session->capacity !== null) {
                    $confirmed = Enrollment::query()
                        ->where('training_session_id', $session->id)
                        ->where('id', '!=', $lockedEnrollment->id)
                        ->whereNotIn('status', ['cancelled'])
                        ->count();

                    if ($confirmed >= $session->capacity) {
                        throw ValidationException::withMessages(['status' => 'La session a atteint sa capacité maximale.']);
                    }
                }
            }

            $attributes = [
                'status' => $targetStatus,
                'completed_at' => $targetStatus === 'completed' ? now() : $lockedEnrollment->completed_at,
            ];

            if ($request->has('administrative_notes')) {
                $attributes['administrative_notes'] = $request->validated('administrative_notes');
            }

            $lockedEnrollment->update($attributes);
        });

        return back()->with('status', 'Le statut de l’inscription a été mis à jour.');
    }

    public function issueCertificate(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizePedagogyManagement($request->user());
        abort_unless($enrollment->status === 'completed', 422, 'Seule une inscription terminée peut recevoir un certificat.');

        DB::transaction(function () use ($request, $enrollment): void {
            $lockedEnrollment = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            abort_unless($lockedEnrollment->status === 'completed', 422);

            if (! $lockedEnrollment->certificates()->exists()) {
                Certificate::create([
                    'enrollment_id' => $lockedEnrollment->id,
                    'user_id' => $lockedEnrollment->user_id,
                    'issued_by' => $request->user()->id,
                    'certificate_number' => 'DKA-'.now()->format('Y').'-'.Str::upper(Str::random(10)),
                    'issued_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'Le certificat a été émis et ajouté à l’espace de l’apprenant.');
    }

    public function attendance(Request $request, TrainingSession $session): View
    {
        $this->authorizeSessionAccess($request->user(), $session);
        $session->load(['training:id,title', 'instructor:id,name']);
        $enrollments = $session->enrollments()
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get();
        $attendance = AttendanceRecord::query()
            ->where('training_session_id', $session->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->get()
            ->keyBy('user_id');

        return view('management.pedagogy.attendance', compact('session', 'enrollments', 'attendance'));
    }

    public function saveAttendance(MarkAttendanceRequest $request, TrainingSession $session): RedirectResponse
    {
        $this->authorizeSessionAccess($request->user(), $session);
        $enrolledUserIds = $session->enrollments()
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->pluck('user_id')
            ->all();
        $submittedUserIds = array_map('intval', array_keys($request->validated()['attendees'] ?? []));

        if (array_diff($submittedUserIds, $enrolledUserIds) !== []) {
            throw ValidationException::withMessages(['attendees' => 'La liste contient une personne non inscrite à cette session.']);
        }

        DB::transaction(function () use ($request, $session): void {
            foreach ($request->validated()['attendees'] ?? [] as $userId => $attendanceData) {
                AttendanceRecord::updateOrCreate(
                    [
                        'training_session_id' => $session->id,
                        'user_id' => (int) $userId,
                        'attendance_date' => $request->validated('attendance_date'),
                    ],
                    [
                        'recorded_by' => $request->user()->id,
                        'status' => $attendanceData['status'],
                        'notes' => $attendanceData['notes'] ?? null,
                    ],
                );
            }
        });

        return back()->with('status', 'Les présences ont été enregistrées.');
    }

    private function validateSessionRelations(array $data): void
    {
        $training = Training::query()->findOrFail($data['training_id']);

        if ($data['delivery_mode_id'] !== null && ! $training->deliveryModes()->whereKey($data['delivery_mode_id'])->exists()) {
            throw ValidationException::withMessages(['delivery_mode_id' => 'Cette modalité n’est pas associée à la formation.']);
        }

        if ($data['instructor_id'] !== null && ! User::query()->findOrFail($data['instructor_id'])->hasRole('formateur')) {
            throw ValidationException::withMessages(['instructor_id' => 'La personne choisie ne possède pas le rôle formateur.']);
        }
    }

    private function authorizeSessionAccess(User $user, TrainingSession $session): void
    {
        $canManageAll = $user->hasRole('administrateur', 'gestionnaire-administratif', 'responsable-pedagogique');
        abort_unless($canManageAll || ($user->hasRole('formateur') && (int) $session->instructor_id === (int) $user->id), 403);
    }

    private function authorizePedagogyManagement(User $user): void
    {
        abort_unless($user->hasRole('administrateur', 'gestionnaire-administratif', 'responsable-pedagogique'), 403);
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'formation';
        $slug = $base;
        $suffix = 2;

        while (Training::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function uniqueLessonSlug(TrainingModule $module, string $value): string
    {
        $base = Str::slug($value) ?: 'lecon';
        $slug = $base;
        $suffix = 2;

        while ($module->lessons()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
