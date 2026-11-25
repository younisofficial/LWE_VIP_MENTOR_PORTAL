{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('mentors.index') }}</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>
@foreach ($profiles as $p)
    <url><loc>{{ route('mentors.show', $p->slug) }}</loc><lastmod>{{ $p->updated_at?->toAtomString() }}</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>
@endforeach
</urlset>
