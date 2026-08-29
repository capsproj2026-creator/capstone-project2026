@extends('layouts.app')

@section('portal_title', 'Smart Campus VMS')
@section('portal_subtitle', 'Vehicle and Parking Management')
@section('portal_icon', 'users')
@section('brand_bg', 'bg-[var(--cspc-navy)]')
@section('nav_active_class', 'portal-nav-item--active')
@section('profile_accent', 'bg-indigo-600')

@section('navigation')
    @include('layouts.partials.sidebar-nav')
@endsection
