# Cookies & Consent

Cookie consent for Statamic. You decide what the categories are, and the visitor decides one service at a time.

Most consent addons ship a fixed set of categories and one script box per category. This addon lets you define as many categories as you need. Inside each category you can add any number of services, and each service can be toggled on or off on its own. A visitor can allow analytics in general but still refuse one specific tool.

## Features

**Unlimited categories.** Add, rename, and reorder them in the control panel. Mark a category as always-on if the site cannot work without it. Always-on categories render as a locked toggle.

**A toggle per service.** Every service inside a category is its own row with its own script, description, and toggle. A category with a single service behaves like a plain category, so you can start simple.

**Safe to cache.** Every third-party script is rendered inside an inert `<template>` and moved into the document by the runtime once it is allowed. The HTML is the same for every visitor, so static and full-page caching keep working. Nothing is read from the request to build the page.

**Google Consent Mode v2.** gtag.js is loaded denied by default and updated from the visitor's choice. You map each of your categories onto the gtag storage keys it should unlock, so you keep control of the mapping.

**Privacy signals.** A browser sending Global Privacy Control or Do Not Track is treated as having refused everything optional. The banner does not interrupt it.

**Editors can gate a block.** A `consent_service` fieldtype lists the configured services. A page-builder block can be tied to one service without a developer editing a template.

**Nothing to build.** The front-end runtime is a single dependency-free script inlined into the page. There is no Vite entry, no published assets, and no `npm run build` after an update.

**Your markup.** The packaged banner is plain on purpose. Publish it and restyle it in Blade or Antlers, or render your own from `{{ vulpo_cookies:categories }}` and keep only the data attributes.

**Blocked embeds.** Wrap a YouTube, Vimeo, or Maps embed in a tag and it is held back behind a placeholder until the visitor allows it. The markup never reaches the document, so the vendor is not contacted at all.

**Cookies deleted on withdrawal.** Switch a service off and the cookies it declared are removed, including wildcards. If a visitor refuses a tool, its cookies should not stay behind.

**Templates for common vendors.** Pick Google Analytics 4, Meta Pixel, Hotjar, and others from a list, then type the ID. The snippet, the cookie names, and the duration come with it. The vendors you can self-host or that are region-specific (Matomo, Plausible, Fathom, HubSpot) take a URL as well.

**Re-ask on demand.** Raise the revision after adding a service and every stored decision becomes invalid. You do not need to know what anyone answered before.

## Installation

```bash
composer require vulpo/cookies-and-consent
```

To work against a local checkout, add a path repository first:

```json
"repositories": [
    { "type": "path", "url": "../vulpo-cookies" }
]
```

Then drop the tag in your layout, just before `</body>`:

```antlers
{{ vulpo_cookies }}
```

Settings live in the control panel under **Tools → Cookies**. The addon adds itself there and removes Statamic's own entry under Tools → Addons, so the screen appears only once. Categories and services collapse, so a long configuration stays scannable. Add your categories and services there. Nothing renders until at least one category exists.

To let people change their mind later, point a link at the preferences panel:

```antlers
<button type="button" onclick="{{ vulpo_cookies:open_preferences }}">Cookie preferences</button>
```

## Tags

| Tag | Output |
|---|---|
| `{{ vulpo_cookies }}` | The banner, the preferences panel, the runtime, and every inert service block. |
| `{{ vulpo_cookies:banner }}` | The same thing, named for clarity in a layout. |
| `{{ vulpo_cookies:scripts }}` | Only the runtime and the service blocks, for a project rendering its own UI. |
| `{{ vulpo_cookies:categories }}` | Loops the categories, each with a `services` array. |
| `{{ vulpo_cookies:declaration }}` | Loops every declared cookie, one row per cookie, for a privacy page. |
| `{{ vulpo_cookies:embed handle="…" }}…{{ /vulpo_cookies:embed }}` | Holds an embed back until that service is allowed. |
| `{{ vulpo_cookies:granted handle="ga4" }}` | Whether a category or service is allowed on this request. |
| `{{ vulpo_cookies:has_decided }}` | Whether this visitor has answered. |
| `{{ vulpo_cookies:open_preferences }}` | JS to open the preferences panel, for an `onclick`. |
| `{{ vulpo_cookies:reset }}` | JS to forget the decision and ask again. |
| `{{ vulpo_cookies:cookie_name }}` | The name of the consent cookie. |

`granted` and `has_decided` read the request, so a page using them is specific to one visitor and must not be cached. Everything else is cache-safe.

## Gating a page-builder block

In a set or entry blueprint, a `consent_service` field lists whatever services are configured:

```yaml
-
  handle: service
  field:
    type: consent_service
    display: 'Needs consent for'
    max_items: 1
    mode: select
```

The stored value is the service handle, so the set's view is a one-liner:

```antlers
{{ vulpo_cookies:embed :handle="service" }}
    {{ embed_code }}
{{ /vulpo_cookies:embed }}
```

An editor picks a service such as Google Analytics 4 from a dropdown and the block is gated. The list comes from the control panel's own relationship endpoint, so it is never stale. The addon ships no JavaScript for it.

## Blocked embeds

An iframe cannot be held back with CSS. Whether it is hidden or not, it loads, and the vendor sets its cookies. Wrap it instead:

```antlers
{{ vulpo_cookies:embed handle="youtube" }}
    <iframe src="https://www.youtube-nocookie.com/embed/{{ video_id }}" title="{{ title }}"></iframe>
{{ /vulpo_cookies:embed }}
```

Until `youtube` is allowed, the markup sits in an inert `<template>` and a placeholder takes its place. The placeholder offers a button that allows just that service. The embed then appears in place, with no page reload, because granting takes nothing back.

Publish `vulpo-cookies::embed` to restyle the placeholder. Two things worth knowing:

- **Allowing an embed is not an answer to the banner.** The grant is stored as pending, so the visitor still gets asked and the banner stays until they answer. Answering the banner normally clears the pending state.
- **A handle nobody configured stays blocked.** If a handle has a typo or is not configured, the placeholder shows instead of the embed. A consent gate should fail closed.

## Cookie declarations, and deleting them again

A service can list its cookies one per row, each with its own purpose and lifetime, because that is how vendors document them. For example, `_ga` lasts 2 years and `__hssc` lasts 30 minutes. For cookies that all last as long, the **Cookie names** shorthand plus one **Default duration** is enough. Any row that leaves duration empty falls back to it. Templates ship real per-cookie rows.

That declaration does two jobs.

**It renders your privacy page.** `{{ vulpo_cookies:declaration }}` gives one row per cookie:

```antlers
<table>
    {{ vulpo_cookies:declaration }}
        <tr>
            <td>{{ name }}</td>
            <td>{{ purpose }}</td>
            <td>{{ provider }}</td>
            <td>{{ duration }}</td>
            <td>{{ category_name }}</td>
        </tr>
    {{ /vulpo_cookies:declaration }}
</table>
```

**It gets the cookies deleted.** When a visitor switches a service off, the runtime removes every cookie it declared: exact names and `prefix*` families. It tries each parent domain, since vendors usually set cookies on the registrable domain. Two limits are worth stating plainly. A script cannot touch `HttpOnly` cookies, and it cannot touch cookies set on the vendor's own domain. Everything reachable from the page is removed.

Always-on categories are never cleaned, and the consent cookie is never deleted by this.

### Renaming a handle

A stored consent refers to a service by its handle, so renaming one would quietly void everyone's consent for it. Put the old handle in **Previous handles** and consent already given still counts. An alias that collides with a live handle is ignored, so it can never hijack another service.

## Service templates

A service can be built from a template instead of a pasted snippet. Choose one in the **Template** field, put the vendor's ID in **Tool ID**, and leave the rest empty. The snippet, provider, cookie names, and duration are filled in from the template.

| Template | ID it needs | Usually goes in | Cookies |
|---|---|---|---|
| Google Analytics 4 | Measurement ID (`G-XXXXXXXXXX`) | analytics | `_ga`, `_ga_*` |
| Google Tag Manager | Container ID (`GTM-XXXXXXX`) | analytics | `_ga`, `_ga_*`, `_gcl_au` |
| Google Ads | Conversion ID (`AW-123456789`) | marketing | `_gcl_au`, `_gcl_aw` |
| Meta Pixel (Facebook) | Pixel ID | marketing | `_fbp` |
| Microsoft Clarity | Project ID | analytics | `_clck`, `_clsk` |
| Hotjar | Site ID | analytics | `_hjSessionUser_*`, `_hjSession_*` |
| LinkedIn Insight Tag | Partner ID | marketing | `li_sugr`, `bcookie`, `lidc`, … |
| TikTok Pixel | Pixel ID | marketing | `_ttp` |
| HubSpot | Portal ID | marketing | `__hstc`, `hubspotutk`, `__hssc`, `__hssrc` |
| Plausible Analytics | your domain | analytics | none |
| Fathom Analytics | Site ID | analytics | none |
| Cloudflare Web Analytics | Beacon token | analytics | none |
| Matomo | Site ID | analytics | `_pk_id.*`, `_pk_ses.*` |

### Templates that load from somewhere else

Four templates can be pointed elsewhere through the **Loaded from** field, which appears once you pick one. Leave it empty and the vendor's own default is used. Matomo is the exception: you host it yourself, so it has no default.

| Template | Loaded from | Default |
|---|---|---|
| HubSpot | loader host | `js.hs-scripts.com`. EU portals need `js-eu1.hs-scripts.com`, Asia-Pacific `js-ap1.hs-scripts.com` |
| Plausible Analytics | script URL | `https://plausible.io/js/script.js`. Point it at your own instance, or at one of Plausible's script variants |
| Fathom Analytics | script URL | `https://cdn.usefathom.com/script.js`. Point it at your custom domain |
| Matomo | server URL | none. For example `https://analytics.example.com/`, with or without the trailing slash |

The ID stays a field of its own in every case, so a portal or site ID is never entered twice.

Anything you type wins over the template, field by field. You can rename it, narrow the cookie list, or paste a script to replace the snippet entirely while keeping the rest. Choose **Custom** for a service that has no template.

Two things to know:

- **Nothing loads until the ID is filled in.** For Matomo, nothing loads until **Loaded from** is filled in either. A template that is missing something renders no script at all, so half a vendor snippet does not run and fail on every page.
- **The cookie names and durations are a starting point.** They are what these vendors documented when the template was written, and vendors change them without notice. Check them against the vendor's current cookie declaration before you rely on them in a privacy policy.

"Usually goes in" is a hint for you, not a rule the addon enforces. You decide which category a service lives in, and the Consent Mode keys are set per category.

New templates live in `src/Presets/Presets.php`. A pull request adding one is welcome.

## Rendering your own consent UI

Publish the packaged view and edit it:

```bash
php artisan vendor:publish --tag=vulpo-cookies-views
```

It lands in `resources/views/vendor/vulpo-cookies/`. Either extension works. Rename it to `consent.antlers.html` if you would rather write Antlers.

The runtime finds everything by data attribute, so a replacement only has to keep these:

| Attribute | On |
|---|---|
| `data-vulpo-cookies-root`, `-banner`, `-preferences` | the containers |
| `data-vulpo-cookies-action="accept-all\|reject-all\|save\|open\|close\|reset"` | a button |
| `data-vulpo-cookies-category="<handle>"` | a category checkbox |
| `data-vulpo-cookies-toggle="<handle>"` + `data-vulpo-cookies-category-of="<category>"` | a service checkbox |
| `data-vulpo-cookies-count="<category>"` | anything that should read `1/2` as the visitor ticks |

Ticking a category ticks everything in it, and a partly-ticked category shows as indeterminate. That is handled for you.

### Collapsing categories

The packaged view puts each category in a `<details>`, which collapses with no JavaScript and no CSS.

A styled view usually wants its own accordion. Each category exposes `panel_id` and `service_count`, so the trigger and the panel can be wired up:

```antlers
{{ categories }}
    <button type="button" x-on:click="expanded = !expanded"
            x-bind:aria-expanded="expanded ? 'true' : 'false'"
            aria-controls="{{ panel_id }}">
        {{ name }}: <span data-vulpo-cookies-count="{{ handle }}">0/{{ service_count }}</span> allowed
    </button>

    <div id="{{ panel_id }}" x-show="expanded" x-collapse>
        {{ services }} ... {{ /services }}
    </div>
{{ /categories }}
```

A collapsed category hides its service toggles, so the runtime writes the allowed count into every `data-vulpo-cookies-count` element and keeps it current as the visitor ticks. Add `data-vulpo-cookies-count-format=":allowed of :total"` to change the wording.

If your view drives its own visibility (Alpine, a transition library, or anything else), leave the container attributes off and listen for the events instead:

| Event | Detail |
|---|---|
| `vulpo-cookies:ready` | `{ decided, granted }`. The runtime has read the cookie |
| `vulpo-cookies:mode` | `{ mode }`. One of `'banner'`, `'preferences'`, or `null` |
| `vulpo-cookies:changed` | `{ granted }`. A decision was just saved |

`window.vulpoCookies` exposes `open()`, `close()`, `acceptAll()`, `rejectAll()`, `save()`, `reset()`, `allow(handle)`, `granted(handle)`, `grantedHandles()`, and `hasDecided()`.

Two more attributes are optional. `data-vulpo-cookies-status` marks an `aria-live` region the runtime writes the save confirmation into, and `data-vulpo-cookies-count="<category>"` gets `1/2` as the visitor ticks.

### Linking to it, and debugging it

| URL | Does |
|---|---|
| `?cookie-preferences` or `#cookie-preferences` | Opens the preferences panel on load, so a privacy page can link to it instead of wiring an `onclick`. |
| `?vulpo-cookies-debug=1` | Logs every decision to the console: what was granted, which scripts and embeds were activated, which cookies were deleted, and what gtag received. |

### When it reloads

Saving a choice reloads the page only when something that already ran is being taken away. A script cannot be un-run and an embed cannot be un-loaded. A first "Accept all", or widening an earlier choice, settles in place with no reload.

## Migrating from alt-design/alt-cookies

There is no automatic migration. The two data models do not line up, and the old one has no service concept at all. Doing it by hand takes about five minutes.

1. Open the old settings in `content/alt-cookies/settings.yaml`.
2. Create a category per old field group (Necessary, Analytics, Advertising) and mark Necessary as always-on.
3. Each old script blob becomes one service inside its category. Split it up if it contained more than one tool. That is the point of the upgrade.
4. `enable_analytics_default` becomes the service's **Pre-ticked in the banner** toggle.
5. `cookie_lifetime` becomes **Remember the choice for (days)**.
6. `enable_google` and `google_tag_id` move to the Google tab. The old addon hardcoded which categories mapped to which gtag keys. Now you set that per category.
7. Replace `{{ AltCookies:Toast }}` with `{{ vulpo_cookies }}` and `{{ AltCookies:reset }}` with `{{ vulpo_cookies:open_preferences }}`. The old reset erased the decision and reloaded. The new one opens the panel with the current choice still in it.
8. Remove `alt-design/alt-cookies` from `composer.json`, along with any `.alt-cookies-hidden` style shim.

Old decisions are not carried over. Visitors are asked once more, which is expected when you change what they are being asked.

## Configuration

```bash
php artisan vendor:publish --tag=cookies-config
```

Note the name. The config file is `config/cookies.php` and the key is `cookies.*`, both derived from the package slug. Environment variables are prefixed `VULPO_COOKIES_`. Do not give the addon a custom `extra.statamic.slug`, because Statamic looks up both the config and the settings file by slug.

| Key | Does |
|---|---|
| `cookies.cookie.name` | The consent cookie's name. Changing it forgets every decision. |
| `cookies.cookie.lifetime_days` | Fallback for the control panel setting. |
| `cookies.cookie.same_site` | `lax`, `strict`, or `none`. |
| `cookies.cookie.domain` | Set to `.example.com` to share one decision across subdomains. `VULPO_COOKIES_COOKIE_DOMAIN`. |
| `cookies.runtime.inline` | Turn off only if you ship your own `window.vulpoCookies`. |
| `cookies.respect_gpc` | Default for the control panel toggle. |
| `cookies.consent_mode.wait_for_update` | Milliseconds gtag waits for an update. |

## Where data lives

| What | Where |
|---|---|
| Categories, services, wording | `resources/addons/cookies.yaml`, from the control panel |
| The visitor's decision | The `vulpo_cookies` cookie, in the clear so both the browser and the server can read it |

Nothing is logged. The addon stores no record of who consented to what.

## Notes and limits

- **The consent cookie is exempt from Laravel's cookie encryption.** It has to be. The runtime writes it in the browser, and Laravel discards any cookie it cannot decrypt. The addon registers the exemption itself. The cookie holds no personal data, only a revision, a timestamp, and the handles that were allowed.
- **Service scripts are injected exactly as pasted.** That is what the field is for. It also means anyone who can edit addon settings can run JavaScript on every page of the site. Treat it as a trusted field.
- Scripts are read unparsed, so a snippet containing `{{ ... }}` survives. Everything else in the settings goes through Antlers, as Statamic does for all addon settings.
- **Saving a choice reloads the page** when a service the visitor has just withdrawn may already have run. Only a fresh document undoes that.
- Google's `gtag.js` is fetched before consent, in its denied-by-default state, which is how Consent Mode is designed to work. If you would rather not contact Google at all until consent, leave the Google tab off and add gtag as an ordinary service instead.
- **No consent log.** If you need auditable proof of consent, this addon does not provide it.
- The banner needs JavaScript. Without it, no third-party script runs at all, which is the safe direction to fail in.

## Translations

Every string goes through `__()`. Publish the English file and translate it, or drop your own `{locale}.json` beside it:

```bash
php artisan vendor:publish --tag=vulpo-cookies-translations
```

Files land in `lang/vendor/vulpo-cookies/`.

## Permissions

The addon adds a **Cookies** item to the Tools section of the sidebar, pointing at its settings screen. An editor does not have to go through Tools → Addons to change what the banner offers.

It registers no permission of its own. The item and the screen are both gated on Statamic's addon settings authorization.

| Permission | Allows |
|---|---|
| `configure addons` | Everything, including these settings |
| `edit vulpo/cookies-and-consent settings` | Only this addon's settings |

## Blueprint

To change the fields themselves, publish the blueprint:

```bash
php artisan vendor:publish --tag=vulpo-cookies-blueprints
```

It lands in `resources/blueprints/vendor/vulpo-cookies/settings.yaml`. Keep the handles the code reads (`categories`, and inside it `handle`, `name`, `required`, `services`, `default_on`, `position`, `script`) or the registry will not find them.

## Testing

```bash
composer install
vendor/bin/pest
vendor/bin/pint
```

## Credits

Built by [Vulpo](https://vulpo.be). Replaces `alt-design/alt-cookies`.

## License

MIT. See [LICENSE.md](LICENSE.md).
