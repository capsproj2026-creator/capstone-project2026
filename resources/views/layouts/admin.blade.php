@extends('layouts.app')

@section('portal_title', 'Smart Campus VMS')
@section('portal_subtitle', 'Vehicle Management System')
@section('portal_icon', 'parking-square')
@section('brand_bg', 'bg-blue-600')
@section('nav_active_class', 'bg-blue-50 text-blue-700')
@section('profile_accent', 'bg-blue-500')

@section('navigation')
    @include('layouts.partials.sidebar-nav')
@endsection
