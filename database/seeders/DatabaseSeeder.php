<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('INITIAL_OWNER_PASSWORD');

        if (
            blank($password)
            || strlen($password) < 10
            || ! preg_match('/[A-Za-z]/', $password)
            || ! preg_match('/\d/', $password)
        ) {
            throw new \RuntimeException(
                'INITIAL_OWNER_PASSWORD wajib minimal 10 karakter serta mengandung huruf dan angka.'
            );
        }

        User::firstOrCreate(
            ['email' => env('INITIAL_OWNER_EMAIL', 'owner@rachaqakost.id')],
            [
                'name' => env('INITIAL_OWNER_NAME', 'RachaqaKost Owner'),
                'password' => $password,
                'role' => 'OWNER',
            ]
        );
    }
}
