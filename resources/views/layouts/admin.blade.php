@extends('layouts.app')

@section('portal_title', 'Smart Campus VMS')
@section('portal_subtitle', 'Admin · Vehicle Management System')
@section('portal_icon', 'parking-square')
@section('brand_bg', 'bg-[var(--cspc-navy)]')
@section('nav_active_class', 'portal-nav-item--active bg-[var(--cspc-navy-soft)] text-[var(--cspc-navy)] shadow-sm')
@section('profile_accent', 'bg-[var(--cspc-navy)]')

@section('navigation')
    @include('layouts.partials.sidebar-nav')
@endsection
