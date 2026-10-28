<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@shop.test');
        $password = env('ADMIN_PASSWORD');

        if (! $password) {
            $this->command->error('ADMIN_PASSWORD is missing in .env');
            return;
        }

        // Role is not fillable, so we set it directly here.
        $user = User::firstOrNew(['email' => $email]);
        $user->name = env('ADMIN_NAME', 'Shop Admin');
        $user->password = $password;
        $user->role = 'admin';
        $user->save();

        $this->command->info("Admin ready: {$email}");
    }
}