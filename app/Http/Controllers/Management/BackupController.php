<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BackupRun;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(): View
    {
        return view('management.backups.index', [
            'backups' => BackupRun::query()
                ->with('creator:id,name')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(15),
        ]);
    }

    public function store(Request $request, BackupService $backupService): RedirectResponse
    {
        $backup = $backupService->create($request->user());
        $isVerified = $backup->status === BackupRun::STATUS_VERIFIED;

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'event' => $isVerified ? 'backup.verified' : 'backup.failed',
            'subject_type' => BackupRun::class,
            'subject_id' => $backup->id,
            'description' => $isVerified
                ? 'Sauvegarde de la base de données et des documents vérifiée.'
                : 'Échec de la création ou de la vérification d’une sauvegarde.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if (! $isVerified) {
            return redirect()->route('management.backups.index')->with('error', $backup->error_message);
        }

        return redirect()->route('management.backups.index')->with('status', 'La sauvegarde a été créée et vérifiée.');
    }

    public function downloadDatabase(BackupRun $backup): BinaryFileResponse
    {
        return $this->downloadVerifiedFile($backup, 'database_file', 'database_size', 'database_sha256');
    }

    public function downloadDocuments(BackupRun $backup): BinaryFileResponse
    {
        return $this->downloadVerifiedFile($backup, 'documents_file', 'documents_size', 'documents_sha256');
    }

    private function downloadVerifiedFile(
        BackupRun $backup,
        string $pathColumn,
        string $sizeColumn,
        string $checksumColumn,
    ): BinaryFileResponse {
        abort_unless($backup->status === BackupRun::STATUS_VERIFIED, 404);

        $relativePath = $backup->{$pathColumn};
        abort_unless(
            is_string($relativePath)
                && str_starts_with($relativePath, 'backups/')
                && ! str_contains($relativePath, '..'),
            404,
        );

        $storage = Storage::disk('local');
        abort_unless($storage->exists($relativePath), 404);

        $absolutePath = $storage->path($relativePath);
        $checksum = hash_file('sha256', $absolutePath);
        $size = filesize($absolutePath);
        abort_unless(
            is_string($checksum)
                && is_int($size)
                && hash_equals((string) $backup->{$checksumColumn}, $checksum)
                && $size === (int) $backup->{$sizeColumn},
            409,
            'Le fichier de sauvegarde ne correspond plus à son empreinte de vérification.',
        );

        return response()->download($absolutePath, basename($relativePath), [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
