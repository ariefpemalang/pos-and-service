<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\FinancialCategory;
use App\Models\ManualCashEntry;
use App\Models\ServiceTicket;
use App\Models\ServiceTicketStatusLog;
use App\Models\Transaction;
use App\Support\CodeGenerator;
use App\Support\PaymentMethodResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceTicketService
{
    public function __construct(
        private ServiceTicketNotifier $notifier,
        private PaymentMethodResolver $paymentResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{ticket: ServiceTicket, notify: array{notified: bool, hiwa_job_id: string|null, error: string|null}}
     */
    public function create(array $data, int $userId, bool $notifyCustomer = false): array
    {
        return DB::transaction(function () use ($data, $userId, $notifyCustomer) {
            $data = $this->resolveCustomer($data);

            $ticket = ServiceTicket::create([
                ...$data,
                'ticket_no' => ServiceTicket::generateTicketNo(),
                'tracking_token' => ServiceTicket::generateTrackingToken(),
                'user_id' => $userId,
                'status' => 'received',
            ]);

            $log = $this->logStatus($ticket, null, 'received', 'Perangkat diterima di toko.', $userId);
            $this->syncDownPaymentTransaction($ticket->load('customer'), $userId);
            $notify = ['notified' => false, 'hiwa_job_id' => null, 'error' => null];

            if ($notifyCustomer) {
                $notify = $this->notifier->notifyCreated($ticket->load('customer'));
                $this->notifier->applyToLog($log, $notify);
            }

            return ['ticket' => $ticket->fresh(['customer', 'technician', 'statusLogs']), 'notify' => $notify];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDetails(ServiceTicket $ticket, array $data, int $userId): ServiceTicket
    {
        if ($ticket->isClosed()) {
            throw new InvalidArgumentException('Tiket yang sudah selesai atau dibatalkan tidak bisa diubah.');
        }

        if (! $ticket->canCreateInvoice()) {
            $incomingDp = round((float) ($data['down_payment'] ?? 0), 2);
            if (abs($incomingDp - $ticket->downPaymentAmount()) > 0.009
                || ($data['down_payment_payment_method'] ?? null) !== $ticket->down_payment_payment_method
                || (int) ($data['down_payment_bank_account_id'] ?? 0) !== (int) ($ticket->down_payment_bank_account_id ?? 0)
            ) {
                throw new InvalidArgumentException('Uang muka tidak bisa diubah setelah nota pelunasan dibuat.');
            }
        }

        return DB::transaction(function () use ($ticket, $data, $userId) {
            $ticket->update($this->resolveCustomer($data));
            $this->syncDownPaymentTransaction($ticket->fresh('customer'), $userId);

            return $ticket->fresh(['customer', 'technician', 'downPaymentTransaction']);
        });
    }

    /**
     * @return array{ticket: ServiceTicket, notify: array{notified: bool, hiwa_job_id: string|null, error: string|null}}
     */
    public function updateStatus(ServiceTicket $ticket, string $status, ?string $note, int $userId, bool $notifyCustomer = true): array
    {
        $allowed = array_keys(config('service-tickets.statuses', []));
        if (! in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Status tidak valid.');
        }

        return DB::transaction(function () use ($ticket, $status, $note, $userId, $notifyCustomer) {
            $locked = ServiceTicket::query()->whereKey($ticket->id)->lockForUpdate()->first();
            if (! $locked) {
                throw new InvalidArgumentException('Tiket servis tidak ditemukan.');
            }

            if ($locked->status === $status) {
                throw new InvalidArgumentException('Status tiket tidak berubah.');
            }

            if ($locked->isClosed()) {
                throw new InvalidArgumentException('Tiket yang sudah selesai atau dibatalkan tidak bisa diubah statusnya.');
            }

            $from = $locked->status;
            $payload = ['status' => $status];

            if ($status === 'ready') {
                $payload['ready_at'] = now();
            }
            if ($status === 'completed') {
                $payload['completed_at'] = now();
            }

            $locked->update($payload);

            if ($status === 'cancelled') {
                $dp = $locked->fresh()->downPaymentTransaction;
                if ($dp) {
                    $this->refundDownPayment($dp, $userId, 'Tiket dibatalkan, uang muka dikembalikan.');
                }
            }

            $log = $this->logStatus($locked, $from, $status, $note, $userId);
            $notify = ['notified' => false, 'hiwa_job_id' => null, 'error' => null];

            if ($notifyCustomer) {
                $notify = $this->notifier->notifyStatus($locked->fresh('customer'), $note);
                $this->notifier->applyToLog($log, $notify);
            }

            return ['ticket' => $locked->fresh(['customer', 'technician', 'statusLogs']), 'notify' => $notify];
        });
    }

    public function resendNotification(ServiceTicket $ticket): array
    {
        $notify = $this->notifier->notifyStatus($ticket->load('customer'));

        ServiceTicketStatusLog::create([
            'service_ticket_id' => $ticket->id,
            'from_status' => $ticket->status,
            'to_status' => $ticket->status,
            'note' => 'Kirim ulang notifikasi WhatsApp.',
            'user_id' => auth()->id(),
            'notified' => $notify['notified'],
            'hiwa_job_id' => $notify['hiwa_job_id'],
            'notify_error' => $notify['error'],
        ]);

        return $notify;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveCustomer(array $data): array
    {
        $newCustomer = $data['new_customer'] ?? null;
        unset($data['new_customer']);

        if (! is_array($newCustomer)) {
            return $data;
        }

        $name = trim((string) ($newCustomer['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Nama pelanggan baru wajib diisi.');
        }

        $customer = Customer::create([
            'code' => Customer::generateCode(),
            'name' => $name,
            'phone' => isset($newCustomer['phone']) ? trim((string) $newCustomer['phone']) ?: null : null,
            'address' => isset($newCustomer['address']) ? trim((string) $newCustomer['address']) ?: null : null,
        ]);

        $data['customer_id'] = $customer->id;

        return $data;
    }

    public function markPaid(ServiceTicket $ticket, Transaction $transaction, int $userId): void
    {
        $locked = ServiceTicket::query()->whereKey($ticket->id)->lockForUpdate()->first() ?? $ticket;

        if ($locked->status === 'cancelled') {
            throw new InvalidArgumentException('Tiket yang dibatalkan tidak bisa ditagih.');
        }

        if ($locked->status === 'completed') {
            return;
        }

        $from = $locked->status;
        $locked->update([
            'status' => 'completed',
            'ready_at' => $locked->ready_at ?? now(),
            'completed_at' => now(),
        ]);

        $log = $this->logStatus(
            $locked,
            $from,
            'completed',
            'Dibayar nota '.$transaction->transaction_no.'.',
            $userId,
        );

        $notify = $this->notifier->notifyStatus(
            $locked->fresh('customer'),
            $this->paidNotificationNote($locked->fresh(), $transaction),
        );
        $this->notifier->applyToLog($log, $notify);
    }

    public function reopenAfterInvoiceCancel(ServiceTicket $ticket, Transaction $transaction, int $userId): void
    {
        if ($ticket->status !== 'completed') {
            return;
        }

        $ticket->update([
            'status' => 'ready',
            'completed_at' => null,
        ]);

        $this->logStatus(
            $ticket,
            'completed',
            'ready',
            'Nota '.$transaction->transaction_no.' dibatalkan. Tiket dibuka lagi untuk penagihan.',
            $userId,
        );
    }

    public function clearDownPayment(ServiceTicket $ticket): void
    {
        $ticket->update([
            'down_payment' => 0,
            'down_payment_payment_method' => null,
            'down_payment_bank_account_id' => null,
        ]);
    }

    public function refundDownPayment(Transaction $downPayment, int $userId, string $reason): ManualCashEntry
    {
        $run = function () use ($downPayment, $userId, $reason) {
            $locked = Transaction::query()->whereKey($downPayment->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isDownPayment() || ! $locked->isCompleted()) {
                throw new InvalidArgumentException('Transaksi uang muka tidak valid.');
            }

            if ($locked->isRefunded()) {
                throw new InvalidArgumentException('Uang muka ini sudah dikembalikan.');
            }

            $amount = round((float) $locked->total, 2);
            if ($amount <= 0) {
                throw new InvalidArgumentException('Nominal uang muka tidak valid.');
            }

            $category = FinancialCategory::query()->firstOrCreate(
                [
                    'name' => (string) config('service-tickets.dp_refund_category', 'Pengembalian Uang Muka'),
                    'type' => 'expense',
                ],
                [
                    'is_active' => true,
                    'sort_order' => 5,
                ],
            );

            $method = $locked->payment_method ?: 'cash';
            $bankAccountId = $method === 'transfer' ? $locked->bank_account_id : null;

            $entry = ManualCashEntry::create([
                'entry_no' => CodeGenerator::nextFromTable(CodeGenerator::PREFIX_MANUAL_EXPENSE, 'manual_cash_entries', 'entry_no'),
                'type' => 'expense',
                'category_id' => $category->id,
                'amount' => $amount,
                'occurred_at' => now(),
                'payment_method' => $method,
                'bank_account_id' => $bankAccountId,
                'description' => trim($reason.' '.$locked->transaction_no),
                'user_id' => $userId,
                'status' => 'completed',
            ]);

            $locked->update([
                'refunded_at' => now(),
                'refund_entry_id' => $entry->id,
            ]);

            if ($locked->service_ticket_id) {
                $ticket = ServiceTicket::query()->lockForUpdate()->find($locked->service_ticket_id);
                if ($ticket) {
                    $this->clearDownPayment($ticket);
                }
            }

            return $entry;
        };

        return DB::transactionLevel() > 0 ? $run() : DB::transaction($run);
    }

    /**
     * @param  array{warranty_days: int, warranty_notes: string|null}  $data
     */
    public function updateWarranty(ServiceTicket $ticket, array $data): ServiceTicket
    {
        if ($ticket->status === 'cancelled') {
            throw new InvalidArgumentException('Tiket yang dibatalkan tidak bisa diubah garansinya.');
        }

        $ticket->update([
            'warranty_days' => $data['warranty_days'],
            'warranty_notes' => $data['warranty_notes'] ?? null,
        ]);

        return $ticket->fresh();
    }

    private function paidNotificationNote(ServiceTicket $ticket, Transaction $transaction): string
    {
        $note = 'Perangkat sudah dibayar dan bisa diambil. Nota: '.$transaction->transaction_no.'.';

        if ($ticket->hasWarranty()) {
            $note .= ' Garansi '.$ticket->warrantySummary().'.';
        }

        return $note;
    }

    private function syncDownPaymentTransaction(ServiceTicket $ticket, int $userId): void
    {
        $amount = $ticket->downPaymentAmount();
        $existing = $ticket->downPaymentTransaction;

        if ($amount <= 0) {
            if ($existing && ! $existing->isRefunded()) {
                $this->refundDownPayment($existing, $userId, 'Uang muka dihapus dari tiket.');
            }

            $ticket->update([
                'down_payment_payment_method' => null,
                'down_payment_bank_account_id' => null,
            ]);

            return;
        }

        if ($existing?->isRefunded()) {
            $existing = null;
        }

        $payment = $this->paymentResolver->resolve(
            (string) $ticket->down_payment_payment_method,
            $ticket->down_payment_bank_account_id ? (int) $ticket->down_payment_bank_account_id : null,
        );

        $cashReceived = $payment['payment_method'] === 'cash' ? $amount : null;
        $notes = 'Uang muka tiket '.$ticket->ticket_no;

        if ($existing) {
            $existing->update([
                'customer_id' => $ticket->customer_id,
                'customer_name' => $ticket->customer?->name,
                'total' => $amount,
                'payment_method' => $payment['payment_method'],
                'bank_account_id' => $payment['bank_account_id'],
                'cash_received' => $cashReceived,
                'cash_change' => $payment['payment_method'] === 'cash' ? 0 : null,
                'notes' => $notes,
            ]);

            return;
        }

        Transaction::create([
            'transaction_no' => CodeGenerator::nextFromTable(CodeGenerator::PREFIX_DOWN_PAYMENT, 'transactions', 'transaction_no'),
            'type' => 'service',
            'customer_id' => $ticket->customer_id,
            'customer_name' => $ticket->customer?->name,
            'user_id' => $userId,
            'service_ticket_id' => $ticket->id,
            'subtotal_items' => 0,
            'subtotal_services' => 0,
            'discount' => 0,
            'down_payment' => 0,
            'is_down_payment' => true,
            'total' => $amount,
            'technician_commission' => 0,
            'owner_service_share' => 0,
            'owner_items_share' => 0,
            'owner_total_share' => 0,
            'status' => 'completed',
            'notes' => $notes,
            'payment_method' => $payment['payment_method'],
            'bank_account_id' => $payment['bank_account_id'],
            'cash_received' => $cashReceived,
            'cash_change' => $payment['payment_method'] === 'cash' ? 0 : null,
        ]);
    }

    private function logStatus(
        ServiceTicket $ticket,
        ?string $from,
        string $to,
        ?string $note,
        int $userId,
    ): ServiceTicketStatusLog {
        return ServiceTicketStatusLog::create([
            'service_ticket_id' => $ticket->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'user_id' => $userId,
        ]);
    }
}
