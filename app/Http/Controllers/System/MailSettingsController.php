<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\MailSettingRequest;
use App\Models\MailSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailSettingsController extends Controller
{
    public function update(MailSettingRequest $request): JsonResponse
    {
        $setting = MailSetting::query()->firstOrNew();
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $setting->fill($data)->save();

        return response()->json(['message' => 'Configuração SMTP salva com sucesso.']);
    }

    public function test(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('system.settings.mail.test'), 403);

        $data = $request->validate(['recipient' => ['required', 'email']]);
        $setting = MailSetting::query()->firstOrFail();

        config([
            'mail.mailers.system-smtp' => [
                'transport' => 'smtp',
                'scheme' => $setting->encryption === 'ssl' ? 'smtps' : 'smtp',
                'host' => $setting->host,
                'port' => $setting->port,
                'username' => $setting->username,
                'password' => $setting->password,
                'timeout' => $setting->timeout,
            ],
            'mail.from.address' => $setting->from_address,
            'mail.from.name' => $setting->from_name,
        ]);

        Mail::purge('system-smtp');
        Mail::mailer('system-smtp')->raw(
            'Este é um e-mail de teste das configurações do sistema.',
            fn ($message) => $message->to($data['recipient'])->subject('Teste de configuração SMTP'),
        );

        $setting->update(['last_tested_at' => now()]);

        return response()->json(['message' => 'E-mail de teste enviado com sucesso.']);
    }
}
