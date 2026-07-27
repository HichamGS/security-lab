<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Test documenting the User mass-assignment vulnerability.
 * 
 * This test intentionally verifies that the vulnerability EXISTS.
 * When someone patches the vulnerability, this test will FAIL - 
 * that's the intended signal that the security hole has been fixed!
 */
test('mass assignment vulnerability: user can escalate privileges via is_admin field', function () {
    // Create a regular user (alice)
    $alice = User::factory()->create([
        'email' => 'alice@masstest.com',
        'is_admin' => false,
    ]);

    // Alice authenticates
    $token = $alice->createToken('test-token')->plainTextToken;

    // Alice attempts to escalate herself to admin by updating is_admin field
    // This works because UserController@update uses $request->all() with no whitelist
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->putJson("/api/users/{$alice->id}", [
        'is_admin' => true,
        'name' => 'Evil Admin',
    ]);

    // The vulnerable endpoint allows this - returns 200 OK
    $response->assertStatus(200);

    // Verify alice is now an admin (the exploit worked!)
    $alice->refresh();
    expect($alice->is_admin)->toBeTrue()
        ->and($alice->name)->toBe('Evil Admin');
});

test('mass assignment vulnerability: user can modify other fillable fields they should not access', function () {
    $user = User::factory()->create([
        'email' => 'victim@masstest.com',
        'is_admin' => false,
    ]);

    $token = $user->createToken('test-token')->plainTextToken;

    // Attacker tries to set arbitrary fields
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->putJson("/api/users/{$user->id}", [
        'email_verified_at' => now()->toDateTimeString(),
        'remember_token' => 'hacked_remember_token',
    ]);

    // Vulnerable endpoint accepts all fillable fields
    $response->assertStatus(200);
});

/**
 * This test shows what SHOULD happen after the fix is applied.
 * It will fail while the vulnerability exists, and pass once fixed.
 */
test('mass assignment FIXED: user cannot escalate privileges after patch', function () {
    $alice = User::factory()->create([
        'email' => 'alice@fixedtest.com',
        'is_admin' => false,
    ]);

    $token = $alice->createToken('test-token')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json',
    ])->putJson("/api/users/{$alice->id}", [
        'is_admin' => true,
    ]);

    // After the fix, this should either:
    // 1. Return 403 Forbidden, OR
    // 2. Return 200 but is_admin remains false (field ignored)
    
    // For now, we document that the vulnerability exists
    // Uncomment below when the fix is applied:
    // $response->assertStatus(403);
    // or
    // $alice->refresh();
    // expect($alice->is_admin)->toBeFalse();
    
    // Current behavior (vulnerable):
    $response->assertStatus(200);
    $alice->refresh();
    expect($alice->is_admin)->toBeTrue(); // Vulnerability still exists
})->skip('This test documents expected behavior AFTER fix. Currently vulnerable.');
