<?php

namespace App\Services;

use App\Models\ServiceTicket;
use App\Models\ServiceTicketStatusLog;
use InvalidArgumentException;

class ServiceTicketNotifier
{
    public function __construct(
        private HiwaWhatsAppService $hiwa,
        private FonnteWhatsAppService $fonnte,
    ) {}

    /**
     * @return array{notified: bool, hiwa_job_id: string|null, error: string|null}
     */
    public function notifyCreated(ServiceTicket $ticket): array
    {
        $ticket->loadMissing('customer');

        $time = $ticket->created_at
            ? $ticket->created_at->translatedFormat('d F Y') . ' jam ' . $ticket->created_at->format('H:i')
            : now()->translatedFormat('d F Y') . ' jam ' . now()->format('H:i');

        $message = "Yth. Bpk/Ibu {$ticket->customer->name},\n\n"
            ."Perangkat {$ticket->deviceSummary()} sudah kami terima di ".brand_name()." pada tanggal {$time}.\n"
            ."No. tiket: {$ticket->ticket_no}\n\n"
            ."Untuk Cek status (Tracking) barang Anda, Silahkan buka link dibawah ini. Terima Kasih.\n"
            ."{$ticket->trackingUrl()}";

        return $this->send($ticket, $message);
    }

    /**
     * @return array{notified: bool, hiwa_job_id: string|null, error: string|null}
     */
    public function notifyStatus(ServiceTicket $ticket, ?string $note = null): array
    {
        $ticket->loadMissing(['customer', 'itemLines', 'serviceLines']);

        $message = "Yth. Bpk/Ibu {$ticket->customer->name},\n\n"
            ."Update servis {$ticket->ticket_no} ({$ticket->deviceSummary()}):\n";

        if ($ticket->status === 'ready') {
            // Hitung biaya dari total rincian pengerjaan / estimasi
            $totalCost = $ticket->hasWorkLines() ? $ticket->workTotal() : (float) ($ticket->estimated_cost ?? 0);
            $costFmt = 'Rp ' . number_format($totalCost, 0, ',', '.');

            $message .= "Status sekarang: *Sudah Jadi* dan *Bisa Diambil* dengan biaya *{$costFmt}*.\n";
        } elseif ($ticket->status === 'cancelled') {
            $message .= "Status sekarang: *Dibatalkan dan Bisa Diambil*.\n";
        } else {
            $message .= "Status sekarang: {$ticket->statusLabel()}\n";
        }

        if (filled($note)) {
            $message .= "Catatan: {$note}\n";
        }

        $message .= "\nUntuk Cek status (Tracking) barang Anda, Silahkan buka link dibawah ini. Terima Kasih.\n{$ticket->trackingUrl()}";

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

        // 1. Coba kirim via Fonnte jika aktif
        if ($this->fonnte->isEnabled()) {
            try {
                $res = $this->fonnte->send($phone, $message);

                return [
                    'notified' => true,
                    'hiwa_job_id' => null,
                    'error' => null,
                ];
            } catch (InvalidArgumentException $e) {
                return ['notified' => false, 'hiwa_job_id' => null, 'error' => 'Fonnte: '.$e->getMessage()];
            }
        }

        // 2. Coba kirim via HiWA jika aktif
        if ($this->hiwa->isEnabled()) {
            try {
                $result = $this->hiwa->send($phone, $message);

                return [
                    'notified' => (bool) $result['ok'],
                    'hiwa_job_id' => $result['job_id'],
                    'error' => $result['ok'] ? null : $result['message'],
                ];
            } catch (InvalidArgumentException $e) {
                return ['notified' => false, 'hiwa_job_id' => null, 'error' => 'HiWA: '.$e->getMessage()];
            }
        }

        return ['notified' => false, 'hiwa_job_id' => null, 'error' => 'Gateway WhatsApp (Fonnte / HiWA) belum aktif.'];
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
