<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_ticket_id', 'workshop_service_id', 'service_code', 'service_name',
    'quantity', 'unit_price', 'subtotal',
])]
class ServiceTicketServiceLine extends Model
{
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class, 'service_ticket_id');
    }

    public function workshopService(): BelongsTo
    {
        return $this->belongsTo(WorkshopService::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }
}
