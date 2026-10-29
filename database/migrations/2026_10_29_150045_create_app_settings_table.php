<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('company_name', 160)->default('DK ACADEMIA');
            $table->string('slogan', 255)->default('Apprendre, maîtriser, réussir.');
            $table->string('contact_email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('website')->nullable();
            $table->char('default_currency', 3)->default('USD');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
