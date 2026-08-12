<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private SettingService $settings,
    ) {
        $this->middleware('permission:settings view')->only(['show']);
        $this->middleware('permission:settings edit')->only(['update']);
    }

    public function show(): JsonResponse
    {
        $all = $this->settings->all();

        return $this->apiSuccess('Pengaturan aplikasi berhasil diambil.', [
            'settings' => [
                'app_name' => (string) ($all['app_name'] ?? ''),
                'app_tagline' => (string) ($all['app_tagline'] ?? ''),
                'app_description' => (string) ($all['app_description'] ?? ''),
                'company_address' => (string) ($all['company_address'] ?? ''),
                'company_whatsapp' => (string) ($all['company_whatsapp'] ?? ''),
                'hiwa_enabled' => (bool) ($all['hiwa_enabled'] ?? false),
                'hiwa_token_device' => (string) ($all['hiwa_token_device'] ?? ''),
                'logo_url' => brand_has_custom_logo() ? brand_logo_url() : null,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_tagline' => ['nullable', 'string', 'max:255'],
            'app_description' => ['nullable', 'string', 'max:2000'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_whatsapp' => ['nullable', 'string', 'max:30'],
            'hiwa_enabled' => ['nullable', 'boolean'],
            'hiwa_token_device' => ['nullable', 'string', 'max:500'],
        ]);

        $payload = [
            'app_name' => $validated['app_name'],
            'app_tagline' => $validated['app_tagline'] ?? '',
            'app_description' => $validated['app_description'] ?? '',
            'company_address' => trim((string) ($validated['company_address'] ?? '')),
            'company_whatsapp' => trim((string) ($validated['company_whatsapp'] ?? '')),
            'hiwa_enabled' => $request->boolean('hiwa_enabled'),
        ];

        if ($request->exists('hiwa_token_device')) {
            $payload['hiwa_token_device'] = trim((string) ($validated['hiwa_token_device'] ?? ''));
        }

        $this->settings->setMany($payload);

        $all = $this->settings->all();

        return $this->apiSuccess('Pengaturan aplikasi berhasil disimpan.', [
            'settings' => [
                'app_name' => (string) ($all['app_name'] ?? ''),
                'app_tagline' => (string) ($all['app_tagline'] ?? ''),
                'app_description' => (string) ($all['app_description'] ?? ''),
                'company_address' => (string) ($all['company_address'] ?? ''),
                'company_whatsapp' => (string) ($all['company_whatsapp'] ?? ''),
                'hiwa_enabled' => (bool) ($all['hiwa_enabled'] ?? false),
                'hiwa_token_device' => (string) ($all['hiwa_token_device'] ?? ''),
                'logo_url' => brand_has_custom_logo() ? brand_logo_url() : null,
            ],
        ]);
    }
}
