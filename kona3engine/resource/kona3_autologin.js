/** kona3_autologin.js */

const KONA3SALT = 'jSPvIkILX2Lx4gng#Uq0l_j14#'
const HOSTNAME = encodeURIComponent(window.location.host)
const AUTOLOGIN_KEY = `kona3_autologin_${HOSTNAME}`
const kona3autologinInfo = {
    flagRedirect: false,
}

function kona3setAutoLogin (email, token) {
    data = {
        email: xorToken(email, KONA3SALT),
        token: xorToken(token, KONA3SALT),
        time: new Date().getTime(),
    }
    localStorage.setItem(AUTOLOGIN_KEY, JSON.stringify(data))
}

function kona3tryAutologin (autojump) {
    const data = localStorage.getItem(AUTOLOGIN_KEY)
    if (!data) {
        console.log('[kona3tryAutologin] no data')
        return false
    }
    const {email, token, time} = JSON.parse(data)
    const email3 = xorToken(email, KONA3SALT)
    const token3 = xorToken(token, KONA3SALT)
    kona3login(email3, token3, autojump)
    return true;
}

function kona3login (email, token, autojump) {
    const page = encodeURIComponent(location.href)
    const href = `index.php?FrontPage&login&a_mode=autologin&token=${encodeURIComponent(token)}&email=${email}&page=${page}`
    fetch(href)
        .then((response) => response.json())
        .then((data) => {
            if (!data.result) {
                console.log(`[konawiki3] (${data.email}) Login failed. ${data.message}`)
                localStorage.removeItem(AUTOLOGIN_KEY)
                return
            }
            if (data.token) {
                console.log('[konawiki3] Login success.')
                kona3setAutoLogin(email, data.token)
                if (autojump) {
                    if (data.nextUrl) {
                        const loginForm = document.querySelector('#user_login_form')
                        if (loginForm) { loginForm.style.display = 'none' }
                        kona3autologinInfo.flagRedirect = true
                        showInfo(data.nextUrl, 5)
                    }
                }
            }
        });
}

function showInfo (nextURL, remain) {
    if (remain <= 0) {
        window.location.href = nextURL
        return
    }
    if (!kona3autologinInfo.flagRedirect) { return; }
    const info = document.querySelector('#loginform .error')
    if (info) {
        console.log('showInfo', nextURL, remain)
        info.innerHTML =
            '<div style="background-color: #fffff0; padding: 1em;">' +
            '<div style="color:green">Login success!</div>' +
            `<div style="font-weight: normal;"><br><a href="${nextURL}">→ Redirecting ... ${remain}/5</a></div>` +
            '</div>'
    }
    setTimeout(()=> {
        showInfo(nextURL, remain - 1)
    }, 300)
}

function xorToken (token, salt) {
    var result = "";
    for (var i = 0; i < token.length; i++) {
        result += String.fromCharCode(token.charCodeAt(i) ^ salt.charCodeAt(i % salt.length))
    }
    return result;
}
