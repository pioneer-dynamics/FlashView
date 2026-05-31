<?php

namespace Tests\Unit\Locker;

use App\Models\LockerCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockerCreditModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_used_returns_false_when_unused(): void
    {
        $credit = LockerCredit::factory()->create();

        $this->assertFalse($credit->isUsed());
    }

    public function test_is_used_returns_true_when_used_at_set(): void
    {
        $credit = LockerCredit::factory()->used()->create();

        $this->assertTrue($credit->isUsed());
    }

    public function test_unused_scope_returns_only_unused(): void
    {
        LockerCredit::factory()->create(['token' => 'unused1']);
        LockerCredit::factory()->used()->create(['token' => 'used1']);

        $unused = LockerCredit::unused()->get();

        $this->assertCount(1, $unused);
        $this->assertEquals('unused1', $unused->first()->token);
    }
}
