@extends('emails.layout', [
    'heading' => 'Visit Confirmation',
])

@section('content')
    @php
        $visitorName = $visitor->displayName();
        $code = (string) ($visitor->confirmation_code ?: '—');
        $exitAt = $visitor->expected_exit_at
            ? ph_datetime($visitor->expected_exit_at, 'M j, Y · g:i A')
            : '—';
        $rows = [
            ['Reference code', $code],
            ['Full name', $visitorName],
            ['Purpose of visit', $visitor->purpose ?: '—'],
            ['Office / Person to visit', $visitor->office_to_visit ?: '—'],
            ['Expected exit', $exitAt],
            ['Contact number', $visitor->contact_number ?: '—'],
            ['Plate number', $visitor->plate_number ?: '—'],
            ['Vehicle type', $visitor->vehicleType?->vehicle_name ?: '—'],
            ['Vehicle color', $visitor->vehicle_color ?: '—'],
        ];
    @endphp

    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151;">
        Hello <strong>{{ $visitorName }}</strong>,
    </p>
    <p style="margin:0 0 18px;font-size:15px;line-height:1.6;color:#374151;">
        Your visit is pre-registered. Show this email to the guard. Your status is Waiting until the guard assigns a temporary RFID and records your entry.
    </p>

    <div style="margin:0 0 18px;padding:14px 16px;border-radius:12px;background:#ecfdf5;border:1px solid #6ee7b7;text-align:center;">
        <div style="font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#047857;">Reference code for the guard</div>
        <div style="margin-top:6px;font-family:Consolas,Monaco,monospace;font-size:22px;font-weight:700;color:#064e3b;letter-spacing:0.04em;">{{ $code }}</div>
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
        @foreach ($rows as $index => $row)
            <tr style="background:{{ $index % 2 === 0 ? '#f8fafc' : '#ffffff' }};">
                <td style="padding:12px 16px;font-size:13px;color:#64748b;width:42%;border-bottom:1px solid #e2e8f0;">{{ $row[0] }}</td>
                <td style="padding:12px 16px;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #e2e8f0;">{{ $row[1] }}</td>
            </tr>
        @endforeach
    </table>
@endsection
