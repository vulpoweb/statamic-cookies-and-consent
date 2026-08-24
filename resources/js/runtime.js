/**
 * Vulpo Cookies front-end runtime.
 *
 * Dependency-free and inlined into the page, so there is nothing to build and no
 * asset to publish. It is the only thing that knows the visitor's decision: the
 * server renders the same HTML for everyone and every third-party script sits in
 * an inert <template> until this decides otherwise, which is what makes the addon
 * safe behind a full-page or static cache.
 *
 * The cookie payload is written by src/Consent/CookieCodec.php as well. Keep the
 * two in step.
 */
(function () {
    var config = window.vulpoCookiesConfig || {}
    var cookie = config.cookie || {}
    var mode = config.consentMode || {}
    var categories = config.categories || []

    /**
     * Add ?vulpo-cookies-debug=1 to a URL to have every decision narrated in the
     * console. Answering "why did analytics not load on the client's site" is
     * otherwise guesswork from the outside.
     */
    var debugging = location.search.indexOf('vulpo-cookies-debug') !== -1

    function debug() {
        if (!debugging || !window.console) return

        var args = Array.prototype.slice.call(arguments)

        args.unshift('[vulpo-cookies]')
        console.log.apply(console, args)
    }

    /**
     * Handles that are on no matter what, so a decision can never switch off
     * something the site needs to work.
     */
    function requiredHandles() {
        var handles = []

        categories.forEach(function (category) {
            if (!category.required) return

            handles.push(category.handle)
            ;(category.services || []).forEach(function (service) {
                handles.push(service.handle)
            })
        })

        return handles
    }

    function allHandles() {
        var handles = []

        categories.forEach(function (category) {
            handles.push(category.handle)
            ;(category.services || []).forEach(function (service) {
                handles.push(service.handle)
            })
        })

        return handles
    }

    function preTickedHandles() {
        var handles = []

        categories.forEach(function (category) {
            var ticked = (category.services || []).filter(function (service) {
                return service.pre_ticked
            })

            if (category.required || ticked.length) handles.push(category.handle)

            ticked.forEach(function (service) {
                handles.push(service.handle)
            })
        })

        return handles
    }

    function unique(handles) {
        return handles.filter(function (handle, index) {
            return handles.indexOf(handle) === index
        })
    }

    function readCookie() {
        var prefix = cookie.name + '='
        var parts = document.cookie ? document.cookie.split(';') : []

        for (var i = 0; i < parts.length; i++) {
            var part = parts[i].trim()

            if (part.indexOf(prefix) !== 0) continue

            try {
                var payload = JSON.parse(decodeURIComponent(part.substring(prefix.length)))

                // A payload from an older settings revision is not a decision:
                // raising the revision is how a site re-asks everyone.
                if (!payload || payload.v !== config.revision) return null
                if (!Array.isArray(payload.g)) return null

                return {
                    granted: payload.g.filter(function (handle) {
                        return typeof handle === 'string'
                    }),
                    pending: !!payload.p,
                }
            } catch (error) {
                return null
            }
        }

        return null
    }

    function writeCookie(granted, pending) {
        var expires = new Date()
        expires.setTime(expires.getTime() + cookie.lifetime_days * 86400000)

        var payload = { v: config.revision, t: Math.floor(Date.now() / 1000), g: unique(granted) }

        if (pending) payload.p = 1

        var value = encodeURIComponent(JSON.stringify(payload))

        document.cookie =
            cookie.name +
            '=' +
            value +
            ';expires=' +
            expires.toUTCString() +
            ';path=/;samesite=' +
            (cookie.same_site || 'lax') +
            // Set on a parent domain, one decision covers every subdomain.
            (cookie.domain ? ';domain=' + cookie.domain : '') +
            (location.protocol === 'https:' ? ';secure' : '')
    }

    function eraseCookie() {
        var stamp = ';expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/'

        document.cookie = cookie.name + '=' + stamp
        if (cookie.domain) document.cookie = cookie.name + '=' + stamp + ';domain=' + cookie.domain
    }

    /**
     * Delete one cookie, trying the places a vendor is likely to have set it:
     * this exact host, and each parent domain up to the registrable one. The
     * browser only deletes a cookie when the path and domain match how it was
     * set, and it never tells us which that was.
     */
    function forget(name) {
        var stamp = ';expires=Thu, 01 Jan 1970 00:00:00 GMT'
        var parts = location.hostname.split('.')

        debug('deleting cookie', name)
        document.cookie = name + '=' + stamp + ';path=/'

        for (var i = 0; i < parts.length - 1; i++) {
            var domain = parts.slice(i).join('.')

            document.cookie = name + '=' + stamp + ';path=/;domain=' + domain
            document.cookie = name + '=' + stamp + ';path=/;domain=.' + domain
        }
    }

    /**
     * Clear out what a service left behind once the visitor withdraws it.
     *
     * Refusing a tool and then finding its cookies still in the browser is not a
     * refusal anyone would recognise. What cannot be reached this way — HttpOnly
     * cookies, and anything set on the vendor's own domain — is out of a script's
     * hands entirely.
     */
    function forgetWithdrawn() {
        var present = (document.cookie ? document.cookie.split(';') : []).map(function (part) {
            return part.trim().split('=')[0]
        })

        categories.forEach(function (category) {
            if (category.required) return

            ;(category.services || []).forEach(function (service) {
                if (isGranted(service.handle)) return

                ;(service.cookie_names || []).forEach(function (declared) {
                    if (declared === cookie.name) return

                    if (declared.slice(-1) !== '*') {
                        forget(declared)

                        return
                    }

                    // A family, e.g. `_ga_*`: whatever is actually in the jar.
                    var prefix = declared.slice(0, -1)

                    present.forEach(function (name) {
                        if (name.indexOf(prefix) === 0 && name !== cookie.name) forget(name)
                    })
                })
            })
        })
    }

    /** A browser asking not to be tracked has already answered the question. */
    function isOptedOut() {
        if (!config.respect_gpc) return false

        return navigator.globalPrivacyControl === true || navigator.doNotTrack === '1'
    }

    var stored = readCookie()

    // Grants can stand without the banner having been answered — see `allow`.
    var granted = stored ? stored.granted : null
    var decided = stored !== null && !stored.pending

    if (granted === null && isOptedOut()) {
        granted = requiredHandles()
        decided = true
    }

    function categoryOf(handle) {
        var found = null

        categories.forEach(function (category) {
            ;(category.services || []).forEach(function (service) {
                if (service.handle === handle) found = category.handle
            })
        })

        return found
    }

    /** The handles a service may be stored under, including ones it outgrew. */
    function handlesOf(handle) {
        var all = [handle]

        categories.forEach(function (category) {
            ;(category.services || []).forEach(function (service) {
                if (service.handle !== handle) return

                all = all.concat(service.aliases || [])
            })
        })

        return all
    }

    function isGranted(handle) {
        if (requiredHandles().indexOf(handle) !== -1) return true

        var stored = granted || []

        return handlesOf(handle).some(function (name) {
            return stored.indexOf(name) !== -1
        })
    }

    /**
     * Move one service's markup out of its inert template and into the document.
     *
     * Script elements are rebuilt rather than cloned: a cloned script is not
     * guaranteed to run, a freshly created one always does.
     */
    function fill(template, target) {
        var nodes = template.content ? Array.prototype.slice.call(template.content.childNodes) : []

        nodes.forEach(function (node) {
            if (node.nodeName === 'SCRIPT') {
                var script = document.createElement('script')

                Array.prototype.forEach.call(node.attributes, function (attribute) {
                    script.setAttribute(attribute.name, attribute.value)
                })

                script.text = node.textContent
                target.appendChild(script)

                return
            }

            target.appendChild(node.cloneNode(true))
        })
    }

    function activate(service) {
        var template = document.querySelector('[data-vulpo-cookies-service="' + service.handle + '"]')

        if (!template || template.dataset.vulpoCookiesActivated) return

        template.dataset.vulpoCookiesActivated = 'true'
        debug('activating script', service.handle, 'into', service.position)

        fill(template, service.position === 'head' ? document.head : document.body)
    }

    /**
     * Let the allowed embeds through, replacing their placeholder in place.
     *
     * An embed is a visible thing on the page, so unlike a tracking script it can
     * appear the moment it is allowed. Nothing needs undoing, so nothing reloads.
     */
    function activateEmbeds() {
        var wrappers = document.querySelectorAll('[data-vulpo-cookies-embed]')

        Array.prototype.forEach.call(wrappers, function (wrapper) {
            var handle = wrapper.dataset.vulpoCookiesEmbed

            if (!isGranted(handle) || wrapper.dataset.vulpoCookiesActivated) return

            var template = wrapper.querySelector('[data-vulpo-cookies-embed-content="' + handle + '"]')

            if (!template) return

            wrapper.dataset.vulpoCookiesActivated = 'true'
            debug('letting embed through', handle)

            var placeholder = wrapper.querySelector('[data-vulpo-cookies-embed-placeholder]')

            if (placeholder) placeholder.remove()

            fill(template, wrapper)
            template.remove()
        })
    }

    function activateGranted() {
        categories.forEach(function (category) {
            ;(category.services || []).forEach(function (service) {
                if (service.has_script && isGranted(service.handle)) activate(service)
            })
        })
    }

    function updateConsentMode() {
        if (!mode.enabled || typeof window.gtag !== 'function') return

        var update = {}

        ;(mode.keys || []).forEach(function (key) {
            update[key] = 'denied'
        })

        Object.keys(mode.map || {}).forEach(function (category) {
            if (!isGranted(category)) return

            mode.map[category].forEach(function (key) {
                update[key] = 'granted'
            })
        })

        debug('gtag consent update', update)
        window.gtag('consent', 'update', update)
    }

    /* --- The visible bits ------------------------------------------------- */

    function panel(name) {
        return document.querySelector('[data-vulpo-cookies-' + name + ']')
    }

    /**
     * Visibility is announced twice: the `hidden` attribute is toggled so the
     * packaged views work with no CSS at all, and an event is dispatched so a
     * project's own view can drive its own transitions instead. Views that own
     * their visibility simply leave the data attributes off.
     */
    var lastFocused = null

    /**
     * Keep Tab inside the preferences panel while it is open, and hand focus back
     * to whatever opened it on the way out. Only applies to the packaged panel: a
     * project view with its own trap has no `data-vulpo-cookies-preferences`
     * element for this to find.
     */
    function trapFocus(container) {
        var selector =
            'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])'

        function onKeydown(event) {
            if (event.key === 'Escape') {
                api.close()

                return
            }

            if (event.key !== 'Tab') return

            var focusable = Array.prototype.filter.call(
                container.querySelectorAll(selector),
                function (element) {
                    return element.offsetParent !== null
                }
            )

            if (!focusable.length) return

            var first = focusable[0]
            var last = focusable[focusable.length - 1]

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault()
                last.focus()
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault()
                first.focus()
            }
        }

        container.addEventListener('keydown', onKeydown)
        container._vulpoCookiesTrap = onKeydown
    }

    function releaseFocus(container) {
        if (container._vulpoCookiesTrap) {
            container.removeEventListener('keydown', container._vulpoCookiesTrap)
            container._vulpoCookiesTrap = null
        }

        if (lastFocused && lastFocused.focus) lastFocused.focus()
        lastFocused = null
    }

    /** Tell a screen reader what just happened; the panel closing is silent. */
    function announce(message) {
        var region = document.querySelector('[data-vulpo-cookies-status]')

        if (region) region.textContent = message
    }

    function show(which) {
        var banner = panel('banner')
        var preferences = panel('preferences')

        if (banner) banner.hidden = which !== 'banner'

        if (preferences) {
            var opening = which === 'preferences' && preferences.hidden
            var closing = which !== 'preferences' && !preferences.hidden

            preferences.hidden = which !== 'preferences'

            if (opening) {
                lastFocused = document.activeElement
                trapFocus(preferences)
                preferences.setAttribute('tabindex', '-1')
                preferences.focus()
            }

            if (closing) releaseFocus(preferences)
        }

        var root = panel('root')
        if (root) {
            root.hidden = which === null
            root.dataset.vulpoCookiesMode = which || 'hidden'
        }

        document.dispatchEvent(new CustomEvent('vulpo-cookies:mode', { detail: { mode: which } }))
    }

    function toggles() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-vulpo-cookies-toggle]'))
    }

    function categoryToggles() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-vulpo-cookies-category]'))
    }

    /**
     * How many of a category's services are ticked, written into any element
     * that asks for it. A collapsed category card hides its services, so this is
     * what keeps its header informative.
     */
    function syncCounts() {
        var elements = document.querySelectorAll('[data-vulpo-cookies-count]')

        Array.prototype.forEach.call(elements, function (element) {
            var handle = element.dataset.vulpoCookiesCount
            var children = toggles().filter(function (child) {
                return child.dataset.vulpoCookiesCategoryOf === handle
            })

            if (!children.length) return

            var on = children.filter(function (child) {
                return child.checked
            }).length

            element.textContent = element.dataset.vulpoCookiesCountFormat
                ? element.dataset.vulpoCookiesCountFormat
                      .replace(':allowed', on)
                      .replace(':total', children.length)
                : on + '/' + children.length
        })
    }

    /** Put the checkboxes where the stored decision (or the defaults) say. */
    function syncToggles() {
        var ticked = decided ? granted : preTickedHandles()

        toggles().forEach(function (input) {
            input.checked = ticked.indexOf(input.dataset.vulpoCookiesToggle) !== -1
        })

        categoryToggles().forEach(function (input) {
            var handle = input.dataset.vulpoCookiesCategory
            var children = toggles().filter(function (child) {
                return child.dataset.vulpoCookiesCategoryOf === handle
            })

            if (!children.length) {
                input.checked = ticked.indexOf(handle) !== -1

                return
            }

            var on = children.filter(function (child) {
                return child.checked
            })

            input.checked = on.length === children.length
            input.indeterminate = on.length > 0 && on.length < children.length
        })

        syncCounts()
    }

    /** What the checkboxes currently say, as a list of handles. */
    function selection() {
        var handles = requiredHandles()

        toggles().forEach(function (input) {
            if (!input.checked) return

            handles.push(input.dataset.vulpoCookiesToggle)

            if (input.dataset.vulpoCookiesCategoryOf) handles.push(input.dataset.vulpoCookiesCategoryOf)
        })

        categoryToggles().forEach(function (input) {
            var handle = input.dataset.vulpoCookiesCategory
            var hasChildren = toggles().some(function (child) {
                return child.dataset.vulpoCookiesCategoryOf === handle
            })

            if (!hasChildren && input.checked) handles.push(handle)
        })

        return unique(handles)
    }

    /**
     * Whether anything that already ran on this page is being taken away.
     *
     * Only that needs a fresh document: a script cannot be un-run, and an embed
     * cannot be un-loaded. Granting is the opposite — it settles in place — so a
     * first "Accept all" or a widened choice does not throw the page away.
     */
    function losesSomethingLive(next) {
        var lost = (granted || []).filter(function (handle) {
            return next.indexOf(handle) === -1
        })

        return lost.some(function (handle) {
            var block = document.querySelector('[data-vulpo-cookies-service="' + handle + '"]')
            var embed = document.querySelector('[data-vulpo-cookies-embed="' + handle + '"]')

            return (
                (block && block.dataset.vulpoCookiesActivated === 'true') ||
                (embed && embed.dataset.vulpoCookiesActivated === 'true')
            )
        })
    }

    function commit(handles) {
        var next = unique(handles)
        var reload = losesSomethingLive(next)

        granted = next
        decided = true

        writeCookie(granted, false)
        forgetWithdrawn()
        announce(config.saved_message || 'Your choices have been saved.')
        debug('saved', granted, reload ? '(reloading: something live was withdrawn)' : '(settled in place)')

        document.dispatchEvent(
            new CustomEvent('vulpo-cookies:changed', { detail: { granted: granted.slice() } })
        )

        if (reload) {
            location.reload()

            return
        }

        activateGranted()
        activateEmbeds()
        updateConsentMode()
        syncToggles()
        show(null)
    }

    var api = {
        granted: isGranted,
        /**
         * Allow one service without touching the rest of the decision, which is
         * what the button on a blocked embed does. Granting takes nothing back,
         * so this settles in place instead of reloading.
         */
        allow: function (handle) {
            if (!handle) return

            var next = (granted || []).concat(requiredHandles()).concat([handle])
            var category = categoryOf(handle)

            if (category) next.push(category)

            granted = unique(next)

            // Deliberately not a decision: one button on one embed is not an
            // answer to the banner, so the visitor still gets asked.
            writeCookie(granted, !decided)
            activateGranted()
            activateEmbeds()
            updateConsentMode()
            syncToggles()
            show(decided ? null : 'banner')

            document.dispatchEvent(
                new CustomEvent('vulpo-cookies:changed', { detail: { granted: granted.slice() } })
            )
        },
        grantedHandles: function () {
            return (granted || []).slice()
        },
        hasDecided: function () {
            return decided
        },
        open: function () {
            syncToggles()
            show('preferences')
        },
        close: function () {
            show(decided ? null : 'banner')
        },
        acceptAll: function () {
            commit(allHandles())
        },
        rejectAll: function () {
            commit(requiredHandles())
        },
        save: function () {
            commit(selection())
        },
        reset: function () {
            eraseCookie()
            granted = null
            decided = false
            location.reload()
        },
    }

    window.vulpoCookies = api

    /**
     * A privacy page wants to link to the preferences panel, not wire up an
     * onclick. `?cookie-preferences` or `#cookie-preferences` opens it.
     */
    function deepLinked() {
        return (
            location.search.indexOf('cookie-preferences') !== -1 ||
            location.hash === '#cookie-preferences'
        )
    }

    function start() {
        debug('decision', decided ? 'made' : 'pending', granted || [])

        activateGranted()
        activateEmbeds()
        forgetWithdrawn()
        updateConsentMode()
        syncToggles()

        if (deepLinked()) {
            show('preferences')
        } else {
            show(decided ? null : 'banner')
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest ? event.target.closest('[data-vulpo-cookies-action]') : null

            if (!trigger) return

            if (trigger.dataset.vulpoCookiesAction === 'allow') {
                event.preventDefault()
                api.allow(trigger.dataset.vulpoCookiesAllow)

                return
            }

            var action = {
                'accept-all': api.acceptAll,
                'reject-all': api.rejectAll,
                save: api.save,
                open: api.open,
                close: api.close,
                reset: api.reset,
            }[trigger.dataset.vulpoCookiesAction]

            if (!action) return

            event.preventDefault()
            action()
        })

        // Ticking a category ticks everything in it, and vice versa.
        document.addEventListener('change', function (event) {
            var input = event.target

            if (!input || !input.dataset) return

            if (input.dataset.vulpoCookiesCategory) {
                toggles().forEach(function (child) {
                    if (child.dataset.vulpoCookiesCategoryOf === input.dataset.vulpoCookiesCategory) {
                        child.checked = input.checked
                    }
                })
            }

            if (input.dataset.vulpoCookiesToggle || input.dataset.vulpoCookiesCategory) {
                categoryToggles().forEach(function (parent) {
                    var handle = parent.dataset.vulpoCookiesCategory
                    var children = toggles().filter(function (child) {
                        return child.dataset.vulpoCookiesCategoryOf === handle
                    })

                    if (!children.length) return

                    var on = children.filter(function (child) {
                        return child.checked
                    })

                    parent.checked = on.length === children.length
                    parent.indeterminate = on.length > 0 && on.length < children.length
                })
            }

            syncCounts()
        })

        document.dispatchEvent(
            new CustomEvent('vulpo-cookies:ready', {
                detail: { decided: decided, granted: (granted || []).slice() },
            })
        )
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start)
    } else {
        start()
    }
})()
