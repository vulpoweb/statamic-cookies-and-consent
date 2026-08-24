<?php

namespace Vulpo\Cookies\Tests;

use Statamic\Testing\AddonTestCase;
use Vulpo\Cookies\Consent\Registry;
use Vulpo\Cookies\ServiceProvider;
use Vulpo\Cookies\Support\Settings;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        Settings::swap([]);
    }

    protected function tearDown(): void
    {
        Settings::flush();

        parent::tearDown();
    }

    /**
     * Configure the addon and rebuild the registry from it.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function settings(array $settings): void
    {
        Settings::swap($settings);

        app(Registry::class)->flush();
    }
}
