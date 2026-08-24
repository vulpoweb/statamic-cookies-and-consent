<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Consent cookie
    |--------------------------------------------------------------------------
    |
    | Where the visitor's decision is stored. Which categories and services
    | exist is controlled from the control panel (Tools → Cookies); this only
    | decides how the decision itself is persisted.
    |
    | Bumping the revision in the control panel invalidates every stored
    | decision, which is how you re-ask everyone after adding a service.
    |
    */

    'cookie' => [
        'name' => 'vulpo_cookies',

        'lifetime_days' => 180,

        // Lax keeps the decision on cross-site navigation to your own site
        // while staying out of third-party contexts. 'strict' and 'none' work
        // too; 'none' only ever makes sense on https.
        'same_site' => 'lax',

        // Set this to share one decision across subdomains, e.g. '.example.com'
        // so www and shop do not each ask. Left empty the cookie belongs to the
        // exact host that set it.
        'domain' => env('VULPO_COOKIES_COOKIE_DOMAIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime
    |--------------------------------------------------------------------------
    |
    | The front-end runtime is a single dependency-free script inlined into the
    | page, so there is nothing to build or publish. Turn it off only if you
    | ship your own implementation of the window.vulpoCookies API.
    |
    */

    'runtime' => [
        'inline' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy signals
    |--------------------------------------------------------------------------
    |
    | With this on, a browser sending Global Privacy Control or Do Not Track is
    | treated as having rejected everything optional, and the banner does not
    | interrupt them. The control panel toggle wins over this default.
    |
    */

    'respect_gpc' => true,

    /*
    |--------------------------------------------------------------------------
    | Google Consent Mode
    |--------------------------------------------------------------------------
    |
    | The tag ID and which storage keys each category unlocks are control panel
    | settings. This is the grace period, in milliseconds, that gtag.js waits
    | for an update before it decides with the defaults.
    |
    */

    'consent_mode' => [
        'wait_for_update' => 500,
    ],

];
