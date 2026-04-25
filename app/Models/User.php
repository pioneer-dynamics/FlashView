<?php

namespace App\Models;

use App\Http\Resources\PlanResource;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use PioneerDynamics\LaravelPasskey\Contracts\PasskeyUser;
use PioneerDynamics\LaravelPasskey\Traits\HasPasskeys;

use function Illuminate\Events\queueable;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use Billable;
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPasskeys;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'notify_secret_retrieved',
        'webhook_url',
        'webhook_secret',
        'store_masked_recipient_email',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'webhook_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'subscription',
        'plan',
        'frequency',
        'is_admin',
    ];

    protected $with = [
        'passkeys',
        'senderIdentity',
    ];

    public function getSubscriptionAttribute()
    {
        return $this->subscriptions()->active()->first();
    }

    /**
     * Resolve the user's current Plan model.
     * Subscribed users get their paid plan; everyone else gets the free plan.
     */
    public function resolvePlan(): ?Plan
    {
        $stripePrice = $this->subscription?->stripe_price;

        if ($stripePrice) {
            return Plan::where(fn ($q) => $q
                ->where('stripe_monthly_price_id', $stripePrice)
                ->orWhere('stripe_yearly_price_id', $stripePrice)
            )->first();
        }

        return Plan::where('is_free_plan', true)->first();
    }

    /**
     * Check if the user's plan includes API access.
     */
    public function hasApiAccess(): bool
    {
        if (! $this->subscribed()) {
            return false;
        }

        $plan = $this->resolvePlan();

        return $plan && isset($plan->features['api']) && $plan->features['api']['type'] === 'feature';
    }

    /**
     * Check if the user's plan supports email notifications.
     */
    public function planSupportsEmailNotifications(): bool
    {
        if (! $this->subscribed()) {
            return false;
        }

        $plan = $this->resolvePlan();

        return $plan && isset($plan->features['email_notification']) && $plan->features['email_notification']['type'] === 'feature';
    }

    /**
     * Check if the user's plan supports webhook notifications.
     */
    public function planSupportsWebhook(): bool
    {
        if (! $this->subscribed()) {
            return false;
        }

        $plan = $this->resolvePlan();

        return $plan && isset($plan->features['webhook_notification']) && $plan->features['webhook_notification']['type'] === 'feature';
    }

    public function getPlanAttribute(): PlanResource
    {
        return new PlanResource($this->resolvePlan());
    }

    public function getFrequencyAttribute(): string
    {
        $stripePrice = $this->subscription?->stripe_price;
        $plan = $this->resolvePlan();

        if ($plan) {
            return $plan->stripe_monthly_price_id == $stripePrice ? 'monthly' : 'yearly';
        } else {
            return 'monthly';
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notify_secret_retrieved' => 'boolean',
            'webhook_secret' => 'encrypted',
            'store_masked_recipient_email' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array(strtolower($this->email), array_map('strtolower', config('admin.emails', [])));
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->isAdmin();
    }

    public function hasWebhookConfigured(): bool
    {
        return filled($this->webhook_url) && filled($this->webhook_secret);
    }

    public function senderIdentity(): HasOne
    {
        return $this->hasOne(SenderIdentity::class);
    }

    public function hasVerifiedSenderIdentity(): bool
    {
        return $this->senderIdentity?->isVerified() ?? false;
    }

    public function planSupportsSenderIdentity(): bool
    {
        if (! $this->subscribed()) {
            return false;
        }

        $plan = $this->resolvePlan();

        return $plan && isset($plan->features['sender_identity']) && $plan->features['sender_identity']['type'] === 'feature';
    }

    public function secrets(): HasMany
    {
        return $this->hasMany(Secret::class);
    }

    protected static function booted(): void
    {
        static::updated(queueable(function (User $customer) {
            if ($customer->hasStripeId()) {
                $customer->syncStripeCustomerDetails();
            }
        }));

        static::deleting(queueable(function (User $customer) {
            $customer->tokens()->delete();
            $customer->subscriptions()->active()->each(function ($subscription) {
                $subscription->cancelNow();
            });
        }));
    }
}
