<?php

use Statamic\Facades\Addon;
use Vulpo\Cookies\Support\Settings;

it('exposes a settings screen built from the blueprint', function () {
    $addon = Addon::get(Settings::PACKAGE);

    expect($addon)->not->toBeNull();
    expect($addon->hasSettingsBlueprint())->toBeTrue();
    expect($addon->slug())->toBe('cookies-and-consent');

    // The slug is left to derive from the package name. Core reads settings from
    // resources/addons/{package}.yaml but writes them to resources/addons/{slug}.yaml,
    // so a custom slug would break the round-trip. Core also keys the merged config
    // by the slug, so the config file and its key follow the package name too.
    expect(config('cookies-and-consent.cookie.name'))->toBe('vulpo_cookies');
});

it('keeps the settings screen behind authentication', function () {
    $this->get(cp_route('addons.settings.edit', 'cookies-and-consent'))->assertRedirect();
});

it('ships every translatable string in lang/en.json', function () {
    $translations = json_decode(file_get_contents(__DIR__.'/../lang/en.json'), true);

    expect($translations)->toBeArray();

    $missing = [];

    $files = collect(['/../src', '/../resources/views'])
        ->flatMap(fn (string $directory) => iterator_to_array(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__.$directory, FilesystemIterator::SKIP_DOTS),
        )))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.php'))
        ->values();

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        preg_match_all('/__\(\s*([\'"])(.+?)\1/', file_get_contents($file->getPathname()), $matches);

        foreach ($matches[2] as $string) {
            if (! array_key_exists($string, $translations)) {
                $missing[] = $file->getFilename().': '.$string;
            }
        }
    }

    expect($missing)->toBe([]);
});
