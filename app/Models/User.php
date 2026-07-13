<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'nrc',
        'phone',
        'province',
        'interests',
        'date_of_birth',
        'role',
        'institution_id',
        'profile_complete',
        'must_change_password',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'date_of_birth'     => 'date',
            'profile_complete'  => 'boolean',
            'must_change_password'  => 'boolean',
            'password_changed_at'   => 'datetime',
            'date_of_birth'         => 'date',
            'interests'             => 'array',
        ];
    }

    /* ── Relationships ── */

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function grades()
    {
        return $this->hasMany(UserGrade::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    /* ── Helpers ── */

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isInstitutionAdmin(): bool
    {
        return $this->role === 'institution_admin';
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}