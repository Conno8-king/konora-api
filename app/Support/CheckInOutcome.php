<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketScan;

class CheckInOutcome
{
    public function __construct(
        public readonly string $result,
        public readonly ?Ticket $ticket,
        public readonly TicketScan $scan,
        public readonly ?TicketScan $firstValidScan = null,
    ) {}
}
