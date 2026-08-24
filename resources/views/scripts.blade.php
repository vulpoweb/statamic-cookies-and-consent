{{--
    The machinery, in the order it has to happen:

    1. gtag is put into a denied-by-default state before Google's own script is
       fetched, which is what Consent Mode v2 expects.
    2. Every service's markup is parked in an inert <template>. Nothing in here
       runs, so this output is identical for every visitor and safe to cache.
    3. The runtime reads the consent cookie and moves the allowed templates into
       the document.

    Override `vulpo-cookies::consent` for your own UI, not this view.
--}}
@if ($consent_mode->isEnabled())
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent', 'default', @json($consent_mode->defaults()));
        @if ($consent_mode->adsDataRedaction())
            gtag('set', 'ads_data_redaction', true);
        @endif
        @if ($consent_mode->urlPassthrough())
            gtag('set', 'url_passthrough', true);
        @endif
        gtag('js', new Date());
        gtag('config', @json($consent_mode->tagId()));
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($consent_mode->tagId()) }}"></script>
@endif

@foreach ($blocks as $block)
    <template data-vulpo-cookies-service="{{ $block['handle'] }}" id="{{ $block['id'] }}">{!! $block['script'] !!}</template>
@endforeach

<script>window.vulpoCookiesConfig = @json($config);</script>

@if ($runtime !== '')
    <script>{!! $runtime !!}</script>
@endif
