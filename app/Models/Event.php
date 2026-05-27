<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'custom_category',
        'date',
        'start_time',
        'end_time',
        'venue_name',
        'venue_address',
        'banner_path',
        'visibility',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticketTiers(): HasMany
    {
        return $this->hasMany(TicketTier::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function calendarStartsAt(): ?Carbon
    {
        if (! $this->date) {
            return null;
        }

        return Carbon::parse(trim((string) $this->date).' '.trim((string) $this->start_time));
    }

    public function calendarEndsAt(): ?Carbon
    {
        if (! $this->date) {
            return null;
        }

        return Carbon::parse(trim((string) $this->date).' '.trim((string) $this->end_time));
    }

    public function attendeeViewHasEnded(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->status === 'ended') {
            return true;
        }

        $end = $this->calendarEndsAt();

        return $end && $end->lt($now);
    }

    /**
     * Published events whose calendar end is still in the future (for “upcoming” tickets).
     *
     * @param  Builder<Event>  $query
     */
    public function scopeWhereUpcomingForTicketListing(Builder $query): void
    {
        $query->where($query->getModel()->getTable().'.status', 'published');
        self::applyCalendarEndOnOrAfter($query, now());
    }

    /**
     * Events that are over for ticket listing (“past” with an active ticket).
     *
     * @param  Builder<Event>  $query
     */
    public function scopeWherePastForTicketListing(Builder $query): void
    {
        $table = $query->getModel()->getTable();

        $query->where(function (Builder $q) use ($table): void {
            $q->where($table.'.status', 'ended')
                ->orWhere(function (Builder $q2) use ($table): void {
                    self::applyCalendarEndBefore($q2, now());
                });
        });
    }

    /**
     * @param  Builder<Event>  $query
     */
    private static function applyCalendarEndBefore(Builder $query, Carbon $when): void
    {
        $table = $query->getModel()->getTable();
        $m = $when->format('Y-m-d H:i:s');

        if ($query->getConnection()->getDriverName() === 'sqlite') {
            $query->whereRaw("datetime({$table}.date || ' ' || {$table}.end_time) < datetime(?)", [$m]);
        } else {
            $query->whereRaw("TIMESTAMP({$table}.date, {$table}.end_time) < ?", [$m]);
        }
    }

    /**
     * @param  Builder<Event>  $query
     */
    private static function applyCalendarEndOnOrAfter(Builder $query, Carbon $when): void
    {
        $table = $query->getModel()->getTable();
        $m = $when->format('Y-m-d H:i:s');

        if ($query->getConnection()->getDriverName() === 'sqlite') {
            $query->whereRaw("datetime({$table}.date || ' ' || {$table}.end_time) >= datetime(?)", [$m]);
        } else {
            $query->whereRaw("TIMESTAMP({$table}.date, {$table}.end_time) >= ?", [$m]);
        }
    }
}
