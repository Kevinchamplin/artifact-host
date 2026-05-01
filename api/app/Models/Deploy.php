<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deploy extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['site_id', 'file_count', 'bytes', 'status', 'error'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
