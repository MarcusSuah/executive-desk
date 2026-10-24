<?php

use App\Models\User;

it('creates a verified administrator from interactive input', function () {
    $this->artisan('app:create-admin', ['--name' => 'Casey Admin', '--email' => 'casey@example.com', '--timezone' => 'Asia/Manila'])
        ->expectsQuestion('Password (min. 12 characters)', 'a-strong-passphrase')
        ->expectsQuestion('Confirm password', 'a-strong-passphrase')
        ->assertSuccessful();

    $user = User::where('email', 'casey@example.com')->sole();

    expect($user->hasRole('administrator'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->timezone)->toBe('Asia/Manila');
});

it('rejects a short password without creating the account', function () {
    $this->artisan('app:create-admin', ['--name' => 'Casey Admin', '--email' => 'casey@example.com', '--timezone' => 'UTC'])
        ->expectsQuestion('Password (min. 12 characters)', 'short')
        ->expectsQuestion('Confirm password', 'short')
        ->assertFailed();

    expect(User::where('email', 'casey@example.com')->exists())->toBeFalse();
});
