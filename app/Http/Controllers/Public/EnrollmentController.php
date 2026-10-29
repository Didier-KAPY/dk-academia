<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreEnrollmentRequest;
use App\Models\Training;
use App\Models\TrainingSession;
use App\Services\EnrollmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function create(Training $training): View
    {
        abort_unless($training->is_published, 404);

        $deliveryModes = $training->deliveryModes()
            ->where('delivery_modes.is_active', true)
            ->orderBy('name')
            ->get(['delivery_modes.id', 'delivery_modes.name', 'delivery_modes.slug']);

        $sessions = TrainingSession::query()
            ->where('training_id', $training->id)
            ->where('status', 'scheduled')
            ->where(fn (Builder $query): Builder => $query->whereNull('starts_at')->orWhere('starts_at', '>=', now()))
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('delivery_mode_id')
                ->orWhereHas('deliveryMode', fn (Builder $modeQuery): Builder => $modeQuery->where('is_active', true)))
            ->with('deliveryMode:id,name')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        return view('public.enrollments.create', compact('training', 'deliveryModes', 'sessions'));
    }

    public function store(
        StoreEnrollmentRequest $request,
        Training $training,
        EnrollmentService $enrollmentService,
    ): RedirectResponse {
        $enrollment = $enrollmentService->submit(
            $request->user(),
            $training,
            $request->integer('delivery_mode_id'),
            $request->filled('training_session_id') ? $request->integer('training_session_id') : null,
        );

        $message = $enrollment->wasRecentlyCreated
            ? 'Votre demande d’inscription est enregistrée. Elle est en attente de confirmation.'
            : 'Une demande d’inscription existe déjà pour cette formation et cette session.';

        return redirect()->route('my-enrollments.index')->with('status', $message);
    }
}
