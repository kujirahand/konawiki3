// ajax library quick quick for konawiki3
function qs(id) { return document.querySelector(id); }
function qq(id) {
    const obj = {}
    const events = {}
    let e = id;
    if (id === undefined) { e = window } // dummy
    if (typeof (id) === 'string') {
        e = document.querySelector(id);
    }
    if (!e) {
        console.warn('qq: not found', id);
        return null;
    }
    if (typeof (id) === 'function') {
        document.addEventListener('DOMContentLoaded', id);
    }
    // set methods
    obj.ready = (f) => {
        document.addEventListener('DOMContentLoaded', f);
        return obj;
    };
    obj.click = (f) => {
        e.addEventListener('click', f);
        return obj;
    };
    obj.keydown = (f) => {
        e.addEventListener('keydown', f);
        return obj;
    };
    obj.change = (f) => {
        e.addEventListener('change', f);
        return obj;
    };
    obj.attr = (key, val) => {
        if (val !== undefined) {
            e.setAttribute(key, val);
            return obj;
        }
        return e.getAttribute(key, val);
    };
    obj.prop = (key, val) => {
        if (val !== undefined) {
            e[key] = val;
            return obj;
        }
        return e[key];
    };
    obj.html = (val) => {
        if (val !== undefined) {
            e.innerHTML = val;
            return obj;
        }
        return e.innerHTML;
    };
    obj.val = (val) => {
        if (val !== undefined) {
            if (e.type == 'checkbox') {
                e.checked = val;
                return obj;
            }
            e.value = val;
            return obj;
        }
        if (e.type == 'checkbox') {
            return e.checked;
        }
        return e.value;
    };
    obj.text = (val) => {
        if (val !== undefined) {
            e.innerText = val;
            return obj;
        }
        return e.innerText;
    };
    obj.css = (styleName, val) => {
        if (typeof (styleName) === 'object') {
            for (const key in styleName) {
                if (styleName.hasOwnProperty(key)) {
                    e.style[key] = styleName[key];
                }
            }
            return obj;
        }
        if (val !== undefined) {
            e.style[styleName] = val;
            return obj;
        }
        return e.style[styleName];
    };
    obj.on = (event, f) => {
        e.addEventListener(event, f);
        if (events[event] === undefined) {
            events[event] = [];
        }
        events[event].push(f);
        return obj;
    };
    obj.off = (event, f) => {
        if (f !== undefined) {
            e.removeEventListener(event, f);
        } else {
            if (events[event] !== undefined) {
                events[event].forEach((f) => {
                    e.removeEventListener(event, f);
                });
            }
        }
        return obj;
    };
    obj.enabled = (val) => {
        if (val !== undefined) {
            e.disabled = !val;
            return obj;
        }
        return !e.disabled;
    };
    obj.show = () => {
        e.style.display = 'block';
        return obj;
    }
    obj.hide = () => {
        e.style.display = 'none';
        return obj;
    }
    obj.toggle = () => {
        if (e.style.display == 'none') {
            e.style.display = 'block';
        } else {
            e.style.display = 'none';
        }
        return obj;
    }
    obj.append = (child) => {
        e.appendChild(child);
        return obj;
    }
    // ajax method
    obj.post = (url, dataObj, callback) => {
        // make FormData
        let formData = new FormData();
        if (dataObj instanceof FormData) {
            formData = dataObj;
        } else {
            for (const key in dataObj) {
                if (dataObj.hasOwnProperty(key)) {
                    formData.append(key, dataObj[key]);
                }
            }
        }
        // make request
        setTimeout(() => {
            // fetch
            fetch(url, {
                method: 'POST',
                body: formData,
            })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Network response was not ok.');
                }
                return response.text();
            })
            .then((text) => {
                if (typeof (text) == 'string') {
                    try {
                        jsonObj = JSON.parse(text);
                    } catch (e) {
                        jsonObj = { "result": false, "reason": text };
                    }
                }
                if (typeof (callback) == 'function') {
                    callback(jsonObj);
                }
                obj._done(jsonObj);
            })
            .catch((error) => {
                obj._fail(error);
            })
        }, 1)
        return obj;
    };
    obj._done = (_f) => { }
    obj._fail = (_f) => { }
    obj.done = (f) => { obj._done = f; return obj; }
    obj.fail = (f) => { obj._fail = f; return obj; }
    obj.hasClass = (className) => {
        return e.classList.contains(className);
    }
    obj.addClass = (className) => {
        e.classList.add(className);
        return obj;
    }
    obj.removeClass = (className) => {
        e.classList.remove(className);
        return obj;
    }
    obj.stop = () => {
        return obj;
    }
    obj.animate = (styles, duration) => {
        setTimeout(() => {
            for (const key in styles) {
                if (styles.hasOwnProperty(key)) {
                    e.style[key] = styles[key];
                }
            }
        }, duration);
        return obj;
    }
    obj.fadeIn = (duration, f) => {
        e.style.opacity = 0.5;
        e.style.display = 'block';
        setTimeout(() => {
            e.style.opacity = 1;
            if (typeof f === 'function') { f(); }
        }, duration);
        return obj;
    }
    obj.fadeOut = (duration, f) => {
        e.style.opacity = 0.5;
        setTimeout(() => {
            e.style.opacity = 0;
            e.style.display = 'none';
            if (typeof f === 'function') { f(); }
        }, duration);
        return obj;
    }
    obj.focus = () => {
        e.focus();
        return obj;
    };
    return obj;
}
if (typeof $ === 'undefined') {
    $ = qq;
}

