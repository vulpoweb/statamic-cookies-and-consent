<?php

/**
 * Statamic's own addon test harness stubs the control panel nav out
 * (`AddonTestCase::setUp()` does `Nav::shouldReceive('build')`), so there is no
 * sidebar to inspect here. These are source assertions: they catch the item being
 * dropped or pointed somewhere else. The assembled sidebar is asserted for real in
 * the consuming project, where Nav is not mocked.
 */
function providerSource(): string
{
    return file_get_contents(__DIR__.'/../src/ServiceProvider.php');
}

it('registers a Tools item pointing at the settings screen', function () {
    expect(providerSource())
        ->toContain('Nav::extend(')
        ->toContain("->section('Tools')")
        ->toContain("->route('addons.settings.edit', \$addon->slug())")
        ->toContain("\$nav->create(__('Cookies'))");
});

it('gates the item on core addon settings authorization', function () {
    // Not a permission of its own: `configure addons` and
    // `edit vulpo/cookies settings` both have to keep working.
    expect(providerSource())
        ->toContain("->can('editSettings', \$addon)")
        ->not->toContain('Permission::register');
});

it('gives the item an icon of its own', function () {
    expect(providerSource())->toMatch('/navIcon\s*=\s*\'<svg/');
});

it('translates the item name', function () {
    $translations = json_decode(file_get_contents(__DIR__.'/../lang/en.json'), true);

    expect($translations)->toHaveKey('Cookies');
});
