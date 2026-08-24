<?php

use Vulpo\Cookies\Consent\Registry;

/**
 * A settings array shaped like the control panel stores it.
 *
 * @param  array<int, array<string, mixed>>  $categories
 */
function registry(array $categories): Registry
{
    test()->settings(['categories' => $categories]);

    return app(Registry::class);
}

it('builds categories with their services', function () {
    $registry = registry([
        [
            'handle' => 'necessary',
            'name' => 'Necessary',
            'required' => true,
            'services' => [
                ['handle' => 'session', 'name' => 'Session'],
            ],
        ],
        [
            'handle' => 'analytics',
            'name' => 'Analytics',
            'description' => 'Helps us see what is used.',
            'consent_mode' => ['analytics_storage'],
            'services' => [
                ['handle' => 'ga4', 'name' => 'Google Analytics', 'provider' => 'Google', 'default_on' => true],
                ['handle' => 'plausible', 'name' => 'Plausible'],
            ],
        ],
    ]);

    expect($registry->categories())->toHaveCount(2);
    expect($registry->category('analytics')->description)->toBe('Helps us see what is used.');
    expect($registry->category('analytics')->serviceHandles())->toBe(['ga4', 'plausible']);
    expect($registry->service('ga4')->provider)->toBe('Google');
    expect($registry->service('ga4')->category)->toBe('analytics');
    expect($registry->services())->toHaveCount(3);
});

it('marks every service of an always-on category as required', function () {
    $registry = registry([
        [
            'handle' => 'necessary',
            'name' => 'Necessary',
            'required' => true,
            'services' => [['handle' => 'session', 'name' => 'Session']],
        ],
        [
            'handle' => 'analytics',
            'name' => 'Analytics',
            'services' => [['handle' => 'ga4', 'name' => 'GA4']],
        ],
    ]);

    expect($registry->service('session')->required)->toBeTrue();
    expect($registry->service('session')->isPreTicked())->toBeTrue();
    expect($registry->service('ga4')->required)->toBeFalse();
    expect($registry->requiredHandles())->toBe(['necessary', 'session']);
});

it('slugifies handles and falls back to the name', function () {
    $registry = registry([
        [
            'handle' => 'Ad Tracking!',
            'name' => 'Advertising',
            'services' => [
                ['name' => 'Meta Pixel'],
            ],
        ],
    ]);

    expect($registry->category('ad_tracking'))->not->toBeNull();
    expect($registry->service('meta_pixel'))->not->toBeNull();
});

it('drops rows without a handle or a name', function () {
    $registry = registry([
        ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
            ['handle' => '', 'name' => ''],
            ['handle' => 'ga4', 'name' => 'GA4'],
        ]],
        ['handle' => '', 'name' => ''],
        ['description' => 'orphaned text'],
    ]);

    expect($registry->categories())->toHaveCount(1);
    expect($registry->services())->toHaveCount(1);
});

it('drops a duplicate handle rather than let two rows share one decision', function () {
    $registry = registry([
        ['handle' => 'analytics', 'name' => 'First', 'services' => [
            ['handle' => 'ga4', 'name' => 'GA4'],
            ['handle' => 'ga4', 'name' => 'GA4 again'],
        ]],
        ['handle' => 'analytics', 'name' => 'Second'],
    ]);

    expect($registry->categories())->toHaveCount(1);
    expect($registry->category('analytics')->name)->toBe('First');
    expect($registry->services())->toHaveCount(1);
    expect($registry->service('ga4')->name)->toBe('GA4');
});

it('reports the handles a visitor accepting everything would grant', function () {
    $registry = registry([
        ['handle' => 'necessary', 'name' => 'Necessary', 'required' => true, 'services' => [
            ['handle' => 'session', 'name' => 'Session'],
        ]],
        ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
            ['handle' => 'ga4', 'name' => 'GA4'],
        ]],
    ]);

    expect($registry->allHandles())->toBe(['necessary', 'session', 'analytics', 'ga4']);
});

it('pre-ticks a category when any of its services is pre-ticked', function () {
    $registry = registry([
        ['handle' => 'necessary', 'name' => 'Necessary', 'required' => true, 'services' => [
            ['handle' => 'session', 'name' => 'Session'],
        ]],
        ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
            ['handle' => 'ga4', 'name' => 'GA4', 'default_on' => true],
            ['handle' => 'plausible', 'name' => 'Plausible'],
        ]],
        ['handle' => 'marketing', 'name' => 'Marketing', 'services' => [
            ['handle' => 'pixel', 'name' => 'Pixel'],
        ]],
    ]);

    expect($registry->preTickedHandles())->toBe(['necessary', 'session', 'analytics', 'ga4']);
});

it('keeps a pasted script exactly as it was written', function () {
    $script = '<script>console.log("{{ not antlers }}")</script>';

    $registry = registry([
        ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
            ['handle' => 'ga4', 'name' => 'GA4', 'script' => $script],
        ]],
    ]);

    expect($registry->service('ga4')->script)->toBe($script);
});

it('splits cookie names into a list', function () {
    $registry = registry([
        ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
            ['handle' => 'ga4', 'name' => 'GA4', 'cookies' => '_ga, _ga_*,  , _gid'],
        ]],
    ]);

    expect($registry->service('ga4')->cookieNames())->toBe(['_ga', '_ga_*', '_gid']);
});

it('is empty without any configuration', function () {
    expect(registry([])->isEmpty())->toBeTrue();
});
