<?php

namespace App\Models;

use App\Models\Scopes\ActiveScope;
use App\Notifications\SecretRetrievedNotification;
use App\Traits\HasHashId;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use mathewparet\LaravelPolicyAbilitiesExport\Traits\ExportsPermissions;

#[ScopedBy([ActiveScope::class])]
class Secret extends Model
{
    use ExportsPermissions;

    /** @use HasFactory<\Database\Factories\SecretFactory> */
    use HasFactory;

    use HasHashId;

    protected $fillable = [
        'message',
        'filepath',
        'filename',
        'user_id',
        'expires_at',
    ];

    protected $hidden = [
        'message',
        'filepath',
        'ip_address_sent',
        'ip_address_retrieved',
        'updated_at',
        'id',
    ];

    protected function casts()
    {
        return [
            'expires_at' => 'datetime',
            'retrieved_at' => 'datetime',
            'message' => 'encrypted',
            'ip_address_sent' => 'encrypted',
            'ip_address_retrieved' => 'encrypted',
        ];
    }

    #[Scope]
    protected function expired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    #[Scope]
    protected function readyToPrune($query)
    {
        return $query->where('expires_at', '<', now()->subDays(config('secrets.prune_after')))->where('message', null);
    }

    #[Scope]
    protected function active($query)
    {
        return $query->where('expires_at', '>=', now())->where('message', '!=', null);
    }

    public static function booted()
    {
        static::creating(function (Secret $secret) {
            $secret->expires_at = $secret->expires_at ?? now()->addMinutes(config('secrets.expiry'));
            $secret->ip_address_sent = request()->ip();
        });

        static::retrieved(function (Secret $secret) {

            if (App::runningInConsole()) {
                return;
            }

            if (blank($secret->retrieved_at) || blank($secret->ip_address_retrieved)) {
                $secret->markSilentlyAsRetrieved();
                // DB::table($secret->getTable())->where('id', $secret->id)->update([
                //     'retrieved_at' => now(),
                //     'ip_address_retrieved' => encrypt(request()->ip(), false),
                //     'message' => null
                // ]);

                if ($user = $secret->user) {
                    $plan = $user->plan->jsonSerialize();

                    /**
                     * @var \App\Models\User $user
                     */
                    if (isset($plan['id'])) {
                        if ($plan['settings']['notification']['notifications']) {
                            $user->notify(new SecretRetrievedNotification($secret));
                        }
                    }
                }
            }
        });
    }

    public function markAsRetrieved()
    {
        $this->forceFill([
            'retrieved_at' => now(),
            'ip_address_retrieved' => request()->ip(),
            'message' => null,
        ])->save();
    }

    public function markSilentlyAsRetrieved()
    {
        DB::table($this->getTable())->where('id', $this->id)->update([
            'retrieved_at' => now(),
            'ip_address_retrieved' => encrypt(request()->ip(), false),
            'message' => null,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
