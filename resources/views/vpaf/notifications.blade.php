@extends('layouts.vpaf')

@section('title', 'Notifications')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Notifications',
        'subtitle' => 'Endorsements waiting for your decision',
    ])

    @include('partials.notifications.inbox')
@endsection

@push('scripts')
    @include('partials.notifications.mark-read-script')
@endpush
