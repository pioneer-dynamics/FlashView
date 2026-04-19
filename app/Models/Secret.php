<?php

namespace App\Models;

use App\Jobs\SendWebhookNotification;
use App\Models\Scopes\ActiveScope;
use App\Notifications\SecretRetrievedNotification;
use App\Traits\HasHashId;
use Database\Factories\SecretFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use mathewparet\LaravelPolicyAbilitiesExport\Traits\ExportsPermissions;

#[ScopedBy([ActiveScope::class])]
class Secret extends Model
{
    use ExportsPermissions;

    /** @use HasFactory<SecretFactory> */
    use HasFactory;

    use HasHashId;

    protected $fillable = [
        'message',
        'filepath',
        'filename',
        'file_size',
        'file_mime_type',
        'user_id',
        'expires_at',
        'masked_recipient_email',
        'sender_company_name',
        'sender_domain',
        'sender_email',
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
            'masked_recipient_email' => 'encrypted',
            'sender_company_name' => 'encrypted',
            'sender_domain' => 'encrypted',
            'sender_email' => 'encrypted',
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
        return $query->where('expires_at', '<', now()->subDays(config('secrets.prune_after')))
            ->whereNull('message')
            ->whereNull('filepath');
    }

    #[Scope]
    protected function active($query)
    {
        return $query->where('expires_at', '>=', now())
            ->where(fn ($q) => $q->whereNotNull('message')->orWhereNotNull('filepath'));
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

            // File secrets are consumed during the download route, not when metadata is loaded.
            if ($secret->isFileSecret()) {
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
                     * @var User $user
                     */
                    if (isset($plan['id'])) {
                        if (($plan['settings']['email_notification']['email'] ?? false) && $user->notify_secret_retrieved) {
                            $user->notify(new SecretRetrievedNotification($secret));
                        }

                        $planSupportsWebhook = ($plan['settings']['webhook_notification']['webhook'] ?? false);
                        if ($planSupportsWebhook && $user->hasWebhookConfigured()) {
                            dispatch(new SendWebhookNotification(
                                webhookUrl: $user->webhook_url,
                                webhookSecret: $user->webhook_secret,
                                hashId: $secret->hash_id,
                                createdAt: $secret->created_at->toIso8601String(),
                                retrievedAt: now()->toIso8601String(),
                                userId: $user->id,
                            ));
                        }
                    }
                }
            }
        });
    }

    public function isFileSecret(): bool
    {
        return filled($this->filepath);
    }

    public function deleteFile(): void
    {
        if ($this->filepath && Storage::exists($this->filepath)) {
            Storage::delete($this->filepath);
        }
    }

    public function markAsRetrieved(): void
    {
        $this->deleteFile();
        $this->forceFill([
            'retrieved_at' => now(),
            'ip_address_retrieved' => request()->ip(),
            'message' => null,
            'filepath' => null,
            'filename' => null,
            'file_size' => null,
            'file_mime_type' => null,
        ])->save();
    }

    public function markSilentlyAsRetrieved(): void
    {
        $this->deleteFile();
        DB::table($this->getTable())->where('id', $this->id)->update([
            'retrieved_at' => now(),
            'ip_address_retrieved' => encrypt(request()->ip(), false),
            'message' => null,
            'filepath' => null,
            'filename' => null,
            'file_size' => null,
            'file_mime_type' => null,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
