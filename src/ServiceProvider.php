<?php

namespace Vulpo\Cookies;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Event;
use Statamic\Events\AddonSettingsSaved;
use Statamic\Facades\Addon;
use Statamic\Facades\CP\Nav;
use Statamic\Providers\AddonServiceProvider;
use Vulpo\Cookies\Consent\Consent;
use Vulpo\Cookies\Consent\ConsentMode;
use Vulpo\Cookies\Consent\CookieCodec;
use Vulpo\Cookies\Consent\Registry;
use Vulpo\Cookies\Fieldtypes\ConsentService;
use Vulpo\Cookies\Support\Settings;
use Vulpo\Cookies\Tags\CookieTags;

class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        CookieTags::class,
    ];

    protected $fieldtypes = [
        ConsentService::class,
    ];

    protected $viewNamespace = 'vulpo-cookies';

    private string $navIcon = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M21 12a9 9 0 1 1-9-9 3.5 3.5 0 0 0 4.5 4.5A3.5 3.5 0 0 0 21 12Z"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="14.5" cy="14" r="1" fill="currentColor" stroke="none"/><circle cx="9.5" cy="15.5" r="1" fill="currentColor" stroke="none"/></svg>';

    public function register(): void
    {
        parent::register();

        $this->app->singleton(Registry::class);

        $this->app->singleton(ConsentMode::class, fn ($app) => new ConsentMode(
            $app[Registry::class],
        ));

        // Scoped, not singleton: a decision belongs to one request.
        $this->app->scoped(Consent::class, fn ($app) => new Consent(
            $app['request'],
            $app[Registry::class],
        ));
    }

    public function bootAddon(): void
    {
        // Strings go through __(), so a project can translate the whole addon by
        // dropping its own {locale}.json next to these.
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/vulpo-cookies'),
        ], 'vulpo-cookies-translations');

        $this->publishes([
            __DIR__.'/../resources/blueprints' => resource_path('blueprints/vendor/vulpo-cookies'),
        ], 'vulpo-cookies-blueprints');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/vulpo-cookies'),
        ], 'vulpo-cookies-views');

        $this->exemptConsentCookieFromEncryption();
        $this->registerNav();

        // The registry is memoised, so it has to forget the tree when an editor
        // adds a category.
        Event::listen(AddonSettingsSaved::class, function (AddonSettingsSaved $event) {
            if ($event->settings->addon()->id() !== Settings::PACKAGE) {
                return;
            }

            Settings::flush();
            $this->app[Registry::class]->flush();
        });
    }

    /**
     * A Tools entry pointing at the settings screen.
     *
     * Statamic lists an addon's settings under Tools → Addons, which is a detour
     * for something an editor changes as often as content. This puts it at the top
     * level instead, and drops core's own entry so the same screen is not offered
     * twice. The item is gated on core's addon policy, so `configure addons` and
     * per-addon settings permissions both keep working without the addon inventing
     * a permission of its own.
     */
    private function registerNav(): void
    {
        Nav::extend(function ($nav) {
            if (! $addon = Addon::get(Settings::PACKAGE)) {
                return;
            }

            // Core nests its entry under Addons for anyone who can configure
            // addons, and puts it straight into Tools for anyone who can only edit
            // this addon's settings. Both are the same screen as ours.
            $nav->remove('Tools', 'Addons', $addon->name());
            $nav->remove('Tools', $addon->name());

            $nav->create(__('Cookies'))
                ->section('Tools')
                ->icon($this->navIcon)
                ->can('editSettings', $addon)
                ->route('addons.settings.edit', $addon->slug());
        });
    }

    /**
     * The consent cookie is written by the front-end runtime, in the clear.
     * Laravel encrypts cookies by default and discards any it cannot decrypt, so
     * without this exemption the server would never see a decision at all and
     * `{{ vulpo_cookies:granted }}` would always be false.
     */
    private function exemptConsentCookieFromEncryption(): void
    {
        EncryptCookies::except([CookieCodec::name()]);
    }
}
