{{--
    The packaged consent UI: deliberately plain markup with no styling, so it
    works on a fresh install and gets out of the way of a project's own design.

    To restyle it, publish it and edit the copy:

        php artisan vendor:publish --tag=vulpo-cookies-views

    A project view may be Blade or Antlers — `resources/views/vendor/vulpo-cookies/consent.antlers.html`
    wins over this file. Everything the runtime needs is a data attribute, so a
    replacement only has to keep those:

    - `data-vulpo-cookies-root`, `-banner`, `-preferences` on the containers
    - `data-vulpo-cookies-action="accept-all|reject-all|save|open|close|reset"` on controls
    - `data-vulpo-cookies-category="<handle>"` on a category checkbox
    - `data-vulpo-cookies-toggle="<handle>"` plus `data-vulpo-cookies-category-of="<category>"` on a service checkbox

    A view that drives its own visibility (Alpine, say) can leave the container
    attributes off and listen for the `vulpo-cookies:mode` event instead.
--}}
{!! $scripts !!}

<div data-vulpo-cookies-root hidden>
    <div data-vulpo-cookies-banner hidden role="region" aria-label="{{ __('Cookie consent') }}">
        <h2>{{ $title }}</h2>

        @if ($text)
            <p>{{ $text }}</p>
        @endif

        @if ($privacy_url)
            <p><a href="{{ $privacy_url }}">{{ __('Privacy policy') }}</a></p>
        @endif

        <button type="button" data-vulpo-cookies-action="reject-all">{{ __('Reject all') }}</button>
        <button type="button" data-vulpo-cookies-action="open">{{ __('Choose what is allowed') }}</button>
        <button type="button" data-vulpo-cookies-action="accept-all">{{ __('Accept all') }}</button>
    </div>

    <div data-vulpo-cookies-preferences hidden role="dialog" aria-modal="true" aria-label="{{ __('Cookie preferences') }}">
        <h2>{{ __('Cookie preferences') }}</h2>

        {{-- The runtime writes the confirmation here; it is otherwise silent. --}}
        <p data-vulpo-cookies-status role="status" aria-live="polite"></p>

        @foreach ($categories as $category)
            {{-- <details> collapses with no JS and no CSS, which is the whole point of the packaged view. --}}
            <details>
                <summary>
                    {{ $category['name'] }}
                    @if ($category['service_count'])
                        (<span data-vulpo-cookies-count="{{ $category['handle'] }}">{{ $category['service_count'] }}</span>)
                    @endif
                    @if ($category['description'])
                        <span>{{ $category['description'] }}</span>
                    @endif
                </summary>

                @if ($category['required'])
                    <p>{{ __('Always on. The site does not work without these.') }}</p>
                @else
                    <p>
                        <input type="checkbox"
                               id="{{ $category['toggle_id'] }}"
                               data-vulpo-cookies-category="{{ $category['handle'] }}">
                        <label for="{{ $category['toggle_id'] }}">{{ __('Allow all of :category', ['category' => $category['name']]) }}</label>
                    </p>
                @endif

                @foreach ($category['services'] as $service)
                    <div>
                        <input type="checkbox"
                               id="{{ $service['toggle_id'] }}"
                               data-vulpo-cookies-toggle="{{ $service['handle'] }}"
                               data-vulpo-cookies-category-of="{{ $service['category'] }}"
                               @checked($service['pre_ticked'])
                               @disabled($service['required'])>
                        <label for="{{ $service['toggle_id'] }}">{{ $service['name'] }}</label>

                        @if ($service['description'])
                            <p>{{ $service['description'] }}</p>
                        @endif

                        @if ($service['provider'] || $service['cookies'] || $service['duration'])
                            <dl>
                                @if ($service['provider'])
                                    <dt>{{ __('Provider') }}</dt>
                                    <dd>{{ $service['provider'] }}</dd>
                                @endif
                                @if ($service['cookies'])
                                    <dt>{{ __('Cookies') }}</dt>
                                    <dd>{{ $service['cookies'] }}</dd>
                                @endif
                                @if ($service['duration'])
                                    <dt>{{ __('Duration') }}</dt>
                                    <dd>{{ $service['duration'] }}</dd>
                                @endif
                            </dl>
                        @endif
                    </div>
                @endforeach
            </details>
        @endforeach

        <button type="button" data-vulpo-cookies-action="reject-all">{{ __('Reject all') }}</button>
        <button type="button" data-vulpo-cookies-action="save">{{ __('Save my choices') }}</button>
        <button type="button" data-vulpo-cookies-action="accept-all">{{ __('Accept all') }}</button>
        <button type="button" data-vulpo-cookies-action="close">{{ __('Close') }}</button>
    </div>
</div>
