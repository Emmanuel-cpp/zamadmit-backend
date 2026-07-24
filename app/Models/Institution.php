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
        'student_number_prefix',
        'student_number_length',
        'next_student_seq',
        'application_fee',
        'is_suspended', 
        'suspended_at', 
        'suspension_reason', 
        'onboarded_by'
    ];

    protected function casts(): array
    {
        return [
            'is_accepting_applications' => 'boolean',
            'application_deadline'      => 'date',
            'established'               => 'integer',
            'is_suspended'              => 'boolean', 
            'suspended_at'              => 'datetime'
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

    public function users()
    {
        return $this->hasMany(User::class);
    }
}