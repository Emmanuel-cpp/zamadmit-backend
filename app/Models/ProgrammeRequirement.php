<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeRequirement extends Model
{
    // No timestamps needed for this simple lookup table
    public $timestamps = false;

    protected $fillable = [
        'programme_id',
        'subject',
        'min_grade',
    ];

    protected function casts(): array
    {
        return [
            'min_grade' => 'integer',
        ];
    }

    /* ── Relationships ── */

    public function programme()
    {
        return $this->belongsTo(Programme::class);
    }
}