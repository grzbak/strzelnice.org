@props([
    'messages' => null,
    'message' => null,
])

@php
    $items = $messages ?? $message;
@endphp

@if ($items)
    <ul {{ $attributes->merge(['class' => 'mt-2 space-y-1 rounded border border-danger bg-white px-2.5 py-2 text-sm leading-5 text-gray-900 shadow-[0_8px_18px_-14px_rgba(249,115,22,0.75)]']) }}>
        @foreach ((array) $items as $item)
            <li class="flex items-start gap-2">

                <i class="bi-exclamation-circle ml-1 mt-0.5 inline-flex h-5 w-5 flex-none items-center justify-center rounded-sm text-danger"></i>
                <span>{{ $item }}</span>
            </li>
        @endforeach
    </ul>
@endif
