<?php

namespace App\Traits;

use App\Exceptions\HashIdDecodeException;
use App\Exceptions\InvalidHashIdException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Vinkla\Hashids\Facades\Hashids as LaravelHashids;

trait HasHashId
{
    protected static function getHashIdConnection()
    {
        $connection = self::autoResolveConnectionName();

        return config('hashids.connections.'.$connection)
            ? $connection
            : config('hasids.default');
    }

    private static function autoResolveConnectionName()
    {
        return class_basename(self::class);
    }

    public function initializeHasHashId()
    {
        $this->append('hash_id');
    }

    public static function decodeHashId(string $hashId): ?int
    {
        $decoded = LaravelHashids::connection(self::getHashIdConnection())->decode($hashId);

        return $decoded[0] ?? null;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $id = static::decodeHashId($value);

        if ($id === null) {
            abort(404);
        }

        return self::findOrFail($id);
    }

    public function hashId(): Attribute
    {
        return Attribute::make(
            get: fn () => LaravelHashids::connection(self::getHashIdConnection())->encode($this->id)
        );
    }

    public static function findByHashID(string $hash_id): static
    {
        try {
            $id = static::decodeHashId($hash_id);
        } catch (\Throwable $e) {
            throw new HashIdDecodeException($hash_id, $e);
        }

        if ($id === null) {
            throw new InvalidHashIdException($hash_id);
        }

        return self::findOrFail($id);
    }
}
