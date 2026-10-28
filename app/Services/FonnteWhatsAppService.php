<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class FonnteWhatsAppService
{
    private const API_URL = 'https://api.fonnte.com/send';

    public function __construct(private SettingService $settings) {}

    public function isEnabled(): bool
    {
        return $this->settings->getBool('fonnte_enabled')
            && $this->settings->getString('fonnte_token') !== '';
    }

    /**
     * @return array{ok: bool, message: string, http_status: int}
     */
    public function send(string $to, string $message): array
    {
        $token = $this->settings->getString('fonnte_token');
        if ($token === '') {
            throw new InvalidArgumentException('Token Fonnte belum dikonfigurasi.');
        }

        $phone = $this->normalizePhone($to);
        if ($phone === '') {
            throw new InvalidArgumentException('Nomor WhatsApp tidak valid.');
        }

        $response = Http::withHeaders([
            'Authorization' => $token,
        ])->timeout(30)->asForm()->post(self::API_URL, [
            'target' => $phone,
            'message' => $message,
            'countryCode' => '62',
        ]);

        return $this->parseResponse($response);
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }

    /**
     * @return array{ok: bool, message: string, http_status: int}
     */
    private function parseResponse(Response $response): array
    {
        $body = $response->json() ?? [];
        $status = (bool) ($body['status'] ?? false);

        if (! $response->successful() || ! $status) {
            $reason = (string) ($body['reason'] ?? $body['message'] ?? 'Gagal mengirim pesan melalui Fonnte.');
            throw new InvalidArgumentException($reason);
        }

        return [
            'ok' => true,
            'message' => (string) ($body['detail'] ?? 'Pesan berhasil dikirim via Fonnte.'),
            'http_status' => $response->status(),
        ];
    }
}
