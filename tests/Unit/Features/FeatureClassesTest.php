<?php

namespace Tests\Unit\Features;

use App\Features\ExpiryFeature;
use App\Features\FileUploadFeature;
use App\Features\MessagesFeature;
use App\Features\ThrottlingFeature;
use Tests\TestCase;

class FeatureClassesTest extends TestCase
{
    // ── MessagesFeature ───────────────────────────────────────────────────────

    public function test_messages_feature_within_limit_returns_true_when_under_limit(): void
    {
        $feature = new MessagesFeature;

        $this->assertTrue($feature->withinLimit(500, ['message_length' => 1000]));
    }

    public function test_messages_feature_within_limit_returns_true_when_equal_to_limit(): void
    {
        $feature = new MessagesFeature;

        $this->assertTrue($feature->withinLimit(1000, ['message_length' => 1000]));
    }

    public function test_messages_feature_within_limit_returns_false_when_over_limit(): void
    {
        $feature = new MessagesFeature;

        $this->assertFalse($feature->withinLimit(1001, ['message_length' => 1000]));
    }

    public function test_messages_feature_within_limit_returns_true_when_config_missing(): void
    {
        $feature = new MessagesFeature;

        $this->assertTrue($feature->withinLimit(PHP_INT_MAX - 1, []));
    }

    public function test_messages_feature_can_be_limit(): void
    {
        $this->assertTrue((new MessagesFeature)->canBeLimit());
    }

    // ── ExpiryFeature ─────────────────────────────────────────────────────────

    public function test_expiry_feature_within_limit_returns_true_when_under_limit(): void
    {
        $feature = new ExpiryFeature;

        $this->assertTrue($feature->withinLimit(1440, ['expiry_minutes' => 43200]));
    }

    public function test_expiry_feature_within_limit_returns_false_when_over_limit(): void
    {
        $feature = new ExpiryFeature;

        $this->assertFalse($feature->withinLimit(43201, ['expiry_minutes' => 43200]));
    }

    public function test_expiry_feature_within_limit_returns_true_when_config_missing(): void
    {
        $feature = new ExpiryFeature;

        $this->assertTrue($feature->withinLimit(PHP_INT_MAX - 1, []));
    }

    public function test_expiry_feature_can_be_limit(): void
    {
        $this->assertTrue((new ExpiryFeature)->canBeLimit());
    }

    public function test_expiry_feature_resolves_label_in_weeks(): void
    {
        $this->assertSame('Up to 2 weeks expiry', (new ExpiryFeature)->resolveLabel(['expiry_minutes' => 20160]));
    }

    public function test_expiry_feature_resolves_label_in_days(): void
    {
        $this->assertSame('Up to 30 days expiry', (new ExpiryFeature)->resolveLabel(['expiry_minutes' => 43200]));
    }

    public function test_expiry_feature_resolves_label_singular_day(): void
    {
        $this->assertSame('Up to 1 day expiry', (new ExpiryFeature)->resolveLabel(['expiry_minutes' => 1440]));
    }

    public function test_expiry_feature_resolves_label_in_hours(): void
    {
        $this->assertSame('Up to 6 hours expiry', (new ExpiryFeature)->resolveLabel(['expiry_minutes' => 360]));
    }

    public function test_expiry_feature_resolves_label_in_minutes(): void
    {
        $this->assertSame('Up to 90 minutes expiry', (new ExpiryFeature)->resolveLabel(['expiry_minutes' => 90]));
    }

    public function test_expiry_feature_resolves_label_with_missing_config(): void
    {
        $this->assertSame('Up to 0 minutes expiry', (new ExpiryFeature)->resolveLabel([]));
    }

    // ── ThrottlingFeature ─────────────────────────────────────────────────────

    public function test_throttling_feature_within_limit_returns_true_when_under_limit(): void
    {
        $feature = new ThrottlingFeature;

        $this->assertTrue($feature->withinLimit(30, ['per_minute' => 60]));
    }

    public function test_throttling_feature_within_limit_returns_false_when_over_limit(): void
    {
        $feature = new ThrottlingFeature;

        $this->assertFalse($feature->withinLimit(61, ['per_minute' => 60]));
    }

    public function test_throttling_feature_can_be_limit(): void
    {
        $this->assertTrue((new ThrottlingFeature)->canBeLimit());
    }

    // ── FileUploadFeature ─────────────────────────────────────────────────────

    public function test_file_upload_feature_within_limit_returns_true_when_under_limit(): void
    {
        $feature = new FileUploadFeature;
        $tenMbInBytes = 10 * 1024 * 1024;

        $this->assertTrue($feature->withinLimit($tenMbInBytes - 1, ['max_file_size_mb' => 10]));
    }

    public function test_file_upload_feature_within_limit_returns_true_when_equal_to_limit(): void
    {
        $feature = new FileUploadFeature;
        $tenMbInBytes = 10 * 1024 * 1024;

        $this->assertTrue($feature->withinLimit($tenMbInBytes, ['max_file_size_mb' => 10]));
    }

    public function test_file_upload_feature_within_limit_returns_false_when_over_limit(): void
    {
        $feature = new FileUploadFeature;
        $tenMbInBytes = 10 * 1024 * 1024;

        $this->assertFalse($feature->withinLimit($tenMbInBytes + 1, ['max_file_size_mb' => 10]));
    }

    public function test_file_upload_feature_within_limit_returns_false_when_config_missing(): void
    {
        $feature = new FileUploadFeature;

        // max_file_size_mb defaults to 0, so any size > 0 fails
        $this->assertFalse($feature->withinLimit(1, []));
    }

    public function test_file_upload_feature_can_be_limit(): void
    {
        $this->assertTrue((new FileUploadFeature)->canBeLimit());
    }
}
