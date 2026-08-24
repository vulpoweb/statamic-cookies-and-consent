<?php

namespace Vulpo\Cookies\Consent;

/**
 * One individually toggleable thing inside a category: a tool, an embed, a
 * script, a cookie the site sets itself.
 */
readonly class Service
{
    /**
     * @param  array<int, Cookie>  $declaration  the cookies this service sets
     * @param  string  $duration  the duration a declared cookie falls back to
     * @param  array<int, string>  $aliases  handles this service used to go by
     */
    public function __construct(
        public string $handle,
        public string $name,
        public string $category,
        public string $description = '',
        public bool $defaultOn = false,
        public bool $required = false,
        public string $position = 'body_end',
        public string $provider = '',
        public array $declaration = [],
        public string $duration = '',
        public string $script = '',
        public array $aliases = [],
    ) {}

    /**
     * The handles a stored consent may refer to this service by: its own, plus
     * any it was called before. Renaming a handle would otherwise void everyone's
     * consent for it silently.
     *
     * @return array<int, string>
     */
    public function handles(): array
    {
        return array_merge([$this->handle], $this->aliases);
    }

    /**
     * The DOM id of this service's inert script block, and of its toggle.
     */
    public function blockId(): string
    {
        return 'vulpo-cookies-service-'.$this->handle;
    }

    public function toggleId(): string
    {
        return 'vulpo-cookies-toggle-'.$this->handle;
    }

    /**
     * Whether this service is ticked before the visitor has decided anything.
     * A required service is always on and has no toggle to tick.
     */
    public function isPreTicked(): bool
    {
        return $this->required || $this->defaultOn;
    }

    /**
     * @return array<int, string>
     */
    public function cookieNames(): array
    {
        return array_map(fn (Cookie $cookie) => $cookie->name, $this->declaration);
    }

    /**
     * The declared names as one string, for a compact summary.
     */
    public function cookies(): string
    {
        return implode(', ', $this->cookieNames());
    }

    public function setsCookies(): bool
    {
        return $this->declaration !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'default_on' => $this->defaultOn,
            'required' => $this->required,
            'pre_ticked' => $this->isPreTicked(),
            'position' => $this->position,
            'provider' => $this->provider,
            'cookies' => $this->cookies(),
            'cookie_names' => $this->cookieNames(),
            'cookie_declaration' => array_map(fn (Cookie $cookie) => $cookie->toArray(), $this->declaration),
            'duration' => $this->duration,
            'block_id' => $this->blockId(),
            'toggle_id' => $this->toggleId(),
            'has_script' => $this->script !== '',
            'aliases' => $this->aliases,
        ];
    }
}
