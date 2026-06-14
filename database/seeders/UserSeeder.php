<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Mirror the NestJS fixture: 1 admin (admin/admin123) + 5 referees (ref1..ref5 / referee123).
     */
    public function run(): void
    {
        // Admin --------------------------------------------------------------
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'password_hash' => Hash::make('admin123'),
                'full_name' => 'Tournament Admin',
                'role' => UserRole::ADMIN->value,
                'active' => true,
            ],
        );

        // Referees -----------------------------------------------------------
        $refereePassword = Hash::make('referee123');

        $referees = [
            ['username' => 'ref1', 'full_name' => 'Aung Ko Ko'],
            ['username' => 'ref2', 'full_name' => 'Su Hnin Aye'],
            ['username' => 'ref3', 'full_name' => 'Kyaw Thiha'],
            ['username' => 'ref4', 'full_name' => 'May Thu Win'],
            ['username' => 'ref5', 'full_name' => 'Thant Zin'],
        ];

        foreach ($referees as $ref) {
            User::updateOrCreate(
                ['username' => $ref['username']],
                [
                    'password_hash' => $refereePassword,
                    'full_name' => $ref['full_name'],
                    'role' => UserRole::REFEREE->value,
                    'active' => true,
                ],
            );
        }
    }
}
