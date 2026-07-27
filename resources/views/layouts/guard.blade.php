@extends('layouts.app')

@section('portal_title', 'Guard Portal')
@section('portal_subtitle', 'Access Control and Monitoring')
@section('portal_icon', 'shield')
@section('brand_bg', 'bg-gradient-to-br from-green-500 to-green-700')
@section('nav_active_class', 'bg-green-50 text-green-700')
@section('profile_accent', 'bg-gradient-to-br from-green-500 to-green-700')

@section('navigation')
    @include('layouts.partials.sidebar-nav')
@endsection
