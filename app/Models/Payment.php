<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * ZamAdmit's platform commission, as a fraction of the application fee.
     * 5% of every transaction; the remainder is forwarded to the institution.
     * The split is stored explicitly on each payment row, so changing this
     * rate later never rewrites history.
     */
    public const PLATFORM_RATE = 0.05;

    protected $fillable = [
        'application_id',
        'user_id',
        'amount',
        'platform_fee',
        'institution_amount',
        'provider',
        'phone',
        'status',
        'reference',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'             => 'decimal:2',
            'platform_fee'       => 'decimal:2',
            'institution_amount' => 'decimal:2',
            'completed_at'       => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}