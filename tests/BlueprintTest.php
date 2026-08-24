<?php

use Statamic\Facades\YAML;
use Vulpo\Cookies\Consent\ConsentMode;

/**
 * @return array<string, mixed>
 */
function blueprint(): array
{
    return YAML::file(__DIR__.'/../resources/blueprints/settings.yaml')->parse();
}

/**
 * @param  array<int, array<string, mixed>>  $fields
 * @return array<string, mixed>
 */
function fieldIn(array $fields, string $handle): array
{
    foreach ($fields as $field) {
        if (($field['handle'] ?? null) === $handle) {
            return $field['field'];
        }
    }

    return [];
}

/**
 * The fields of a single-set replicator. Categories and services are replicators
 * rather than grids for one reason: only a replicator's rows collapse, and a
 * category with its services expanded fills the screen.
 *
 * @param  array<string, mixed>  $field
 * @return array<int, array<string, mixed>>
 */
function setFields(array $field, string $set): array
{
    return $field['sets']['main']['sets'][$set]['fields'] ?? [];
}

it('nests the services inside the categories, both collapsible', function () {
    $categories = fieldIn(blueprint()['tabs']['consent']['sections'][0]['fields'], 'categories');

    expect($categories['type'])->toBe('replicator');
    expect($categories['collapse'])->toBeTrue();
    expect($categories['previews'])->toBeTrue();

    $services = fieldIn(setFields($categories, 'category'), 'services');

    expect($services['type'])->toBe('replicator');
    expect($services['collapse'])->toBeTrue();
    expect(collect(setFields($services, 'service'))->pluck('handle')->all())
        ->toContain('handle', 'name', 'default_on', 'position', 'script');
});

it('offers every consent mode key gtag understands', function () {
    $categories = fieldIn(blueprint()['tabs']['consent']['sections'][0]['fields'], 'categories');
    $options = array_keys(fieldIn(setFields($categories, 'category'), 'consent_mode')['options']);

    expect($options)->toBe(ConsentMode::KEYS);
});

it('names the settings handles the code reads', function () {
    $handles = collect(blueprint()['tabs'])
        ->flatMap(fn ($tab) => collect($tab['sections'])->flatMap(fn ($section) => $section['fields']))
        ->pluck('handle')
        ->all();

    expect($handles)->toContain(
        'categories',
        'banner_title',
        'banner_text',
        'privacy_url',
        'revision',
        'cookie_lifetime_days',
        'respect_gpc',
        'enable_google',
        'google_tag_id',
        'url_passthrough',
        'ads_data_redaction',
    );
});

it('lets the nested sets use the full width of the settings screen', function () {
    // Addon settings render through PublishForm::asConfig(), which lays every
    // field out as label-left / input-right. Nested one level down that halves
    // twice over, so the fields inside opt out of it.
    $categories = fieldIn(blueprint()['tabs']['consent']['sections'][0]['fields'], 'categories');

    expect($categories['full_width_setting'])->toBeTrue();

    $services = fieldIn(setFields($categories, 'category'), 'services');

    expect($services['full_width_setting'])->toBeTrue();

    foreach ([setFields($categories, 'category'), setFields($services, 'service')] as $fields) {
        foreach ($fields as $field) {
            expect($field['field']['full_width_setting'] ?? false)
                ->toBeTrue("{$field['handle']} would render at half width");
        }
    }
});
