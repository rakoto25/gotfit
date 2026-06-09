<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Full access to the system'],
            ['name' => 'Intervenant', 'slug' => 'intervenant', 'description' => 'Can publish services and receive bookings'],
            ['name' => 'Client', 'slug' => 'client', 'description' => 'Can search, book and pay services'],
            ['name' => 'Structure', 'slug' => 'structure', 'description' => 'Can publish missions and manage applications'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
