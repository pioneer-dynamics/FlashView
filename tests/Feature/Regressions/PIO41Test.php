<?php

use App\Models\User;

test('existing device shows stored name as read only', function () {
    $user = createUserWithApiAccess();

    // Create an existing CLI token with a custom name (simulating first authorization
    // where the user renamed "MyMachine.local" to "MyMachine")
    $token = $user->createToken('MyMachine', ['secrets:create']);
    $token->accessToken->update(['type' => 'cli']);
    $tokenId = $token->accessToken->id;

    // Second login: CLI sends hostname again, but also sends the stored token ID
    $response = $this->actingAs($user)
        ->get("/cli/authorize?port=12345&state=abcdef1234567890&name=MyMachine.local&token_id={$tokenId}");

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Cli/Authorize')
        // The stored name "MyMachine" should be returned, not the CLI-sent "MyMachine.local"
        ->where('existingDeviceName', 'MyMachine')
    );
});
