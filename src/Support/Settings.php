<?php

namespace Vulpo\Cookies\Support;

use Statamic\Facades\Addon;
use Statamic\Support\Arr;

/**
 * Reads the addon's control panel settings.
 *
 * Statamic stores these in `resources/addons/cookies-and-consent.yaml` from the settings
 * blueprint (`resources/blueprints/settings.yaml`), and hands them back in two
 * shapes:
 *
 * - `all()` runs every value through Antlers and casts it to a string, and fills
 *   in the blueprint's defaults. Good for the scalar settings.
 * - `raw()` is what is on disk, untouched. The service scripts have to come from
 *   here: a pasted vendor snippet containing `{{ ... }}` would otherwise be
 *   parsed as Antlers and mangled on the way out.
 */
class Settings
{
    public const PACKAGE = 'vulpo/cookies-and-consent';

    private static ?array $values = null;

    private static ?array $raw = null;

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        self::load();

        return self::$values ?? [];
    }

    /**
     * The settings exactly as stored, without Antlers parsing or defaults.
     *
     * @return array<string, mixed>
     */
    public static function raw(): array
    {
        self::load();

        return self::$raw ?? [];
    }

    /**
     * Forget the memoised settings, e.g. after they were saved in the CP.
     */
    public static function flush(): void
    {
        self::$values = null;
        self::$raw = null;
    }

    /**
     * Use the given values instead of reading them from disk. Meant for tests.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>|null  $raw  defaults to $values
     */
    public static function swap(array $values, ?array $raw = null): void
    {
        self::$values = $values;
        self::$raw = $raw ?? $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Arr::get(self::all(), $key);

        return $value === null || $value === '' || $value === [] ? $default : $value;
    }

    public static function string(string $key, ?string $default = null): ?string
    {
        $value = self::get($key);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null
            ? $default
            : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function int(string $key, ?int $default = null): ?int
    {
        $value = self::get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return array<int, mixed>
     */
    public static function list(string $key): array
    {
        $value = self::get($key, []);

        return array_values(array_filter(Arr::wrap($value), fn ($item) => $item !== null && $item !== ''));
    }

    /**
     * Rows of a grid field, as stored, with empty rows removed.
     *
     * Grids are read raw so that pasted scripts survive verbatim.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(string $key): array
    {
        $rows = Arr::wrap(Arr::get(self::raw(), $key, []));

        return array_values(array_filter(
            $rows,
            fn ($row) => is_array($row) && array_filter($row, fn ($value) => $value !== null && $value !== '' && $value !== []) !== [],
        ));
    }

    private static function load(): void
    {
        if (self::$values !== null) {
            return;
        }

        try {
            $settings = Addon::get(self::PACKAGE)?->settings();

            self::$values = $settings?->all() ?? [];
            self::$raw = $settings?->raw() ?? [];
        } catch (\Throwable $e) {
            // Settings live wherever Statamic's addon settings repository puts
            // them, which on an eloquent-driver site is a table that may not
            // have been migrated yet. A missing consent configuration must not
            // take the front end down with it.
            report($e);

            self::$values = [];
            self::$raw = [];
        }
    }
}
