@extends('emails.layout', [
    'heading' => $status === 'Approved' ? 'Registration Approved' : 'Registration Declined',
])

@section('content')
    @php
        // Pick colors based on approval vs decline so the email tone matches the outcome.
        $isApproved = $status === 'Approved';
        $bannerBg = $isApproved ? '#ecfdf5' : '#fef2f2';
        $bannerBorder = $isApproved ? '#a7f3d0' : '#fecaca';
        $bannerText = $isApproved ? '#065f46' : '#991b1b';
        $statusColor = $isApproved ? '#047857' : '#b91c1c';
        $icon = $isApproved ? '✓' : '✕';
    @endphp

    {{-- Status banner --}}
    <div style="margin-bottom:20px;padding:14px 16px;border-radius:12px;background:{{ $bannerBg }};border:1px solid {{ $bannerBorder }};">
        <p style="margin:0;font-size:14px;font-weight:700;color:{{ $bannerText }};">
            {{ $icon }} Your vehicle registration request has been {{ strtolower($status) }}.
        </p>
    </div>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151;">
        Hello <strong>{{ $ownerName }}</strong>,
    </p>

    @if ($isApproved)
        <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
            Good news! An administrator has reviewed and approved your vehicle registration
            for the Smart Campus Vehicle Management System. You may now sign in and use
            campus services according to your assigned access level.
        </p>
    @else
        <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
            After reviewing your submission, an administrator was unable to approve your
            vehicle registration at this time. Please review the details below and contact
            campus administration if you need assistance.
        </p>
    @endif

    {{-- Status detail card --}}
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:24px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;">
        <tr>
            <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;">
                <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Owner Name</p>
                <p style="margin:6px 0 0;font-size:18px;font-weight:600;color:#111827;">{{ $ownerName }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 18px;@if($remarks) border-bottom:1px solid #e5e7eb; @endif">
                <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Status</p>
                <p style="margin:6px 0 0;font-size:18px;font-weight:700;color:{{ $statusColor }};">{{ $status }}</p>
            </td>
        </tr>
        @if ($remarks)
            <tr>
                <td style="padding:16px 18px;">
                    <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Admin Remarks</p>
                    <p style="margin:6px 0 0;font-size:15px;line-height:1.6;color:#374151;">{{ $remarks }}</p>
                </td>
            </tr>
        @endif
    </table>

    @if ($isApproved)
        <div style="padding:16px 18px;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe;">
            <p style="margin:0;font-size:14px;line-height:1.6;color:#1e40af;">
                You can sign in to the portal to view your dashboard, parking information, and notifications.
            </p>
        </div>
    @else
        <div style="padding:16px 18px;border-radius:12px;background:#f3f4f6;border:1px solid #d1d5db;">
            <p style="margin:0;font-size:14px;line-height:1.6;color:#374151;">
                You may submit a new registration request after correcting the issues mentioned above,
                or contact the campus security office for further clarification.
            </p>
        </div>
    @endif
@endsection
