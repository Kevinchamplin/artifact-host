<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $fillable = [
        'user_id',
        'subdomain',
        'custom_domain',
        'plesk_domain_id',
        'doc_root',
        'ssl_enabled',
        'bytes_used',
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'bytes_used' => 'integer',
        'plesk_domain_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deploys(): HasMany
    {
        return $this->hasMany(Deploy::class);
    }

    public function fqdn(): string
    {
        return $this->subdomain.'.'.config('artifacthost.apex');
    }

    public function url(): string
    {
        return ($this->ssl_enabled ? 'https://' : 'http://').$this->fqdn();
    }
}
