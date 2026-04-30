<?php

namespace App\Models;

use Database\Factories\PipeSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PipeSession extends Model
{
    /** @use HasFactory<PipeSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'is_complete',
        'transfer_mode',
        'storage_path',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_complete' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (PipeSession $session): void {
            if ($session->storage_path && Storage::exists($session->storage_path)) {
                Storage::delete($session->storage_path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(PipeSignal::class);
    }
}
