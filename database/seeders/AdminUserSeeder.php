<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
  public function run(): void
  {
    User::create([
      'name' => 'Admin',
      'email' => 'admin@admin.com',
      'password' => Hash::make('password'),
      'is_admin' => true,
      'api_url' => env('DEFAULT_API_URL'),
    ]);
  }
}
