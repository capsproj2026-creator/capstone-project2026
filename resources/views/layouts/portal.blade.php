@php
    $roleLayout = match (strtolower(auth()->user()?->roleName() ?? '')) {
        'admin' => 'layouts.admin',
        'guard' => 'layouts.guard',
        default => 'layouts.user',
    };
@endphp

@extends($roleLayout)
