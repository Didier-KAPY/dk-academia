<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class BackupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_backup_permission_is_forbidden_from_backup_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('management.backups.index'))
            ->assertForbidden();

        $this->post(route('management.backups.store'))
            ->assertForbidden();

        $this->assertDatabaseCount('backup_runs', 0);
    }

    public function test_authorized_user_sees_backup_history(): void
    {
        $administrator = $this->userWithBackupPermission();
        BackupRun::create([
            'created_by' => $administrator->id,
            'status' => BackupRun::STATUS_VERIFIED,
            'database_file' => 'backups/database.sql',
            'documents_file' => 'backups/documents.tar.gz',
            'database_size' => 2048,
            'documents_size' => 1024,
            'database_sha256' => str_repeat('a', 64),
            'documents_sha256' => str_repeat('b', 64),
            'verified_at' => now(),
        ]);

        $this->actingAs($administrator)
            ->get(route('management.backups.index'))
            ->assertOk()
            ->assertSee('Sauvegardes')
            ->assertSee('Vérifiée')
            ->assertSee('Lancer la sauvegarde')
            ->assertSee('Télécharger le fichier SQL');
    }

    public function test_backup_request_records_audit_event_for_verified_backup(): void
    {
        $administrator = $this->userWithBackupPermission();
        $backup = BackupRun::create([
            'created_by' => $administrator->id,
            'status' => BackupRun::STATUS_VERIFIED,
            'database_file' => 'backups/database.sql',
            'documents_file' => 'backups/documents.tar.gz',
            'database_size' => 100,
            'documents_size' => 200,
            'database_sha256' => str_repeat('a', 64),
            'documents_sha256' => str_repeat('b', 64),
            'verified_at' => now(),
        ]);

        $this->mock(BackupService::class, function (MockInterface $mock) use ($administrator, $backup): void {
            $mock->shouldReceive('create')->once()->with($administrator)->andReturn($backup);
        });

        $this->actingAs($administrator)
            ->post(route('management.backups.store'))
            ->assertRedirect(route('management.backups.index'))
            ->assertSessionHas('status', 'La sauvegarde a été créée et vérifiée.');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $administrator->id,
            'event' => 'backup.verified',
            'subject_type' => BackupRun::class,
            'subject_id' => $backup->id,
        ]);
    }

    public function test_verified_database_backup_download_checks_its_integrity(): void
    {
        Storage::fake('local');
        $administrator = $this->userWithBackupPermission();
        $contents = "-- MySQL dump\nCREATE TABLE demo (id INT);\n";
        Storage::disk('local')->put('backups/database.sql', $contents);
        $backup = BackupRun::create([
            'created_by' => $administrator->id,
            'status' => BackupRun::STATUS_VERIFIED,
            'database_file' => 'backups/database.sql',
            'database_size' => strlen($contents),
            'database_sha256' => hash('sha256', $contents),
            'verified_at' => now(),
        ]);

        $this->actingAs($administrator)
            ->get(route('management.backups.database.download', $backup))
            ->assertDownload('database.sql');

        Storage::disk('local')->put('backups/database.sql', 'tampered backup');

        $this->get(route('management.backups.database.download', $backup))
            ->assertStatus(409);
    }

    public function test_backup_service_records_a_clear_failure_when_database_driver_is_unsupported(): void
    {
        Storage::fake('local');
        $administrator = $this->userWithBackupPermission();

        $backup = app(BackupService::class)->create($administrator);

        $this->assertSame(BackupRun::STATUS_FAILED, $backup->status);
        $this->assertSame(
            'La sauvegarde a échoué. Vérifiez la configuration MySQL et les outils système.',
            $backup->error_message,
        );
        $this->assertDatabaseHas('backup_runs', [
            'id' => $backup->id,
            'status' => BackupRun::STATUS_FAILED,
            'database_file' => null,
        ]);
    }

    private function userWithBackupPermission(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Administrateur', 'slug' => 'administrateur']);
        $permission = Permission::create(['name' => 'Gérer les sauvegardes', 'slug' => 'backups.manage']);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role, ['is_primary' => true]);

        return $user;
    }
}
