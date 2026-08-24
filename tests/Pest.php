<?php

use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Vulpo\Cookies\Tags\CookieTags;
use Vulpo\Cookies\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * The tag, configured and ready to have a subtag method called on it.
 *
 * @param  array<string, mixed>  $settings
 * @param  array<string, mixed>  $params
 */
function tag(array $settings = [], array $params = []): CookieTags
{
    test()->settings(array_merge([
        'banner_title' => 'We use cookies',
        'banner_text' => 'You choose what is allowed.',
        'categories' => [
            [
                'handle' => 'necessary',
                'name' => 'Necessary',
                'required' => true,
                'services' => [
                    ['handle' => 'session', 'name' => 'Session', 'cookies' => 'laravel_session', 'duration' => '2 hours'],
                ],
            ],
            [
                'handle' => 'analytics',
                'name' => 'Analytics',
                'consent_mode' => ['analytics_storage'],
                'services' => [
                    [
                        'handle' => 'ga4',
                        'name' => 'Google Analytics',
                        'provider' => 'Google',
                        'position' => 'head',
                        'script' => '<script>window.__ga4 = true</script>',
                    ],
                    [
                        'handle' => 'plausible',
                        'name' => 'Plausible',
                        'default_on' => true,
                        'script' => '<script src="https://plausible.io/js/script.js"></script>',
                    ],
                ],
            ],
        ],
    ], $settings));

    $tag = app(CookieTags::class);

    $tag->setContext(new Context([]));
    $tag->setParameters(new Parameters($params, new Context([])));

    return $tag;
}
