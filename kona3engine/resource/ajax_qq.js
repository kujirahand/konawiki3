// 簡単なDOM操作の関数群
function qs(id) { return document.querySelector(id); }
function qsa(id) { return document.querySelectorAll(id); }
function prop(id, key) {
    const e = qs(id);
    if (!e) { return ''; }
    return e.getAttribute(key);
}
function setEnabled(id, enabled) {
    let e = id;
    if (typeof (id) === 'string') { e = qs(id); }
    if (!e) { return false; }
    e.disabled = !enabled;
}
function getEnabled(id) {
    let e = id;
    if (typeof (id) === 'string') { e = qs(id); }
    if (!e) { return false; }
    return !e.disabled;
}
function qq(id) {
    let e = id;
    if (id === undefined) { e = window } // dummy
    if (typeof (id) === 'string') {
        e = document.querySelector(id);
    }
    if (!e) {
        console.warn('qq: not found', id);
        return null;
    }
    const obj = {}
    const events = {}
    obj.click = (f) => {
        e.addEventListener('click', f);
    };
    obj.keydown = (f) => {
        e.addEventListener('keydown', f);
    };
    obj.change = (f) => {
        e.addEventListener('change', f);
    };
    obj.attr = (key, val) => {
        if (val !== undefined) {
            return e.setAttribute(key, val);
        }
        return e.getAttribute(key, val);
    };
    obj.prop = (key, val) => {
        if (val !== undefined) {
            e[key] = val;
        }
        return e[key];
    };
    obj.html = (val) => {
        if (val !== undefined) {
            e.innerHTML = val;
        }
        return e.innerHTML;
    };
    obj.val = (val) => {
        if (val !== undefined) {
            e.value = val;
        }
        return e.value;
    };
    obj.text = (val) => {
        if (val !== undefined) {
            e.innerText = val;
        }
        return e.innerText;
    };
    obj.css = (styleName, val) => {
        if (val !== undefined) {
            e.style[styleName] = val;
        }
        return e.style[styleName];
    };
    obj.on = (event, f) => {
        e.addEventListener(event, f);
        if (events[event] === undefined) {
            events[event] = [];
        }
        events[event].push(f);
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
    };
    obj.enabled = (val) => {
        if (val !== undefined) {
            e.disabled = !val;
        }
        return !e.disabled;
    };
    obj.show = () => {
        e.style.display = 'block';
    }
    obj.hide = () => {
        e.style.display = 'none';
    }
    obj.append = (child) => {
        e.appendChild(child);
    }
    // ajax method
    obj.post = (url, dataObj) => {
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
    return obj;
}
