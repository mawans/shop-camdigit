/* CamDigit — realtime utilities (cd-realtime.js)
 * Loaded on every page via cd_render_head().
 * Provides:
 *   CD.toast(msg, kind)             — show a toast notification
 *   CD.ajax(url, body, opts)        — JSON POST helper with same-origin cookies
 *   CD.submitForm(form, opts)       — AJAX-ify a <form> (CSRF aware)
 *   CD.confirm(opts) → Promise      — promise-based confirm modal
 *   CD.debounce(fn, ms)             — debounce utility
 *   CD.onScroll()                   — header shadow on scroll, lazy reveal
 */
(function () {
    'use strict';

    var CD = window.CD = window.CD || {};

    // ── Toast ────────────────────────────────────────────────────────
    function ensureToastWrap() {
        var w = document.getElementById('cdToastWrap');
        if (w) return w;
        w = document.createElement('div');
        w.id = 'cdToastWrap';
        w.className = 'cd-toast-wrap';
        document.body.appendChild(w);
        return w;
    }
    var ICONS = { ok: 'fa-circle-check', err: 'fa-circle-exclamation', warn: 'fa-triangle-exclamation', info: 'fa-circle-info' };
    CD.toast = function (message, kind, ttl) {
        kind = kind || 'info';
        var wrap = ensureToastWrap();
        var t = document.createElement('div');
        t.className = 'cd-toast ' + kind;
        t.innerHTML = '<i class="fa ' + (ICONS[kind] || ICONS.info) + '"></i><span></span><button aria-label="close">&times;</button>';
        t.querySelector('span').textContent = message;
        t.querySelector('button').onclick = function () { dismiss(t); };
        wrap.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('show'); });
        var timer = setTimeout(function () { dismiss(t); }, ttl || 3500);
        function dismiss(node) {
            clearTimeout(timer);
            node.classList.remove('show');
            setTimeout(function () { if (node.parentNode) node.parentNode.removeChild(node); }, 280);
        }
        return { dismiss: function () { dismiss(t); } };
    };

    // ── AJAX (JSON) ──────────────────────────────────────────────────
    CD.ajax = function (url, body, opts) {
        opts = opts || {};
        return fetch(url, {
            method: opts.method || 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json', 'Accept': 'application/json' }, opts.headers || {}),
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : null
        }).then(function (r) {
            return r.json().catch(function () { return { result: 'error', message: 'Invalid response' }; })
                .then(function (data) {
                    data.__status = r.status;
                    data.__ok = r.ok;
                    return data;
                });
        });
    };

    // ── Form (AJAX-ify) ──────────────────────────────────────────────
    // Usage: <form data-cd-ajax="/some-endpoint.php" data-cd-redirect="/cart.php">
    // Or call CD.submitForm(form, { url: ..., onSuccess: fn, onError: fn })
    CD.submitForm = function (form, opts) {
        opts = opts || {};
        var url = opts.url || form.getAttribute('data-cd-ajax') || form.action;
        var submitBtn = form.querySelector('[type="submit"]');
        var origBtnHtml = submitBtn ? submitBtn.innerHTML : null;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="cd-spin"></span> <span>' + (opts.busyText || 'Submitting…') + '</span>';
        }

        var fd = new FormData(form);
        var body = {};
        fd.forEach(function (v, k) {
            if (body[k] !== undefined) {
                if (!Array.isArray(body[k])) body[k] = [body[k]];
                body[k].push(v);
            } else {
                body[k] = v;
            }
        });

        return CD.ajax(url, body, opts.fetchOpts).then(function (data) {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = origBtnHtml; }
            if (data.result === 'success' || data.__ok) {
                if (opts.successMessage) CD.toast(opts.successMessage, 'ok');
                else if (data.message) CD.toast(data.message, 'ok');
                if (opts.onSuccess) opts.onSuccess(data);
                var redir = form.getAttribute('data-cd-redirect') || opts.redirect;
                if (redir) setTimeout(function () { window.location.href = redir; }, 600);
            } else {
                CD.toast(data.message || opts.errorMessage || 'Something went wrong', 'err');
                if (opts.onError) opts.onError(data);
            }
            return data;
        }).catch(function (err) {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = origBtnHtml; }
            CD.toast(opts.errorMessage || 'Network error — please try again', 'err');
            if (opts.onError) opts.onError(err);
        });
    };

    // ── Confirm modal (promise) ──────────────────────────────────────
    CD.confirm = function (opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var bd = document.createElement('div');
            bd.className = 'cd-modal-backdrop open';
            bd.innerHTML =
                '<div class="cd-modal">' +
                    '<h3>' + (opts.title || 'Are you sure?') + '</h3>' +
                    '<p>' + (opts.message || '') + '</p>' +
                    '<div class="cd-modal-actions">' +
                        '<button class="cd-btn cd-btn-secondary" data-cd-cancel>' + (opts.cancelLabel || 'Cancel') + '</button>' +
                        '<button class="cd-btn ' + (opts.dangerous ? 'cd-btn-danger' : '') + '" data-cd-ok>' + (opts.okLabel || 'Confirm') + '</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(bd);
            function close(v) {
                bd.classList.remove('open');
                setTimeout(function () { if (bd.parentNode) bd.parentNode.removeChild(bd); }, 200);
                resolve(v);
            }
            bd.querySelector('[data-cd-ok]').onclick = function () { close(true); };
            bd.querySelector('[data-cd-cancel]').onclick = function () { close(false); };
            bd.onclick = function (e) { if (e.target === bd) close(false); };
        });
    };

    // ── Debounce ─────────────────────────────────────────────────────
    CD.debounce = function (fn, ms) {
        var t;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, ms || 250);
        };
    };

    // ── Auto-wire: data-cd-ajax forms, data-cd-confirm buttons ──────
    function wire() {
        // Sticky header shadow on scroll
        var hdr = document.querySelector('.cd-header');
        if (hdr) {
            var onScroll = function () { hdr.classList.toggle('scrolled', window.scrollY > 8); };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }

        // AJAX forms
        document.querySelectorAll('form[data-cd-ajax]').forEach(function (form) {
            if (form.__cdWired) return;
            form.__cdWired = true;
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                CD.submitForm(form);
            });
        });

        // Confirm-before-submit
        document.querySelectorAll('[data-cd-confirm]').forEach(function (el) {
            if (el.__cdWired) return;
            el.__cdWired = true;
            el.addEventListener('click', function (e) {
                if (el.__cdConfirmed) { el.__cdConfirmed = false; return; }
                e.preventDefault();
                CD.confirm({
                    title: el.getAttribute('data-cd-confirm-title') || 'Are you sure?',
                    message: el.getAttribute('data-cd-confirm') || '',
                    okLabel: el.getAttribute('data-cd-confirm-ok') || 'Confirm',
                    dangerous: el.hasAttribute('data-cd-danger')
                }).then(function (ok) {
                    if (!ok) return;
                    el.__cdConfirmed = true;
                    if (el.tagName === 'BUTTON' || el.tagName === 'INPUT') el.click();
                    else if (el.tagName === 'A') window.location.href = el.href;
                });
            });
        });

        // Fade-in any [data-cd-reveal] when scrolled into view
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) {
                        e.target.classList.add('cd-fade-up');
                        io.unobserve(e.target);
                    }
                });
            }, { threshold: .1 });
            document.querySelectorAll('[data-cd-reveal]').forEach(function (n) { io.observe(n); });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire);
    } else {
        wire();
    }
    // expose for dynamic content
    CD.wire = wire;
})();
