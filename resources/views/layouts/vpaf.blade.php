@extends('layouts.app')

@section('portal_title', 'Smart Campus VMS')
@section('portal_subtitle', 'VPAF · Parking Approvals')
@section('portal_icon', 'file-text')
@section('brand_bg', 'bg-[var(--cspc-navy)]')
@section('nav_active_class', 'portal-nav-item--active')
@section('profile_accent', 'bg-[var(--cspc-navy)]')

@section('navigation')
    @include('layouts.partials.sidebar-nav')
@endsection
