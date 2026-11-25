@php
    $schema = [[
        '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'Learning with Earning mentors',
        'itemListElement' => $mentors->values()->map(fn ($m, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => route('mentors.show', $m['slug']), 'name' => $m['name']])->all(),
    ]];
@endphp
@extends('public.layout', [
    'title' => 'Mentors — Learning with Earning',
    'description' => 'Learn e-commerce, freelancing, AI and digital skills live from mentors who do the work themselves. See each mentor\'s packages, student results and reviews.',
    'canonical' => route('mentors.index'),
    'schema' => $schema,
])

@section('content')
    <section class="bg-[#1A1E1C] text-white">
        <div class="s-wrap py-16 sm:py-24">
            <h1 class="max-w-3xl font-display text-5xl font-extrabold leading-[1.05] sm:text-6xl">Learn from people who do the work</h1>
            <p class="mt-6 max-w-2xl text-lg leading-relaxed text-white/80">Every mentor here earns from the skill they teach. Pick a mentor, see their packages and what their students achieved.</p>
        </div>
    </section>

    <section class="py-16 sm:py-20" aria-label="Mentors">
        <div class="s-wrap">
            @if ($mentors->isEmpty())
                <p class="border-2 border-[#E1E6E3] p-10 text-center text-[#4F5753]">Mentor pages are being prepared. Please check back soon.</p>
            @else
                <div class="grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($mentors as $m)
                        <a href="{{ route('mentors.show', $m['slug']) }}" class="group block focus-visible:outline-offset-4">
                            <div class="relative">
                                <div class="absolute inset-0 translate-x-3 translate-y-3 bg-[#2FBF71] transition-transform group-hover:translate-x-4 group-hover:translate-y-4" aria-hidden="true"></div>
                                @if ($m['photo'])
                                    <img src="{{ $m['photo'] }}" alt="{{ $m['name'] }}" width="480" height="600" loading="lazy" class="relative aspect-[4/5] w-full object-cover">
                                @else
                                    <div class="relative flex aspect-[4/5] w-full items-center justify-center bg-[#262B29] font-display text-7xl font-extrabold text-white/80">{{ mb_substr($m['name'], 0, 1) }}</div>
                                @endif
                            </div>
                            <h2 class="mt-7 font-display text-2xl font-bold group-hover:underline group-hover:decoration-[#2FBF71] group-hover:decoration-4 group-hover:underline-offset-4">{{ $m['name'] }}</h2>
                            @if ($m['headline'])<p class="mt-2 leading-relaxed text-[#4F5753]">{{ $m['headline'] }}</p>@endif
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                @foreach ($m['expertise'] as $tag)<span class="border border-[#E1E6E3] px-2.5 py-1 text-xs font-semibold">{{ $tag }}</span>@endforeach
                                @if ($m['from_price'])<span class="bg-[#1A1E1C] px-2.5 py-1 text-xs font-bold text-white">From PKR {{ number_format($m['from_price']) }}</span>@endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
