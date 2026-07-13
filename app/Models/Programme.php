<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Programme extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'institution_id',
        'name',
        'qualification',
        'school',
        'duration_years',
        'study_mode',
        'intake',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'duration_years' => 'integer',
        ];
    }

    /* ── Relationships ── */

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function requirements()
    {
        return $this->hasMany(ProgrammeRequirement::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}