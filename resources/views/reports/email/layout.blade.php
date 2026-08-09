<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;">
                <tr>
                    <td style="background-color:#4f46e5;padding:20px 24px;">
                        <span style="font-size:18px;font-weight:bold;color:#ffffff;">{{ config('app.name') }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px;">
                        @yield('body')
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px;background-color:#f8fafc;font-size:11px;color:#94a3b8;">
                        {{ config('app.name') }} &copy; {{ now()->format('Y') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
