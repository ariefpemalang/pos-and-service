<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesEntityPhoto;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    use HandlesEntityPhoto;

    public function __construct(
        private SettingService $settings,
    ) {
        $this->middleware('permission:settings view')->only(['edit']);
        $this->middleware('permission:settings edit')->only(['update']);
    }

    public function edit(): View
    {
        $values = $this->settings->all();
        $token = $values['hiwa_token_device'] ?? '';
        $values['hiwa_token_device_masked'] = $token !== '' ? str_repeat('•', min(20, strlen($token))) : '';

        return view('settings.edit', [
            'settings' => $values,
            'logoUrl' => brand_has_custom_logo() ? brand_logo_url() : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_merge([
            'app_name' => ['required', 'string', 'max:255'],
            'app_tagline' => ['nullable', 'string', 'max:255'],
            'app_description' => ['nullable', 'string', 'max:2000'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_whatsapp' => ['nullable', 'string', 'max:30'],
            'hiwa_enabled' => ['nullable', 'boolean'],
            'hiwa_token_device' => ['nullable', 'string', 'max:500'],
        ], $this->photoRules()));

        $currentLogo = $this->settings->getString('company_logo') ?: null;
        $logoPath = $this->resolvePhotoPath($request, 'photos/branding', $currentLogo);

        $payload = [
            'app_name' => $validated['app_name'],
            'app_tagline' => $validated['app_tagline'] ?? '',
            'app_description' => $validated['app_description'] ?? '',
            'company_address' => trim((string) ($validated['company_address'] ?? '')),
            'company_whatsapp' => trim((string) ($validated['company_whatsapp'] ?? '')),
            'company_logo' => $logoPath ?? '',
            'hiwa_enabled' => $request->boolean('hiwa_enabled'),
        ];

        $newToken = trim((string) ($validated['hiwa_token_device'] ?? ''));
        if ($newToken !== '') {
            $payload['hiwa_token_device'] = $newToken;
        }

        $this->settings->setMany($payload);

        return redirect()
            ->route('settings.edit')
            ->with('success', 'Pengaturan aplikasi berhasil disimpan.');
    }
}
