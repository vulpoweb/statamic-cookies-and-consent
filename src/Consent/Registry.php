<?php

namespace Vulpo\Cookies\Consent;

use Statamic\Support\Arr;
use Statamic\Support\Str;
use Vulpo\Cookies\Presets\Preset;
use Vulpo\Cookies\Presets\Presets;
use Vulpo\Cookies\Support\Settings;

/**
 * The configured categories and services, cleaned up.
 *
 * Everything else in the addon — the tags, the runtime payload, the Consent Mode
 * mapping — reads the tree from here, so handle rules live in exactly one place.
 */
class Registry
{
    /** @var array<int, Category>|null */
    private ?array $categories = null;

    /**
     * @return array<int, Category>
     */
    public function categories(): array
    {
        return $this->categories ??= $this->build();
    }

    public function isEmpty(): bool
    {
        return $this->categories() === [];
    }

    public function category(string $handle): ?Category
    {
        foreach ($this->categories() as $category) {
            if ($category->handle === $handle) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return array<int, Service>
     */
    public function services(): array
    {
        return array_merge(...array_map(
            fn (Category $category) => $category->services,
            $this->categories(),
        ) ?: [[]]);
    }

    public function service(string $handle): ?Service
    {
        foreach ($this->services() as $service) {
            if ($service->handle === $handle) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Handles that are granted whether or not the visitor ever decides
     * anything: the always-on categories and everything inside them.
     *
     * @return array<int, string>
     */
    public function requiredHandles(): array
    {
        $handles = [];

        foreach ($this->categories() as $category) {
            if (! $category->required) {
                continue;
            }

            $handles[] = $category->handle;
            $handles = array_merge($handles, $category->serviceHandles());
        }

        return $handles;
    }

    /**
     * Every handle a visitor accepting everything would grant.
     *
     * @return array<int, string>
     */
    public function allHandles(): array
    {
        $handles = [];

        foreach ($this->categories() as $category) {
            $handles[] = $category->handle;
            $handles = array_merge($handles, $category->serviceHandles());
        }

        return $handles;
    }

    /**
     * Handles pre-ticked in a banner nobody has answered yet.
     *
     * @return array<int, string>
     */
    public function preTickedHandles(): array
    {
        $handles = [];

        foreach ($this->categories() as $category) {
            $ticked = array_values(array_filter(
                $category->services,
                fn (Service $service) => $service->isPreTicked(),
            ));

            if ($category->required || $ticked !== []) {
                $handles[] = $category->handle;
            }

            foreach ($ticked as $service) {
                $handles[] = $service->handle;
            }
        }

        return $handles;
    }

    /**
     * Forget the built tree, e.g. after the settings were saved.
     */
    public function flush(): void
    {
        $this->categories = null;
    }

    /**
     * @return array<int, Category>
     */
    private function build(): array
    {
        $categories = [];
        $seen = [];

        foreach (Settings::rows('categories') as $row) {
            $handle = $this->handle($row, $seen);

            if ($handle === null) {
                continue;
            }

            $seen[] = $handle;
            $required = $this->bool($row['required'] ?? false);

            $categories[] = new Category(
                handle: $handle,
                name: $this->text($row['name'] ?? '') ?: $handle,
                description: $this->text($row['description'] ?? ''),
                required: $required,
                services: $this->buildServices($row, $handle, $required, $seen),
                consentMode: array_values(array_filter(array_map(
                    fn ($key) => is_string($key) ? trim($key) : '',
                    Arr::wrap($row['consent_mode'] ?? []),
                ))),
            );
        }

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $seen
     * @return array<int, Service>
     */
    private function buildServices(array $row, string $category, bool $required, array &$seen): array
    {
        $services = [];

        foreach (Arr::wrap($row['services'] ?? []) as $service) {
            if (! is_array($service)) {
                continue;
            }

            $handle = $this->handle($service, $seen);

            if ($handle === null) {
                continue;
            }

            $seen[] = $handle;

            // A template fills in whatever the row left empty. What the editor
            // typed always wins, so a preset can be adjusted without abandoning it.
            $preset = Presets::find($this->text($service['preset'] ?? ''));

            // Not trimmed or escaped: this is markup, kept exactly as pasted.
            $script = is_string($service['script'] ?? null) ? $service['script'] : '';

            if ($script === '' && $preset !== null) {
                $script = $preset->script(
                    $this->text($service['preset_id'] ?? ''),
                    $this->text($service['preset_option'] ?? ''),
                );
            }

            $duration = $this->text($service['duration'] ?? '');

            $services[] = new Service(
                handle: $handle,
                name: $this->text($service['name'] ?? '') ?: $preset?->name ?: $handle,
                category: $category,
                description: $this->text($service['description'] ?? '') ?: (string) $preset?->description,
                defaultOn: $this->bool($service['default_on'] ?? false),
                required: $required,
                position: ($service['position'] ?? '') === 'head' ? 'head' : 'body_end',
                provider: $this->text($service['provider'] ?? '') ?: (string) $preset?->provider,
                declaration: $this->declaration($service, $preset, $duration),
                duration: $duration,
                script: $script,
                aliases: $this->aliases($service, $handle, $seen),
            );
        }

        return $services;
    }

    /**
     * The cookies a service declares, from the grid the editor filled in, else
     * from its template, else from a plain comma-separated list of names.
     *
     * A row without its own duration falls back to the service's, so the common
     * case — several cookies that all last as long — stays one field.
     *
     * @param  array<string, mixed>  $service
     * @return array<int, Cookie>
     */
    private function declaration(array $service, ?Preset $preset, string $default): array
    {
        $rows = array_values(array_filter(
            Arr::wrap($service['cookie_declaration'] ?? []),
            fn ($row) => is_array($row) && $this->text($row['name'] ?? '') !== '',
        ));

        if ($rows === []) {
            // `cookies` is the shorthand: names only, sharing one duration.
            $names = array_values(array_filter(array_map(
                'trim',
                explode(',', $this->text($service['cookies'] ?? '')),
            )));

            $rows = array_map(fn (string $name) => ['name' => $name], $names);
        }

        if ($rows === []) {
            return $preset?->cookies() ?? [];
        }

        return array_map(fn (array $row) => new Cookie(
            name: $this->text($row['name']),
            purpose: $this->text($row['purpose'] ?? ''),
            duration: $this->text($row['duration'] ?? '') ?: $default,
        ), $rows);
    }

    /**
     * Handles this service used to go by, slugified the same way as a handle so
     * an old stored consent still matches. Anything already taken by another
     * service or category is dropped: an alias must never hijack a live handle.
     *
     * @param  array<string, mixed>  $service
     * @param  array<int, string>  $seen
     * @return array<int, string>
     */
    private function aliases(array $service, string $handle, array $seen): array
    {
        $aliases = array_map(
            fn (string $alias) => Str::slug(trim($alias), '_'),
            explode(',', $this->text($service['aliases'] ?? '')),
        );

        return array_values(array_unique(array_filter(
            $aliases,
            fn (string $alias) => $alias !== '' && $alias !== $handle && ! in_array($alias, $seen, true),
        )));
    }

    /**
     * A row's handle, slugified, or null when it has none or duplicates one that
     * came before it. Handles are the identity of a stored consent, so a
     * duplicate has to lose rather than silently share a decision.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $seen
     */
    private function handle(array $row, array $seen): ?string
    {
        $handle = Str::slug($this->text($row['handle'] ?? '') ?: $this->text($row['name'] ?? ''), '_');

        if ($handle === '' || in_array($handle, $seen, true)) {
            return null;
        }

        return $handle;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
