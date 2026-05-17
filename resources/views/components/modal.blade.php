@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        default => 'sm:max-w-2xl',
    };
@endphp

<div
    x-data="{ open: @js((bool) $show) }"
    x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
    x-on:close.window="open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: none;"
>
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 bg-gray-500/75"
        x-on:click="open = false"
    ></div>

    <div
        x-show="open"
        x-transition
        class="mb-6 bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidthClass }} sm:mx-auto"
    >
        {{ $slot }}
    </div>
</div>
