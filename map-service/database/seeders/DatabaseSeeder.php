<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Local demo administrator (password "password", like the main app's demo accounts).
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Map admin',
            'email' => 'admin@map.test',
        ]);
    }
}
