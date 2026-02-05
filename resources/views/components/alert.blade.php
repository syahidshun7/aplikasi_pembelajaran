@php
    $type = $type ?? 'success';
    $message = $message ?? null;
@endphp

@if($message)
    <p style="color: {{ $type === 'error' ? 'red' : 'green' }}; text-align:center;">
        {{ $message }}
    </p>
@endif
