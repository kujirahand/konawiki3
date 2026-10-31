(function () {
    'use strict';

    var STORAGE_KEY = 'kona3_edit_highlight';
    var DEBUG_STORAGE_KEY = 'kona3_edit_highlight_debug';

    var ta = null;
    var modeSelect = null;
    var wrap = null;
    var backdrop = null;
    var content = null;
    var resizeObserver = null;
    var renderFrame = null;
    var renderFrameIsTimeout = false;
    var isComposing = false;
    var MAX_CHARS = 200000;
    var enabled = false;
    var lastValue = null;
    var lastHtml = null;
    var valuePrototypeDescriptor = null;
    var setRangeTextPrototype = null;
    var handlers = {};

    function readStorage(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    function writeStorage(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            // Storage may be unavailable; the runtime state still changes.
        }
    }

    function currentMode() {
        return modeSelect && modeSelect.value === 'Markdown'
            ? 'Markdown'
            : 'KonaNotation';
    }

    function warnAndDisable(error) {
        if (window.console && typeof window.console.warn === 'function') {
            window.console.warn('KonaWiki3 edit highlighting was disabled.', error);
        }
        disable();
    }

    // Automatic (non-user) disabling: tell the user in the status bar
    // info field and fall back to the plain textarea. This never
    // persists an opt-out; re-enabling from the drawer is possible.
    function autoDisable(message) {
        try {
            var info = document.getElementById('edit_info');
            if (info) {
                info.textContent = message;
            }
            if (window.console && typeof window.console.info === 'function') {
                window.console.info('KonaWiki3 edit highlighting: ' + message);
            }
        } catch (error) {
            // Notification is best-effort.
        }
        disable();
    }

    function syncScroll() {
        if (!enabled || !ta || !backdrop) {
            return;
        }

        backdrop.scrollTop = ta.scrollTop;
        backdrop.scrollLeft = ta.scrollLeft;
    }

    function updateGutter() {
        if (!enabled || !ta || !backdrop) {
            return;
        }

        try {
            var style = window.getComputedStyle(ta);
            var borderLeft = parseFloat(style.borderLeftWidth) || 0;
            var borderRight = parseFloat(style.borderRightWidth) || 0;
            var gutter = ta.offsetWidth - ta.clientWidth - borderLeft - borderRight;

            backdrop.style.paddingRight = (16 + Math.max(0, gutter)) + 'px';
        } catch (error) {
            warnAndDisable(error);
        }
    }

    function render() {
        if (!enabled || !ta || !content ||
                typeof window.k3hlRender !== 'function') {
            return;
        }

        if (isComposing) {
            // Do not touch the backdrop during IME composition; the
            // compositionend handler renders the committed text.
            return;
        }

        try {
            var value = ta.value;
            if (value.length > MAX_CHARS) {
                autoDisable('本文が大きいため、シンタックスハイライトを無効化しました');
                return;
            }
            var html = window.k3hlRender(value, currentMode());

            lastValue = value;
            if (html !== lastHtml) {
                content.innerHTML = html;
                lastHtml = html;
            }

            syncScroll();
            updateGutter();
        } catch (error) {
            warnAndDisable(error);
        }
    }

    function scheduleRender() {
        if (!enabled || renderFrame !== null) {
            return;
        }

        if (typeof window.requestAnimationFrame === 'function') {
            renderFrameIsTimeout = false;
            renderFrame = window.requestAnimationFrame(function () {
                renderFrame = null;
                render();
            });
        } else {
            renderFrameIsTimeout = true;
            renderFrame = window.setTimeout(function () {
                renderFrame = null;
                render();
            }, 0);
        }
    }

    function safetyCheck() {
        if (enabled && !isComposing && ta && ta.value !== lastValue) {
            scheduleRender();
        }
    }

    function installHooks() {
        valuePrototypeDescriptor = Object.getOwnPropertyDescriptor(
            window.HTMLTextAreaElement.prototype,
            'value'
        );

        if (!valuePrototypeDescriptor ||
                typeof valuePrototypeDescriptor.get !== 'function' ||
                typeof valuePrototypeDescriptor.set !== 'function') {
            throw new Error('HTMLTextAreaElement.value descriptor is unavailable.');
        }

        Object.defineProperty(ta, 'value', {
            configurable: true,
            enumerable: valuePrototypeDescriptor.enumerable,
            get: function () {
                return valuePrototypeDescriptor.get.call(this);
            },
            set: function (value) {
                valuePrototypeDescriptor.set.call(this, value);
                scheduleRender();
            }
        });

        setRangeTextPrototype =
            window.HTMLTextAreaElement.prototype.setRangeText;

        if (typeof setRangeTextPrototype === 'function') {
            Object.defineProperty(ta, 'setRangeText', {
                configurable: true,
                writable: true,
                value: function () {
                    var result = setRangeTextPrototype.apply(this, arguments);
                    scheduleRender();
                    return result;
                }
            });
        }
    }

    function addListeners() {
        handlers.input = scheduleRender;
        handlers.scroll = syncScroll;
        handlers.keyup = safetyCheck;
        handlers.focus = safetyCheck;
        handlers.selectionchange = safetyCheck;
        handlers.modeChange = render;
        handlers.compositionStart = function () {
            isComposing = true;
        };
        handlers.compositionEnd = function () {
            isComposing = false;
            render();
        };
        handlers.resize = function () {
            updateGutter();
            scheduleRender();
        };

        ta.addEventListener('input', handlers.input);
        ta.addEventListener('scroll', handlers.scroll, { passive: true });
        ta.addEventListener('keyup', handlers.keyup);
        ta.addEventListener('focus', handlers.focus);
        ta.addEventListener('compositionstart', handlers.compositionStart);
        ta.addEventListener('compositionend', handlers.compositionEnd);
        document.addEventListener(
            'selectionchange',
            handlers.selectionchange
        );
        window.addEventListener('resize', handlers.resize);

        if (modeSelect) {
            modeSelect.addEventListener('change', handlers.modeChange);
        }

        if (typeof window.ResizeObserver === 'function') {
            resizeObserver = new window.ResizeObserver(updateGutter);
            resizeObserver.observe(ta);
        }
    }

    function removeListeners() {
        if (ta) {
            if (handlers.input) {
                ta.removeEventListener('input', handlers.input);
            }
            if (handlers.scroll) {
                ta.removeEventListener('scroll', handlers.scroll);
            }
            if (handlers.keyup) {
                ta.removeEventListener('keyup', handlers.keyup);
            }
            if (handlers.focus) {
                ta.removeEventListener('focus', handlers.focus);
            }
            if (handlers.compositionStart) {
                ta.removeEventListener('compositionstart', handlers.compositionStart);
            }
            if (handlers.compositionEnd) {
                ta.removeEventListener('compositionend', handlers.compositionEnd);
            }
        }

        if (handlers.selectionchange) {
            document.removeEventListener(
                'selectionchange',
                handlers.selectionchange
            );
        }
        if (handlers.resize) {
            window.removeEventListener('resize', handlers.resize);
        }
        if (modeSelect && handlers.modeChange) {
            modeSelect.removeEventListener('change', handlers.modeChange);
        }

        handlers = {};
    }

    function enable() {
        if (enabled || typeof window.k3hlRender !== 'function') {
            return;
        }

        ta = document.getElementById('edit_txt');
        if (!ta || !ta.parentNode) {
            ta = null;
            return;
        }

        var viewSnapshot = snapshotView(ta);

        try {
            modeSelect = document.getElementById('page_mode');

            wrap = document.createElement('div');
            wrap.className = 'k3hl-wrap';

            backdrop = document.createElement('pre');
            backdrop.className = 'k3hl-backdrop';
            backdrop.setAttribute('aria-hidden', 'true');

            content = document.createElement('code');
            content.className = 'k3hl-content';
            backdrop.appendChild(content);

            ta.parentNode.insertBefore(wrap, ta);
            wrap.appendChild(backdrop);
            wrap.appendChild(ta);

            ta.classList.add('k3hl-on');
            if (readStorage(DEBUG_STORAGE_KEY) === '1') {
                ta.classList.add('k3hl-debug');
            }

            enabled = true;
            installHooks();
            addListeners();
            render();
            restoreView(ta, viewSnapshot);
            scheduleVerify();
        } catch (error) {
            warnAndDisable(error);
        }
    }

    function disable() {
        var viewSnapshot = ta ? snapshotView(ta) : null;
        enabled = false;
        isComposing = false;

        if (renderFrame !== null) {
            if (renderFrameIsTimeout) {
                window.clearTimeout(renderFrame);
            } else if (typeof window.cancelAnimationFrame === 'function') {
                window.cancelAnimationFrame(renderFrame);
            }
            renderFrame = null;
        }

        try {
            removeListeners();
        } catch (error) {
            // Continue cleanup.
        }

        if (resizeObserver) {
            try {
                resizeObserver.disconnect();
            } catch (error) {
                // Continue cleanup.
            }
            resizeObserver = null;
        }

        if (ta) {
            try {
                delete ta.value;
            } catch (error) {
                // Continue cleanup.
            }

            try {
                delete ta.setRangeText;
            } catch (error) {
                // Continue cleanup.
            }

            ta.classList.remove('k3hl-on');
            ta.classList.remove('k3hl-debug');
        }

        if (wrap && wrap.parentNode) {
            try {
                if (ta) {
                    wrap.parentNode.insertBefore(ta, wrap);
                }
                wrap.parentNode.removeChild(wrap);
            } catch (error) {
                // The DOM may already have been changed externally.
            }
        }

        if (ta) {
            restoreView(ta, viewSnapshot);
            scheduleVerify();
        }

        wrap = null;
        backdrop = null;
        content = null;
        modeSelect = null;
        lastValue = null;
        lastHtml = null;
        valuePrototypeDescriptor = null;
        setRangeTextPrototype = null;
    }

    // Self-verification: if the backdrop's content height disagrees
    // with the textarea's by more than one line, the two layers cannot
    // be aligned in this environment (font, zoom, extension), so turn
    // the highlight off instead of showing shifted text.
    function verifyAlignment() {
        if (!enabled || !ta || !backdrop) {
            return;
        }
        try {
            var backdropHeight = backdrop.scrollHeight || 0;
            var textareaHeight = ta.scrollHeight || 0;
            if (!backdropHeight || !textareaHeight) {
                return;
            }
            var lineHeight = parseFloat(window.getComputedStyle(ta).lineHeight) || 27.2;
            if (Math.abs(backdropHeight - textareaHeight) > lineHeight) {
                autoDisable('シンタックスハイライトの位置合わせに失敗したため無効化しました');
            }
        } catch (error) {
            // Verification is best-effort.
        }
    }

    function scheduleVerify() {
        var run = function () {
            verifyAlignment();
        };
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(run);
        } else {
            window.setTimeout(run, 0);
        }
        if (document.fonts && document.fonts.ready &&
                typeof document.fonts.ready.then === 'function') {
            document.fonts.ready.then(function () {
                verifyAlignment();
            });
        }
    }

    // Moving a textarea in the DOM can reset its scroll position,
    // selection and focus in some browsers; snapshot and restore them
    // around enable/disable.
    function snapshotView(el) {
        var snap = null;
        try {
            snap = {
                scrollTop: el.scrollTop,
                scrollLeft: el.scrollLeft,
                selectionStart: el.selectionStart,
                selectionEnd: el.selectionEnd,
                height: el.style ? el.style.height : ''
            };
        } catch (error) {
            snap = null;
        }
        return snap;
    }

    function restoreView(el, snap) {
        if (!el || !snap) {
            return;
        }
        try {
            if (snap.height) {
                el.style.height = snap.height;
            }
            el.scrollTop = snap.scrollTop;
            el.scrollLeft = snap.scrollLeft;
            if (typeof snap.selectionStart === 'number' &&
                    typeof el.setSelectionRange === 'function' &&
                    document.activeElement === el) {
                el.setSelectionRange(snap.selectionStart, snap.selectionEnd);
            }
        } catch (error) {
            // View state is best-effort.
        }
    }

    function isEnabled() {
        return enabled;
    }

    function renderNow() {
        render();
    }

    function init() {
        var textarea = document.getElementById('edit_txt');
        if (!textarea || typeof window.k3hlRender !== 'function') {
            return;
        }

        // On by default; only an explicit opt-out ('0') keeps it off.
        if (readStorage(STORAGE_KEY) !== '0') {
            enable();
        }
    }

    // The public API persists the choice; the internal enable/disable
    // (also used for error recovery) must not, so a transient failure
    // never turns the feature off for good.
    window.kona3EditHighlight = {
        enable: function () {
            writeStorage(STORAGE_KEY, '1');
            enable();
        },
        disable: function () {
            writeStorage(STORAGE_KEY, '0');
            disable();
        },
        isEnabled: isEnabled,
        renderNow: renderNow
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
