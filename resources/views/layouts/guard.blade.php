@extends('layouts.app')

@section('portal_title', 'Smart Campus VMS')
@section('portal_subtitle', 'Access Control and Monitoring')
@section('portal_icon', 'shield')
@section('brand_bg', 'bg-[var(--cspc-navy)]')
@section('nav_active_class', 'portal-nav-item--active bg-[var(--cspc-navy-soft)] text-[var(--cspc-navy)] shadow-sm')
@section('profile_accent', 'bg-emerald-600')

@section('navigation')
    @include('layouts.partials.sidebar-nav')
@endsection
