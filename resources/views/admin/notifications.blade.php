@extends('layouts.admin')

@section('title', 'Notifications')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Notifications',
        'subtitle' => 'New registrations, overnight requests, and sanction updates',
    ])

    @include('partials.shell.flash')
    @include('partials.notifications.inbox')
@endsection

@push('scripts')
    @include('partials.notifications.mark-read-script')
@endpush
