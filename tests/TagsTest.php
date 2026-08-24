<?php

use Vulpo\Cookies\Consent\CookieCodec;

it('renders a toggle for every service, not just every category', function () {
    $html = tag()->banner();

    expect($html)
        ->toContain('data-vulpo-cookies-category="analytics"')
        ->toContain('data-vulpo-cookies-toggle="ga4"')
        ->toContain('data-vulpo-cookies-toggle="plausible"')
        ->toContain('data-vulpo-cookies-toggle="session"')
        ->toContain('Google Analytics')
        ->toContain('Plausible');

    // An always-on category is stated, never offered as a choice.
    expect($html)->not->toContain('data-vulpo-cookies-category="necessary"');
});

it('links a service toggle to its category', function () {
    expect(tag()->banner())->toContain('data-vulpo-cookies-category-of="analytics"');
});

it('pre-ticks only what the settings pre-tick', function () {
    $html = tag()->banner();

    expect($html)->toMatch('/id="vulpo-cookies-toggle-plausible"[^>]*checked/s');
    expect($html)->not->toMatch('/id="vulpo-cookies-toggle-ga4"[^>]*checked/s');
});

it('locks the toggle of an always-on service', function () {
    expect(tag()->banner())->toMatch('/id="vulpo-cookies-toggle-session"[^>]*disabled/s');
});

it('parks third-party scripts in an inert template', function () {
    $html = tag()->banner();

    expect($html)->toContain('<template data-vulpo-cookies-service="ga4"');
    expect($html)->toContain('<template data-vulpo-cookies-service="plausible"');

    // The script is present but cannot run: it only ever appears inside a
    // template, which the browser does not execute.
    expect($html)->toContain('window.__ga4 = true');
    expect(substr_count($html, 'plausible.io/js/script.js'))->toBe(1);
    expect($html)->toMatch('/<template data-vulpo-cookies-service="plausible"[^>]*>\s*<script src="https:\/\/plausible\.io\/js\/script\.js">/');
});

it('renders the same markup whatever the visitor already decided', function () {
    $withoutCookie = tag()->banner();

    request()->cookies->set('vulpo_cookies', CookieCodec::encode(['analytics', 'ga4']));

    expect(tag()->banner())->toBe($withoutCookie);
});

it('hands the runtime a payload of the whole tree', function () {
    $html = tag()->banner();

    expect($html)->toContain('window.vulpoCookiesConfig =');

    preg_match('/window\.vulpoCookiesConfig = (.+?);<\/script>/s', $html, $matches);
    $config = json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES), true);

    expect($config['cookie']['name'])->toBe('vulpo_cookies');
    expect($config['revision'])->toBe(1);
    expect($config['categories'])->toHaveCount(2);
    expect($config['categories'][1]['services'][0]['handle'])->toBe('ga4');
    expect($config['categories'][1]['services'][0]['position'])->toBe('head');

    // A script never travels in the payload, only the fact that there is one.
    expect($matches[1] ?? '')->not->toContain('__ga4');
});

it('inlines the runtime', function () {
    expect(tag()->banner())->toContain('window.vulpoCookies = api');
});

it('leaves the runtime out when it is switched off', function () {
    config()->set('cookies.runtime.inline', false);

    expect(tag()->banner())->not->toContain('window.vulpoCookies = api');
});

it('renders nothing at all until categories are configured', function () {
    expect(tag(['categories' => []])->banner())->toBe('');
    expect(tag(['categories' => []])->scripts())->toBe('');
});

it('sets up google consent mode before loading gtag', function () {
    $html = tag(['enable_google' => true, 'google_tag_id' => 'G-ABC123'])->scripts();

    expect($html)
        ->toContain("gtag('consent', 'default'")
        ->toContain('"analytics_storage":"denied"')
        ->toContain('googletagmanager.com/gtag/js?id=G-ABC123');

    expect(strpos($html, "gtag('consent', 'default'"))->toBeLessThan(strpos($html, 'googletagmanager.com'));
});

it('leaves google alone when it is not enabled', function () {
    expect(tag()->scripts())->not->toContain('googletagmanager.com');
});

it('exposes the categories for a project rendering its own ui', function () {
    $categories = tag()->categories();

    expect($categories)->toHaveCount(2);
    expect($categories[0]['services'][0])
        ->toMatchArray([
            'handle' => 'session',
            'name' => 'Session',
            'cookies' => 'laravel_session',
            'duration' => '2 hours',
            'required' => true,
        ]);
    expect($categories[0]['services'][0]['cookie_names'])->toBe(['laravel_session']);
    expect($categories[0]['services'][0])->not->toHaveKey('script');
});

it('answers whether a handle is granted on this request', function () {
    request()->cookies->set('vulpo_cookies', CookieCodec::encode(['analytics', 'ga4']));

    expect(tag([], ['handle' => 'ga4'])->granted())->toBeTrue();
    expect(tag([], ['handle' => 'plausible'])->granted())->toBeFalse();
    expect(tag([], ['handle' => 'session'])->granted())->toBeTrue();
    expect(tag([], [])->granted())->toBeFalse();
});

it('hands out the javascript the footer link needs', function () {
    expect(tag()->openPreferences())->toBe('window.vulpoCookies && window.vulpoCookies.open()');
    expect(tag()->reset())->toBe('window.vulpoCookies && window.vulpoCookies.reset()');
    expect(tag()->cookieName())->toBe('vulpo_cookies');
});

it('gives a category the panel id and service count a collapsible view needs', function () {
    $categories = tag()->categories();

    expect($categories[1])->toMatchArray([
        'handle' => 'analytics',
        'panel_id' => 'vulpo-cookies-panel-analytics',
        'service_count' => 2,
    ]);

    expect($categories[0]['service_count'])->toBe(1);
});

it('collapses each category in the packaged view without any javascript', function () {
    $html = tag()->banner();

    // <details>/<summary> needs neither JS nor CSS, so the packaged view still
    // collapses on a fresh install with no styling of its own.
    expect($html)
        ->toContain('<details>')
        ->toContain('<summary>')
        ->toContain('data-vulpo-cookies-count="analytics"');
});

it('flattens every declared cookie for a privacy page', function () {
    $rows = tag()->declaration();

    expect($rows)->toHaveCount(1);
    expect($rows[0])->toMatchArray([
        'category' => 'necessary',
        'category_name' => 'Necessary',
        'required' => true,
        'service' => 'session',
        'service_name' => 'Session',
        'name' => 'laravel_session',
        'duration' => '2 hours',
        'wildcard' => false,
    ]);
});

it('carries each cookie of a template into the declaration', function () {
    $rows = tag(['categories' => [
        ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
            ['handle' => 'ga4', 'name' => '', 'preset' => 'google_analytics_4', 'preset_id' => 'G-1'],
        ]],
    ]])->declaration();

    expect(collect($rows)->pluck('name')->all())->toBe(['_ga', '_ga_*']);
    expect(collect($rows)->pluck('duration')->unique()->all())->toBe(['2 years']);
    expect($rows[1]['wildcard'])->toBeTrue();
    expect($rows[0]['service_name'])->toBe('Google Analytics 4');
});

it('holds an embed back behind an inert template', function () {
    $tag = tag([], ['handle' => 'ga4']);
    $tag->setContent('<iframe src="https://www.youtube.com/embed/abc"></iframe>');

    $html = $tag->embed();

    expect($html)
        ->toContain('data-vulpo-cookies-embed="ga4"')
        ->toContain('data-vulpo-cookies-embed-content="ga4"')
        ->toContain('data-vulpo-cookies-embed-placeholder')
        ->toContain('data-vulpo-cookies-allow="ga4"');

    // The iframe is present, but only inside the template — so the browser never
    // requests it and YouTube sets nothing.
    expect($html)->toMatch('/<template data-vulpo-cookies-embed-content="ga4">\s*<iframe/');
    expect(substr_count($html, '<iframe'))->toBe(1);
});

it('names the service in the embed placeholder', function () {
    $tag = tag([], ['handle' => 'ga4']);
    $tag->setContent('<iframe></iframe>');

    expect($tag->embed())
        ->toContain('Google Analytics is blocked until you allow it.')
        ->toContain('This content comes from Google.')
        ->toContain('Allow Google Analytics');
});

it('treats an embed with an unknown handle as not allowed', function () {
    // A consent gate that fails open is not a gate, so an unconfigured handle
    // still blocks and falls back to showing the handle itself.
    $tag = tag([], ['handle' => 'nope']);
    $tag->setContent('<iframe></iframe>');

    $html = $tag->embed();

    expect($html)->toContain('data-vulpo-cookies-embed="nope"');
    expect($html)->toContain('nope is blocked until you allow it.');
});

it('tells the runtime about aliases, the cookie domain and the saved message', function () {
    config()->set('cookies.cookie.domain', '.example.com');

    $html = tag(['categories' => [
        ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
            ['handle' => 'ga4', 'name' => 'GA4', 'aliases' => 'old_ga'],
        ]],
    ]])->banner();

    preg_match('/window\.vulpoCookiesConfig = (.+?);<\/script>/s', $html, $matches);
    $config = json_decode(html_entity_decode($matches[1] ?? '', ENT_QUOTES), true);

    expect($config['cookie']['domain'])->toBe('.example.com');
    expect($config['saved_message'])->toBe('Your choices have been saved.');
    expect($config['categories'][0]['services'][0]['aliases'])->toBe(['old_ga']);
});
