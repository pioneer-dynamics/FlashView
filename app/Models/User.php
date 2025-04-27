<?php

namespace App\Models;

use App\Http\Resources\PlanResource;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    /** @use HasFactory<\Database\Factories\UserFactory> */
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

    public function getSubscriptionAttribute()
    {
        return $this->subscriptions()->active()->first();
    }

    public function getPlanAttribute()
    {
        $stripe_price_id = optional($this->subscription)->stripe_price;

        return new PlanResource(Plan::where('stripe_monthly_price_id', $stripe_price_id)->orWhere('stripe_yearly_price_id', $stripe_price_id)->first());
    }

    public function getFrequencyAttribute()
    {
        $stripe_price_id = optional($this->subscription)->stripe_price;

        $plan = Plan::where('stripe_monthly_price_id', $stripe_price_id)->orWhere('stripe_yearly_price_id', $stripe_price_id)->first();

        if ($plan) {
            return $plan->stripe_monthly_price_id == $stripe_price_id ? 'monthly' : 'yearly';
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
        ];
    }

    public function secrets()
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
            $customer->subscriptions()->active()->each(function ($subscription) {
                $subscription->cancelNow();
            });
        }));
    }
}
