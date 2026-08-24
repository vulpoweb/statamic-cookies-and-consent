{{--
    An embed held back until its service is allowed.

    The embed's markup sits in an inert <template>, so the vendor is never
    contacted and sets nothing until the visitor says so — hiding an iframe with
    CSS would not achieve that, because it still loads.

    Publish and restyle:

        php artisan vendor:publish --tag=vulpo-cookies-views

    The runtime only needs the data attributes:

    - `data-vulpo-cookies-embed="<handle>"` on the wrapper
    - `data-vulpo-cookies-embed-content="<handle>"` on the <template>
    - `data-vulpo-cookies-embed-placeholder` on whatever shows in the meantime
    - `data-vulpo-cookies-action="allow"` + `data-vulpo-cookies-allow="<handle>"` on the button
--}}
<div data-vulpo-cookies-embed="{{ $handle }}">
    <template data-vulpo-cookies-embed-content="{{ $handle }}">{!! $content !!}</template>

    <div data-vulpo-cookies-embed-placeholder>
        <p>{{ __(':name is blocked until you allow it.', ['name' => $name]) }}</p>

        @if ($provider)
            <p>{{ __('This content comes from :provider.', ['provider' => $provider]) }}</p>
        @endif

        <button type="button"
                data-vulpo-cookies-action="allow"
                data-vulpo-cookies-allow="{{ $handle }}">{{ __('Allow :name', ['name' => $name]) }}</button>
    </div>
</div>
