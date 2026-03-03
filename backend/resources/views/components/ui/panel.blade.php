@props([
    'title' => '',
])

<section {{ $attributes->merge(['class' => 'rounded-xl bg-white p-6 shadow']) }}>
    @if ($title !== '')
        <h2 class="text-xl font-semibold">{{ $title }}</h2>
    @endif

    <div class="{{ $title !== '' ? 'mt-3' : '' }}">
        {{ $slot }}
    </div>
</section>
