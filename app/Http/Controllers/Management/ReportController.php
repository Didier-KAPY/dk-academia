<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        return view('management.reports.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            ...$this->reportData($from, $to),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $report = $this->reportData($from, $to);
        $filename = 'rapport-dk-academia-'.$from->toDateString().'-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($report, $from, $to): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Rapport DK Academia', $from->toDateString(), $to->toDateString()], ';');
            fputcsv($stream, [], ';');
            fputcsv($stream, ['Type', 'Devise', 'Montant', 'Statut', 'Formation'], ';');

            foreach ($report['receipts'] as $row) {
                fputcsv($stream, ['Recette', $row->currency, $row->total, 'Validé', ''], ';');
            }

            foreach ($report['expenses'] as $row) {
                fputcsv($stream, ['Dépense', $row->currency, $row->total, 'Validé', ''], ';');
            }

            foreach ($report['enrollments'] as $row) {
                fputcsv($stream, ['Inscription', '', $row->total, $row->status, ''], ';');
            }

            foreach ($report['trainingEnrollments'] as $row) {
                fputcsv($stream, ['Inscription par formation', '', $row->total, $row->status, $row->training_title], ';');
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? now()->toDateString())->endOfDay();

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages(['to' => 'La date de fin doit être égale ou postérieure à la date de début.']);
        }

        return [$from, $to];
    }

    /** @return array{receipts: Collection, expenses: Collection, enrollments: Collection, trainingEnrollments: Collection} */
    private function reportData(Carbon $from, Carbon $to): array
    {
        $receipts = Payment::query()
            ->where('status', 'validated')
            ->whereBetween('received_at', [$from, $to])
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->groupBy('currency')->orderBy('currency')->get();
        $expenses = CashMovement::query()
            ->where('status', 'validated')->where('type', CashMovement::TYPE_EXPENSE)
            ->whereBetween('occurred_at', [$from, $to])
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->groupBy('currency')->orderBy('currency')->get();
        $enrollments = Enrollment::query()
            ->whereBetween('registered_at', [$from, $to])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->orderBy('status')->get();
        $trainingEnrollments = Enrollment::query()
            ->join('trainings', 'trainings.id', '=', 'enrollments.training_id')
            ->whereBetween('enrollments.registered_at', [$from, $to])
            ->select('trainings.title as training_title', 'enrollments.status', DB::raw('COUNT(*) as total'))
            ->groupBy('trainings.id', 'trainings.title', 'enrollments.status')
            ->orderBy('trainings.title')->orderBy('enrollments.status')->get();

        return compact('receipts', 'expenses', 'enrollments', 'trainingEnrollments');
    }
}
