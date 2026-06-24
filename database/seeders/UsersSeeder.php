<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'misbahul',
                'email' => 'misbahul@gmail.com',
                'password' => bcrypt('misbahul123'),
                'role' => 'user',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'administrator',
                'email' => 'admin@gmail.com',
                'password' => bcrypt('admin123'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('users')->insert($users);
    }
}
