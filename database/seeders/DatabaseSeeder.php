<?php

namespace Database\Seeders;

use App\Modules\AccountsAccess\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'display_name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([DemoMallSeeder::class, DemoAccountsSeeder::class]);
    }
}
