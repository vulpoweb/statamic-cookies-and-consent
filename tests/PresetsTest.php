<?php

use Statamic\Facades\YAML;
use Vulpo\Cookies\Consent\Registry;
use Vulpo\Cookies\Fieldtypes\ConsentService;
use Vulpo\Cookies\Presets\Presets;

/**
 * A category holding one service that uses a template.
 *
 * @param  array<string, mixed>  $service
 */
function withPreset(array $service): Registry
{
    test()->settings(['categories' => [
        ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
            array_merge(['handle' => 'tool', 'name' => ''], $service),
        ]],
    ]]);

    return app(Registry::class);
}

it('ships a template for the tools sites actually load', function () {
    expect(Presets::keys())->toContain(
        'google_analytics_4',
        'google_tag_manager',
        'meta_pixel',
        'microsoft_clarity',
        'hotjar',
        'linkedin_insight',
        'tiktok_pixel',
        'hubspot',
        'plausible',
        'fathom',
        'cloudflare_analytics',
        'google_ads',
        'matomo',
    );
});

it('describes every template well enough to fill a cookie table', function () {
    foreach (Presets::all() as $key => $preset) {
        expect($preset->key)->toBe($key);
        expect($preset->name)->not->toBe('');
        expect($preset->provider)->not->toBe('');
        expect($preset->description)->not->toBe('');
        expect($preset->suggests)->toBeIn(['analytics', 'marketing', 'functional']);
        expect($preset->idLabel)->not->toBe('');
        expect($preset->idExample)->not->toBe('');

        // The snippet has to be markup, and has to take the editor's ID.
        expect($preset->script)->toContain('<script');
        expect($preset->script)->toContain('{id}');

        // A preset either takes a second value and uses it, or takes none at
        // all. A label with nowhere to go would be a field that does nothing.
        if ($preset->usesOption()) {
            $this->assertStringContainsString('{option}', $preset->script, "{$key} asks for a second value and ignores it");
        } else {
            $this->assertStringNotContainsString('{option}', $preset->script, "{$key} uses a second value it never asks for");
            $this->assertSame('', $preset->optionDefault, "{$key} defaults a second value it never asks for");
        }

        // A cookie-setting template must name each cookie and say what it is for
        // and how long it lasts, otherwise the declaration it feeds is a lie by
        // omission.
        foreach ($preset->cookies() as $cookie) {
            $this->assertNotSame('', $cookie->name, "{$key} declares a nameless cookie");
            $this->assertNotSame('', $cookie->purpose, "{$key}: {$cookie->name} has no purpose");
            $this->assertNotSame('', $cookie->duration, "{$key}: {$cookie->name} has no duration");
        }
    }
});

it('offers custom first in the blueprint and matches the catalogue', function () {
    $blueprint = YAML::file(__DIR__.'/../resources/blueprints/settings.yaml')->parse();

    $categories = collect($blueprint['tabs']['consent']['sections'][0]['fields'])
        ->firstWhere('handle', 'categories')['field'];

    $services = collect(setFields($categories, 'category'))->firstWhere('handle', 'services')['field'];

    $preset = collect(setFields($services, 'service'))->firstWhere('handle', 'preset')['field'];

    // The options are YAML and the catalogue is PHP, so this is what keeps the
    // two from drifting apart.
    expect(array_keys($preset['options']))->toBe(array_keys(Presets::options()));
    expect(array_key_first($preset['options']))->toBe(Presets::CUSTOM);
    expect($preset['default'])->toBe(Presets::CUSTOM);

    // And the extra field is offered for exactly the presets that read it, so
    // nobody is asked for a URL that goes nowhere.
    $option = collect(setFields($services, 'service'))->firstWhere('handle', 'preset_option')['field'];

    $shown = str($option['if']['preset'])->after('contains_any')->explode(',')
        ->map(fn (string $key) => trim($key))->all();

    expect($shown)->toBe(Presets::keysWithOption());
});

it('fills in the snippet and the cookie declaration from a template', function () {
    $service = withPreset(['preset' => 'google_analytics_4', 'preset_id' => 'G-ABC123'])->service('tool');

    expect($service->script)->toContain('gtag/js?id=G-ABC123');
    expect($service->script)->toContain("gtag('config', 'G-ABC123')");
    expect($service->name)->toBe('Google Analytics 4');
    expect($service->provider)->toBe('Google');
    expect($service->cookies())->toBe('_ga, _ga_*');
    expect($service->description)->not->toBe('');

    // Each cookie carries its own lifetime, which is how the vendors document them.
    expect(collect($service->declaration)->map->toArray()->all())->toBe([
        ['name' => '_ga', 'purpose' => 'Tells visitors apart', 'duration' => '2 years', 'wildcard' => false],
        ['name' => '_ga_*', 'purpose' => 'Keeps the session state for this property', 'duration' => '2 years', 'wildcard' => true],
    ]);
});

it('lets what the editor typed win over the template', function () {
    $service = withPreset([
        'preset' => 'google_analytics_4',
        'preset_id' => 'G-ABC123',
        'name' => 'Our stats',
        'provider' => 'Google Ireland',
        'cookies' => '_ga',
        'duration' => '14 months',
        'description' => 'Only page counts.',
    ])->service('tool');

    expect($service->name)->toBe('Our stats');
    expect($service->provider)->toBe('Google Ireland');
    expect($service->cookies())->toBe('_ga');
    expect($service->duration)->toBe('14 months');
    expect($service->description)->toBe('Only page counts.');

    // The snippet still comes from the template.
    expect($service->script)->toContain('G-ABC123');
});

it('lets a pasted script override the template entirely', function () {
    $service = withPreset([
        'preset' => 'plausible',
        'preset_id' => 'example.com',
        'script' => '<script src="https://stats.example.com/js/script.js"></script>',
    ])->service('tool');

    expect($service->script)->toBe('<script src="https://stats.example.com/js/script.js"></script>');
    expect($service->script)->not->toContain('plausible.io');
});

it('loads nothing when a template has no id yet', function () {
    // Half a vendor snippet would run and fail on every page, which is worse
    // than not loading at all.
    $service = withPreset(['preset' => 'meta_pixel', 'preset_id' => ''])->service('tool');

    expect($service->script)->toBe('');
    expect($service->name)->toBe('Meta Pixel (Facebook)');
});

it('treats an unknown or custom template as no template', function () {
    expect(Presets::find('custom'))->toBeNull();
    expect(Presets::find(''))->toBeNull();
    expect(Presets::find(null))->toBeNull();
    expect(Presets::find('nope'))->toBeNull();

    $service = withPreset(['preset' => 'nope', 'preset_id' => 'x', 'name' => 'Mystery'])->service('tool');

    expect($service->script)->toBe('');
    expect($service->cookies())->toBe('');
});

it('marks the cookieless tools as cookieless', function () {
    foreach (['plausible', 'fathom', 'cloudflare_analytics'] as $key) {
        expect(Presets::all()[$key]->isCookieless())->toBeTrue();
    }

    expect(Presets::all()['google_analytics_4']->isCookieless())->toBeFalse();
});

it('puts the id into every template that asks for one', function () {
    foreach (Presets::all() as $key => $preset) {
        $script = $preset->script('THE-ID', 'https://tool.example.test/script.js');

        $this->assertStringContainsString('THE-ID', $script, "{$key} drops the id");
        $this->assertStringNotContainsString('{id}', $script, "{$key} left a placeholder behind");
        $this->assertStringNotContainsString('{option}', $script, "{$key} left the second value behind");
    }
});

it('loads every template from the vendor by default', function () {
    // Nothing but an ID should be needed to get a working snippet, so every
    // preset that can load from elsewhere still has to know where the vendor is.
    // Matomo is the exception: it is self-hosted, there is nowhere to default to.
    foreach (Presets::keysWithOption() as $key) {
        $preset = Presets::all()[$key];

        if ($key === 'matomo') {
            $this->assertSame('', $preset->script('1'), 'matomo loaded without knowing which server');

            continue;
        }

        $script = $preset->script('THE-ID');

        $this->assertStringNotContainsString('{option}', $script, "{$key} needs a value it should have defaulted");
        $this->assertStringContainsString('THE-ID', $script, "{$key} dropped the id into its default");
    }
});

it('loads hubspot from the region the portal lives in', function () {
    $default = withPreset(['preset' => 'hubspot', 'preset_id' => '1234567'])->service('tool');

    expect($default->script)->toContain('https://js.hs-scripts.com/1234567.js');

    // Only the host moves. The portal id stays one field, so it cannot end up
    // spelled two different ways.
    $eu = withPreset([
        'preset' => 'hubspot',
        'preset_id' => '1234567',
        'preset_option' => 'js-eu1.hs-scripts.com',
    ])->service('tool');

    expect($eu->script)->toContain('https://js-eu1.hs-scripts.com/1234567.js');
    expect($eu->script)->not->toContain('//js.hs-scripts.com');

    // Still the template's cookies, region or not.
    expect(collect($eu->declaration)->map->name->all())->toContain('hubspotutk');
});

it('loads a self-hosted plausible or fathom from your own url', function () {
    $plausible = withPreset([
        'preset' => 'plausible',
        'preset_id' => 'example.com',
        'preset_option' => 'https://stats.example.com/js/script.outbound-links.js',
    ])->service('tool');

    expect($plausible->script)
        ->toContain('data-domain="example.com"')
        ->toContain('https://stats.example.com/js/script.outbound-links.js')
        ->not->toContain('plausible.io');

    $fathom = withPreset([
        'preset' => 'fathom',
        'preset_id' => 'ABCDEFGH',
        'preset_option' => 'https://cdn.example.com/script.js',
    ])->service('tool');

    expect($fathom->script)
        ->toContain('data-site="ABCDEFGH"')
        ->not->toContain('usefathom.com');
});

it('needs the server url before it loads matomo', function () {
    $without = withPreset(['preset' => 'matomo', 'preset_id' => '1'])->service('tool');

    expect($without->script)->toBe('');

    // The name and cookies still come from the template, so the row is not blank
    // in the control panel while somebody looks the URL up.
    expect($without->name)->toBe('Matomo');
    expect($without->cookies())->toBe('_pk_id.*, _pk_ses.*');
});

it('does not care whether the matomo url ends in a slash', function () {
    foreach (['https://analytics.example.com', 'https://analytics.example.com/'] as $url) {
        $script = withPreset([
            'preset' => 'matomo',
            'preset_id' => '7',
            'preset_option' => $url,
        ])->service('tool')->script;

        // The snippet normalises it in the browser, so both spellings produce
        // the same tracker and script URLs.
        $this->assertStringContainsString("'{$url}'.replace(/\/?$/, '/')", $script);
        $this->assertStringContainsString("u + 'matomo.php'", $script);
        $this->assertStringContainsString("_paq.push(['setSiteId', '7'])", $script);
    }
});

it('offers every configured service to the picker fieldtype', function () {
    test()->settings(['categories' => [
        ['handle' => 'necessary', 'name' => 'Necessary', 'required' => true, 'services' => [
            ['handle' => 'session', 'name' => 'Session'],
        ]],
        ['handle' => 'analytics', 'name' => 'Analytics', 'services' => [
            ['handle' => 'ga4', 'name' => 'GA4'],
        ]],
    ]]);

    $fieldtype = new ConsentService;

    // The list is whatever is configured now, so it cannot go stale.
    expect($fieldtype->getIndexItems(request())->all())->toBe([
        ['id' => 'session', 'title' => 'Session — Necessary'],
        ['id' => 'ga4', 'title' => 'GA4 — Analytics'],
    ]);

    expect($fieldtype->toItemArray('ga4'))->toBe(['id' => 'ga4', 'title' => 'GA4 — Analytics']);

    // A handle whose service is gone shows as missing rather than vanishing.
    expect($fieldtype->toItemArray('ghost')['title'] ?? null)->not->toBe('GA4 — Analytics');
});
