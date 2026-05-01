<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservedSubdomain extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'subdomain';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['subdomain'];
}
