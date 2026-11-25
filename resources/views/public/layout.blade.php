{{--
    Layout for public pages (mentor landing pages). Plain HTML for speed and SEO:
    title, description, canonical, Open Graph, structured data, Meta Pixel and GA4.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta name="robots" content="{{ ($noindex ?? false) ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' }}">
    <meta name="theme-color" content="#1A1E1C">
    <link rel="icon" type="image/png" href="/icons/favicon-48.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

    {{-- Social sharing (WhatsApp, Facebook, LinkedIn, X) --}}
    <meta property="og:site_name" content="Learning with Earning">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="en_PK">
    @if (! empty($ogImage))
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:alt" content="{{ $title }}">
    @endif
    <meta name="twitter:card" content="{{ empty($ogImage) ? 'summary' : 'summary_large_image' }}">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    @if (! empty($ogImage))<meta name="twitter:image" content="{{ $ogImage }}">@endif

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=sora:600,700,800|manrope:400,500,600,700,800&display=swap" rel="stylesheet">
    @if (! empty($preloadImage))<link rel="preload" as="image" href="{{ $preloadImage }}">@endif
    @vite('resources/css/site.css')
    {{-- Turn on scroll animations only when JS runs, so content is never hidden from crawlers. --}}
    <script>if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) document.documentElement.classList.add('anim');</script>

    @foreach ($schema ?? [] as $block)
        <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach

    {{-- Meta Pixel: the site pixel (Settings) plus this page's own pixel, if any --}}
    @if (! empty($tracking['pixels']) && ! ($noindex ?? false))
        <script>
            !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
            @foreach ($tracking['pixels'] as $pixel)
                fbq('init', @json($pixel));
            @endforeach
            fbq('track', 'PageView');
            @if (! empty($viewContent)) fbq('track', 'ViewContent', @json($viewContent)); @endif
        </script>
        @foreach ($tracking['pixels'] as $pixel)
            <noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id={{ $pixel }}&ev=PageView&noscript=1"></noscript>
        @endforeach
    @endif
    @if (! empty($tracking['ga4']) && ! ($noindex ?? false))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $tracking['ga4'] }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config',@json($tracking['ga4']));</script>
    @endif
</head>
<body class="antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:bg-white focus:px-4 focus:py-2 focus:font-bold">Skip to content</a>

    <header class="sticky top-0 z-40 border-b border-white/10 bg-[#1A1E1C]/95 text-white backdrop-blur">
        <div class="s-wrap flex min-h-[4.25rem] items-center justify-between gap-4">
            <a href="{{ route('mentors.index') }}" class="font-display text-lg font-bold tracking-tight">Learning <span class="text-[#2FBF71]">with</span> Earning</a>
            <nav class="hidden items-center gap-7 text-sm font-semibold text-white/80 md:flex" aria-label="On this page">
                @hasSection('nav')
                    @yield('nav')
                @else
                    <a href="{{ route('mentors.index') }}" class="hover:text-white">Mentors</a>
                @endif
            </nav>
            @hasSection('header_cta')
                @yield('header_cta')
            @else
                <a href="{{ route('login') }}" class="border border-white/40 px-4 py-2.5 text-sm font-semibold hover:border-white">Student login</a>
            @endif
        </div>
    </header>

    <main id="main">@yield('content')</main>

    <footer class="bg-[#1A1E1C] pb-28 pt-12 text-sm text-white/70 lg:pb-12">
        <div class="s-wrap flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="font-display text-lg font-bold text-white">Learning with Earning</p>
                <p class="mt-1 max-w-md">Live skills training with mentors who do the work themselves. Online across Pakistan and at our Multan campus.</p>
            </div>
            <p>© {{ now()->year }} Learning with Earning</p>
        </div>
    </footer>

    <script>
        // Lead tracking: any link with data-lead tells the pixel / GA4 which package or button was tapped.
        document.addEventListener('click', function (e) {
            var a = e.target.closest('[data-lead]');
            if (!a) return;
            var d = {}; try { d = JSON.parse(a.getAttribute('data-lead')); } catch (x) {}
            if (window.fbq) fbq('track', 'Lead', d);
            if (window.gtag) gtag('event', 'generate_lead', { currency: d.currency, value: d.value, item_name: d.content_name });
        });
        // Scroll animations: each [data-anim] element plays once when it comes into view.
        (function () {
            var els = document.querySelectorAll('[data-anim]');
            if (!document.documentElement.classList.contains('anim') || !('IntersectionObserver' in window)) { els.forEach(function (el) { el.classList.add('in'); }); return; }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
            }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
            els.forEach(function (el) { io.observe(el); });
        })();

        // Offer countdown: [data-ends] holds the end time; the block hides itself when time is up.
        document.querySelectorAll('[data-ends]').forEach(function (box) {
            var end = new Date(box.dataset.ends).getTime();
            var parts = box.querySelectorAll('[data-unit]');
            function tick() {
                var left = Math.max(0, end - Date.now());
                if (!left) { box.hidden = true; return clearInterval(t); }
                var v = { d: Math.floor(left / 864e5), h: Math.floor(left / 36e5) % 24, m: Math.floor(left / 6e4) % 60, s: Math.floor(left / 1e3) % 60 };
                parts.forEach(function (p) { p.textContent = String(v[p.dataset.unit]).padStart(2, '0'); });
            }
            var t = setInterval(tick, 1000); tick();
        });

        // Roadmap: when it comes into view every stage branches out in turn, then the line glides
        // one stage at a time and starts over. Arrows move it by hand; hovering pauses it.
        document.querySelectorAll('.jr').forEach(function (jr) {
            var track = jr.querySelector('.jr-track'), cols = jr.querySelectorAll('.jr-col'), n = cols.length;
            if (!n) return;
            var box = jr.closest('section') || document;
            var anim = document.documentElement.classList.contains('anim');
            var k = 0, timer = null, hovering = false, started = false;
            function fit() { return Math.max(1, Math.floor(jr.offsetWidth / cols[0].offsetWidth) - 1); }
            function go(to) {
                var max = Math.max(0, n - fit());
                k = to > max ? 0 : (to < 0 ? max : to);
                track.style.transform = 'translateX(' + (-k * cols[0].offsetWidth) + 'px)';
                cols.forEach(function (c, i) { c.classList.toggle('now', i === k); });
            }
            function play() { clearInterval(timer); if (anim) timer = setInterval(function () { if (!hovering) go(k + 1); }, 3000); }
            function start() {
                if (started) return; started = true;
                cols.forEach(function (c, i) { setTimeout(function () { c.classList.add('on'); }, anim ? i * 140 : 0); });
                setTimeout(function () { go(0); play(); }, anim ? n * 140 + 600 : 0);
            }
            box.querySelectorAll('[data-rm]').forEach(function (b) {
                b.addEventListener('click', function () { start(); go(k + Number(b.dataset.rm)); play(); });
            });
            jr.addEventListener('mouseenter', function () { hovering = true; });
            jr.addEventListener('mouseleave', function () { hovering = false; });
            if (!anim || !('IntersectionObserver' in window)) { start(); return; }
            new IntersectionObserver(function (es) { if (es[0].isIntersecting) start(); }, { threshold: 0.25 }).observe(jr);
        });

        // Inside the portal: tabs change on their own every few seconds until someone taps one.
        document.querySelectorAll('[data-ptabs]').forEach(function (root) {
            var tabs = root.querySelectorAll('[role="tab"]'), panes = root.querySelectorAll('.ppane'), i = 0, auto = null;
            function show(j) {
                i = j;
                tabs.forEach(function (t, k) { t.setAttribute('aria-selected', k === j ? 'true' : 'false'); t.tabIndex = k === j ? 0 : -1; });
                panes.forEach(function (p, k) { p.classList.toggle('on', k === j); });
            }
            tabs.forEach(function (t, k) { t.addEventListener('click', function () { clearInterval(auto); show(k); }); });
            show(0);
            if (document.documentElement.classList.contains('anim')) auto = setInterval(function () { show((i + 1) % tabs.length); }, 5000);
        });

        // Review videos: swap the thumbnail for the real YouTube player only when tapped.
        document.querySelectorAll('.lite-yt').forEach(function (el) {
            el.addEventListener('click', function () {
                if (el.querySelector('iframe')) return;
                var f = document.createElement('iframe');
                f.src = 'https://www.youtube-nocookie.com/embed/' + el.dataset.id + '?autoplay=1&rel=0&playsinline=1';
                f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
                f.title = el.getAttribute('aria-label') || 'Video';
                el.innerHTML = ''; el.appendChild(f);
                var m = el.closest('.marquee-track'); if (m) m.style.animationPlayState = 'paused';
            }, { once: false });
        });
    </script>
</body>
</html>
