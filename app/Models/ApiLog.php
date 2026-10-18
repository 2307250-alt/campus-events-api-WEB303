<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'method',
        'status_code',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
