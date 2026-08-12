<?php

namespace App\Services;

use App\Models\ServiceTicket;
use App\Models\ServiceTicketStatusLog;
use InvalidArgumentException;

class ServiceTicketNotifier
{
    public function __construct(private HiwaWhatsAppService $hiwa) {}

    /**
     * @return array{notified: bool, hiwa_job_id: string|null, error: string|null}
     */
    public function notifyCreated(ServiceTicket $ticket): array
    {
        $ticket->loadMissing('customer');

        $message = "Halo Bpk/Ibu {$ticket->customer->name},\n\n"
            ."Perangkat {$ticket->deviceSummary()} sudah kami terima di ".brand_name().".\n"
            ."No. tiket: {$ticket->ticket_no}\n\n"
            ."Lacak status servis di tautan ini:\n"
            ."{$ticket->trackingUrl()}\n\n"
            .'Terima kasih.';

        return $this->send($ticket, $message);
    }

    /**
     * @return array{notified: bool, hiwa_job_id: string|null, error: string|null}
     */
    public function notifyStatus(ServiceTicket $ticket, ?string $note = null): array
    {
        $ticket->loadMissing('customer');

        $message = "Halo Bpk/Ibu {$ticket->customer->name},\n\n"
            ."Update servis {$ticket->ticket_no} ({$ticket->deviceSummary()}):\n"
            ."Status sekarang: {$ticket->statusLabel()}\n";

        if (filled($note)) {
            $message .= "Catatan: {$note}\n";
        }

        $message .= "\nLacak status:\n{$ticket->trackingUrl()}\n\nTerima kasih.";

        return $this->send($ticket, $message);
    }

    /**
     * @return array{notified: bool, hiwa_job_id: string|null, error: string|null}
     */
    public function send(ServiceTicket $ticket, string $message): array
    {
        $phone = trim((string) $ticket->customer?->phone);

        if ($phone === '') {
            return ['notified' => false, 'hiwa_job_id' => null, 'error' => 'Nomor WhatsApp pelanggan kosong.'];
        }

        if (! $this->hiwa->isEnabled()) {
            return ['notified' => false, 'hiwa_job_id' => null, 'error' => 'Gateway HiWA belum aktif.'];
        }

        try {
            $result = $this->hiwa->send($phone, $message);

            return [
                'notified' => (bool) $result['ok'],
                'hiwa_job_id' => $result['job_id'],
                'error' => $result['ok'] ? null : $result['message'],
            ];
        } catch (InvalidArgumentException $e) {
            return ['notified' => false, 'hiwa_job_id' => null, 'error' => $e->getMessage()];
        }
    }

    public function applyToLog(ServiceTicketStatusLog $log, array $result): void
    {
        $log->update([
            'notified' => $result['notified'],
            'hiwa_job_id' => $result['hiwa_job_id'],
            'notify_error' => $result['error'],
        ]);
    }
}
