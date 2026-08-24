<?php

namespace Vulpo\Cookies\Presets;

/**
 * The bundled service presets.
 *
 * These cover the tools most sites actually load. Each carries the vendor's own
 * snippet and the cookies it sets, so a service row needs an ID and nothing else
 * — and the cookie table on a privacy page is filled in for free.
 *
 * The cookie names and lifetimes are what these vendors document at the time of
 * writing. Vendors change them without notice, so treat them as a starting point
 * and check them against the vendor's current cookie declaration before you rely
 * on them in a legal document.
 */
class Presets
{
    public const CUSTOM = 'custom';

    /** @var array<string, Preset>|null */
    private static ?array $presets = null;

    /**
     * @return array<string, Preset>
     */
    public static function all(): array
    {
        return self::$presets ??= self::build();
    }

    public static function find(?string $key): ?Preset
    {
        if ($key === null || $key === '' || $key === self::CUSTOM) {
            return null;
        }

        return self::all()[$key] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * The presets that take a second value, in catalogue order. The settings
     * blueprint shows its extra field only for these.
     *
     * @return array<int, string>
     */
    public static function keysWithOption(): array
    {
        return array_keys(array_filter(self::all(), fn (Preset $preset) => $preset->usesOption()));
    }

    /**
     * Options for the settings blueprint's preset field, "custom" first because
     * that is what a hand-written service is.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [self::CUSTOM => 'Custom — paste your own snippet'];

        foreach (self::all() as $preset) {
            $options[$preset->key] = $preset->name;
        }

        return $options;
    }

    /**
     * @return array<string, Preset>
     */
    private static function build(): array
    {
        $presets = [];

        foreach (self::definitions() as $definition) {
            $preset = new Preset(...$definition);

            $presets[$preset->key] = $preset;
        }

        return $presets;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function definitions(): array
    {
        return [
            [
                'key' => 'google_analytics_4',
                'name' => 'Google Analytics 4',
                'provider' => 'Google',
                'suggests' => 'analytics',
                'idLabel' => 'Measurement ID',
                'idExample' => 'G-XXXXXXXXXX',
                'description' => 'Counts visits and shows which pages are read.',
                'declaration' => [
                    ['name' => '_ga', 'purpose' => 'Tells visitors apart', 'duration' => '2 years'],
                    ['name' => '_ga_*', 'purpose' => 'Keeps the session state for this property', 'duration' => '2 years'],
                ],
                'script' => <<<'HTML'
                    <script async src="https://www.googletagmanager.com/gtag/js?id={id}"></script>
                    <script>
                      window.dataLayer = window.dataLayer || [];
                      function gtag(){dataLayer.push(arguments);}
                      gtag('js', new Date());
                      gtag('config', '{id}');
                    </script>
                    HTML,
            ],
            [
                'key' => 'google_tag_manager',
                'name' => 'Google Tag Manager',
                'provider' => 'Google',
                'suggests' => 'analytics',
                'idLabel' => 'Container ID',
                'idExample' => 'GTM-XXXXXXX',
                'description' => 'Loads the other tags configured in your Tag Manager container.',
                'declaration' => [
                    ['name' => '_ga', 'purpose' => 'Tells visitors apart', 'duration' => '2 years'],
                    ['name' => '_ga_*', 'purpose' => 'Keeps the session state for this property', 'duration' => '2 years'],
                    ['name' => '_gcl_au', 'purpose' => 'Measures ad clicks that led to this site', 'duration' => '3 months'],
                ],
                'script' => <<<'HTML'
                    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{id}');</script>
                    HTML,
            ],
            [
                'key' => 'google_ads',
                'name' => 'Google Ads',
                'provider' => 'Google',
                'suggests' => 'marketing',
                'idLabel' => 'Conversion ID',
                'idExample' => 'AW-123456789',
                'description' => 'Measures which ads led to a visit or a sale.',
                'declaration' => [
                    ['name' => '_gcl_au', 'purpose' => 'Measures ad clicks that led to this site', 'duration' => '3 months'],
                    ['name' => '_gcl_aw', 'purpose' => 'Remembers the ad click a visit came from', 'duration' => '3 months'],
                ],
                'script' => <<<'HTML'
                    <script async src="https://www.googletagmanager.com/gtag/js?id={id}"></script>
                    <script>
                      window.dataLayer = window.dataLayer || [];
                      function gtag(){dataLayer.push(arguments);}
                      gtag('js', new Date());
                      gtag('config', '{id}');
                    </script>
                    HTML,
            ],
            [
                'key' => 'meta_pixel',
                'name' => 'Meta Pixel (Facebook)',
                'provider' => 'Meta',
                'suggests' => 'marketing',
                'idLabel' => 'Pixel ID',
                'idExample' => '123456789012345',
                'description' => 'Measures advertising on Facebook and Instagram.',
                'declaration' => [
                    ['name' => '_fbp', 'purpose' => 'Identifies the browser for ad measurement', 'duration' => '3 months'],
                ],
                'script' => <<<'HTML'
                    <script>
                      !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
                      fbq('init', '{id}');
                      fbq('track', 'PageView');
                    </script>
                    HTML,
            ],
            [
                'key' => 'microsoft_clarity',
                'name' => 'Microsoft Clarity',
                'provider' => 'Microsoft',
                'suggests' => 'analytics',
                'idLabel' => 'Project ID',
                'idExample' => 'abcdefghij',
                'description' => 'Records how visitors move through pages, to find what is confusing.',
                'declaration' => [
                    ['name' => '_clck', 'purpose' => 'Keeps one Clarity user ID for this site', 'duration' => '1 year'],
                    ['name' => '_clsk', 'purpose' => 'Joins the page views of one session together', 'duration' => '1 day'],
                ],
                'script' => <<<'HTML'
                    <script>
                      (function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script","{id}");
                    </script>
                    HTML,
            ],
            [
                'key' => 'hotjar',
                'name' => 'Hotjar',
                'provider' => 'Hotjar',
                'suggests' => 'analytics',
                'idLabel' => 'Site ID',
                'idExample' => '1234567',
                'description' => 'Heatmaps and session recordings.',
                'declaration' => [
                    ['name' => '_hjSessionUser_*', 'purpose' => 'Keeps one Hotjar user ID for this site', 'duration' => '1 year'],
                    ['name' => '_hjSession_*', 'purpose' => 'Holds the current session data', 'duration' => '30 minutes'],
                ],
                'script' => <<<'HTML'
                    <script>
                      (function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};h._hjSettings={hjid:{id},hjsv:6};a=o.getElementsByTagName('head')[0];r=o.createElement('script');r.async=1;r.src=t+h._hjSettings.hjid+j;a.appendChild(r);})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
                    </script>
                    HTML,
            ],
            [
                'key' => 'linkedin_insight',
                'name' => 'LinkedIn Insight Tag',
                'provider' => 'LinkedIn',
                'suggests' => 'marketing',
                'idLabel' => 'Partner ID',
                'idExample' => '1234567',
                'description' => 'Measures advertising on LinkedIn.',
                'declaration' => [
                    ['name' => 'li_sugr', 'purpose' => 'Guesses the visitor for ad measurement', 'duration' => '3 months'],
                    ['name' => 'bcookie', 'purpose' => 'Identifies the browser to LinkedIn', 'duration' => '1 year'],
                    ['name' => 'lidc', 'purpose' => 'Routes the request to a LinkedIn data centre', 'duration' => '1 day'],
                    ['name' => 'UserMatchHistory', 'purpose' => 'Syncs LinkedIn ad identifiers', 'duration' => '30 days'],
                    ['name' => 'AnalyticsSyncHistory', 'purpose' => 'Records when the sync last ran', 'duration' => '30 days'],
                ],
                'script' => <<<'HTML'
                    <script>
                      _linkedin_partner_id = "{id}";
                      window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
                      window._linkedin_data_partner_ids.push(_linkedin_partner_id);
                      (function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}var s=document.getElementsByTagName("script")[0];var b=document.createElement("script");b.type="text/javascript";b.async=true;b.src="https://snap.licdn.com/li.lms-analytics/insight.min.js";s.parentNode.insertBefore(b,s);})(window.lintrk);
                    </script>
                    HTML,
            ],
            [
                'key' => 'tiktok_pixel',
                'name' => 'TikTok Pixel',
                'provider' => 'TikTok',
                'suggests' => 'marketing',
                'idLabel' => 'Pixel ID',
                'idExample' => 'CXXXXXXXXXXXXXXXXXXX',
                'description' => 'Measures advertising on TikTok.',
                'declaration' => [
                    ['name' => '_ttp', 'purpose' => 'Identifies the browser for ad measurement', 'duration' => '13 months'],
                ],
                'script' => <<<'HTML'
                    <script>
                      !function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=r;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};var o=d.createElement("script");o.type="text/javascript";o.async=!0;o.src=r+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
                        ttq.load('{id}');
                        ttq.page();
                      }(window,document,'ttq');
                    </script>
                    HTML,
            ],
            [
                'key' => 'hubspot',
                'name' => 'HubSpot',
                'provider' => 'HubSpot',
                'suggests' => 'marketing',
                'idLabel' => 'Portal ID',
                'idExample' => '1234567',
                'optionLabel' => 'Loader host',
                'optionDefault' => 'js.hs-scripts.com',
                'description' => 'Chat widget, forms and visit tracking.',
                'declaration' => [
                    ['name' => '__hstc', 'purpose' => 'Tracks visits over time', 'duration' => '6 months'],
                    ['name' => 'hubspotutk', 'purpose' => 'Identifies the visitor across forms', 'duration' => '6 months'],
                    ['name' => '__hssc', 'purpose' => 'Counts the page views of this session', 'duration' => '30 minutes'],
                    ['name' => '__hssrc', 'purpose' => 'Detects whether the browser was restarted', 'duration' => 'Session'],
                ],
                'script' => <<<'HTML'
                    <script type="text/javascript" id="hs-script-loader" async defer src="https://{option}/{id}.js"></script>
                    HTML,
            ],
            [
                'key' => 'plausible',
                'name' => 'Plausible Analytics',
                'provider' => 'Plausible',
                'suggests' => 'analytics',
                'idLabel' => 'Domain',
                'idExample' => 'example.com',
                'optionLabel' => 'Script URL',
                'optionDefault' => 'https://plausible.io/js/script.js',
                'description' => 'Privacy-friendly visit counts. Sets no cookies.',
                'script' => <<<'HTML'
                    <script defer data-domain="{id}" src="{option}"></script>
                    HTML,
            ],
            [
                'key' => 'fathom',
                'name' => 'Fathom Analytics',
                'provider' => 'Fathom',
                'suggests' => 'analytics',
                'idLabel' => 'Site ID',
                'idExample' => 'ABCDEFGH',
                'optionLabel' => 'Script URL',
                'optionDefault' => 'https://cdn.usefathom.com/script.js',
                'description' => 'Privacy-friendly visit counts. Sets no cookies.',
                'script' => <<<'HTML'
                    <script src="{option}" data-site="{id}" defer></script>
                    HTML,
            ],
            [
                'key' => 'cloudflare_analytics',
                'name' => 'Cloudflare Web Analytics',
                'provider' => 'Cloudflare',
                'suggests' => 'analytics',
                'idLabel' => 'Beacon token',
                'idExample' => '0123456789abcdef',
                'description' => 'Visit counts measured at the edge. Sets no cookies.',
                'script' => <<<'HTML'
                    <script defer src="https://static.cloudflareinsights.com/beacon.min.js" data-cf-beacon='{"token": "{id}"}'></script>
                    HTML,
            ],
            [
                'key' => 'matomo',
                'name' => 'Matomo',
                'provider' => 'Matomo',
                'suggests' => 'analytics',
                'idLabel' => 'Site ID',
                'idExample' => '1',
                'optionLabel' => 'Matomo server URL',
                'description' => 'Self-hosted visit statistics.',
                'declaration' => [
                    ['name' => '_pk_id.*', 'purpose' => 'Tells visitors apart', 'duration' => '13 months'],
                    ['name' => '_pk_ses.*', 'purpose' => 'Holds the current session data', 'duration' => '30 minutes'],
                ],
                'script' => <<<'HTML'
                    <script>
                      var _paq = window._paq = window._paq || [];
                      _paq.push(['trackPageView']);
                      _paq.push(['enableLinkTracking']);
                      (function() {
                        var u = '{option}'.replace(/\/?$/, '/');
                        _paq.push(['setTrackerUrl', u + 'matomo.php']);
                        _paq.push(['setSiteId', '{id}']);
                        var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
                        g.async = true; g.src = u + 'matomo.js'; s.parentNode.insertBefore(g, s);
                      })();
                    </script>
                    HTML,
            ],
        ];
    }
}
