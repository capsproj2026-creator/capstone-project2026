@extends('emails.layout', ['heading' => 'Reset your password'])

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151;">
        Hello <strong>{{ $ownerName }}</strong>,
    </p>
    <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
        We received a request to reset the password for your Smart Campus Vehicle Management System account.
        This link expires in {{ $expireMinutes }} minutes.
    </p>
    <p style="margin:0 0 24px;">
        <a href="{{ $resetUrl }}" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#1e3a8a;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">
            Reset password
        </a>
    </p>
    <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#6b7280;">
        If the button does not open, copy this address into your browser:
    </p>
    <p style="margin:0 0 20px;font-size:12px;line-height:1.6;color:#1d4ed8;word-break:break-all;">
        {{ $resetUrl }}
    </p>
    <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
        If you did not ask for this, you can ignore this email. Your password will stay the same.
    </p>
@endsection
