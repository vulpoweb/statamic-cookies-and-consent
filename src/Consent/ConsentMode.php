<?php

namespace Vulpo\Cookies\Consent;

use Vulpo\Cookies\Support\Settings;

/**
 * Google Consent Mode v2.
 *
 * gtag.js is loaded with every storage key denied, and the runtime sends an
 * update once it knows what the visitor allowed. Which keys a category unlocks is
 * a per-category setting, so a site can map its own categories onto Google's
 * without the addon guessing.
 */
class ConsentMode
{
    /**
     * Every key gtag understands. Anything not mapped to a category stays denied.
     */
    public const KEYS = [
        'analytics_storage',
        'ad_storage',
        'ad_user_data',
        'ad_personalization',
        'functionality_storage',
        'personalization_storage',
        'security_storage',
    ];

    public function __construct(private readonly Registry $registry) {}

    public function isEnabled(): bool
    {
        return Settings::bool('enable_google') && $this->tagId() !== null;
    }

    public function tagId(): ?string
    {
        return Settings::string('google_tag_id') ?: null;
    }

    /**
     * The state gtag starts in: everything denied, plus the grace period it
     * waits before acting on those defaults.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = array_fill_keys(self::KEYS, 'denied');

        $defaults['wait_for_update'] = (int) config('cookies-and-consent.consent_mode.wait_for_update', 500);

        return $defaults;
    }

    /**
     * Which keys each category unlocks, for the runtime to apply once it has read
     * the cookie. Categories without a mapping are left out entirely.
     *
     * @return array<string, array<int, string>>
     */
    public function map(): array
    {
        $map = [];

        foreach ($this->registry->categories() as $category) {
            $keys = array_values(array_intersect($category->consentMode, self::KEYS));

            if ($keys !== []) {
                $map[$category->handle] = $keys;
            }
        }

        return $map;
    }

    /**
     * The update gtag should receive for a given set of granted handles.
     *
     * @param  array<int, string>  $granted
     * @return array<string, string>
     */
    public function update(array $granted): array
    {
        $update = array_fill_keys(self::KEYS, 'denied');

        foreach ($this->map() as $category => $keys) {
            if (! in_array($category, $granted, true)) {
                continue;
            }

            foreach ($keys as $key) {
                $update[$key] = 'granted';
            }
        }

        return $update;
    }

    public function urlPassthrough(): bool
    {
        return Settings::bool('url_passthrough');
    }

    public function adsDataRedaction(): bool
    {
        return Settings::bool('ads_data_redaction', true);
    }
}
