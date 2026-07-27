<?php

namespace Database\Seeders;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test users
        $alice = User::create([
            'name' => 'Alice',
            'email' => 'alice@lab.test',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        $bob = User::create([
            'name' => 'Bob',
            'email' => 'bob@lab.test',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@lab.test',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        // Create 3 notes for Alice
        Note::create([
            'user_id' => $alice->id,
            'title' => "Alice's Note 1",
            'content' => 'This is private content from Alice.',
        ]);

        Note::create([
            'user_id' => $alice->id,
            'title' => "Alice's Note 2",
            'content' => 'Another note by Alice with sensitive info.',
        ]);

        Note::create([
            'user_id' => $alice->id,
            'title' => "Alice's Note 3",
            'content' => 'Third note belonging to Alice.',
        ]);

        // Create 3 notes for Bob
        Note::create([
            'user_id' => $bob->id,
            'title' => "Bob's Note 1",
            'content' => 'This is private content from Bob.',
        ]);

        Note::create([
            'user_id' => $bob->id,
            'title' => "Bob's Note 2",
            'content' => 'Another note by Bob with secrets.',
        ]);

        Note::create([
            'user_id' => $bob->id,
            'title' => "Bob's Note 3",
            'content' => 'Third note belonging to Bob.',
        ]);
    }
}
