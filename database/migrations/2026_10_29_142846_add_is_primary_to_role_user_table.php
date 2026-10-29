<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('role_user', 'is_primary')) {
            Schema::table('role_user', function (Blueprint $table): void {
                $table->boolean('is_primary')->default(false);
                $table->index(['user_id', 'is_primary']);
            });
        }

        $singleRoleUsers = DB::query()
            ->fromSub(
                DB::table('role_user')
                    ->select('user_id')
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(*) = 1'),
                'single_role_users',
            )
            ->select('user_id');

        DB::table('role_user')
            ->where('is_primary', false)
            ->whereIn('user_id', $singleRoleUsers)
            ->update(['is_primary' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The original role schema declares this column; keep it when rolling back this drift repair.
    }
};
