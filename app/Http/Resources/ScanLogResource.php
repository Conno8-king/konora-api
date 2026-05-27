<?php

namespace App\Http\Resources;

use App\Models\TicketScan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScanLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TicketScan $scan */
        $scan = $this->resource;

        return [
            'id' => $scan->id,
            'result' => $scan->result,
            'attempted_code' => $scan->attempted_code,
            'scanned_at' => $scan->scanned_at?->toIso8601String(),
            'scanned_at_human' => $scan->scanned_at?->diffForHumans(),
            'scanner' => $this->whenLoaded('scannedBy', function () use ($scan) {
                $user = $scan->scannedBy;
                if (! $user) {
                    return null;
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                ];
            }),
            'ticket' => $this->whenLoaded('ticket', function () use ($scan) {
                return $scan->ticket
                    ? new ScanTicketResource($scan->ticket)
                    : null;
            }),
        ];
    }
}
