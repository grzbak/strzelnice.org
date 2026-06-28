@props(['status'])

@if ($status)
    @php
        $type = session('status_type', 'success');
        $classes = match ($type) {
            'warning' => 'text-amber-900 bg-amber-50 border-amber-300',
            'danger', 'error' => 'text-red-900 bg-red-50 border-red-300',
            default => 'text-green-900 bg-green-50 border-green-300',
        };
    @endphp

    <div {{ $attributes->merge(['class' => 'rounded-md border px-4 py-3 text-sm font-medium  text-amber-900 bg-amber-50 border-amber-300' . $classes]) }}>
        {{ $status }}
    </div>
@endif
