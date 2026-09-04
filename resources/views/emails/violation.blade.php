@extends('emails.layout', [
    'heading' => 'Traffic / Parking Violation Alert',
])

@section('content')
    <div style="margin-bottom:20px;padding:14px 16px;border-radius:12px;background:#fef2f2;border:1px solid #fecaca;">
        <p style="margin:0;font-size:14px;font-weight:700;color:#991b1b;">
            A campus traffic or parking violation has been recorded on your vehicle.
        </p>
    </div>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151;">
        Hello,
    </p>

    <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;">
        Our campus security team logged a violation linked to your registered vehicle.
        Please review the details below and follow all campus parking and traffic regulations.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:24px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;">
        <tr>
            <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;">
                <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Plate Number</p>
                <p style="margin:6px 0 0;font-size:20px;font-weight:700;color:#111827;">{{ $plateNumber }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;">
                <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Violation Type</p>
                <p style="margin:6px 0 0;font-size:18px;font-weight:600;color:#b91c1c;">{{ $violationType }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;">
                <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Date &amp; Time</p>
                <p style="margin:6px 0 0;font-size:15px;color:#111827;">{{ $occurredDate }} · {{ $occurredTime }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;">
                <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Location</p>
                <p style="margin:6px 0 0;font-size:15px;color:#111827;">{{ $location }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;">
                <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Reported By</p>
                <p style="margin:6px 0 0;font-size:15px;color:#111827;">{{ $reportedBy }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 18px;border-bottom:1px solid #e5e7eb;">
                <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Description / Remarks</p>
                <p style="margin:6px 0 0;font-size:15px;line-height:1.6;color:#374151;">{{ $remarks ?: ($description ?: 'No remarks provided.') }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 18px;">
                <p style="margin:0 0 10px;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;">Photo Evidence</p>
                @if (! empty($hasEvidence) && ! empty($evidencePublicUrls))
                    @foreach ($evidencePublicUrls as $url)
                        <div style="margin:0 0 12px;">
                            <img src="{{ $url }}" alt="Violation evidence" style="display:block;max-width:100%;height:auto;border-radius:10px;border:1px solid #e5e7eb;">
                        </div>
                    @endforeach
                    <p style="margin:0;font-size:12px;color:#6b7280;">Evidence images are also attached to this email.</p>
                @elseif (! empty($hasEvidence))
                    <p style="margin:0;font-size:14px;color:#374151;">Photo evidence is attached to this email.</p>
                @else
                    <p style="margin:0;font-size:14px;font-style:italic;color:#9ca3af;">No Photo Evidence Available.</p>
                @endif
            </td>
        </tr>
    </table>

    <div style="padding:16px 18px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;">
        <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#9a3412;">Important Reminder</p>
        @php
            $strikeHint = isset($strikeCount)
                ? \App\Support\ViolationSanctionPresenter::labelForStrike((int) $strikeCount)
                : null;
        @endphp
        @if ($strikeHint)
            <p style="margin:0 0 10px;font-size:14px;line-height:1.6;color:#7c2d12;">
                Current offense level: {{ $strikeHint }}
            </p>
        @endif
        <p style="margin:0;font-size:14px;line-height:1.6;color:#7c2d12;">
            Repeated violations may result in account strikes, loss of campus gate access,
            or towing of the vehicle in accordance with CSPC parking policies.
            If you believe this citation was recorded in error, contact campus administration promptly.
        </p>
    </div>

    <p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:#6b7280;">
        Sign in to the Smart Campus VMS portal to view your violation history and notifications.
    </p>
@endsection
