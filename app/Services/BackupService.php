<?php

namespace App\Services;

use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class BackupService
{
    public function create(?User $user = null): BackupRun
    {
        $storage = Storage::disk('local');
        $filename = now()->format('Ymd-His').'-'.Str::uuid();
        $databaseFile = 'backups/'.$filename.'.sql';
        $documentsFile = 'backups/'.$filename.'-documents.tar.gz';
        $credentialFile = $storage->path('backups/.mysql-'.Str::uuid().'.cnf');
        $run = BackupRun::create([
            'created_by' => $user?->id,
            'status' => BackupRun::STATUS_RUNNING,
            'database_file' => $databaseFile,
            'documents_file' => $documentsFile,
        ]);

        try {
            $storage->makeDirectory('backups');
            $this->writeDatabaseBackup($storage, $databaseFile, $credentialFile);
            $this->writeDocumentsBackup($storage, $documentsFile);

            $databasePath = $storage->path($databaseFile);
            $documentsPath = $storage->path($documentsFile);
            $databaseSize = filesize($databasePath);
            $documentsSize = filesize($documentsPath);
            $databaseChecksum = hash_file('sha256', $databasePath);
            $documentsChecksum = hash_file('sha256', $documentsPath);

            if (! is_int($databaseSize) || ! is_int($documentsSize) || ! is_string($databaseChecksum) || ! is_string($documentsChecksum)) {
                throw new RuntimeException('Impossible de vérifier les fichiers de sauvegarde.');
            }

            $run->update([
                'status' => BackupRun::STATUS_VERIFIED,
                'database_size' => $databaseSize,
                'documents_size' => $documentsSize,
                'database_sha256' => $databaseChecksum,
                'documents_sha256' => $documentsChecksum,
                'error_message' => null,
                'verified_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $storage->delete([$databaseFile, $documentsFile]);
            $run->update([
                'status' => BackupRun::STATUS_FAILED,
                'database_file' => null,
                'documents_file' => null,
                'database_size' => 0,
                'documents_size' => null,
                'database_sha256' => null,
                'documents_sha256' => null,
                'error_message' => 'La sauvegarde a échoué. Vérifiez la configuration MySQL et les outils système.',
            ]);
            Log::error('La création de la sauvegarde DK Academia a échoué.', [
                'backup_run_id' => $run->id,
                'exception' => $exception::class,
            ]);
        } finally {
            if (is_file($credentialFile)) {
                unlink($credentialFile);
            }
        }

        return $run->refresh();
    }

    private function writeDatabaseBackup(FilesystemAdapter $storage, string $relativePath, string $credentialFile): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Les sauvegardes de base de données nécessitent MySQL ou MariaDB.');
        }

        $databaseConfiguration = config('database.connections.'.config('database.default'), []);
        $database = (string) ($databaseConfiguration['database'] ?? '');

        if ($database === '') {
            throw new RuntimeException('La base de données n’est pas configurée.');
        }

        $this->writeCredentialsFile($databaseConfiguration, $credentialFile);
        $binary = $this->findDatabaseDumpBinary($driver);
        $process = new Process([
            $binary,
            '--defaults-extra-file='.$credentialFile,
            '--single-transaction',
            '--quick',
            '--routines',
            '--events',
            '--triggers',
            '--no-tablespaces',
            '--databases',
            $database,
        ]);
        $process->setTimeout(1800);
        $process->disableOutput();
        $output = fopen($storage->path($relativePath), 'wb');

        if ($output === false) {
            throw new RuntimeException('Impossible de créer le fichier de sauvegarde SQL.');
        }

        try {
            $process->run(function (string $type, string $buffer) use ($output): void {
                if ($type === Process::OUT) {
                    $offset = 0;

                    while ($offset < strlen($buffer)) {
                        $written = fwrite($output, substr($buffer, $offset));

                        if ($written === false || $written === 0) {
                            throw new RuntimeException('Écriture incomplète du fichier SQL.');
                        }

                        $offset += $written;
                    }
                }
            });
        } finally {
            fclose($output);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump a retourné le code '.$process->getExitCode().'.');
        }

        $path = $storage->path($relativePath);
        $header = file_get_contents($path, false, null, 0, 8192);

        if (filesize($path) === 0 || $header === false || ! str_contains($header, 'MySQL dump')) {
            throw new RuntimeException('Le fichier SQL généré ne contient pas un export MySQL valide.');
        }
    }

    /** @param array<string, mixed> $configuration */
    private function writeCredentialsFile(array $configuration, string $path): void
    {
        $lines = [
            '[client]',
            'user='.$this->quoteOption((string) ($configuration['username'] ?? '')),
            'password='.$this->quoteOption((string) ($configuration['password'] ?? '')),
            'host='.$this->quoteOption((string) ($configuration['host'] ?? '127.0.0.1')),
            'port='.(int) ($configuration['port'] ?? 3306),
            'default-character-set=utf8mb4',
        ];

        if (! empty($configuration['unix_socket'])) {
            $lines[] = 'socket='.$this->quoteOption((string) $configuration['unix_socket']);
        }

        if (file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Impossible de préparer les identifiants MySQL temporaires.');
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($path, 0600);
        }
    }

    private function quoteOption(string $value): string
    {
        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new RuntimeException('Une valeur de connexion MySQL contient un caractère non autorisé.');
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function findDatabaseDumpBinary(string $driver): string
    {
        $executable = $driver === 'mariadb' ? 'mariadb-dump' : 'mysqldump';
        $extension = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        $baseDirectory = DB::selectOne('SELECT @@basedir AS base_dir')?->base_dir;

        if (is_string($baseDirectory) && $baseDirectory !== '') {
            $candidate = rtrim($baseDirectory, '/\\').DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.$executable.$extension;

            if (is_file($candidate)) {
                return $candidate;
            }

            if ($driver === 'mariadb') {
                $mysqlCandidate = rtrim($baseDirectory, '/\\').DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'mysqldump'.$extension;

                if (is_file($mysqlCandidate)) {
                    return $mysqlCandidate;
                }
            }
        }

        $finder = new ExecutableFinder;
        $binary = $finder->find($executable) ?? ($driver === 'mariadb' ? $finder->find('mysqldump') : null);

        if ($binary === null) {
            throw new RuntimeException('L’exécutable de sauvegarde MySQL est introuvable.');
        }

        return $binary;
    }

    private function writeDocumentsBackup(FilesystemAdapter $storage, string $relativePath): void
    {
        $storageAppDirectory = dirname($storage->path(''));
        $privateDirectory = $storage->path('');
        $publicDirectory = storage_path('app/public');
        $archivePath = $storage->path($relativePath);

        if (! is_dir($publicDirectory) && ! mkdir($publicDirectory, 0750, true) && ! is_dir($publicDirectory)) {
            throw new RuntimeException('Impossible de préparer le dossier des documents publics.');
        }

        if (! is_dir($privateDirectory)) {
            throw new RuntimeException('Le dossier privé des documents est introuvable.');
        }

        $tar = (new ExecutableFinder)->find('tar');

        if ($tar === null) {
            throw new RuntimeException('L’outil tar est introuvable pour archiver les documents.');
        }

        $process = new Process([
            $tar,
            '-czf',
            $archivePath,
            '--exclude=private/backups',
            '-C',
            $storageAppDirectory,
            'private',
            'public',
        ]);
        $process->setTimeout(1800);
        $process->disableOutput();
        $process->run();

        if (! $process->isSuccessful() || ! is_file($archivePath) || filesize($archivePath) === 0) {
            throw new RuntimeException('L’archive de documents n’a pas pu être créée.');
        }

        $verification = new Process([$tar, '-tzf', $archivePath]);
        $verification->setTimeout(1800);
        $verification->disableOutput();
        $verification->run();

        if (! $verification->isSuccessful()) {
            throw new RuntimeException('L’archive de documents est illisible.');
        }
    }
}
