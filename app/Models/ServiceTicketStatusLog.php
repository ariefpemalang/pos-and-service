<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_ticket_id', 'from_status', 'to_status', 'note',
    'user_id', 'notified', 'hiwa_job_id', 'notify_error',
])]
class ServiceTicketStatusLog extends Model
{
    use HasUuids;

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class, 'service_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toStatusLabel(): string
    {
        return (string) config("service-tickets.statuses.{$this->to_status}", $this->to_status);
    }

    protected function casts(): array
    {
        return [
            'notified' => 'boolean',
        ];
    }
}
