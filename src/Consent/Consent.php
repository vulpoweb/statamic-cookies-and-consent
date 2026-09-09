<?php

namespace Vulpo\Cookies\Consent;

use Illuminate\Http\Request;
use Vulpo\Cookies\Support\Settings;

/**
 * The decision carried by the current request.
 *
 * Reading this makes a response visitor-specific, so it is deliberately only used
 * by `{{ vulpo_cookies:granted }}`. Everything the banner itself renders is the
 * same for every visitor and gets sorted out client-side by the runtime, which is
 * what keeps the addon safe behind a full-page or static cache.
 */
class Consent
{
    /** @var array<int, string>|null */
    private ?array $granted = null;

    private bool $decided = false;

    private bool $read = false;

    public function __construct(
        private readonly Request $request,
        private readonly Registry $registry,
    ) {}

    public function hasDecided(): bool
    {
        $this->read();

        return $this->decided;
    }

    /**
     * Whether the browser asked not to be tracked, through Global Privacy
     * Control or Do Not Track. Such a request counts as a rejection of
     * everything optional and is never interrupted by the banner.
     */
    public function isOptedOut(): bool
    {
        if (! Settings::bool('respect_gpc', (bool) config('cookies-and-consent.respect_gpc', true))) {
            return false;
        }

        return $this->request->header('Sec-GPC') === '1' || $this->request->header('DNT') === '1';
    }

    /**
     * @return array<int, string>
     */
    public function grantedHandles(): array
    {
        $this->read();

        return $this->granted ?? [];
    }

    /**
     * Whether a category or service handle is allowed to run.
     */
    public function granted(string $handle): bool
    {
        if (in_array($handle, $this->registry->requiredHandles(), true)) {
            return true;
        }

        $stored = $this->grantedHandles();

        if (in_array($handle, $stored, true)) {
            return true;
        }

        // A consent stored under a handle this service used to have still counts.
        $service = $this->registry->service($handle);

        return $service !== null && array_intersect($service->aliases, $stored) !== [];
    }

    private function read(): void
    {
        if ($this->read) {
            return;
        }

        $this->read = true;

        $stored = CookieCodec::decode($this->request->cookie(CookieCodec::name()));

        if ($stored === null) {
            $this->decided = $this->isOptedOut();
            $this->granted = $this->registry->requiredHandles();

            return;
        }

        // Grants made by allowing one blocked embed are not an answer to the
        // banner, so the visitor still gets asked.
        $this->decided = ! CookieCodec::isPending($this->request->cookie(CookieCodec::name()));
        $this->granted = array_values(array_unique(array_merge(
            $this->registry->requiredHandles(),
            $stored,
        )));
    }
}
