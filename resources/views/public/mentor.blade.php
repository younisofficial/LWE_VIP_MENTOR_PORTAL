@php
    // Short name for sentences: "Haider", but "Muhammad Imran" / "Syed Ali" when the first word is a title-like name.
    $words = preg_split('/\s+/', trim($name));
    $first = in_array(strtolower(rtrim($words[0], '.')), ['muhammad', 'mohammad', 'muhammed', 'mohammed', 'm', 'syed', 'sayed', 'hafiz', 'malik', 'rana', 'ch', 'chaudhry', 'mian', 'dr', 'sheikh'], true) && isset($words[1])
        ? $words[0].' '.$words[1] : $words[0];
    $nameLines = preg_split('/\s+/', trim($name), 2); // big hero name on two lines
    $fmt = fn ($n) => number_format((int) $n);
    $highlights = collect($p->highlights ?? []);
    if ($highlights->isEmpty() && $students > 0) {
        $highlights = collect([['value' => number_format($students).'+', 'label' => 'students on the portal']]);
    }
    $socials = collect($p->socials ?? [])->filter();
    $socialNames = ['linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'website' => 'Website'];
    $hasPackages = $packages->isNotEmpty();
    $mainCta = $hasPackages ? '#packages' : ($whatsappHref ?? '#');
    // Line icons for the outcomes, used in turn.
    $icons = ['M4 20h16M7 16V9M12 16V5M17 16v-4', 'M4 6h16v12H4zM4 10h16', 'M5 4h14v16H5zM9 9h6M9 13h6', 'M4 12l5 5L20 6', 'M3 7h12v10H3zM15 10h4l2 3v4h-6', 'M4 18l6-6 4 4 6-8'];
    $steps = [
        ['Choose a package', 'Pay by bank, JazzCash or Easypaisa and get your portal login on WhatsApp.'],
        ['Join live classes', 'Classes open inside the portal. Missed one? The recording is there the same night.'],
        ['Do the weekly tasks', "Build step by step. {$first} and the team check your work and reply."],
        ['Get certified', 'Meet the attendance and task rules and your verified certificate is issued.'],
    ];

    // Structured data for Google: the person, their offers, the FAQ and the breadcrumb.
    $person = array_filter([
        '@context' => 'https://schema.org', '@type' => 'Person', 'name' => $name, 'url' => $canonical,
        'jobTitle' => $p->headline, 'description' => $description, 'image' => $photo,
        'knowsAbout' => $p->expertise ?: null,
        'sameAs' => $socials->values()->all() ?: null,
        'worksFor' => ['@type' => 'EducationalOrganization', 'name' => 'Learning with Earning', 'url' => url('/')],
        'hasOfferCatalog' => $packages->isEmpty() ? null : [
            '@type' => 'OfferCatalog', 'name' => $name.' — packages',
            'itemListElement' => $packages->map(fn ($pk) => ['@type' => 'Offer', 'name' => $pk['name'], 'price' => (string) $pk['price'], 'priceCurrency' => 'PKR', 'availability' => 'https://schema.org/InStock', 'url' => $canonical.'#packages'])->all(),
        ],
    ]);
    $schema = [
        $person,
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Mentors', 'item' => route('mentors.index')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $name, 'item' => $canonical],
        ]],
    ];
    if ($faqs->isNotEmpty()) {
        $schema[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqs->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->all()];
    }
    if ($introId) {
        $schema[] = ['@context' => 'https://schema.org', '@type' => 'VideoObject', 'name' => 'Meet '.$name, 'description' => $description,
            'thumbnailUrl' => 'https://i.ytimg.com/vi/'.$introId.'/hqdefault.jpg', 'embedUrl' => 'https://www.youtube.com/embed/'.$introId, 'uploadDate' => $p->updated_at?->toIso8601String()];
    }
    $lead = fn (string $what, $value = null) => json_encode(array_filter(['content_name' => $name.' — '.$what, 'content_category' => 'mentor', 'value' => $value, 'currency' => $value !== null ? 'PKR' : null], fn ($v) => $v !== null));
    $h2 = 'font-display text-[2.15rem] font-extrabold leading-[1.05] sm:text-5xl';
    $sub = 'mt-4 max-w-xl text-[1.05rem] leading-relaxed text-[#4F5753]';
@endphp
@extends('public.layout', [
    'ogType' => 'profile', 'ogImage' => $cover ?: ($photo ?: ($introId ? 'https://i.ytimg.com/vi/'.$introId.'/hqdefault.jpg' : null)), 'preloadImage' => null, 'schema' => $schema, 'noindex' => $preview,
    'viewContent' => ['content_name' => $name, 'content_category' => 'mentor'],
])

@section('nav')
    @if ($journey->isNotEmpty())<a href="#program" class="hover:text-white">Roadmap</a>@endif
    @if ($hasPackages)<a href="#packages" class="hover:text-white">Packages</a>@endif
    @if ($videos->isNotEmpty())<a href="#reviews" class="hover:text-white">Success stories</a>@endif
    @if ($faqs->isNotEmpty())<a href="#faq" class="hover:text-white">FAQ</a>@endif
@endsection

@section('header_cta')
    <a href="{{ $mainCta }}" class="hidden min-h-[2.75rem] items-center bg-[#2FBF71] px-5 text-sm font-extrabold text-[#1A1E1C] sm:inline-flex" @if (! $hasPackages && $whatsappHref) target="_blank" rel="noopener" data-lead="{{ $lead('WhatsApp (header)') }}" @endif>{{ $hasPackages ? 'Join the next batch' : 'Ask on WhatsApp' }}</a>
@endsection

@section('content')
    @if ($preview)
        <div class="bg-[#2FBF71] px-5 py-2 text-center text-sm font-bold text-[#1A1E1C]">Preview — this page is hidden. Tick “Show this page publicly” in the portal to publish it.</div>
    @endif

    {{-- ───────── Hero: the intro video, big, in a frame whose corners close in ───────── --}}
    <section class="relative overflow-hidden bg-[#121514] text-white">
        <div class="ghost absolute left-1/2 top-6 hidden -translate-x-1/2 text-[17rem] lg:block" aria-hidden="true">{{ \Illuminate\Support\Str::afterLast($first, ' ') }}</div>
        <div class="s-wrap relative pb-16 pt-10 sm:pb-20 sm:pt-14">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2.5" data-anim="down">
                        @if ($photo)<img src="{{ $photo }}" alt="" width="40" height="40" class="h-10 w-10 border-2 border-[#2FBF71] object-cover">@endif
                        <span class="inline-flex items-center gap-2 border border-[#2FBF71]/50 bg-[#2FBF71]/15 px-3 py-2 text-[13px] font-bold text-[#A8E8C4]">
                            <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><path d="M2 8.5l4 4 8-9" stroke="currentColor" stroke-width="2.4" fill="none" stroke-linecap="square"/></svg>Verified LWE mentor</span>
                        @if ($p->cta_note)<span class="border border-white/25 px-3 py-2 text-[13px] font-semibold text-white/85">{{ $p->cta_note }}</span>@endif
                    </div>
                    <h1 class="mt-5 font-display text-[2.6rem] font-extrabold leading-[1] sm:text-6xl"><span class="rise"><span>{{ $name }}</span></span></h1>
                    @if ($p->headline)<p class="mt-4 max-w-2xl text-lg leading-relaxed text-white/75" data-anim="up" style="--d:.3s">{{ $p->headline }}</p>@endif
                </div>
                @if ($seats)
                    <div class="border-2 border-[#2FBF71] px-5 py-3" data-anim="right" style="--d:.4s">
                        <p class="text-xs font-semibold text-white/70">Seats left this batch</p>
                        <p class="mt-1 font-display text-3xl font-extrabold text-[#2FBF71]">{{ $seats['left'] }} <span class="text-lg text-white/60">of {{ $seats['total'] }}</span></p>
                    </div>
                @endif
            </div>

            <div class="v-frame mt-10" data-anim="fade" style="--d:.2s">
                <i aria-hidden="true"></i><i aria-hidden="true"></i><i aria-hidden="true"></i><i aria-hidden="true"></i>
                @if ($introId)
                    <button type="button" class="lite-yt shadow-[0_40px_80px_-30px_rgba(0,0,0,.8)]" data-id="{{ $introId }}" aria-label="Play: {{ $name }} introduces the course" style="background-image:url('https://i.ytimg.com/vi/{{ $introId }}/maxresdefault.jpg'),url('https://i.ytimg.com/vi/{{ $introId }}/hqdefault.jpg')">
                        <span class="v-play"><svg width="30" height="30" viewBox="0 0 14 14" aria-hidden="true"><path d="M3 1l10 6-10 6z" fill="currentColor"/></svg></span>
                        <span class="play !px-5 !py-3.5">Watch {{ $first }}’s introduction</span>
                    </button>
                @elseif ($photo)
                    <img src="{{ $photo }}" alt="{{ $name }}" class="aspect-video w-full object-cover" fetchpriority="high">
                @else
                    <div class="flex aspect-video w-full items-center justify-center bg-[#262B29] text-white/60">Add an intro video in the mentor editor</div>
                @endif
            </div>

            <div class="mt-10 flex flex-wrap items-center justify-between gap-6">
                <div class="flex flex-wrap gap-4" data-anim="up" style="--d:.5s">
                    @if ($hasPackages)
                        <a href="#packages" class="s-btn bg-[#2FBF71] text-[#1A1E1C] shadow-[5px_5px_0_#fff] hover:shadow-[7px_7px_0_#fff]">See packages and prices
                            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="square"/></svg></a>
                    @endif
                    @if ($whatsappHref)<a href="{{ $whatsappHref }}" target="_blank" rel="noopener" class="s-btn-light" data-lead="{{ $lead('WhatsApp (hero)') }}">Ask on WhatsApp</a>@endif
                </div>
                @if ($highlights->isNotEmpty())
                    <dl class="flex flex-wrap gap-x-10 gap-y-4">
                        @foreach ($highlights->take(3) as $h)
                            <div class="flex flex-col" data-anim="up" style="--d: {{ .6 + $loop->index * .1 }}s">
                                <dt class="order-2 text-sm text-white/65">{{ $h['label'] }}</dt>
                                <dd class="order-1 font-display text-3xl font-extrabold text-[#2FBF71]">{{ $h['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </div>
    </section>


    {{-- Where students sell --}}
    @if ($platforms->isNotEmpty())
        <section class="border-b border-[#E1E6E3]">
            <div class="s-wrap flex flex-wrap items-center justify-between gap-6 py-7">
                <p class="text-[15px] font-bold text-[#4F5753]" data-anim="left">Students of {{ $first }} now work on</p>
                <ul class="flex flex-wrap gap-x-10 gap-y-3">
                    @foreach ($platforms as $pl)<li class="font-display text-[1.35rem] font-bold" data-anim="up" style="--d: {{ $loop->index * .08 }}s">{{ $pl }}</li>@endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Success stories: student videos in a carousel that keeps moving --}}
    @if ($videos->isNotEmpty())
        <section id="reviews" class="scroll-mt-20 overflow-hidden bg-white py-20 sm:py-28" aria-labelledby="videos-title">
            <div class="s-wrap flex flex-wrap items-end justify-between gap-6">
                <h2 id="videos-title" class="{{ $h2 }}" data-anim="wipe">Success stories</h2>
                <p class="text-[#4F5753]" data-anim="fade" style="--d:.2s">Students of {{ $first }}, in their own words. Tap a video to play it.</p>
            </div>
            <div class="marquee mt-14" data-anim="fade" style="--d:.3s; --speed: {{ max(30, $videos->count() * 12) }}s">
                <div class="marquee-track">
                    @foreach ([false, true] as $copy)
                        @foreach ($videos as $v)
                            <figure class="w-[280px] shrink-0 border-2 border-[#1A1E1C] bg-white sm:w-[340px]" @if ($copy) aria-hidden="true" @endif>
                                <button type="button" class="lite-yt !aspect-[4/5]" data-id="{{ $v['id'] }}" aria-label="Play review{{ ! empty($v['name']) ? ' by '.$v['name'] : '' }}" style="background-image:url('https://i.ytimg.com/vi/{{ $v['id'] }}/hqdefault.jpg')" @if ($copy) tabindex="-1" @endif>
                                    <span class="play"><svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"><path d="M2 1l11 6-11 6z" fill="currentColor"/></svg> Play</span>
                                </button>
                                <figcaption class="p-5">
                                    @if (! empty($v['title']))<p class="font-display text-lg font-bold">{{ $v['title'] }}</p>@endif
                                    @if (! empty($v['name']))<p class="mt-1 text-sm text-[#4F5753]">{{ $v['name'] }}</p>@endif
                                </figcaption>
                            </figure>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Course roadmap: every stage branches out, then the line glides like a carousel --}}
    @if ($journey->isNotEmpty())
        <section id="program" class="scroll-mt-20 overflow-hidden bg-[#1A1E1C] py-20 text-white sm:py-28" aria-labelledby="journey-title">
            <div class="s-wrap">
                <div class="flex flex-wrap items-end justify-between gap-6">
                    <div><h2 id="journey-title" class="{{ $h2 }}" data-anim="wipe">Complete course roadmap</h2><p class="mt-3 text-white/70" data-anim="fade" style="--d:.15s">{{ $journey->count() }} stages, from the first class to your first income.</p></div>
                    <div class="flex gap-2" data-anim="right" style="--d:.2s">
                        <button type="button" data-rm="-1" class="flex h-12 w-12 items-center justify-center border-2 border-white/40 hover:border-[#2FBF71] hover:text-[#2FBF71]" aria-label="Previous stages"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="2.4" fill="none" stroke-linecap="square"/></svg></button>
                        <button type="button" data-rm="1" class="flex h-12 w-12 items-center justify-center bg-[#2FBF71] text-[#1A1E1C]" aria-label="Next stages"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="2.4" fill="none" stroke-linecap="square"/></svg></button>
                    </div>
                </div>
            </div>
            <div class="jr rm mt-12">
                <ol class="jr-track pl-[max(1.25rem,calc((100vw_-_72rem)/2_+_2rem))]" aria-label="Program stages">
                    @foreach ($journey as $j)
                        @php($top = $loop->index % 2 === 0)
                        <li class="jr-col">
                            <span class="jr-seg" aria-hidden="true"></span>
                            <div class="jr-slot top" style="grid-row:1">
                                @if ($top)
                                    <div class="jr-card">
                                        <p class="text-[11px] font-bold text-[#2FBF71]">Stage {{ $loop->iteration }}</p>
                                        <h3 class="mt-1 font-display text-lg font-bold leading-snug">{{ $j['title'] }}</h3>
                                        @if (! empty($j['points']))<ul class="mt-3 space-y-1.5 text-[13px] text-white/75">@foreach ($j['points'] as $pt)<li class="flex gap-2"><span class="text-[#2FBF71]" aria-hidden="true">—</span>{{ $pt }}</li>@endforeach</ul>@endif
                                    </div>
                                    <span class="jr-branch" aria-hidden="true"></span>
                                @endif
                            </div>
                            <span class="jr-node" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="jr-slot bottom" style="grid-row:3">
                                @unless ($top)
                                    <span class="jr-branch" aria-hidden="true"></span>
                                    <div class="jr-card">
                                        <p class="text-[11px] font-bold text-[#2FBF71]">Stage {{ $loop->iteration }}</p>
                                        <h3 class="mt-1 font-display text-lg font-bold leading-snug">{{ $j['title'] }}</h3>
                                        @if (! empty($j['points']))<ul class="mt-3 space-y-1.5 text-[13px] text-white/75">@foreach ($j['points'] as $pt)<li class="flex gap-2"><span class="text-[#2FBF71]" aria-hidden="true">—</span>{{ $pt }}</li>@endforeach</ul>@endif
                                    </div>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif


    {{-- Inside the portal: tabs that switch on their own, with a live-looking screen --}}
    <section class="overflow-hidden bg-[#F3F5F4] py-20 sm:py-28" aria-labelledby="portal-title">
        <div class="s-wrap">
            <h2 id="portal-title" class="{{ $h2 }} max-w-3xl" data-anim="wipe">Everything happens inside one portal</h2>
            <p class="{{ $sub }}" data-anim="fade" style="--d:.15s">Live classes, recordings, weekly tasks and your certificate — on your phone or laptop.</p>
            <div class="mt-14 grid gap-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.6fr)] lg:items-center" data-ptabs>
                <div role="tablist" aria-label="Portal features" class="flex flex-col gap-1" data-anim="left">
                    @foreach ([['Live classes', 'Join Zoom classes without leaving the portal.'], ['Recordings', 'Missed a class? The recording is there the same night.'], ['Weekly tasks', 'Submit your work and get feedback from the team.'], ['Certificate', 'Verified certificate and a Skill Passport you can share.']] as [$t, $d])
                        <button type="button" role="tab" id="ptab-{{ $loop->index }}" aria-controls="ppane-{{ $loop->index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" class="ptab">
                            <span class="flex items-center gap-4"><span class="font-display text-sm font-extrabold text-[#17784A]">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span class="font-display text-xl font-bold">{{ $t }}</span></span>
                            <span class="mt-1.5 block pl-9 text-[15px] leading-relaxed text-[#4F5753]">{{ $d }}</span>
                            <span class="bar" aria-hidden="true"></span>
                        </button>
                    @endforeach
                </div>

                {{-- The screen: a simple drawing of the portal, one pane per tab --}}
                <div class="relative pb-4 pr-4" data-anim="right" style="--d:.15s">
                    <div class="absolute inset-0 translate-x-4 translate-y-4 bg-[#2FBF71]" aria-hidden="true"></div>
                    <div class="relative border-2 border-[#1A1E1C] bg-white">
                        <div class="flex items-center gap-2 bg-[#1A1E1C] px-4 py-3" aria-hidden="true">
                            <span class="h-3 w-3 bg-[#2FBF71]"></span><span class="h-3 w-3 bg-white/30"></span><span class="h-3 w-3 bg-white/30"></span>
                            <span class="ml-3 bg-white/10 px-3 py-1 text-xs text-white/70">portal.learningwithearning.org</span>
                        </div>
                        <div class="min-h-[22rem] p-5 sm:p-7">
                            <div class="ppane on" id="ppane-0" role="tabpanel" aria-labelledby="ptab-0">
                                <div class="flex items-center justify-between"><p class="font-display font-bold">Class 7 — {{ $journey->get(6)['title'] ?? 'Live class' }}</p><span class="flex items-center gap-1.5 bg-red-600 px-2 py-1 text-xs font-extrabold text-white"><span class="h-1.5 w-1.5 bg-white"></span>LIVE</span></div>
                                <div class="mt-4 grid grid-cols-3 gap-2" aria-hidden="true">
                                    <div class="col-span-2 row-span-2 flex aspect-video items-end bg-[#262B29] p-2 text-xs font-semibold text-white">{{ $first }}</div>
                                    <div class="flex aspect-video items-end bg-[#46504B] p-1.5 text-[11px] text-white">Ayesha</div>
                                    <div class="flex aspect-video items-end bg-[#46504B] p-1.5 text-[11px] text-white">Bilal</div>
                                </div>
                                <div class="mt-3 grid grid-cols-4 gap-2" aria-hidden="true">@foreach (['Sana', 'Usman', 'Hira', '+38'] as $s)<div class="flex aspect-video items-end bg-[#46504B] p-1.5 text-[11px] text-white">{{ $s }}</div>@endforeach</div>
                            </div>
                            <div class="ppane" id="ppane-1" role="tabpanel" aria-labelledby="ptab-1">
                                <p class="font-display font-bold">Recordings</p>
                                <ul class="mt-4 space-y-2.5">
                                    @foreach ($journey->take(5)->values() as $j)
                                        <li class="flex items-center gap-3 border border-[#E1E6E3] p-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center bg-[#1A1E1C] text-[#2FBF71]"><svg width="12" height="12" viewBox="0 0 14 14" aria-hidden="true"><path d="M3 1l10 6-10 6z" fill="currentColor"/></svg></span>
                                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">Class {{ $loop->iteration }} — {{ $j['title'] }}</span><span class="mt-1.5 block h-1.5 bg-[#F3F5F4]"><span class="block h-full bg-[#2FBF71]" style="width: {{ [100, 100, 80, 45, 10][$loop->index] }}%"></span></span></span>
                                        </li>
                                    @endforeach
                                    @if ($journey->isEmpty())<li class="text-sm text-[#4F5753]">Every class, recorded and ready.</li>@endif
                                </ul>
                            </div>
                            <div class="ppane" id="ppane-2" role="tabpanel" aria-labelledby="ptab-2">
                                <p class="font-display font-bold">This week’s tasks</p>
                                <ul class="mt-4 space-y-2.5">
                                    @foreach ([['Submit your course outline', 'Reviewed', 'bg-[#12804A] text-white'], ['Record a 2-minute intro', 'Submitted', 'bg-[#F3F5F4] text-[#1A1E1C]'], ['Plan your first scholarship session', 'Due Friday', 'bg-[#EAF8F0] text-[#17784A]']] as [$t, $st, $c])
                                        <li class="flex items-center justify-between gap-3 border border-[#E1E6E3] p-3.5"><span class="text-sm font-semibold">{{ $t }}</span><span class="shrink-0 px-2 py-1 text-xs font-bold {{ $c }}">{{ $st }}</span></li>
                                    @endforeach
                                </ul>
                                <p class="mt-4 border-l-4 border-[#2FBF71] bg-[#EAF8F0] p-3 text-sm"><b>Feedback:</b> Strong outline. Add one quiz per week.</p>
                            </div>
                            <div class="ppane" id="ppane-3" role="tabpanel" aria-labelledby="ptab-3">
                                <div class="border-2 border-[#1A1E1C] p-6">
                                    <div class="flex justify-between gap-4"><p class="font-display text-sm font-bold">Learning <span class="text-[#17784A]">with</span> Earning</p><p class="text-xs font-bold text-[#4F5753]">Verified</p></div>
                                    <p class="mt-8 text-sm text-[#4F5753]">This certifies that</p>
                                    <p class="mt-1 font-display text-2xl font-extrabold">Your name</p>
                                    <p class="mt-2 text-sm text-[#4F5753]">completed {{ $certificateCourse ?: 'the course' }} with {{ $name }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Packages --}}
    @if ($hasPackages)
        <section id="packages" class="scroll-mt-20 py-20 sm:py-28" aria-labelledby="packages-title">
            <div class="s-wrap">
                <div class="flex flex-wrap items-end justify-between gap-8">
                    <div>
                        <h2 id="packages-title" class="{{ $h2 }}" data-anim="wipe">Choose your package</h2>
                        <p class="{{ $sub }}" data-anim="fade" style="--d:.15s">Every package is taught live by {{ $first }}.</p>
                    </div>
                    @if ($offerEndsAt)
                        <div class="flex items-stretch border-2 border-[#1A1E1C]" data-ends="{{ $offerEndsAt }}" data-anim="right" role="timer" aria-label="Time left for this offer">
                            <p class="flex items-center bg-[#1A1E1C] px-4 text-[13px] font-bold text-white">Offer ends in</p>
                            @foreach (['d' => 'days', 'h' => 'hours', 'm' => 'min', 's' => 'sec'] as $u => $label)
                                <p class="min-w-[4rem] border-l border-[#E1E6E3] px-3 py-2 text-center"><span class="block font-display text-2xl font-extrabold leading-tight" data-unit="{{ $u }}">00</span><span class="block text-[11px] font-semibold text-[#4F5753]">{{ $label }}</span></p>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-16 grid gap-8 {{ [1 => 'mx-auto max-w-md', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3'][min(3, $packages->count())] }} lg:items-stretch">
                    @foreach ($packages as $pk)
                        @php($dark = $pk['featured'])
                        <article class="{{ $dark ? 's-offset' : '' }} flex" data-anim="{{ $dark ? 'zoom' : ($loop->first ? 'left' : 'right') }}" style="--d: {{ $dark ? .05 : .2 }}s">
                            <div class="flex w-full flex-col border-2 p-8 sm:p-9 {{ $dark ? 'border-[#1A1E1C] bg-[#1A1E1C] text-white' : 'border-[#E1E6E3] bg-white' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-display text-2xl font-bold">{{ $pk['name'] }}</h3>
                                    @if (! empty($pk['badge']))<span class="shrink-0 whitespace-nowrap bg-[#2FBF71] px-2.5 py-1 text-xs font-extrabold text-[#1A1E1C]">{{ $pk['badge'] }}</span>@endif
                                </div>
                                @if (! empty($pk['tagline']))<p class="mt-2 text-sm {{ $dark ? 'text-white/70' : 'text-[#4F5753]' }}">{{ $pk['tagline'] }}</p>@endif
                                <div class="mt-7 flex min-h-[1.5rem] items-center gap-2.5 text-sm">
                                    @if ($pk['off'])
                                        <s class="{{ $dark ? 'text-white/55' : 'text-[#6B7470]' }}">PKR {{ $fmt($pk['old_price']) }}</s>
                                        <span class="bg-[#12804A] px-2 py-0.5 text-xs font-extrabold text-white">Save {{ $pk['off'] }}%</span>
                                    @endif
                                </div>
                                <p class="mt-1.5 whitespace-nowrap font-display font-extrabold leading-none">
                                    @if ($pk['price'] > 0)<span class="mr-1 align-top text-lg">PKR</span><span class="text-[2.9rem] sm:text-[3.25rem] lg:text-[2.75rem] xl:text-[3.25rem]">{{ $fmt($pk['price']) }}</span>@else<span class="text-5xl">Free</span>@endif
                                </p>
                                @if (! empty($pk['features']))
                                    <ul class="mt-7 space-y-3.5 border-t pt-7 text-[0.95rem] {{ $dark ? 'border-white/15' : 'border-[#E1E6E3]' }}">
                                        @foreach ($pk['features'] as $f)
                                            <li class="flex gap-3"><svg class="mt-1 h-4 w-4 shrink-0 text-[#2FBF71]" viewBox="0 0 16 16" aria-hidden="true"><path d="M2 8.5l4 4 8-9" stroke="currentColor" stroke-width="2.6" fill="none" stroke-linecap="square"/></svg><span>{{ $f }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="mt-auto pt-9">
                                    @if ($pk['href'])
                                        <a href="{{ $pk['href'] }}" target="_blank" rel="noopener" class="s-btn w-full {{ $dark ? 'bg-[#2FBF71] text-[#1A1E1C] shadow-[5px_5px_0_#fff]' : 'bg-[#1A1E1C] text-white hover:bg-[#262B29]' }}" data-lead="{{ $lead($pk['name'], $pk['price']) }}">Join {{ $pk['name'] }}</a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($compare->isNotEmpty())
                    <div class="mt-20 overflow-x-auto border-2 border-[#1A1E1C]" data-anim="up">
                        <table class="w-full min-w-[640px] border-collapse text-[15px]">
                            <thead><tr class="bg-[#1A1E1C] text-left text-white">
                                <th scope="col" class="px-5 py-4 font-bold">Compare packages</th>
                                @foreach ($packages as $pk)<th scope="col" class="px-5 py-4 font-bold {{ $pk['featured'] ? 'bg-[#2FBF71] text-[#1A1E1C]' : '' }}">{{ $pk['name'] }}</th>@endforeach
                            </tr></thead>
                            <tbody>
                                @foreach ($compare as $row)
                                    <tr class="border-t border-[#E1E6E3]">
                                        <th scope="row" class="px-5 py-3.5 text-left font-semibold">{{ $row['feature'] }}</th>
                                        @foreach ($row['has'] as $i => $yes)
                                            <td class="px-5 py-3.5 {{ $packages[$i]['featured'] ? 'bg-[#EAF8F0]' : '' }}">
                                                @if ($yes)<svg class="h-5 w-5 text-[#12804A]" viewBox="0 0 16 16" role="img" aria-label="Included"><path d="M2 8.5l4 4 8-9" stroke="currentColor" stroke-width="2.6" fill="none" stroke-linecap="square"/></svg>@else<span class="text-[#A0A8A4]" aria-label="Not included">—</span>@endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if ($whatsappHref)
                    <p class="mt-10 text-center text-sm text-[#4F5753]">Not sure which one? <a href="{{ $whatsappHref }}" target="_blank" rel="noopener" class="font-bold text-[#1A1E1C] underline decoration-[#2FBF71] decoration-2 underline-offset-4" data-lead="{{ $lead('WhatsApp (packages help)') }}">Ask us on WhatsApp</a> — we reply the same day.</p>
                @endif
            </div>
        </section>
    @endif

    {{-- About the mentor --}}
    @if ($p->bio || $timeline->isNotEmpty())
        <section class="py-20 sm:py-28" aria-labelledby="about">
            <div class="s-wrap flex flex-wrap gap-16">
                <div class="min-w-0 flex-[1_1_440px]">
                    <h2 id="about" class="{{ $h2 }}" data-anim="wipe">About {{ $first }}</h2>
                    @if ($p->bio)
                        <div class="mt-6 space-y-4 text-[1.05rem] leading-[1.8] text-[#4F5753]" data-anim="fade" style="--d:.15s">
                            @foreach (preg_split('/\n\s*\n/', trim($p->bio)) as $para)<p>{!! nl2br(e($para)) !!}</p>@endforeach
                        </div>
                    @endif
                    @if ($socials->isNotEmpty())
                        <ul class="mt-8 flex flex-wrap gap-2.5" aria-label="Find {{ $first }} online">
                            @foreach ($socials as $k => $url)
                                <li data-anim="up" style="--d: {{ .2 + $loop->index * .06 }}s"><a href="{{ $url }}" target="_blank" rel="noopener me" class="inline-flex min-h-[2.75rem] items-center border-2 border-[#1A1E1C] px-4 text-sm font-bold hover:bg-[#1A1E1C] hover:text-white">{{ $socialNames[$k] ?? ucfirst($k) }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if ($timeline->isNotEmpty())
                    <ol class="min-w-0 flex-[1_1_440px] border-l-2 border-[#1A1E1C]">
                        @foreach ($timeline as $t)
                            <li class="relative pb-8 pl-7" data-anim="left" style="--d: {{ $loop->index * .12 }}s">
                                <span class="absolute -left-2 top-1 h-3.5 w-3.5 bg-[#2FBF71]" aria-hidden="true"></span>
                                <p class="font-display text-[15px] font-extrabold text-[#17784A]">{{ $t['year'] }}</p>
                                <p class="mt-1.5 leading-relaxed">{{ $t['text'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>
    @endif

    {{-- Courses on the portal --}}
    @if ($courses->isNotEmpty())
        <section class="border-t border-[#E1E6E3] py-20 sm:py-28" aria-labelledby="courses-title">
            <div class="s-wrap">
                <h2 id="courses-title" class="{{ $h2 }}" data-anim="wipe">Courses by {{ $first }}</h2>
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($courses as $c)
                        <article class="border-2 border-[#E1E6E3] bg-white" data-anim="up" style="--d: {{ ($loop->index % 3) * .1 }}s">
                            @if ($c['thumbnail'])<img src="{{ $c['thumbnail'] }}" alt="" width="640" height="360" loading="lazy" class="aspect-video w-full object-cover">@endif
                            <div class="p-6">
                                <h3 class="font-display text-lg font-bold">{{ $c['title'] }}</h3>
                                @if ($c['short_description'])<p class="mt-2 text-sm leading-relaxed text-[#4F5753]">{{ $c['short_description'] }}</p>@endif
                                <p class="mt-4 font-bold">{{ $c['fee'] }}@if ($c['is_vip']) <span class="ml-2 bg-[#2FBF71] px-2 py-0.5 text-xs font-extrabold text-[#1A1E1C]">VIP</span>@endif</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- FAQ --}}
    @if ($faqs->isNotEmpty())
        <section id="faq" class="scroll-mt-20 border-t border-[#E1E6E3] py-20 sm:py-28" aria-labelledby="faq-title">
            <div class="s-wrap flex flex-wrap gap-14">
                <div class="min-w-0 flex-[1_1_320px]">
                    <h2 id="faq-title" class="{{ $h2 }}" data-anim="wipe">Questions people ask</h2>
                    @if ($whatsappHref)<p class="mt-4 leading-relaxed text-[#4F5753]" data-anim="fade">Something else? <a href="{{ $whatsappHref }}" target="_blank" rel="noopener" class="font-bold text-[#1A1E1C] underline decoration-[#2FBF71] decoration-2 underline-offset-4" data-lead="{{ $lead('WhatsApp (FAQ)') }}">Ask us on WhatsApp</a> — we reply the same day.</p>@endif
                </div>
                <div class="min-w-0 flex-[2_1_520px] border-t-2 border-[#1A1E1C]">
                    @foreach ($faqs as $f)
                        <details class="group border-b border-[#E1E6E3]" data-anim="up" style="--d: {{ $loop->index * .07 }}s" @if ($loop->first) open @endif>
                            <summary class="flex min-h-[2.75rem] items-center justify-between gap-6 py-5 font-display text-lg font-semibold">
                                <span>{{ $f['q'] }}</span>
                                <span class="faq-sign flex h-9 w-9 shrink-0 items-center justify-center bg-[#1A1E1C] text-xl text-white transition-transform group-open:bg-[#2FBF71] group-open:text-[#1A1E1C]" aria-hidden="true"><span>+</span></span>
                            </summary>
                            <p class="pb-6 pr-14 leading-relaxed text-[#4F5753]">{!! nl2br(e($f['a'])) !!}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Closing call to action --}}
    <section class="bg-[#2FBF71] text-[#1A1E1C]">
        <div class="s-wrap flex flex-wrap items-center justify-between gap-8 py-16 sm:py-20">
            <div class="min-w-0" data-anim="left">
                <h2 class="font-display text-[2rem] font-extrabold leading-tight sm:text-5xl">Start learning with {{ $first }}</h2>
                @if ($seats)
                    <p class="mt-3 text-lg font-semibold">{{ $seats['left'] }} of {{ $seats['total'] }} seats left{{ $p->cta_note ? ' — '.$p->cta_note : '' }}</p>
                @elseif ($p->cta_note)
                    <p class="mt-3 text-lg font-semibold">{{ $p->cta_note }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-4" data-anim="right" style="--d:.15s">
                @if ($hasPackages)<a href="#packages" class="s-btn bg-[#1A1E1C] text-white shadow-[5px_5px_0_#fff]">Choose a package</a>@endif
                @if ($whatsappHref)<a href="{{ $whatsappHref }}" target="_blank" rel="noopener" class="s-btn-line" data-lead="{{ $lead('WhatsApp (footer)') }}">Ask on WhatsApp</a>@endif
            </div>
        </div>
    </section>

    {{-- Phones: buttons always within reach --}}
    @if ($hasPackages || $whatsappHref)
        <div class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-2 border-t-2 border-[#1A1E1C] bg-white lg:hidden" style="padding-bottom: env(safe-area-inset-bottom)">
            @if ($hasPackages)
                <a href="#packages" class="flex h-14 items-center justify-center bg-[#1A1E1C] text-sm font-bold text-white {{ $whatsappHref ? '' : 'col-span-2' }}">From PKR {{ $fmt($packages->min('price')) }}</a>
            @endif
            @if ($whatsappHref)
                <a href="{{ $whatsappHref }}" target="_blank" rel="noopener" class="flex h-14 items-center justify-center bg-[#25D366] text-sm font-bold text-[#1A1E1C] {{ $hasPackages ? '' : 'col-span-2' }}" data-lead="{{ $lead('WhatsApp (mobile bar)') }}">WhatsApp</a>
            @endif
        </div>
    @endif
@endsection