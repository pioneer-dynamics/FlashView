<?php

use App\Models\CallSession;
use App\Models\SecureLineCredit;
use App\Models\SecureLineProduct;

/**
 * Creates a CallSession that is currently active (started in the past, ends in the future).
 * Returns the model so tests can access hash_id for navigation.
 */
function createActiveCallSession(): CallSession
{
    return CallSession::factory()->create();
}

/**
 * Creates a CallSession that has not yet started (starts in the future).
 * Returns the model so tests can access hash_id for navigation.
 */
function createFutureCallSession(): CallSession
{
    return CallSession::factory()->notYetStarted()->create();
}

/**
 * Creates a SecureLineCredit and returns it so tests can access its token.
 * Credits are token-scoped — use $credit->token for navigation.
 *
 * @param  bool  $used  Whether the credit has already been used
 */
function createSecureLineCredit(bool $used = false): SecureLineCredit
{
    return SecureLineCredit::factory()->create([
        'secure_line_product_id' => SecureLineProduct::factory()->withStripePrice()->create([
            'duration_minutes' => 30,
            'max_participants' => 5,
        ])->id,
        'used_at' => $used ? now() : null,
    ]);
}
