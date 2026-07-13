<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'path',
        'size_bytes',
        'verified',
        'verification_status',
        'verification_reason',
        'ocr_text',
        'confidence_score',
    ];

    protected function casts(): array
    {
        return [
            'verified'         => 'boolean',
            'size_bytes'       => 'integer',
            'confidence_score' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}