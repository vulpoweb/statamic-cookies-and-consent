<?php

namespace Vulpo\Cookies\Presets;

use Vulpo\Cookies\Consent\Cookie;

/**
 * A ready-made service: the vendor's snippet plus the cookie declaration that
 * goes with it, so an editor supplies an ID instead of pasting markup.
 *
 * Most tools need nothing but that ID. A few also need to know where to load
 * from — a HubSpot portal in the EU, a self-hosted Plausible, a Matomo server —
 * so a preset may declare one extra value, filled into the snippet as {option}
 * and defaulted to the vendor's own host where there is one.
 */
readonly class Preset
{
    /**
     * @param  string  $key  what the settings store
     * @param  string  $suggests  the kind of category this usually belongs in, for the docs
     * @param  string  $script  the snippet, with {id} where the vendor's ID goes and {option} for the extra value
     * @param  array<int, array{name: string, purpose?: string, duration?: string}>  $declaration
     *                                                                                             the cookies the vendor documents, each with its own lifetime
     * @param  string  $idLabel  what the vendor calls the ID
     * @param  string  $optionLabel  what the extra value is, empty when the preset needs none
     * @param  string  $optionDefault  the vendor's own value, empty when the editor has to supply it
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $provider,
        public string $suggests,
        public string $script,
        public array $declaration = [],
        public string $description = '',
        public string $idLabel = '',
        public string $idExample = '',
        public bool $requiresId = true,
        public string $optionLabel = '',
        public string $optionDefault = '',
    ) {}

    /**
     * @return array<int, Cookie>
     */
    public function cookies(): array
    {
        return array_map(
            fn (array $cookie) => new Cookie(
                name: $cookie['name'],
                purpose: $cookie['purpose'] ?? '',
                duration: $cookie['duration'] ?? '',
            ),
            $this->declaration,
        );
    }

    /**
     * Whether this preset takes a second value beyond the ID.
     */
    public function usesOption(): bool
    {
        return $this->optionLabel !== '';
    }

    /**
     * The snippet with the editor's values filled in, or an empty string when
     * something needed is missing. A half-built vendor snippet is worse than
     * none: it would run and fail on every page.
     *
     * The option lands before the ID, so a preset may point {option} at a host
     * and still put the ID in the path behind it.
     */
    public function script(string $id, string $option = ''): string
    {
        $id = trim($id);

        if ($this->requiresId && $id === '') {
            return '';
        }

        $option = trim($option) ?: $this->optionDefault;

        if ($this->usesOption() && $option === '') {
            return '';
        }

        return str_replace('{id}', $id, str_replace('{option}', $option, $this->script));
    }

    public function isCookieless(): bool
    {
        return $this->declaration === [];
    }
}
