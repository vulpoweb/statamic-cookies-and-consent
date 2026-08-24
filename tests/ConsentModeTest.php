<?php

use Vulpo\Cookies\Consent\ConsentMode;

function consentMode(array $settings = []): ConsentMode
{
    test()->settings(array_merge([
        'enable_google' => true,
        'google_tag_id' => 'G-ABC123',
        'categories' => [
            ['handle' => 'necessary', 'name' => 'Necessary', 'required' => true, 'consent_mode' => ['security_storage']],
            ['handle' => 'analytics', 'name' => 'Analytics', 'consent_mode' => ['analytics_storage']],
            ['handle' => 'marketing', 'name' => 'Marketing', 'consent_mode' => ['ad_storage', 'ad_user_data', 'ad_personalization']],
            ['handle' => 'functional', 'name' => 'Functional'],
        ],
    ], $settings));

    return app(ConsentMode::class);
}

it('starts with every storage key denied', function () {
    $defaults = consentMode()->defaults();

    foreach (ConsentMode::KEYS as $key) {
        expect($defaults[$key])->toBe('denied');
    }

    expect($defaults['wait_for_update'])->toBe(500);
});

it('maps only the categories that were given keys', function () {
    expect(consentMode()->map())->toBe([
        'necessary' => ['security_storage'],
        'analytics' => ['analytics_storage'],
        'marketing' => ['ad_storage', 'ad_user_data', 'ad_personalization'],
    ]);
});

it('ignores a key gtag does not understand', function () {
    $mode = consentMode(['categories' => [
        ['handle' => 'analytics', 'name' => 'Analytics', 'consent_mode' => ['analytics_storage', 'made_up_storage']],
    ]]);

    expect($mode->map())->toBe(['analytics' => ['analytics_storage']]);
});

it('grants the keys of the categories the visitor allowed', function () {
    $update = consentMode()->update(['necessary', 'analytics']);

    expect($update['analytics_storage'])->toBe('granted');
    expect($update['security_storage'])->toBe('granted');
    expect($update['ad_storage'])->toBe('denied');
    expect($update['ad_user_data'])->toBe('denied');
    expect($update['functionality_storage'])->toBe('denied');
});

it('denies everything for a visitor who allowed nothing', function () {
    expect(consentMode()->update([]))->toBe(array_fill_keys(ConsentMode::KEYS, 'denied'));
});

it('is off without a tag id, however the toggle is set', function () {
    expect(consentMode()->isEnabled())->toBeTrue();
    expect(consentMode(['google_tag_id' => ''])->isEnabled())->toBeFalse();
    expect(consentMode(['enable_google' => false])->isEnabled())->toBeFalse();
});

it('defaults to redacting ads data but not to url passthrough', function () {
    expect(consentMode()->adsDataRedaction())->toBeTrue();
    expect(consentMode()->urlPassthrough())->toBeFalse();
    expect(consentMode(['url_passthrough' => true])->urlPassthrough())->toBeTrue();
    expect(consentMode(['ads_data_redaction' => false])->adsDataRedaction())->toBeFalse();
});
