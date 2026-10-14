@php
    $roleLayout = match (strtolower(auth()->user()?->roleName() ?? '')) {
        'admin' => 'layouts.admin',
        'guard' => 'layouts.guard',
        'vpaf' => 'layouts.vpaf',
        default => 'layouts.user',
    };
@endphp

@extends($roleLayout)
