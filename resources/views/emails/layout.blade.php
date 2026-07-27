{{--
    Shared email layout for Smart Campus VMS.
    Email clients work best with inline CSS (style="" attributes), not external stylesheets.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Inter,Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f3f4f6;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,0.06);">
                    {{-- Brand header --}}
                    <tr>
                        <td style="padding:24px 28px;background:#1e3a8a;color:#ffffff;">
                            <p style="margin:0;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;opacity:0.85;">Smart Campus VMS</p>
                            <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;line-height:1.3;">{{ $heading ?? 'Campus Notification' }}</h1>
                        </td>
                    </tr>

                    {{-- Main content injected by each email template --}}
                    <tr>
                        <td style="padding:28px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:18px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#6b7280;">
                                This is an automated message from the CSPC Vehicle Management System.
                                Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
