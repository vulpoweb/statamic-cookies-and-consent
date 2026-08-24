# Contributing

Thanks for helping out.

## Getting set up

```bash
composer install
vendor/bin/pest
vendor/bin/pint
```

## Pull requests

- One change per pull request.
- Add a test. Bugs get a test that fails before the fix; features get a test for the behaviour, not the implementation.
- Run `vendor/bin/pint` before pushing.
- Note anything user-visible in `CHANGELOG.md` under "Unreleased".

## Reporting a bug

Include the Statamic and PHP version, whether the site uses static caching, and whether the consent view is the packaged one or your own. A failing test is the fastest possible bug report.

## Touching the front-end runtime

`resources/js/runtime.js` and `src/Consent/CookieCodec.php` write and read the same cookie payload, and no PHP test can execute the JavaScript. `tests/RuntimeTest.php` asserts on the runtime's source to catch the two drifting apart — if you change the payload, the cookie attributes, or the way script elements are rebuilt, update both sides and that test, and say in the pull request that you checked it in a browser.

## Adding a service template

Templates live in `Presets::definitions()` in `src/Presets/Presets.php`. Add the definition, then add the same key to the `preset` field's options in `resources/blueprints/settings.yaml` — a test asserts the two lists match, because one is PHP and the other is YAML.

Use `{id}` wherever the vendor's ID goes, and fill in `cookies` and `duration` from the vendor's own cookie declaration (leave `cookies` empty for a cookieless tool). `tests/PresetsTest.php` checks every template is described well enough to fill a cookie table.

## Adding a Consent Mode key

Google's keys live in `ConsentMode::KEYS` and in the `consent_mode` options of `resources/blueprints/settings.yaml`. A test asserts the two lists match, so add the key to both.
