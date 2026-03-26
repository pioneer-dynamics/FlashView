<?php

namespace Tests\Unit\Traits;

use App\Exceptions\InvalidHashIdException;
use App\Models\Secret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class HasHashIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_hash_id_is_appended_on_initialization(): void
    {
        $secret = Secret::factory()->create();

        $this->assertArrayHasKey('hash_id', $secret->toArray());
        $this->assertNotEmpty($secret->hash_id);
    }

    public function test_hash_id_encodes_and_decodes_correctly(): void
    {
        $secret = Secret::factory()->create();

        $decoded = Secret::decodeHashId($secret->hash_id);

        $this->assertEquals($secret->id, $decoded);
    }

    public function test_decode_hash_id_returns_null_for_invalid_hash(): void
    {
        $result = Secret::decodeHashId('');

        $this->assertNull($result);
    }

    public function test_find_by_hash_id_returns_model(): void
    {
        $secret = Secret::factory()->create();

        $found = Secret::findByHashID($secret->hash_id);

        $this->assertTrue($found->is($secret));
    }

    public function test_find_by_hash_id_throws_on_invalid_hash(): void
    {
        $this->expectException(InvalidHashIdException::class);

        Secret::findByHashID('');
    }

    public function test_resolve_route_binding_returns_model(): void
    {
        $secret = Secret::factory()->create();

        $resolved = $secret->resolveRouteBinding($secret->hash_id);

        $this->assertTrue($resolved->is($secret));
    }

    public function test_resolve_route_binding_aborts_for_invalid_hash(): void
    {
        $secret = Secret::factory()->create();

        $this->expectException(NotFoundHttpException::class);

        $secret->resolveRouteBinding('');
    }
}
