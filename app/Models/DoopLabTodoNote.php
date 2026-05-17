<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoopLabTodoNote extends Model
{
    protected $table = 'dooplab_todo_notes';

    protected $fillable = [
        'todo_id',
        'author_user_id',
        'note',
        'image_path',
    ];

    public function todo()
    {
        return $this->belongsTo(DoopLabTodo::class, 'todo_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
