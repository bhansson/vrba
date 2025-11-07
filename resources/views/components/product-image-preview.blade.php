@props([
    'src' => null,
    'alt' => 'Product image preview',
    'size' => 'w-16 h-16',
])

@if ($src)
    <div
        data-image-wrapper
        {{ $attributes->class([
            'relative flex-shrink-0 overflow-hidden rounded-md border border-gray-200 bg-gray-50',
            $size,
        ]) }}
    >
        <img
            src="{{ $src }}"
            alt="{{ $alt }}"
            title="{{ $alt }}"
            loading="lazy"
            decoding="async"
            referrerpolicy="no-referrer"
            class="h-full w-full object-cover opacity-0 transition-opacity duration-300"
            onload="this.classList.remove('opacity-0')"
            onerror="const wrapper = this.closest('[data-image-wrapper]'); if (wrapper) { wrapper.remove(); }"
        />
    </div>
@endif
