<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = new User();
        $user->name = 'Mirko';
        $user->email = 'mirko@example.com';
        $user->password = bcrypt('password');
        $user->remember_token = 'tPEpcYrL3XZAtEAbnzdpM3rxAT7U06UYZAQIYG9jHxeOs33rN92pTXrvgfeS';
        $user->save();
    }
}
