<?php

use App\Models\Secret;
use App\Models\User;
use App\Policies\SecretPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new SecretPolicy;
});

test('owner can view own secret', function () {
    $user = User::factory()->create();
    $secret = Secret::factory()->forUser($user)->create();

    expect($this->policy->view($user, $secret))->toBeTrue();
});

test('user cannot view other users secret', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $secret = Secret::factory()->forUser($owner)->create();

    expect($this->policy->view($other, $secret))->toBeFalse();
});

test('owner can delete own secret', function () {
    $user = User::factory()->create();
    $secret = Secret::factory()->forUser($user)->create();

    expect($this->policy->delete($user, $secret))->toBeTrue();
});

test('user cannot delete other users secret', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $secret = Secret::factory()->forUser($owner)->create();

    expect($this->policy->delete($other, $secret))->toBeFalse();
});

test('web session user can view any', function () {
    $user = User::factory()->create();

    expect($this->policy->viewAny($user))->toBeTrue();
});

test('web session user can create', function () {
    $user = User::factory()->create();

    expect($this->policy->create($user))->toBeTrue();
});

test('api token with correct ability can view', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['secrets:list']);
    $user->withAccessToken($token->accessToken);

    $secret = Secret::factory()->forUser($user)->create();

    expect($this->policy->view($user, $secret))->toBeTrue();
});

test('api token without list ability cannot view', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['secrets:create']);
    $user->withAccessToken($token->accessToken);

    $secret = Secret::factory()->forUser($user)->create();

    expect($this->policy->view($user, $secret))->toBeFalse();
});

test('api token without delete ability cannot delete', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['secrets:list']);
    $user->withAccessToken($token->accessToken);

    $secret = Secret::factory()->forUser($user)->create();

    expect($this->policy->delete($user, $secret))->toBeFalse();
});

test('api token with delete ability can delete own', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['secrets:delete']);
    $user->withAccessToken($token->accessToken);

    $secret = Secret::factory()->forUser($user)->create();

    expect($this->policy->delete($user, $secret))->toBeTrue();
});
