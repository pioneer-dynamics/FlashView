<?php

namespace Tests\Unit\Locker;

use App\Models\Locker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_update_token_matches_hash(): void
    {
        $token = 'mytoken';
        $locker = Locker::factory()->create([
            'update_token_hash' => hash('sha256', $token),
        ]);

        $this->assertTrue($locker->verifyUpdateToken($token));
    }

    public function test_verify_update_token_rejects_wrong_token(): void
    {
        $locker = Locker::factory()->create([
            'update_token_hash' => hash('sha256', 'correcttoken'),
        ]);

        $this->assertFalse($locker->verifyUpdateToken('wrongtoken'));
    }

    public function test_verify_auth_verifier_matches(): void
    {
        $verifier = str_repeat('a', 64);
        $locker = Locker::factory()->create([
            'auth_verifier' => $verifier,
        ]);

        $this->assertTrue($locker->verifyAuthVerifier($verifier));
    }

    public function test_is_file_locker_returns_true_when_storage_path_set(): void
    {
        $locker = Locker::factory()->fileLocker()->create();

        $this->assertTrue($locker->isFileLocker());
    }

    public function test_is_file_locker_returns_false_when_no_storage_path(): void
    {
        $locker = Locker::factory()->create(['storage_path' => null]);

        $this->assertFalse($locker->isFileLocker());
    }

    public function test_active_scope_excludes_expired(): void
    {
        Locker::factory()->create(['account_id' => '1111111111']);
        Locker::factory()->expired()->create(['account_id' => '2222222222']);

        $active = Locker::active()->get();

        $this->assertCount(1, $active);
        $this->assertEquals('1111111111', $active->first()->account_id);
    }

    public function test_expired_scope_returns_only_expired(): void
    {
        Locker::factory()->create(['account_id' => '1111111111']);
        Locker::factory()->expired()->create(['account_id' => '2222222222']);

        $expired = Locker::expired()->get();

        $this->assertCount(1, $expired);
        $this->assertEquals('2222222222', $expired->first()->account_id);
    }
}
