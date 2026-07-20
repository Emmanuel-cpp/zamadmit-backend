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
        'capacity'
    ];

    protected function casts(): array
    {
        return [
            'duration_years' => 'integer',
            'capacity' => 'integer',
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

    /**
     * Applications that occupy or claim a seat.
     * Rule: submitted + under_review + accepted count against capacity.
     * Drafts (unpaid) and rejected/waitlisted applications do not.
     */
    public function activeApplicationsCount(): int
    {
        return $this->applications()
            ->whereIn('status', ['submitted', 'under_review', 'accepted'])
            ->count();
    }

    /**
     * True when the programme has a capacity and it is reached.
     * NULL capacity = unlimited = never full.
     */
    public function isFull(): bool
    {
        if ($this->capacity === null) {
            return false;
        }

        return $this->activeApplicationsCount() >= $this->capacity;
    }
}