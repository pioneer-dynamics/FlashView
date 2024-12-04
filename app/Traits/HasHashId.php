<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Vinkla\Hashids\Facades\Hashids as LaravelHashids;

trait HasHashId
{
    protected static function getHashIdConnection()
    {
        $connection = self::autoResolveConnectionName();

        return config('hashids.connections.'. $connection) 
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

    public function resolveRouteBinding($value, $field = null)
    {
        return self::findOrFail(LaravelHashids::connection(self::getHashIdConnection())->decode($value)[0]);
    }

    public function hashId(): Attribute
    {
        return Attribute::make(
            get: fn() => LaravelHashids::connection(self::getHashIdConnection())->encode($this->id)
        );
    }

    public static function findByHashID($hash_id)
    {
        return self::findOrFail(LaravelHashids::connection(self::getHashIdConnection())->decode($hash_id)[0]);
    }
}