<?php

use Illuminate\Support\Facades\File;

/**
 * A project's own consent view, written where Laravel looks for an override of a
 * packaged namespaced view.
 */
function publishOverride(string $filename, string $contents): string
{
    $path = resource_path('views/vendor/vulpo-cookies/'.$filename);

    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);

    // The finder caches which paths a namespace resolves to, and the override
    // directory only counts if it existed when the namespace was registered.
    view()->replaceNamespace('vulpo-cookies', [
        resource_path('views/vendor/vulpo-cookies'),
        __DIR__.'/../resources/views',
    ]);
    view()->flushFinderCache();

    return $path;
}

afterEach(function () {
    File::deleteDirectory(resource_path('views/vendor/vulpo-cookies'));

    view()->flushFinderCache();
});

it('lets a project replace the consent view with its own antlers one', function () {
    publishOverride('consent.antlers.html', 'Our own banner. {{ categories }}{{ name }} {{ /categories }}');

    $html = tag()->banner();

    expect($html)->toContain('Our own banner.');
    expect($html)->toContain('Necessary');
    expect($html)->not->toContain('data-vulpo-cookies-root');
});

it('lets a project replace it with a blade one', function () {
    publishOverride('consent.blade.php', 'Blade banner: {{ count($categories) }} categories');

    expect(tag()->banner())->toContain('Blade banner: 2 categories');
});

it('leaves the machinery view alone for the project to include', function () {
    publishOverride('consent.blade.php', 'Only ours: {!! $scripts !!}');

    $html = tag()->banner();

    expect($html)->toContain('Only ours:');
    expect($html)->toContain('window.vulpoCookiesConfig =');
});
