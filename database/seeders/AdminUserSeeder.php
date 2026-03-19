<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'ADMIN'],
            [
                'name' => 'ADMIN',
                'password' => Hash::make('RedDevils'),
                'profile' => 'admin',
            ]
        );
    }
}
