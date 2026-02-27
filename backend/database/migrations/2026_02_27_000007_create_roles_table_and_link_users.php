<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('password');
            $table->index('role_id');
        });

        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'Admin',
            'slug' => 'admin',
            'permissions' => json_encode(['*'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userRoleId = DB::table('roles')->insertGetId([
            'name' => 'User',
            'slug' => 'user',
            'permissions' => json_encode([Role::ACCESS_FRONTEND_PORTAL], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasColumn('users', 'role')) {
            DB::table('users')
                ->where('role', 'admin')
                ->update(['role_id' => $adminRoleId]);

            DB::table('users')
                ->where('role', '!=', 'admin')
                ->update(['role_id' => $userRoleId]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        } else {
            DB::table('users')->update(['role_id' => $adminRoleId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin')->after('password');
        });

        $rolesById = DB::table('roles')->pluck('slug', 'id');

        DB::table('users')->orderBy('id')->get(['id', 'role_id'])->each(function ($user) use ($rolesById): void {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['role' => $rolesById[$user->role_id] ?? 'user']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
            $table->dropColumn('role_id');
        });

        Schema::dropIfExists('roles');
    }
};
