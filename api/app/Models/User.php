<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class User extends Model
{
    protected $fillable = ['email', 'api_key_hash', 'tier', 'stripe_customer'];

    protected $hidden = ['api_key_hash'];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public static function generateApiKey(): string
    {
        return 'ah_'.Str::random(48);
    }

    public static function hashKey(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
