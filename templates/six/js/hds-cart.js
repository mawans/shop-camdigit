/* CamDigit — realtime domain search + cart (shared module)
 * Exposes window.HDS with:
 *   HDS.init({ site, proxy, cartApi, lang })            — must be called once
 *   HDS.mountSearch(container, opts)                    — wires a search section
 *   HDS.addDomain(domain, years?)                       — AJAX add to cart
 *   HDS.removeDomain(domain)                            — AJAX remove from cart
 *   HDS.refresh()                                       — re-pull cart state
 *   HDS.cart                                            — current cart snapshot
 *
 * The cart pill and toast layer self-mount on init.
 */
(function () {
    'use strict';

    var HDS = window.HDS = window.HDS || {};
    var cfg = { site: '', proxy: '', cartApi: '', lang: 'english' };
    var csrf = null;
    var state = { count: 0, totals: { subtotal: 0, currency: 'XAF' }, cart: { domains: [] } };
    var subscribers = [];
    var ALL_TLDS = ['.cm', '.com', '.net', '.org', '.africa', '.io', '.info', '.biz'];

    function t(en, fr) { return cfg.lang === 'french' ? fr : en; }
    function esc(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
    function inCart(domain) {
        return (state.cart.domains || []).some(function (d) { return d.domain === domain; });
    }

    function postJSON(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (r) { return r.json().catch(function () { return { result: 'error' }; }); });
    }

    function ensureCsrf() {
        if (csrf) return Promise.resolve(csrf);
        return postJSON(cfg.cartApi, { action: '__csrf__' }).then(function (d) {
            csrf = d.csrf_token || null;
            return csrf;
        });
    }

    function applyState(d) {
        if (!d || d.result !== 'success') return;
        state = { count: d.count || 0, totals: d.totals || state.totals, cart: d.cart || state.cart };
        renderCartPill();
        subscribers.forEach(function (fn) { try { fn(state); } catch (e) {} });
    }

    // ── Cart pill (floating, site-wide) ─────────────────────────────────────
    function ensurePill() {
        var el = document.getElementById('hdsCartPill');
        if (el) return el;
        el = document.createElement('a');
        el.id = 'hdsCartPill';
        el.className = 'hds-cart-pill';
        el.href = cfg.site + '/cart.php';
        el.innerHTML =
            '<span class="hds-cart-icon"><i class="fa fa-shopping-cart"></i></span>' +
            '<span class="hds-cart-count" id="hdsCartCount">0</span>' +
            '<span class="hds-cart-total" id="hdsCartTotal"></span>' +
            '<span style="color:#666;font-weight:500;font-size:12px">' + esc(t('View cart', 'Voir le panier')) + '</span>';
        document.body.appendChild(el);
        return el;
    }

    function formatMoney(amount, currency) {
        var n = Number(amount || 0).toFixed(2).replace('.', ',');
        if (currency === 'EUR') return n + ' €';
        if (currency === 'USD') return '$' + n;
        if (currency === 'GBP') return '£' + n;
        return (currency || '') + ' ' + n;
    }

    function renderCartPill() {
        var el = ensurePill();
        var cnt = document.getElementById('hdsCartCount');
        var tot = document.getElementById('hdsCartTotal');
        if (cnt) cnt.textContent = state.count;
        if (tot) tot.textContent = state.count > 0 ? formatMoney(state.totals.total || state.totals.subtotal, state.totals.currency) : '';
        if (state.count > 0) el.classList.add('show'); else el.classList.remove('show');
    }

    // ── Toast ───────────────────────────────────────────────────────────────
    function ensureToastWrap() {
        var w = document.getElementById('hdsToastWrap');
        if (w) return w;
        w = document.createElement('div');
        w.id = 'hdsToastWrap';
        w.className = 'hds-toast-wrap';
        document.body.appendChild(w);
        return w;
    }

    function toast(msg, kind) {
        var wrap = ensureToastWrap();
        var n = document.createElement('div');
        n.className = 'hds-toast' + (kind ? ' ' + kind : '');
        n.textContent = msg;
        wrap.appendChild(n);
        requestAnimationFrame(function () { n.classList.add('show'); });
        setTimeout(function () {
            n.classList.remove('show');
            setTimeout(function () { if (n.parentNode) n.parentNode.removeChild(n); }, 250);
        }, 2800);
    }

    // ── Public: add/remove/refresh ──────────────────────────────────────────
    HDS.addDomain = function (domain, years) {
        return ensureCsrf().then(function (token) {
            return postJSON(cfg.cartApi, {
                action: 'add_domain', _csrf: token,
                domain: domain, years: years || 1
            });
        }).then(function (d) {
            if (d.result !== 'success') {
                toast(d.message || t('Could not add to cart', 'Impossible d\'ajouter au panier'), 'error');
                return d;
            }
            applyState(d);
            toast(t('Added to cart: ', 'Ajouté au panier : ') + domain);
            return d;
        }).catch(function () {
            toast(t('Network error', 'Erreur réseau'), 'error');
        });
    };

    HDS.removeDomain = function (domain) {
        return ensureCsrf().then(function (token) {
            return postJSON(cfg.cartApi, {
                action: 'remove_domain', _csrf: token, domain: domain
            });
        }).then(function (d) {
            if (d.result === 'success') {
                applyState(d);
                toast(t('Removed: ', 'Retiré : ') + domain, 'info');
            }
            return d;
        });
    };

    HDS.refresh = function () {
        return postJSON(cfg.cartApi, { action: 'get' }).then(function (d) {
            applyState(d);
            return d;
        });
    };

    HDS.onChange = function (fn) { subscribers.push(fn); };
    Object.defineProperty(HDS, 'cart', { get: function () { return state; } });

    // ── Search section ──────────────────────────────────────────────────────
    function buildSearchUI(container, opts) {
        var defaultTld = (opts && opts.defaultTld) || '.cm';
        var tlds = (opts && opts.tlds) || ['.cm','.com','.net','.org','.africa','.io'];
        var optionsHtml = tlds.map(function (x) {
            return '<option value="' + esc(x) + '"' + (x === defaultTld ? ' selected' : '') + '>' + esc(x) + '</option>';
        }).join('');

        container.innerHTML =
            '<form class="hds-form" data-hds-form>' +
                '<div class="hds-input-wrap">' +
                    '<input type="text" data-hds-sld placeholder="' + esc(t('yourbrand','votremarque')) + '" required>' +
                    '<select data-hds-tld>' + optionsHtml + '</select>' +
                '</div>' +
                '<button class="theme-btn bg-color-2 hds-search-btn" type="submit" data-hds-submit>' +
                    '<i class="fa fa-search"></i> ' + esc(t('Search','Rechercher')) +
                '</button>' +
            '</form>' +
            '<div class="hds-result" data-hds-result hidden></div>' +
            '<div class="hds-suggestions-wrap" data-hds-suggestions-wrap hidden>' +
                '<h4>' +
                    '<i class="fa fa-globe"></i> ' + esc(t('Other extensions','Autres extensions')) +
                    ' <span data-hds-sld-echo></span>' +
                '</h4>' +
                '<div class="hds-suggestions-grid" data-hds-suggestions>' +
                    '<div class="hds-loading">' + esc(t('Checking availability…','Vérification…')) + '</div>' +
                '</div>' +
                '<div class="hds-cta-wrap" data-hds-cta-wrap>' +
                    '<a href="' + cfg.site + '/cart.php" class="theme-btn">' +
                        '<i class="fa fa-shopping-cart"></i> ' + esc(t('View Cart','Voir le panier')) +
                    '</a>' +
                    '<a href="' + cfg.site + '/order-hosting.php" class="theme-btn">' +
                        '<i class="fa fa-server"></i> ' + esc(t('Add hosting','Ajouter un hébergement')) +
                    '</a>' +
                '</div>' +
            '</div>';
    }

    function sanitizeSld(v) {
        return String(v || '').toLowerCase()
            .replace(/[^a-z0-9-]/g, '')
            .replace(/^-+|-+$/g, '')
            .substring(0, 63);
    }

    function checkDomains(sld, tlds) {
        return ensureProxyCsrf().then(function (token) {
            return postJSON(cfg.proxy, {
                action: 'CheckMultiAvailability',
                _csrf: token,
                sld: sld,
                tlds: tlds
            });
        });
    }

    // proxy uses its OWN csrf endpoint
    var proxyCsrf = null;
    function ensureProxyCsrf() {
        if (proxyCsrf) return Promise.resolve(proxyCsrf);
        return postJSON(cfg.proxy, { action: '__csrf__' }).then(function (d) {
            proxyCsrf = d.csrf_token || null;
            return proxyCsrf;
        });
    }

    function renderAddBtn(domain, label) {
        var added = inCart(domain);
        var cls = 'hds-add-btn' + (added ? ' in-cart' : '');
        var txt = added ? t('In cart','Au panier') : (label || t('Add to Cart','Ajouter au panier'));
        return '<button class="' + cls + '" type="button" data-hds-add="' + esc(domain) + '"' +
                    (added ? ' disabled' : '') + '>' +
                    (added ? '' : '<i class="fa fa-cart-plus"></i> ') + esc(txt) +
               '</button>';
    }

    function renderPrimary(root, sld, tld, status) {
        var box = root.querySelector('[data-hds-result]');
        var domain = sld + tld;
        box.hidden = false;
        if (status === 'available') {
            box.className = 'hds-result available';
            box.innerHTML =
                '<div><strong>' + esc(domain) + '</strong> — ' + esc(t('available','disponible')) + '</div>' +
                '<div class="hds-result-actions">' + renderAddBtn(domain) + '</div>';
        } else if (status === 'unavailable' || status === 'taken' || status === 'registered') {
            box.className = 'hds-result taken';
            box.innerHTML = '<strong>' + esc(domain) + '</strong> — ' + esc(t('is already registered. Try another name or extension.','est déjà enregistré. Essayez un autre nom ou extension.'));
        } else {
            box.className = 'hds-result error';
            box.innerHTML = '<strong>' + esc(domain) + '</strong> — ' + esc(t('Status unknown. Please try again.','Statut inconnu. Veuillez réessayer.'));
        }
    }

    function renderSuggestions(root, sld, items, searchedTld) {
        var wrap = root.querySelector('[data-hds-suggestions-wrap]');
        var grid = root.querySelector('[data-hds-suggestions]');
        var echo = root.querySelector('[data-hds-sld-echo]');
        if (echo) echo.textContent = ' ' + sld;
        wrap.hidden = false;

        var html = items.filter(function (d) { return d.tld !== searchedTld; }).map(function (d) {
            var avail = d.status === 'available';
            var cls   = avail ? 'available' : 'taken';
            var badge = avail ? t('Available','Disponible') : t('Taken','Pris');
            var btn   = avail ? renderAddBtn(d.domain) : '';
            return '<div class="hds-tld-item ' + cls + '" data-hds-item-domain="' + esc(d.domain) + '">' +
                       '<div class="hds-tld-row">' +
                           '<span class="hds-tld-ext">' + esc(d.tld) + '</span>' +
                           '<span class="hds-tld-badge">' + esc(badge) + '</span>' +
                       '</div>' +
                       '<div class="hds-tld-domain">' + esc(d.domain) + '</div>' +
                       btn +
                   '</div>';
        }).join('');

        grid.innerHTML = html || '<div class="hds-loading">' + esc(t('No suggestions available.','Aucune suggestion disponible.')) + '</div>';
    }

    function refreshAddButtons(root) {
        var btns = root.querySelectorAll('[data-hds-add]');
        btns.forEach(function (b) {
            var domain = b.getAttribute('data-hds-add');
            var added = inCart(domain);
            b.disabled = added;
            b.classList.toggle('in-cart', added);
            b.innerHTML = added
                ? esc(t('In cart','Au panier'))
                : '<i class="fa fa-cart-plus"></i> ' + esc(t('Add to Cart','Ajouter au panier'));
        });
    }

    function runSearch(root) {
        var sldInput = root.querySelector('[data-hds-sld]');
        var tldSelect = root.querySelector('[data-hds-tld]');
        var btn = root.querySelector('[data-hds-submit]');
        var resBox = root.querySelector('[data-hds-result]');
        var sugWrap = root.querySelector('[data-hds-suggestions-wrap]');

        var sldRaw = sldInput.value.trim();
        var sld    = sanitizeSld(sldRaw.replace(/\.[a-z.]+$/i, ''));
        var tld    = tldSelect.value;
        if (!sld) { sldInput.focus(); return; }

        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + t('Searching…','Recherche…');
        resBox.hidden = true;
        sugWrap.hidden = true;

        var tldsToCheck = [tld].concat(ALL_TLDS.filter(function (x) { return x !== tld; })).slice(0, 8);

        checkDomains(sld, tldsToCheck).then(function (data) {
            btn.disabled = false;
            btn.innerHTML = orig;
            if (!data || data.result !== 'success' || !Array.isArray(data.domains)) {
                resBox.hidden = false;
                resBox.className = 'hds-result error';
                resBox.textContent = t('Could not check availability. Please try again.','Vérification impossible. Veuillez réessayer.');
                return;
            }
            var primary = data.domains.find(function (d) { return d.tld === tld; });
            if (primary) renderPrimary(root, sld, tld, primary.status);
            renderSuggestions(root, sld, data.domains, tld);
        }).catch(function () {
            btn.disabled = false;
            btn.innerHTML = orig;
            resBox.hidden = false;
            resBox.className = 'hds-result error';
            resBox.textContent = t('Network error. Please try again.','Erreur réseau. Veuillez réessayer.');
        });
    }

    HDS.mountSearch = function (container, opts) {
        if (typeof container === 'string') container = document.querySelector(container);
        if (!container) return;
        // Only build UI if the container doesn't already have a server-rendered form
        if (!container.querySelector('[data-hds-form]')) {
            buildSearchUI(container, opts || {});
        }

        container.addEventListener('submit', function (e) {
            if (e.target && e.target.matches('[data-hds-form]')) {
                e.preventDefault();
                runSearch(container);
            }
        });

        container.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-hds-add]');
            if (!btn || btn.disabled) return;
            var domain = btn.getAttribute('data-hds-add');
            btn.disabled = true;
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + t('Adding…','Ajout…');
            HDS.addDomain(domain).then(function () {
                refreshAddButtons(container);
            }).catch(function () {
                btn.disabled = false;
                btn.innerHTML = orig;
            });
        });

        HDS.onChange(function () { refreshAddButtons(container); });

        // If a starting search is provided, fire it.
        if (opts && opts.initialSld) {
            container.querySelector('[data-hds-sld]').value = opts.initialSld;
            if (opts.initialTld) container.querySelector('[data-hds-tld]').value = opts.initialTld;
            runSearch(container);
        }
    };

    // ── Init ────────────────────────────────────────────────────────────────
    HDS.init = function (opts) {
        cfg = Object.assign(cfg, opts || {});
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { HDS.refresh(); });
        } else {
            HDS.refresh();
        }
    };
})();
