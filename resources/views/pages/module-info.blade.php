@extends('layouts.portal')

@section('title', $pageTitle ?? 'Module')

@section('content')
    @include('partials.shell.page-header', [
        'title' => $pageTitle ?? 'Module',
        'subtitle' => $description ?? '',
    ])

    <div class="rounded-xl border border-gray-200 bg-white p-10 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
            <i data-lucide="video" class="h-7 w-7 text-gray-400"></i>
        </div>
        <h2 class="text-lg font-semibold text-gray-900">{{ $pageTitle }}</h2>
        <p class="mx-auto mt-2 max-w-lg text-sm text-gray-500">{{ $description }}</p>
    </div>
@endsection
