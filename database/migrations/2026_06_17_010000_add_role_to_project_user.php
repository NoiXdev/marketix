<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->string('role')->default('member')->after('user_id');
        });

        // Backfill role from the legacy permissions JSON: anything containing
        // "admin" becomes admin, everything else member.
        DB::table('project_user')->orderBy('project_id')->orderBy('user_id')
            ->get(['project_id', 'user_id', 'permissions'])
            ->each(function ($row) {
                DB::table('project_user')
                    ->where('project_id', $row->project_id)
                    ->where('user_id', $row->user_id)
                    ->update(['role' => str_contains((string) $row->permissions, 'admin') ? 'admin' : 'member']);
            });

        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->json('permissions')->default('[]')->after('user_id');
        });

        DB::table('project_user')->orderBy('project_id')->orderBy('user_id')
            ->get(['project_id', 'user_id', 'role'])
            ->each(function ($row) {
                DB::table('project_user')
                    ->where('project_id', $row->project_id)
                    ->where('user_id', $row->user_id)
                    ->update(['permissions' => $row->role === 'admin' ? '"admin"' : '[]']);
            });

        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
