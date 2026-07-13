<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'short_name',
        'type',
        'city',
        'province',
        'description',
        'established',
        'application_deadline',
        'is_accepting_applications',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'is_accepting_applications' => 'boolean',
            'application_deadline'      => 'date',
            'established'               => 'integer',
        ];
    }

    /* ── Relationships ── */

    public function programmes()
    {
        return $this->hasMany(Programme::class);
    }

    public function admins()
    {
        return $this->hasMany(User::class)->where('role', 'institution_admin');
    }
}