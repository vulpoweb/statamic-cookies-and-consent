<?php

namespace Vulpo\Cookies\Consent;

/**
 * A group of services. The category itself is what Google Consent Mode keys are
 * mapped to; the services inside it are what the visitor toggles.
 */
readonly class Category
{
    /**
     * @param  array<int, Service>  $services
     * @param  array<int, string>  $consentMode
     */
    public function __construct(
        public string $handle,
        public string $name,
        public string $description = '',
        public bool $required = false,
        public array $services = [],
        public array $consentMode = [],
    ) {}

    public function toggleId(): string
    {
        return 'vulpo-cookies-category-'.$this->handle;
    }

    /**
     * The id of the panel a collapsible category shows and hides, so a view can
     * wire up `aria-controls` without inventing its own naming.
     */
    public function panelId(): string
    {
        return 'vulpo-cookies-panel-'.$this->handle;
    }

    public function serviceCount(): int
    {
        return count($this->services);
    }

    /**
     * @return array<int, string>
     */
    public function serviceHandles(): array
    {
        return array_map(fn (Service $service) => $service->handle, $this->services);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'description' => $this->description,
            'required' => $this->required,
            'consent_mode' => $this->consentMode,
            'toggle_id' => $this->toggleId(),
            'panel_id' => $this->panelId(),
            'service_count' => $this->serviceCount(),
            'services' => array_map(fn (Service $service) => $service->toArray(), $this->services),
        ];
    }
}
