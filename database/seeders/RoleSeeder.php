<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Full access to the system',
            'is_active' => true,
        ]);

        Role::create([
            'name' => 'Intervenant',
            'slug' => 'intervenant',
            'description' => 'Can manage interventions and tasks',
            'is_active' => true,
        ]);

        Role::create([
            'name' => 'Client',
            'slug' => 'client',
            'description' => 'Basic user / customer',
            'is_active' => true,
        ]);
    }
}
