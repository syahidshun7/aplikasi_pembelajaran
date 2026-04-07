<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreationCollaborator extends Model
{
    public const ROLE_EDITOR = 'editor';
    public const ROLE_CONTRIBUTOR = 'contributor';
    public const ROLE_VIEWER = 'viewer';

    protected $fillable = [
        'creation_id',
        'user_id',
        'role',
        'added_by',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public static function assignableRoles(): array
    {
        return [
            self::ROLE_EDITOR,
            self::ROLE_CONTRIBUTOR,
            self::ROLE_VIEWER,
        ];
    }

    public function creation()
    {
        return $this->belongsTo(Creation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
