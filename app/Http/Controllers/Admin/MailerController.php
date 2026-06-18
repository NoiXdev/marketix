<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MailSettingsRequest;
use App\Mail\TestMail;
use App\Settings\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailerController extends Controller
{
    public function edit(MailSettings $settings)
    {
        return inertia('Admin/Mailer/Edit', [
            'settings' => [
                'default_mailer' => $settings->default_mailer,
                'from_address' => $settings->from_address,
                'from_name' => $settings->from_name,
                'postal_url' => $settings->postal_url,
                'smtp_host' => $settings->smtp_host,
                'smtp_port' => $settings->smtp_port,
                'smtp_username' => $settings->smtp_username,
                'smtp_scheme' => $settings->smtp_scheme,
            ],
            'has_postal_key' => $settings->postal_key !== '',
            'has_smtp_password' => $settings->smtp_password !== '',
        ]);
    }

    public function update(MailSettingsRequest $request, MailSettings $settings)
    {
        $data = $request->validated();

        $settings->default_mailer = $data['default_mailer'];
        $settings->from_address = $data['from_address'];
        $settings->from_name = $data['from_name'];
        $settings->postal_url = $data['postal_url'] ?? '';
        $settings->smtp_host = $data['smtp_host'] ?? '';
        $settings->smtp_port = (int) ($data['smtp_port'] ?? 587);
        $settings->smtp_username = $data['smtp_username'] ?? '';
        $settings->smtp_scheme = $data['smtp_scheme'] ?? '';

        // Only overwrite secrets when a new value is supplied (mask behaviour).
        if (! empty($data['postal_key'])) {
            $settings->postal_key = $data['postal_key'];
        }
        if (! empty($data['smtp_password'])) {
            $settings->smtp_password = $data['smtp_password'];
        }

        $settings->save();

        return redirect()->route('app.admin.mailer.edit')->with('success', 'Mailer settings saved.');
    }

    public function test(Request $request)
    {
        $request->validate(['test_email' => ['nullable', 'email']]);

        $to = $request->string('test_email')->toString() ?: $request->user()->email;

        try {
            Mail::to($to)->send(new TestMail);
        } catch (Throwable $e) {
            return back()->with('error', 'Test email failed: '.$e->getMessage());
        }

        return back()->with('success', 'Test email sent to '.$to.'.');
    }
}
