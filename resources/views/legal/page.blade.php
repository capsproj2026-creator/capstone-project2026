@extends('layouts.guest')

@section('title', $title.' - Smart Campus VMS')

@section('use_campus_bg', '1')

@section('guest_align', 'start')

@section('card_width')
    max-w-2xl
@endsection

@section('content')
    <article class="guest-card w-full overflow-hidden rounded-2xl border border-white/30 bg-white/95 shadow-2xl backdrop-blur-sm">
        <div class="bg-gradient-to-br from-[#1A365D] via-[#122844] to-slate-900 px-6 py-6 text-white sm:px-8">
            <p class="text-[11px] font-semibold tracking-[0.14em] text-blue-200 uppercase">Camarines Sur Polytechnic Colleges</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $title }}</h1>
            <p class="mt-2 text-sm text-blue-100">{{ $intro }}</p>
        </div>
        <div class="space-y-5 px-6 py-6 sm:px-8">
            @foreach ($sections as $section)
                <section>
                    <h2 class="text-sm font-semibold text-gray-900">{{ $section['heading'] }}</h2>
                    @foreach ($section['paragraphs'] as $paragraph)
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $paragraph }}</p>
                    @endforeach
                </section>
            @endforeach
        </div>
    </article>
@endsection
