@extends('layouts.portal')

@section('title', 'Zone Access')

@section('content')
    @include('partials.shell.page-header', [
        'title' => 'Parking — Zone Access',
        'subtitle' => 'Control which parking zones are visible to students and staff in the user portal.',
    ])

    @include('partials.admin.parking-nav', ['active' => 'zone-access'])
    @include('partials.admin.zone-access-settings')
@endsection
