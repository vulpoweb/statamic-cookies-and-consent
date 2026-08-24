<?php

namespace Vulpo\Cookies\Fieldtypes;

use Illuminate\Support\Collection;
use Statamic\Fieldtypes\Relationship;
use Vulpo\Cookies\Consent\Registry;
use Vulpo\Cookies\Consent\Service;

/**
 * Picks one of the configured services, by handle.
 *
 * Put it in an entry or set blueprint and an editor can gate a block on consent
 * — a video, a map, an embedded form — without anyone touching a template. It
 * extends Statamic's relationship fieldtype so the options come from the control
 * panel's own endpoint: no bundled JavaScript, and the list is always whatever is
 * configured right now.
 */
class ConsentService extends Relationship
{
    protected static $handle = 'consent_service';

    protected $categories = ['relationship'];

    protected $canEdit = false;

    protected $canCreate = false;

    protected $canSearch = true;

    protected $statusIcons = false;

    protected $indexComponent = 'text';

    protected function configFieldItems(): array
    {
        return [
            'max_items' => [
                'display' => __('Maximum services'),
                'type' => 'integer',
                'default' => 1,
                'instructions' => __('Usually one: the service that has to be allowed before this block loads.'),
            ],
            'mode' => [
                'display' => __('UI mode'),
                'type' => 'select',
                'default' => 'select',
                'options' => [
                    'select' => __('Dropdown'),
                    'typeahead' => __('Typeahead'),
                ],
            ],
        ];
    }

    public function toItemArray($id): array
    {
        if ($service = $this->registry()->service($id)) {
            return [
                'id' => $service->handle,
                'title' => $this->label($service),
            ];
        }

        // A handle whose service was renamed or removed. Showing it as missing is
        // better than dropping it silently: the block is gated on something that
        // no longer exists, and somebody has to notice.
        return $this->invalidItemArray($id);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    public function getIndexItems($request)
    {
        return collect($this->registry()->services())->map(fn (Service $service) => [
            'id' => $service->handle,
            'title' => $this->label($service),
        ])->values();
    }

    private function label(Service $service): string
    {
        $category = $this->registry()->category($service->category);

        return $category === null
            ? $service->name
            : $service->name.' — '.$category->name;
    }

    private function registry(): Registry
    {
        return app(Registry::class);
    }
}
