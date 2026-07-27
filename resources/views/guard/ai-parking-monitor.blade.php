@extends('layouts.guard')

@section('title', 'AI Parking Monitor')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'AI Parking Monitor',
        'subtitle' => 'Real-time YOLOv9 detections and AI Test Lot occupancy',
    ])

    @include('partials.ai-camera-feed', [
        'streamUrl' => $streamUrl,
        'ai' => $ai,
        'statusUrl' => $statusUrl,
        'parkingUrl' => $parkingUrl,
        'showDetections' => true,
        'areaName' => $aiAreaName ?? 'AI Test Lot',
    ])
@endsection
