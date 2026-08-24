<?php

namespace App\Models;

use App\Support\CodeGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'ticket_no', 'tracking_token', 'customer_id', 'technician_id', 'user_id',
    'device_type', 'brand', 'model', 'color', 'serial_imei', 'specs',
    'accessories', 'complaint', 'diagnosis', 'estimated_cost', 'down_payment',
    'down_payment_payment_method', 'down_payment_bank_account_id', 'status',
    'internal_notes', 'warranty_days', 'warranty_notes', 'ready_at', 'completed_at',
])]
class ServiceTicket extends Model
{
    use HasUuids;

    public static function generateTicketNo(): string
    {
        return CodeGenerator::next(CodeGenerator::PREFIX_SERVICE_TICKET, static::class, 'ticket_no');
    }

    public static function generateTrackingToken(): string
    {
        return (string) Str::uuid();
    }

    public function isClosed(): bool
    {
        return in_array($this->status, config('service-tickets.closed_statuses', []), true);
    }

    public function statusLabel(): string
    {
        return (string) config("service-tickets.statuses.{$this->status}", $this->status);
    }

    public function statusColor(): string
    {
        return (string) config("service-tickets.status_colors.{$this->status}", 'secondary');
    }

    public function deviceTypeLabel(): string
    {
        return (string) config("service-tickets.device_types.{$this->device_type}", $this->device_type);
    }

    public function deviceSummary(): string
    {
        return trim($this->deviceTypeLabel().' '.$this->brand.' '.$this->model);
    }

    public function trackingUrl(): string
    {
        return route('service-tickets.track', $this->tracking_token);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ServiceTicketStatusLog::class);
    }

    public function itemLines(): HasMany
    {
        return $this->hasMany(ServiceTicketItem::class);
    }

    public function serviceLines(): HasMany
    {
        return $this->hasMany(ServiceTicketServiceLine::class);
    }

    public function hasWorkLines(): bool
    {
        if ($this->relationLoaded('itemLines') && $this->relationLoaded('serviceLines')) {
            return $this->itemLines->isNotEmpty() || $this->serviceLines->isNotEmpty();
        }

        return $this->itemLines()->exists() || $this->serviceLines()->exists();
    }

    /**
     * @return list<array{item_id: int, code: string, name: string, quantity: int, unit_price: float}>
     */
    public function workItemsPayload(): array
    {
        return $this->itemLines
            ->map(fn (ServiceTicketItem $line) => [
                'item_id' => (int) $line->item_id,
                'code' => (string) $line->item_code,
                'name' => (string) $line->item_name,
                'quantity' => (int) $line->quantity,
                'unit_price' => (float) $line->unit_price,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{workshop_service_id: int|null, code: string|null, name: string, quantity: int, unit_price: float}>
     */
    public function workServicesPayload(): array
    {
        return $this->serviceLines
            ->map(fn (ServiceTicketServiceLine $line) => [
                'workshop_service_id' => $line->workshop_service_id !== null ? (int) $line->workshop_service_id : null,
                'code' => $line->service_code,
                'name' => (string) $line->service_name,
                'quantity' => (int) $line->quantity,
                'unit_price' => (float) $line->unit_price,
            ])
            ->values()
            ->all();
    }

    public function workTotal(): float
    {
        $items = $this->relationLoaded('itemLines')
            ? $this->itemLines->sum(fn (ServiceTicketItem $line) => (float) $line->subtotal)
            : (float) $this->itemLines()->sum('subtotal');
        $services = $this->relationLoaded('serviceLines')
            ? $this->serviceLines->sum(fn (ServiceTicketServiceLine $line) => (float) $line->subtotal)
            : (float) $this->serviceLines()->sum('subtotal');

        return round($items + $services, 2);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function paidTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class)->ofMany(
            ['created_at' => 'max'],
            fn ($query) => $query->where('status', 'completed')->where('is_down_payment', false),
        );
    }

    public function downPaymentTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class)->ofMany(
            ['created_at' => 'max'],
            fn ($query) => $query->where('status', 'completed')
                ->where('is_down_payment', true)
                ->whereNull('refunded_at'),
        );
    }

    public function downPaymentBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'down_payment_bank_account_id');
    }

    public function downPaymentAmount(): float
    {
        return max(0, (float) $this->down_payment);
    }

    public function hasDownPayment(): bool
    {
        return $this->downPaymentAmount() > 0;
    }

    public function remainingEstimate(): ?float
    {
        if ($this->estimated_cost === null) {
            return null;
        }

        return round(max(0, (float) $this->estimated_cost - $this->downPaymentAmount()), 2);
    }

    public function hasFinalInvoice(): bool
    {
        return $this->transactions()
            ->where('status', 'completed')
            ->where('is_down_payment', false)
            ->exists();
    }

    public function canCreateInvoice(): bool
    {
        // Nota bayar hanya boleh dibuat saat status "Bisa diambil"
        // dan tiket belum punya nota pelunasan. Setelah bayar, status jadi "Sudah diambil".
        return $this->status === 'ready' && ! $this->hasFinalInvoice();
    }

    public function invoiceNotes(): string
    {
        $lines = [
            'Tiket '.$this->ticket_no,
            $this->deviceSummary(),
        ];

        if (filled($this->serial_imei)) {
            $lines[] = 'IMEI/SN: '.$this->serial_imei;
        }

        if (filled($this->complaint)) {
            $lines[] = 'Keluhan: '.$this->complaint;
        }

        if ($this->hasDownPayment()) {
            $lines[] = 'Uang muka: Rp '.number_format($this->downPaymentAmount(), 0, ',', '.');
        }

        if ($this->hasWarranty()) {
            $lines[] = 'Garansi: '.$this->warrantySummary();
        }

        return implode("\n", $lines);
    }

    public function warrantyDays(): int
    {
        return max(0, (int) $this->warranty_days);
    }

    public function hasWarranty(): bool
    {
        return $this->warrantyDays() > 0;
    }

    public function warrantyStartsAt(): ?Carbon
    {
        if (! $this->hasWarranty() || ! $this->completed_at) {
            return null;
        }

        return $this->completed_at->copy();
    }

    public function warrantyEndsAt(): ?Carbon
    {
        $start = $this->warrantyStartsAt();
        if (! $start) {
            return null;
        }

        return $start->copy()->addDays($this->warrantyDays())->endOfDay();
    }

    public function warrantyDaysRemaining(): ?int
    {
        $end = $this->warrantyEndsAt();
        if (! $end) {
            return null;
        }

        $endDay = $end->copy()->startOfDay();
        $today = now()->startOfDay();

        if ($today->gt($endDay)) {
            return 0;
        }

        return (int) $today->diffInDays($endDay);
    }

    /**
     * @return 'none'|'pending'|'active'|'expired'
     */
    public function warrantyStatus(): string
    {
        if ($this->status === 'cancelled' || ! $this->hasWarranty()) {
            return 'none';
        }

        if (! $this->completed_at) {
            return 'pending';
        }

        $end = $this->warrantyEndsAt();

        return $end && now()->lte($end) ? 'active' : 'expired';
    }

    public function warrantyStatusLabel(): string
    {
        return match ($this->warrantyStatus()) {
            'pending' => 'Menunggu selesai',
            'active' => 'Aktif',
            'expired' => 'Habis',
            default => 'Tidak ada',
        };
    }

    public function warrantyStatusColor(): string
    {
        return match ($this->warrantyStatus()) {
            'pending' => 'info',
            'active' => 'success',
            'expired' => 'danger',
            default => 'secondary',
        };
    }

    public function warrantyPeriodLabel(): string
    {
        if (! $this->hasWarranty()) {
            return 'Tanpa garansi';
        }

        return $this->warrantyDays().' hari';
    }

    public function warrantySummary(): string
    {
        if (! $this->hasWarranty()) {
            return 'Tanpa garansi';
        }

        $end = $this->warrantyEndsAt();
        if ($end) {
            return $this->warrantyPeriodLabel().' s/d '.$end->timezone(config('app.timezone'))->format('d/m/Y');
        }

        return $this->warrantyPeriodLabel().' (mulai setelah servis selesai)';
    }

    public function warrantyIndexLabel(): string
    {
        return match ($this->warrantyStatus()) {
            'pending' => $this->warrantyPeriodLabel(),
            'active' => 'Aktif · '.$this->warrantyDaysRemaining().'h',
            'expired' => 'Habis',
            default => '—',
        };
    }

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'warranty_days' => 'integer',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
