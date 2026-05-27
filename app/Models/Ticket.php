<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_id',
        'tier_id',
        'qr_code',
        'payment_reference',
        'status',
        'purchased_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function ticketTier(): BelongsTo
    {
        return $this->belongsTo(TicketTier::class, 'tier_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_reference', 'paystack_reference');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(TicketScan::class);
    }
}
