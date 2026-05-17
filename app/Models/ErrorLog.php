<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'trace_id',
        'exception_class',
        'file',
        'line',
        'message',
        'status_code',
        'url',
        'method',
        'user_id',
        'ip',
        'user_agent',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
