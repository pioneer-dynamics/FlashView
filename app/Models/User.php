<?php

namespace App\Models;

use App\Http\Resources\PlanResource;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    protected $with = [
        'passkeys',
    ];

    public function getSubscriptionAttribute()
    {
        return $this->subscriptions()->active()->first();
    }

    /**
     * Resolve the user's current subscription Plan model.
     */
    public function resolvePlan(): ?Plan
    {
        $stripePrice = $this->subscription?->stripe_price;

        if (! $stripePrice) {
            return null;
        }

        return Plan::where(fn ($q) => $q
            ->where('stripe_monthly_price_id', $stripePrice)
            ->orWhere('stripe_yearly_price_id', $stripePrice)
        )->first();
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

        return $plan && ($plan->features['api']['type'] ?? 'missing') === 'feature';
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
        ];
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
