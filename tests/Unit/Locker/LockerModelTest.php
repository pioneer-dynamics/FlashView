<?php

namespace Tests\Unit\Locker;

use App\Models\Locker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_wrapped_file_key_is_encrypted_at_rest(): void
    {
        $plaintext = 'my-base64-wrapped-dek-value';
        $locker = Locker::factory()->create([
            'wrapped_file_key' => $plaintext,
        ]);

        // Raw DB value must differ from plaintext (it is wrapped by APP_KEY via encrypted cast)
        $rawValue = DB::table('lockers')
            ->where('id', $locker->id)
            ->value('wrapped_file_key');

        $this->assertNotEquals($plaintext, $rawValue, 'wrapped_file_key must be encrypted in the database');
        $this->assertEquals($plaintext, $locker->fresh()->wrapped_file_key, 'Model accessor must decrypt to original value');
    }
}
