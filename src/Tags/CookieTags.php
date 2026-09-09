<?php

namespace Vulpo\Cookies\Tags;

use Statamic\Tags\Tags;
use Vulpo\Cookies\Consent\Consent;
use Vulpo\Cookies\Consent\ConsentMode;
use Vulpo\Cookies\Consent\CookieCodec;
use Vulpo\Cookies\Consent\Registry;
use Vulpo\Cookies\Support\Settings;

/**
 * {{ vulpo_cookies }}                        The banner, the preferences panel, the runtime and every inert service block.
 * {{ vulpo_cookies:banner }}                 The same thing, named for clarity in a layout.
 * {{ vulpo_cookies:scripts }}                Only the runtime and the service blocks, for a project rendering its own UI.
 * {{ vulpo_cookies:categories }}             Loops the configured categories, each with its services.
 * {{ vulpo_cookies:declaration }}            Loops every declared cookie, for a privacy page's table.
 * {{ vulpo_cookies:embed handle="youtube" }} Holds an embed back until that service is allowed.
 * {{ vulpo_cookies:granted handle="ga4" }}   Whether a category or service is allowed on this request.
 * {{ vulpo_cookies:open_preferences }}       JS to open the preferences panel, for an onclick.
 * {{ vulpo_cookies:reset }}                  JS to forget the decision and ask again.
 * {{ vulpo_cookies:cookie_name }}            The name of the consent cookie.
 */
class CookieTags extends Tags
{
    protected static $handle = 'vulpo_cookies';

    public function index(): string
    {
        return $this->banner();
    }

    public function banner(): string
    {
        if ($this->registry()->isEmpty()) {
            return '';
        }

        return (string) view('vulpo-cookies::consent', [
            'title' => Settings::string('banner_title', __('We use cookies')),
            'text' => Settings::string('banner_text', ''),
            'privacy_url' => Settings::string('privacy_url', ''),
            'categories' => $this->categories(),
            'scripts' => $this->scripts(),
        ]);
    }

    public function scripts(): string
    {
        if ($this->registry()->isEmpty()) {
            return '';
        }

        return (string) view('vulpo-cookies::scripts', [
            'consent_mode' => $this->consentMode(),
            'config' => $this->config(),
            'runtime' => $this->runtime(),
            'blocks' => $this->blocks(),
        ]);
    }

    /**
     * The inert markup blocks, one per service that has a script. Kept out of
     * Service::toArray() so a script never leaks into the runtime's JSON payload.
     *
     * @return array<int, array<string, string>>
     */
    private function blocks(): array
    {
        $blocks = [];

        foreach ($this->registry()->services() as $service) {
            if ($service->script === '') {
                continue;
            }

            $blocks[] = [
                'handle' => $service->handle,
                'id' => $service->blockId(),
                'script' => $service->script,
            ];
        }

        return $blocks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return array_map(
            fn ($category) => $category->toArray(),
            $this->registry()->categories(),
        );
    }

    /**
     * Every declared cookie, flattened, for a privacy page's table: one row per
     * cookie, carrying the service and category it belongs to.
     *
     * @return array<int, array<string, mixed>>
     */
    public function declaration(): array
    {
        $rows = [];

        foreach ($this->registry()->categories() as $category) {
            foreach ($category->services as $service) {
                foreach ($service->declaration as $cookie) {
                    $rows[] = [
                        'category' => $category->handle,
                        'category_name' => $category->name,
                        'required' => $category->required,
                        'service' => $service->handle,
                        'service_name' => $service->name,
                        'provider' => $service->provider,
                        'name' => $cookie->name,
                        'purpose' => $cookie->purpose,
                        'duration' => $cookie->duration,
                        'wildcard' => $cookie->isWildcard(),
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * {{ vulpo_cookies:embed handle="youtube" }}<iframe …></iframe>{{ /vulpo_cookies:embed }}
     *
     * Holds an embed back until its service is allowed, showing a placeholder in
     * the meantime. The markup never reaches the document, so the vendor is not
     * contacted and sets nothing — which is the whole point, and the reason an
     * iframe cannot simply be hidden with CSS.
     *
     * A handle nobody configured counts as not allowed: a consent gate that fails
     * open is not a gate.
     */
    public function embed(): string
    {
        $handle = (string) $this->params->get('handle', '');
        $service = $handle === '' ? null : $this->registry()->service($handle);

        // Antlers hands the tag a parser, so the embed can contain variables.
        // Without one — a direct call, e.g. from a test — the content is markup
        // already and `parse()` would hand back its data array instead.
        $content = $this->parse();

        return (string) view('vulpo-cookies::embed', [
            'handle' => $handle,
            'name' => $service?->name ?: ($this->registry()->category($handle)?->name ?: $handle),
            'provider' => (string) $service?->provider,
            'content' => is_string($content) ? $content : (string) $this->content,
        ]);
    }

    public function granted(): bool
    {
        $handle = (string) $this->params->get('handle', '');

        return $handle !== '' && app(Consent::class)->granted($handle);
    }

    public function hasDecided(): bool
    {
        return app(Consent::class)->hasDecided();
    }

    public function openPreferences(): string
    {
        return 'window.vulpoCookies && window.vulpoCookies.open()';
    }

    public function reset(): string
    {
        return 'window.vulpoCookies && window.vulpoCookies.reset()';
    }

    public function cookieName(): string
    {
        return CookieCodec::name();
    }

    /**
     * Everything the runtime needs to know, as a JSON payload. Identical for
     * every visitor, so a cached page stays correct.
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $mode = $this->consentMode();

        return [
            'revision' => CookieCodec::revision(),
            'respect_gpc' => Settings::bool('respect_gpc', (bool) config('cookies-and-consent.respect_gpc', true)),
            'saved_message' => __('Your choices have been saved.'),
            'cookie' => [
                'name' => CookieCodec::name(),
                'lifetime_days' => CookieCodec::lifetimeDays(),
                'same_site' => (string) config('cookies-and-consent.cookie.same_site', 'lax'),
                'domain' => (string) config('cookies-and-consent.cookie.domain', ''),
            ],
            'consentMode' => [
                'enabled' => $mode->isEnabled(),
                'keys' => ConsentMode::KEYS,
                'map' => $mode->map(),
            ],
            'categories' => $this->categories(),
        ];
    }

    private function runtime(): string
    {
        if (! config('cookies-and-consent.runtime.inline', true)) {
            return '';
        }

        return (string) file_get_contents(__DIR__.'/../../resources/js/runtime.js');
    }

    private function registry(): Registry
    {
        return app(Registry::class);
    }

    private function consentMode(): ConsentMode
    {
        return app(ConsentMode::class);
    }
}
