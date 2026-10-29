<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('running');
            $table->string('database_file')->nullable();
            $table->string('documents_file')->nullable();
            $table->unsignedBigInteger('database_size')->default(0);
            $table->unsignedBigInteger('documents_size')->nullable();
            $table->char('database_sha256', 64)->nullable();
            $table->char('documents_sha256', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
