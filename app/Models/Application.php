<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'programme_id',
        'status',
        'personal_statement',
        'internal_note',
        'submitted_at',
        'decision_at',
        'student_number'
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'decision_at'  => 'datetime',
        ];
    }

    /* ── Relationships ── */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function programme()
    {
        return $this->belongsTo(Programme::class);
    }

    /* ── Helpers ── */

    public function isPending(): bool
    {
        return in_array($this->status, ['submitted', 'under_review']);
    }

    public function isDecided(): bool
    {
        return in_array($this->status, ['accepted', 'rejected', 'waitlisted']);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}