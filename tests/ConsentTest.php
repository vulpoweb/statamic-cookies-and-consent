<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Vulpo\Cookies\Consent\Consent;
use Vulpo\Cookies\Consent\CookieCodec;
use Vulpo\Cookies\Consent\Registry;

beforeEach(function () {
    test()->settings([
        'revision' => 1,
        'categories' => [
            ['handle' => 'necessary', 'name' => 'Necessary', 'required' => true, 'services' => [
                ['handle' => 'session', 'name' => 'Session'],
            ]],
            ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
                ['handle' => 'ga4', 'name' => 'GA4'],
                ['handle' => 'plausible', 'name' => 'Plausible'],
            ]],
        ],
    ]);
});

/**
 * The addon under a request carrying the given consent cookie value.
 */
function consentFor(?string $cookie): Consent
{
    $cookie === null
        ? request()->cookies->remove(CookieCodec::name())
        : request()->cookies->set(CookieCodec::name(), $cookie);

    return new Consent(request(), app(Registry::class));
}

it('round-trips a decision through the cookie', function () {
    $encoded = CookieCodec::encode(['analytics', 'ga4'], false, 1755500000);

    expect(CookieCodec::decode($encoded))->toBe(['analytics', 'ga4']);
});

it('reads nothing out of a missing or broken cookie', function () {
    expect(CookieCodec::decode(null))->toBeNull();
    expect(CookieCodec::decode(''))->toBeNull();
    expect(CookieCodec::decode('not json'))->toBeNull();
    expect(CookieCodec::decode(rawurlencode('{"v":1,"g":"analytics"}')))->toBeNull();
});

it('treats a decision from an older revision as no decision', function () {
    $encoded = CookieCodec::encode(['analytics', 'ga4']);

    test()->settings(['revision' => 2, 'categories' => []]);

    expect(CookieCodec::decode($encoded))->toBeNull();
});

it('grants only what the cookie says, plus what is always on', function () {
    $consent = consentFor(CookieCodec::encode(['analytics', 'ga4']));

    expect($consent->hasDecided())->toBeTrue();
    expect($consent->granted('ga4'))->toBeTrue();
    expect($consent->granted('analytics'))->toBeTrue();
    expect($consent->granted('plausible'))->toBeFalse();
    expect($consent->granted('session'))->toBeTrue();
    expect($consent->granted('necessary'))->toBeTrue();
});

it('grants nothing but the always-on handles before a decision', function () {
    $consent = consentFor(null);

    expect($consent->hasDecided())->toBeFalse();
    expect($consent->grantedHandles())->toBe(['necessary', 'session']);
    expect($consent->granted('ga4'))->toBeFalse();
    expect($consent->granted('session'))->toBeTrue();
});

it('ignores a handle nobody configured', function () {
    expect(consentFor(CookieCodec::encode(['ghost']))->granted('ghost'))->toBeTrue();
    expect(consentFor(null)->granted('ghost'))->toBeFalse();
});

it('counts a Global Privacy Control signal as a decision to refuse', function () {
    request()->headers->set('Sec-GPC', '1');

    $consent = consentFor(null);

    expect($consent->isOptedOut())->toBeTrue();
    expect($consent->hasDecided())->toBeTrue();
    expect($consent->granted('ga4'))->toBeFalse();
    expect($consent->granted('session'))->toBeTrue();
});

it('honours Do Not Track as well', function () {
    request()->headers->set('DNT', '1');

    expect(consentFor(null)->isOptedOut())->toBeTrue();
});

it('ignores the privacy signals when told to', function () {
    test()->settings(['respect_gpc' => false, 'categories' => []]);
    request()->headers->set('Sec-GPC', '1');

    expect(consentFor(null)->isOptedOut())->toBeFalse();
});

it('reads a cookie the front end wrote, unencrypted', function () {
    // Laravel encrypts cookies and discards what it cannot decrypt. Without the
    // exemption the service provider registers, a decision written by the
    // runtime would never reach the server at all.
    $request = Request::create('/');
    $request->cookies->set(CookieCodec::name(), CookieCodec::encode(['analytics', 'ga4']));
    $request->cookies->set('not_exempt', 'plain value');

    $seen = [];

    app(EncryptCookies::class)->handle($request, function (Request $request) use (&$seen) {
        $seen = $request->cookies->all();

        return response('');
    });

    expect(CookieCodec::decode($seen[CookieCodec::name()] ?? null))->toBe(['analytics', 'ga4']);

    // The control: any other unencrypted cookie is thrown away, which is exactly
    // what would happen to the consent cookie without the exemption.
    expect($seen['not_exempt'])->toBeNull();
});

it('lifts the cookie lifetime from the settings', function () {
    test()->settings(['cookie_lifetime_days' => 30, 'categories' => []]);

    expect(CookieCodec::lifetimeDays())->toBe(30);
});

it('falls back to the configured lifetime', function () {
    config()->set('cookies.cookie.lifetime_days', 90);

    expect(CookieCodec::lifetimeDays())->toBe(90);
});

it('does not treat a grant made on a blocked embed as an answer', function () {
    // Clicking "allow" on one embed grants that service, but the banner question
    // stays open — otherwise a single button would dismiss it for good.
    $pending = CookieCodec::encode(['necessary', 'session', 'analytics', 'ga4'], true);

    expect(CookieCodec::isPending($pending))->toBeTrue();
    expect(CookieCodec::decode($pending))->toContain('ga4');

    $consent = consentFor($pending);

    expect($consent->hasDecided())->toBeFalse();
    expect($consent->granted('ga4'))->toBeTrue();
    expect($consent->granted('plausible'))->toBeFalse();
});

it('treats a saved choice as an answer', function () {
    $saved = CookieCodec::encode(['necessary', 'session', 'analytics', 'ga4']);

    expect(CookieCodec::isPending($saved))->toBeFalse();
    expect(consentFor($saved)->hasDecided())->toBeTrue();
});

it('reports nothing pending for a cookie it cannot read', function () {
    expect(CookieCodec::isPending(null))->toBeFalse();
    expect(CookieCodec::isPending('not json'))->toBeFalse();
});

it('still honours consent stored under a handle a service outgrew', function () {
    // Renaming a handle would otherwise void everyone's consent for it silently.
    test()->settings(['categories' => [
        ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
            ['handle' => 'ga4', 'name' => 'GA4', 'aliases' => 'universal_analytics, old-ga'],
        ]],
    ]]);

    $consent = consentFor(CookieCodec::encode(['analytics', 'universal_analytics']));

    expect($consent->granted('ga4'))->toBeTrue();

    // Slugified the same way handles are, so `old-ga` matches too.
    expect(consentFor(CookieCodec::encode(['old_ga']))->granted('ga4'))->toBeTrue();
});

it('refuses an alias that would hijack a live handle', function () {
    test()->settings(['categories' => [
        ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
            ['handle' => 'ga4', 'name' => 'GA4'],
            ['handle' => 'plausible', 'name' => 'Plausible', 'aliases' => 'ga4, plausible'],
        ]],
    ]]);

    $plausible = app(Registry::class)->service('plausible');

    expect($plausible->aliases)->toBe([]);
    expect(consentFor(CookieCodec::encode(['ga4']))->granted('plausible'))->toBeFalse();
});
