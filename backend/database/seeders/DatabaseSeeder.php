<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $adminRole = Role::query()->where('slug', 'admin')->first();

        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@wbtforge.test',
            'password' => 'password',
            'role_id' => $adminRole?->id,
        ]);
    }
}
