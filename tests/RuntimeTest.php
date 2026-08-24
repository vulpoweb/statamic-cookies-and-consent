<?php

use Vulpo\Cookies\Consent\CookieCodec;

/**
 * The runtime writes the same cookie payload PHP reads, and it is the only thing
 * that decides what runs. Neither is reachable from a PHP test, so these are
 * source assertions: they catch the two halves drifting apart, which is the
 * failure that would otherwise only show up in a browser.
 */
function runtime(): string
{
    return file_get_contents(__DIR__.'/../resources/js/runtime.js');
}

it('writes the same cookie payload the codec reads', function () {
    expect(runtime())
        ->toContain('v: config.revision')
        ->toContain('g: unique(granted)')
        ->toContain('payload.v !== config.revision');

    expect([CookieCodec::VERSION_KEY, CookieCodec::TIME_KEY, CookieCodec::GRANTED_KEY])->toBe(['v', 't', 'g']);
});

it('sets the cookie with the attributes a consent cookie needs', function () {
    expect(runtime())
        ->toContain(';path=/;samesite=')
        ->toContain("location.protocol === 'https:' ? ';secure' : ''");
});

it('rebuilds script elements rather than cloning them', function () {
    // A cloned script node is not guaranteed to execute; a freshly created one
    // always does. Getting this wrong means allowed services silently never run.
    expect(runtime())
        ->toContain("document.createElement('script')")
        ->toContain('script.text = node.textContent');
});

it('reads the privacy signals the server also honours', function () {
    expect(runtime())
        ->toContain('navigator.globalPrivacyControl === true')
        ->toContain("navigator.doNotTrack === '1'");
});

it('announces its state both ways so a custom view can drive itself', function () {
    expect(runtime())
        ->toContain("'vulpo-cookies:mode'")
        ->toContain("'vulpo-cookies:changed'")
        ->toContain("'vulpo-cookies:ready'");
});

it('exposes the api the tags point at', function () {
    foreach (['open', 'close', 'acceptAll', 'rejectAll', 'save', 'reset', 'granted'] as $method) {
        expect(runtime())->toContain($method.':');
    }

    expect(runtime())->toContain('window.vulpoCookies = api');
});

it('keeps a collapsed category header counting what is allowed inside it', function () {
    // A collapsed card hides its service toggles, so the header is the only thing
    // telling the visitor how much of the category they allowed.
    expect(runtime())
        ->toContain('[data-vulpo-cookies-count]')
        ->toContain('vulpoCookiesCountFormat')
        ->toContain("replace(':allowed', on)")
        ->toContain('syncCounts()');

    // Counted on load and again after any toggle changes.
    expect(substr_count(runtime(), 'syncCounts()'))->toBeGreaterThanOrEqual(3);
});

it('deletes what a withdrawn service left behind', function () {
    expect(runtime())
        ->toContain('function forgetWithdrawn()')
        ->toContain('function forget(name)')
        // Vendors set cookies on the parent domain, and the browser only deletes
        // a cookie when the domain matches how it was set.
        ->toContain(";path=/;domain=' + domain")
        ->toContain(";path=/;domain=.' + domain")
        // Its own cookie and the always-on categories are off limits.
        ->toContain('if (declared === cookie.name) return')
        ->toContain('if (category.required) return');

    // Cleaned up on load and again after every decision.
    expect(substr_count(runtime(), 'forgetWithdrawn()'))->toBeGreaterThanOrEqual(3);
});

it('lets a blocked embed through without reloading the page', function () {
    expect(runtime())
        ->toContain('function activateEmbeds()')
        ->toContain('data-vulpo-cookies-embed-placeholder')
        ->toContain('allow: function (handle)')
        ->toContain('api.allow(trigger.dataset.vulpoCookiesAllow)');

    // Allowing one embed also grants its category, so the toggles and Consent
    // Mode agree with what just happened.
    expect(runtime())->toContain('var category = categoryOf(handle)');

    // Granting takes nothing back, so nothing needs a fresh document.
    $allow = str(runtime())->after('allow: function (handle)')->before('},')->toString();
    expect($allow)->not->toContain('location.reload');
});

it('keeps asking the banner question after a grant on an embed', function () {
    expect(runtime())
        ->toContain('writeCookie(granted, !decided)')
        ->toContain("show(decided ? null : 'banner')")
        ->toContain('pending: !!payload.p')
        ->toContain('if (pending) payload.p = 1');

    // A saved choice is an answer, so it is never written as pending.
    expect(runtime())->toContain('writeCookie(granted, false)');
});

it('reloads only when something already running is taken away', function () {
    // A script cannot be un-run and an embed cannot be un-loaded, so withdrawal
    // needs a fresh document. Granting settles in place.
    expect(runtime())
        ->toContain('function losesSomethingLive(next)')
        ->toContain('var reload = losesSomethingLive(next)')
        ->toContain("vulpoCookiesActivated === 'true'");

    $commit = str(runtime())->after('function commit(handles)')->before("\n    }")->toString();

    expect($commit)->toContain('if (reload) {');
    expect($commit)->toContain('location.reload()');
});

it('opens the preferences panel from a link', function () {
    expect(runtime())
        ->toContain("location.search.indexOf('cookie-preferences')")
        ->toContain("location.hash === '#cookie-preferences'")
        ->toContain('if (deepLinked()) {');
});

it('narrates every decision when debugging is switched on', function () {
    expect(runtime())
        ->toContain("location.search.indexOf('vulpo-cookies-debug')")
        ->toContain('function debug()')
        ->toContain("debug('activating script'")
        ->toContain("debug('deleting cookie'")
        ->toContain("debug('letting embed through'")
        ->toContain("debug('gtag consent update'");

    // Silent unless asked for.
    expect(runtime())->toContain('if (!debugging || !window.console) return');
});

it('can share one decision across subdomains', function () {
    expect(runtime())->toContain("(cookie.domain ? ';domain=' + cookie.domain : '')");
    expect(runtime())->toContain("if (cookie.domain) document.cookie = cookie.name + '=' + stamp + ';domain=' + cookie.domain");
});

it('keeps focus in the panel and hands it back on the way out', function () {
    expect(runtime())
        ->toContain('function trapFocus(container)')
        ->toContain('function releaseFocus(container)')
        ->toContain("if (event.key === 'Escape')")
        ->toContain("if (event.key !== 'Tab') return")
        ->toContain('if (lastFocused && lastFocused.focus) lastFocused.focus()')
        // And says what happened, since the panel closing is silent.
        ->toContain('data-vulpo-cookies-status')
        ->toContain('announce(config.saved_message');
});

it('honours the handles a service used to go by', function () {
    expect(runtime())
        ->toContain('function handlesOf(handle)')
        ->toContain('service.aliases || []');
});
