/* CamDigit Main — shared JS (cdm.js)
 * window.CDM = { toast, ajax, confirm, submitForm, mountCart, format }
 */
(function () {
    'use strict';

    var CDM = window.CDM = window.CDM || {};
    CDM.config = { site: '', lang: 'english' };

    // ── Toast ────────────────────────────────────────────────────────
    var ICONS = { ok: 'fa-circle-check', err: 'fa-circle-exclamation', warn: 'fa-triangle-exclamation', info: 'fa-circle-info' };
    function ensureToastWrap() {
        var w = document.getElementById('cdmToastWrap');
        if (w) return w;
        w = document.createElement('div');
        w.id = 'cdmToastWrap'; w.className = 'cdm-toast-wrap';
        document.body.appendChild(w);
        return w;
    }
    CDM.toast = function (message, kind, ttl) {
        kind = kind || 'ok';
        var wrap = ensureToastWrap();
        var t = document.createElement('div');
        t.className = 'cdm-toast ' + kind;
        t.innerHTML = '<i class="fa-solid ' + (ICONS[kind] || ICONS.info) + '"></i><span></span><button class="close" aria-label="close">&times;</button>';
        t.querySelector('span').textContent = message;
        t.querySelector('.close').onclick = function () { dismiss(); };
        wrap.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('show'); });
        var timer = setTimeout(dismiss, ttl || 3200);
        function dismiss() {
            clearTimeout(timer);
            t.classList.remove('show');
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 280);
        }
        return { dismiss: dismiss };
    };

    // ── AJAX (JSON POST) ─────────────────────────────────────────────
    CDM.ajax = function (url, body, opts) {
        opts = opts || {};
        return fetch(url, {
            method: opts.method || 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json', 'Accept': 'application/json' }, opts.headers || {}),
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : null
        }).then(function (r) {
            return r.json().catch(function () { return { result: 'error', message: 'Invalid response' }; })
                .then(function (data) { data.__status = r.status; data.__ok = r.ok; return data; });
        });
    };

    // ── Confirm modal ────────────────────────────────────────────────
    CDM.confirm = function (opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var bd = document.createElement('div');
            bd.className = 'cdm-modal-bd open';
            bd.innerHTML =
                '<div class="cdm-modal">' +
                    '<h3>' + (opts.title || 'Are you sure?') + '</h3>' +
                    '<p>' + (opts.message || '') + '</p>' +
                    '<div class="cdm-modal-actions">' +
                        '<button class="cdm-btn cdm-btn-ghost" data-cancel>' + (opts.cancelLabel || 'Cancel') + '</button>' +
                        '<button class="cdm-btn ' + (opts.dangerous ? 'cdm-btn-danger' : '') + '" data-ok>' + (opts.okLabel || 'Confirm') + '</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(bd);
            function close(v) {
                bd.classList.remove('open');
                setTimeout(function () { if (bd.parentNode) bd.parentNode.removeChild(bd); }, 200);
                resolve(v);
            }
            bd.querySelector('[data-ok]').onclick = function () { close(true); };
            bd.querySelector('[data-cancel]').onclick = function () { close(false); };
            bd.onclick = function (e) { if (e.target === bd) close(false); };
        });
    };

    // ── Form submitter (data-cdm-ajax="endpoint") ────────────────────
    CDM.submitForm = function (form, opts) {
        opts = opts || {};
        var url = opts.url || form.getAttribute('data-cdm-ajax') || form.action;
        var submit = form.querySelector('[type="submit"]');
        var origHtml = submit ? submit.innerHTML : null;
        if (submit) { submit.disabled = true; submit.innerHTML = '<span class="spin"></span> ' + (opts.busy || 'Submitting…'); }

        var fd = new FormData(form);
        var body = {};
        fd.forEach(function (v, k) {
            if (body[k] !== undefined) {
                if (!Array.isArray(body[k])) body[k] = [body[k]];
                body[k].push(v);
            } else body[k] = v;
        });
        return CDM.ajax(url, body).then(function (data) {
            if (submit) { submit.disabled = false; submit.innerHTML = origHtml; }
            if ((data.result === 'success') || data.__ok) {
                if (data.message) CDM.toast(data.message, 'ok');
                if (opts.onSuccess) opts.onSuccess(data);
                var redir = form.getAttribute('data-cdm-redirect') || opts.redirect;
                if (redir) setTimeout(function () { location.href = redir; }, 500);
            } else {
                CDM.toast(data.message || 'Something went wrong', 'err');
                if (opts.onError) opts.onError(data);
            }
            return data;
        }).catch(function (err) {
            if (submit) { submit.disabled = false; submit.innerHTML = origHtml; }
            CDM.toast('Network error — please try again', 'err');
            if (opts.onError) opts.onError(err);
        });
    };

    // ── Money formatter ──────────────────────────────────────────────
    CDM.format = function (amount, currency) {
        var n = Number(amount || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        switch ((currency || 'XAF').toUpperCase()) {
            case 'EUR': return n + ' €';
            case 'USD': return '$' + n;
            case 'GBP': return '£' + n;
            case 'XAF': return n + ' FCFA';
            default:    return (currency || '') + ' ' + n;
        }
    };

    // ── Auto-wire ───────────────────────────────────────────────────
    function wire() {
        // Sticky topbar shadow
        var topbar = document.querySelector('.cdm-topbar');
        if (topbar) {
            var onScroll = function () { topbar.classList.toggle('scrolled', window.scrollY > 8); };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }
        // Mobile nav
        var burger = document.querySelector('.cdm-burger');
        var nav = document.querySelector('.cdm-nav');
        if (burger && nav) burger.addEventListener('click', function () { nav.classList.toggle('open'); });

        // AJAX-ify forms with data-cdm-ajax
        document.querySelectorAll('form[data-cdm-ajax]').forEach(function (f) {
            if (f.__wired) return; f.__wired = true;
            f.addEventListener('submit', function (e) { e.preventDefault(); CDM.submitForm(f); });
        });

        // Confirm-before-submit
        document.querySelectorAll('[data-cdm-confirm]').forEach(function (el) {
            if (el.__wired) return; el.__wired = true;
            el.addEventListener('click', function (e) {
                if (el.__confirmed) { el.__confirmed = false; return; }
                e.preventDefault();
                CDM.confirm({
                    title: el.getAttribute('data-cdm-confirm-title') || 'Are you sure?',
                    message: el.getAttribute('data-cdm-confirm') || '',
                    okLabel: el.getAttribute('data-cdm-confirm-ok') || 'Confirm',
                    dangerous: el.hasAttribute('data-cdm-danger')
                }).then(function (ok) {
                    if (!ok) return;
                    el.__confirmed = true;
                    if (el.tagName === 'FORM') el.submit();
                    else if (el.tagName === 'A') location.href = el.href;
                    else el.click();
                });
            });
        });

        // Password show/hide toggle (any [data-cdm-pw-toggle])
        document.querySelectorAll('[data-cdm-pw-toggle]').forEach(function (b) {
            if (b.__wired) return; b.__wired = true;
            b.addEventListener('click', function () {
                var target = document.getElementById(b.getAttribute('data-cdm-pw-toggle'));
                if (!target) return;
                var hidden = target.type === 'password';
                target.type = hidden ? 'text' : 'password';
                b.innerHTML = '<i class="fa-solid fa-eye' + (hidden ? '-slash' : '') + '"></i>';
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', wire);
    else wire();
    CDM.wire = wire;
})();
