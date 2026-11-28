/**
 * Grid402 - Helper Unificado de Persistencia de Session Pass (Zero-PII)
 * Compartilha o token ativo `pass_g402_...` entre Home (/), /pass, /dossie-kyb e /payment-dashboard,
 * mantendo leitura retrocompativel das chaves legadas (grid402_last_pass_token e grid402_session_pass).
 */
(function (global) {
    'use strict';

    var CANONICAL_KEY = 'grid402_active_pass_token';
    var LEGACY_KEYS = ['grid402_session_pass', 'grid402_last_pass_token'];
    var COOKIE_NAME = 'g402_pass_token';
    var COOKIE_MAX_AGE = 2592000; // 30 dias

    function isValidToken(token) {
        return typeof token === 'string' && /^pass_g402_[a-z0-9_]{8,24}_[a-f0-9]{16}$/i.test(token.trim());
    }

    function readCookie(name) {
        try {
            var pairs = (document.cookie || '').split(';');
            for (var i = 0; i < pairs.length; i++) {
                var part = pairs[i].trim();
                if (part.indexOf(name + '=') === 0) {
                    return decodeURIComponent(part.slice(name.length + 1)).trim();
                }
            }
        } catch (_) {}
        return '';
    }

    function writeCookie(token) {
        try {
            var base = COOKIE_NAME + '=' + encodeURIComponent(token) + '; path=/; max-age=' + COOKIE_MAX_AGE + '; SameSite=Lax';
            document.cookie = base + (global.location && global.location.protocol === 'https:' ? '; Secure' : '');
        } catch (_) {}
    }

    function clearCookie() {
        try {
            document.cookie = COOKIE_NAME + '=; path=/; max-age=0; SameSite=Lax';
        } catch (_) {}
    }

    function load() {
        var candidate = '';
        try {
            candidate = (global.localStorage.getItem(CANONICAL_KEY) || '').trim();
            if (isValidToken(candidate)) return candidate;
        } catch (_) {}

        candidate = readCookie(COOKIE_NAME);
        if (isValidToken(candidate)) {
            try { global.localStorage.setItem(CANONICAL_KEY, candidate); } catch (_) {}
            return candidate;
        }

        for (var i = 0; i < LEGACY_KEYS.length; i++) {
            try {
                candidate = (global.localStorage.getItem(LEGACY_KEYS[i]) || '').trim();
                if (isValidToken(candidate)) {
                    save(candidate);
                    return candidate;
                }
            } catch (_) {}
        }

        return '';
    }

    function save(token) {
        var clean = typeof token === 'string' ? token.trim() : '';
        if (!isValidToken(clean)) return false;
        try {
            global.localStorage.setItem(CANONICAL_KEY, clean);
            for (var i = 0; i < LEGACY_KEYS.length; i++) {
                global.localStorage.setItem(LEGACY_KEYS[i], clean);
            }
        } catch (_) {}
        writeCookie(clean);
        return true;
    }

    function clear() {
        try {
            global.localStorage.removeItem(CANONICAL_KEY);
            for (var i = 0; i < LEGACY_KEYS.length; i++) {
                global.localStorage.removeItem(LEGACY_KEYS[i]);
            }
        } catch (_) {}
        clearCookie();
    }

    global.Grid402PassStorage = {
        CANONICAL_KEY: CANONICAL_KEY,
        COOKIE_NAME: COOKIE_NAME,
        isValidToken: isValidToken,
        load: load,
        save: save,
        clear: clear
    };
})(window);
