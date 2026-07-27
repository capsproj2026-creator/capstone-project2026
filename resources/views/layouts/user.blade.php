@extends('layouts.app')

@section('portal_title', 'User Portal')
@section('portal_subtitle', 'Vehicle and Parking Management')
@section('portal_icon', 'users')
@section('brand_bg', 'bg-gradient-to-br from-purple-500 to-purple-700')
@section('nav_active_class', 'bg-purple-50 text-purple-700')
@section('profile_accent', 'bg-gradient-to-br from-purple-500 to-purple-700')

@section('navigation')
    @include('layouts.partials.sidebar-nav')
@endsection
