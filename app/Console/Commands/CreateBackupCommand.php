<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backups:create')]
#[Description('Crée et vérifie une sauvegarde de la base MySQL et des documents.')]
class CreateBackupCommand extends Command
{
    public function handle(BackupService $backupService): int
    {
        $backup = $backupService->create();

        if ($backup->status !== BackupRun::STATUS_VERIFIED) {
            $this->error($backup->error_message ?? 'La sauvegarde a échoué.');

            return self::FAILURE;
        }

        $this->info('Sauvegarde vérifiée : '.$backup->id);
        $this->line('Base de données : '.number_format((int) $backup->database_size).' octets');
        $this->line('Documents : '.number_format((int) $backup->documents_size).' octets');

        return self::SUCCESS;
    }
}
