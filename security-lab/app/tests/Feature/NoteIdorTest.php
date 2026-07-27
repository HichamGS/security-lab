<?php

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Test proving that Note IDOR protection works correctly.
 * This test should PASS - it demonstrates proper ownership enforcement.
 */
test('note idor protection: user cannot view another users note', function () {
    // Create two users
    $alice = User::factory()->create(['email' => 'alice@test.com']);
    $bob = User::factory()->create(['email' => 'bob@test.com']);

    // Create a note owned by Bob
    $bobNote = Note::factory()->create([
        'user_id' => $bob->id,
        'title' => "Bob's Secret Note",
        'content' => 'This is confidential.',
    ]);

    // Alice tries to access Bob's note - should be forbidden
    $response = $this->actingAs($alice)
        ->getJson("/api/notes/{$bobNote->id}");

    // Should return 403 Forbidden due to NotePolicy
    $response->assertStatus(403);
});

test('note idor protection: user can view their own note', function () {
    $user = User::factory()->create();
    
    $note = Note::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Own Note',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/api/notes/{$note->id}");

    $response->assertStatus(200)
        ->assertJsonPath('id', $note->id);
});

test('note idor protection: user cannot update another users note', function () {
    $alice = User::factory()->create(['email' => 'alice2@test.com']);
    $bob = User::factory()->create(['email' => 'bob2@test.com']);

    $bobNote = Note::factory()->create([
        'user_id' => $bob->id,
        'title' => "Bob's Note",
    ]);

    $response = $this->actingAs($alice)
        ->putJson("/api/notes/{$bobNote->id}", [
            'title' => 'Hacked Title',
            'content' => 'Hacked content',
        ]);

    $response->assertStatus(403);
    
    // Verify the note was not modified
    $bobNote->refresh();
    expect($bobNote->title)->toBe("Bob's Note");
});

test('note idor protection: user cannot delete another users note', function () {
    $alice = User::factory()->create(['email' => 'alice3@test.com']);
    $bob = User::factory()->create(['email' => 'bob3@test.com']);

    $bobNote = Note::factory()->create([
        'user_id' => $bob->id,
        'title' => "Bob's Note To Delete",
    ]);

    $response = $this->actingAs($alice)
        ->deleteJson("/api/notes/{$bobNote->id}");

    $response->assertStatus(403);
    
    // Verify the note still exists
    expect(Note::find($bobNote->id))->not->toBeNull();
});
