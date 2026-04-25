<?php

namespace Tests\Unit\Features;

use App\Features\ApiFeature;
use App\Features\ExpiryFeature;
use App\Features\MessagesFeature;
use App\Services\FeatureRegistry;
use RuntimeException;
use Tests\TestCase;

class FeatureRegistryTest extends TestCase
{
    private function makeRegistry(): FeatureRegistry
    {
        return new FeatureRegistry([
            new MessagesFeature,
            new ExpiryFeature,
            new ApiFeature,
        ]);
    }

    public function test_all_returns_all_registered_features(): void
    {
        $registry = $this->makeRegistry();

        $this->assertCount(3, $registry->all());
    }

    public function test_has_returns_true_for_known_key(): void
    {
        $registry = $this->makeRegistry();

        $this->assertTrue($registry->has('messages'));
        $this->assertTrue($registry->has('expiry'));
        $this->assertTrue($registry->has('api'));
    }

    public function test_has_returns_false_for_unknown_key(): void
    {
        $registry = $this->makeRegistry();

        $this->assertFalse($registry->has('nonexistent_feature'));
    }

    public function test_get_returns_correct_feature_class(): void
    {
        $registry = $this->makeRegistry();

        $feature = $registry->get('messages');

        $this->assertInstanceOf(MessagesFeature::class, $feature);
        $this->assertEquals('messages', $feature->key());
    }

    public function test_get_throws_for_unknown_key(): void
    {
        $registry = $this->makeRegistry();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown plan feature key: [nonexistent]');

        $registry->get('nonexistent');
    }

    public function test_for_frontend_returns_correct_shape(): void
    {
        $registry = $this->makeRegistry();

        $result = $registry->forFrontend();

        $this->assertCount(3, $result);

        $messagesEntry = collect($result)->firstWhere('key', 'messages');
        $this->assertNotNull($messagesEntry);
        $this->assertArrayHasKey('key', $messagesEntry);
        $this->assertArrayHasKey('label', $messagesEntry);
        $this->assertArrayHasKey('description', $messagesEntry);
        $this->assertArrayHasKey('defaultOrder', $messagesEntry);
        $this->assertArrayHasKey('canBeLimit', $messagesEntry);
        $this->assertArrayHasKey('configSchema', $messagesEntry);
        $this->assertTrue($messagesEntry['canBeLimit']);
        $this->assertNotEmpty($messagesEntry['configSchema']);
    }

    public function test_for_frontend_marks_boolean_features_as_not_can_be_limit(): void
    {
        $registry = $this->makeRegistry();

        $result = $registry->forFrontend();
        $apiEntry = collect($result)->firstWhere('key', 'api');

        $this->assertNotNull($apiEntry);
        $this->assertFalse($apiEntry['canBeLimit']);
        $this->assertEmpty($apiEntry['configSchema']);
    }

    public function test_singleton_is_registered_in_container(): void
    {
        $registry = app(FeatureRegistry::class);

        $this->assertInstanceOf(FeatureRegistry::class, $registry);
        $this->assertSame($registry, app(FeatureRegistry::class));
    }
}
