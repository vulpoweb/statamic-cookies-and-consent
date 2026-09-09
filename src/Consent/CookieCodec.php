<?php

namespace Vulpo\Cookies\Consent;

use Vulpo\Cookies\Support\Settings;

/**
 * Encodes a decision into the consent cookie and back out again.
 *
 * The payload is url-encoded JSON: `{"v":1,"t":1755500000,"g":["analytics","ga4"]}`
 * — `v` is the settings revision, `t` when the choice was made, `g` the granted
 * handles. Both categories and services appear in `g`, so a decision survives a
 * category being reordered or renamed in the control panel.
 *
 * A `"p":1` marks the grants as pending: they came from allowing one blocked
 * embed, and the banner's question has not actually been answered yet. Letting
 * that count as a decision would dismiss the banner for good over a single
 * button nobody understood as consent to everything.
 *
 * A payload whose `v` does not match the current revision is treated as no
 * decision at all. That is what raising the revision does: it re-asks everyone
 * without having to know anything about their old answer.
 *
 * The same shape is written by the front-end runtime, so keep the two in step
 * (`resources/js/runtime.js`).
 */
class CookieCodec
{
    public const VERSION_KEY = 'v';

    public const TIME_KEY = 't';

    public const GRANTED_KEY = 'g';

    public const PENDING_KEY = 'p';

    public static function name(): string
    {
        return (string) config('cookies-and-consent.cookie.name', 'vulpo_cookies');
    }

    public static function revision(): int
    {
        return Settings::int('revision', 1) ?: 1;
    }

    public static function lifetimeDays(): int
    {
        $days = Settings::int('cookie_lifetime_days')
            ?? (int) config('cookies-and-consent.cookie.lifetime_days', 180);

        return max(1, $days);
    }

    /**
     * @param  array<int, string>  $granted
     * @param  bool  $pending  the grants stand, but the banner is still unanswered
     */
    public static function encode(array $granted, bool $pending = false, ?int $timestamp = null): string
    {
        $payload = [
            self::VERSION_KEY => self::revision(),
            self::TIME_KEY => $timestamp ?? time(),
            self::GRANTED_KEY => array_values(array_unique($granted)),
        ];

        if ($pending) {
            $payload[self::PENDING_KEY] = 1;
        }

        return rawurlencode((string) json_encode($payload));
    }

    /**
     * The granted handles in a cookie value, or null when there is nothing usable
     * in it: absent, malformed, or from an older revision.
     *
     * @return array<int, string>|null
     */
    public static function decode(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $payload = json_decode(rawurldecode($value), true);

        if (! is_array($payload)) {
            return null;
        }

        if ((int) ($payload[self::VERSION_KEY] ?? 0) !== self::revision()) {
            return null;
        }

        $granted = $payload[self::GRANTED_KEY] ?? [];

        if (! is_array($granted)) {
            return null;
        }

        return array_values(array_filter($granted, 'is_string'));
    }

    /**
     * Whether a cookie value holds grants that are not yet a decision.
     */
    public static function isPending(?string $value): bool
    {
        if (self::decode($value) === null) {
            return false;
        }

        $payload = json_decode(rawurldecode((string) $value), true);

        return is_array($payload) && ! empty($payload[self::PENDING_KEY]);
    }
}
