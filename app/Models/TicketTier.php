<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class TicketTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'custom_name',
        'description',
        'price',
        'capacity',
        'sold_count',
        'sales_start',
        'sales_end',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'tier_id');
    }
}
