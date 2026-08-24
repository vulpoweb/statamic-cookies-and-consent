<?php

namespace Vulpo\Cookies\Consent;

/**
 * One cookie a service sets, as declared to the visitor.
 *
 * The name may end in `*` for a family of cookies whose suffix the vendor
 * generates, e.g. `_ga_*` or `_hjSession_*`.
 */
readonly class Cookie
{
    public function __construct(
        public string $name,
        public string $purpose = '',
        public string $duration = '',
    ) {}

    public function isWildcard(): bool
    {
        return str_ends_with($this->name, '*');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'purpose' => $this->purpose,
            'duration' => $this->duration,
            'wildcard' => $this->isWildcard(),
        ];
    }
}
