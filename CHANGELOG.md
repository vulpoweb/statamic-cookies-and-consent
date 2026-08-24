# Changelog

## Unreleased

Initial release.

- Unlimited consent categories, each holding any number of individually toggleable services, configured in the control panel under Tools → Vulpo Cookies.
- Third-party scripts render inside inert `<template>` blocks and are moved into the document by the front-end runtime once they are allowed, so the HTML is identical for every visitor and safe behind static and full-page caching.
- Google Consent Mode v2: gtag.js loads denied-by-default and is updated from the visitor's choice, with the gtag storage keys mapped per category rather than hardcoded.
- Global Privacy Control and Do Not Track are honoured as a refusal of everything optional, and such a visitor is not shown the banner.
- `{{ vulpo_cookies }}` renders the banner, the preferences panel, the runtime and the service blocks. `{{ vulpo_cookies:categories }}`, `{{ vulpo_cookies:granted }}`, `{{ vulpo_cookies:open_preferences }}` and `{{ vulpo_cookies:reset }}` are there for projects rendering their own UI.
- The packaged consent view is publishable and can be replaced in Blade or Antlers; the runtime binds to data attributes and also dispatches `vulpo-cookies:ready`, `:mode` and `:changed` so a view can drive its own transitions.
- The consent cookie holds a revision, a timestamp and the granted handles. Raising the revision in the control panel invalidates every stored decision and re-asks.
- The front-end runtime is one dependency-free inlined script. There is nothing to build and no asset to publish.
- Saving a choice only reloads the page when something that already ran is being withdrawn. Granting settles in place.
- `?cookie-preferences` (or `#cookie-preferences`) opens the preferences panel on load, so a privacy page can link to it; `?vulpo-cookies-debug=1` narrates every decision, activation and deletion in the console.
- A `consent_service` fieldtype lists the configured services, so an editor can gate a page-builder block on consent without a developer touching a template. Built on Statamic's relationship fieldtype, so it ships no JavaScript.
- `cookies.cookie.domain` shares one decision across subdomains.
- **Previous handles** per service: renaming a handle no longer voids consent already given under the old one. An alias that collides with a live handle is ignored.
- The packaged preferences panel keeps Tab inside itself, closes on Escape, hands focus back to whatever opened it, and announces the saved confirmation through an `aria-live` region.
- `{{ vulpo_cookies:embed }}` holds an iframe or widget back behind a placeholder until its service is allowed, then drops it into place without reloading. Allowing an embed is stored as a pending grant, so the banner still asks its question rather than being dismissed by one click.
- Withdrawing a service now deletes the cookies it declared, exact names and `prefix*` families, across the parent domains a vendor is likely to have used. `HttpOnly` cookies and cookies on the vendor's own domain are out of reach of any script and are documented as such.
- Cookies are declared one per row, each with its own purpose and lifetime, with the old single duration kept as the fallback for rows that omit one. `{{ vulpo_cookies:declaration }}` renders the lot as a flat list for a privacy page, and the bundled templates ship real per-cookie lifetimes.
- Service templates for the tools most sites load — Google Analytics 4, Google Tag Manager, Google Ads, Meta Pixel, Microsoft Clarity, Hotjar, LinkedIn Insight, TikTok Pixel, HubSpot, Plausible, Fathom, Cloudflare Web Analytics and Matomo. Pick one, supply the vendor's ID, and the snippet, provider, cookie names and duration are filled in; anything typed into the row overrides the template, field by field. A template with no ID renders no script rather than a broken snippet.
- Categories collapse: the packaged view uses `<details>`, and a custom view gets `panel_id` plus `service_count` per category. The runtime writes the allowed count into any `data-vulpo-cookies-count` element and keeps it current, so a collapsed header still says how much of the category is allowed.
- Categories and the services inside them are replicators rather than grids, so their rows collapse. A settings screen that used to run to three screens now fits on one, with each row previewing just its handle and name.
- A **Cookies** item in the Tools section of the control panel sidebar, pointing straight at the settings screen and gated on Statamic's addon settings authorization rather than a permission of its own. Core's own entry for the addon — nested under Tools → Addons — is removed, so the same screen is not offered twice.
- Templates that are not served from one fixed place take a **Loaded from** value beside the ID, shown only for the templates that read it: a HubSpot loader host, so an EU or Asia-Pacific portal is not left with a dead script; a Plausible or Fathom script URL, for self-hosting, a proxy or a script variant; and the Matomo server URL, which has no default and holds the snippet back until it is filled in. The ID stays its own field, so it is never spelled twice.
- Every string goes through `__()`, with `lang/en.json` shipped and publishable.
