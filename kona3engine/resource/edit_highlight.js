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

        try {
            var value = ta.value;
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
        if (enabled && ta && ta.value !== lastValue) {
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
        handlers.resize = function () {
            updateGutter();
            scheduleRender();
        };

        ta.addEventListener('input', handlers.input);
        ta.addEventListener('scroll', handlers.scroll, { passive: true });
        ta.addEventListener('keyup', handlers.keyup);
        ta.addEventListener('focus', handlers.focus);
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
        } catch (error) {
            warnAndDisable(error);
        }
    }

    function disable() {
        var viewSnapshot = ta ? snapshotView(ta) : null;
        enabled = false;

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
