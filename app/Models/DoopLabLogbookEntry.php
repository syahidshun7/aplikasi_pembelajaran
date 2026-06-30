<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DoopLabLogbookEntry extends Model
{
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';

    protected $table = 'dooplab_logbook_entries';

    protected $fillable = [
        'uuid', 'logbook_id', 'todo_id',
        'activity_date', 'activity_time',
        'activity', 'purpose', 'result',
        'status', 'documentation_path', 'documentation_paths',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'documentation_paths' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m): void {
            if (empty($m->uuid)) $m->uuid = (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string { return 'uuid'; }

    public function logbook() { return $this->belongsTo(DoopLabLogbook::class, 'logbook_id'); }
    public function todo() { return $this->belongsTo(DoopLabTodo::class, 'todo_id'); }
}
