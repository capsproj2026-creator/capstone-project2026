@extends('layouts.portal')

@section('title', $pageTitle ?? 'Module')

@section('content')
    @include('partials.shell.page-header', [
        'title' => $pageTitle ?? 'Module',
        'subtitle' => 'This module is ready for integration. Navigation and access control are configured.',
    ])

    <div class="rounded-xl border border-gray-200 bg-white p-10 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
            <i data-lucide="layout-template" class="h-7 w-7 text-gray-400"></i>
        </div>
        <h2 class="text-lg font-semibold text-gray-900">{{ $pageTitle ?? 'Module' }}</h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">
            Page content from your Figma design can be added here. The portal shell, sidebar, and header match the capstone UI export.
        </p>
    </div>
@endsection
