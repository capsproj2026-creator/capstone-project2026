@php
    $snapshot = $snapshot ?? null;
    $compact = ! empty($compact);
@endphp
@if (is_array($snapshot) && ! empty($snapshot['path']))
    <figure @class(['parking-zone-snapshot', 'parking-zone-snapshot--compact' => $compact])>
        <img
            src="{{ asset($snapshot['path']) }}"
            alt="{{ $snapshot['label'] }} parking snapshot"
            width="767"
            height="1024"
        >
        <figcaption>
            <span class="parking-zone-snapshot__title">{{ $snapshot['label'] }} camera snapshot</span>
            <span @class([
                'parking-zone-snapshot__badge',
                'parking-zone-snapshot__badge--ready' => ! empty($snapshot['calibrated']),
                'parking-zone-snapshot__badge--pending' => empty($snapshot['calibrated']),
            ])>
                {{ ! empty($snapshot['calibrated']) ? 'Calibrated' : 'Needs calibration' }}
            </span>
        </figcaption>
    </figure>
@endif
