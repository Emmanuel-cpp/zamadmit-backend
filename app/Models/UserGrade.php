<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGrade extends Model
{
    protected $fillable = ['user_id', 'subject', 'grade'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}