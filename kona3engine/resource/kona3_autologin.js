/** kona3_autologin.js */

const KONA3SALT = 'jSPvIkILX2Lx4gng#Uq0l_j14#'
const HOSTNAME = encodeURIComponent(location.host)
const AUTOLOGIN_KEY = `kona3_autologin_${HOSTNAME}`

function kona3setAutoLogin (email, token) {
    data = {
        email: xorToken(email, KONA3SALT),
        token: xorToken(token, KONA3SALT),
        time: new Date().getTime(),
    }
    localStorage.setItem(AUTOLOGIN_KEY, JSON.stringify(data))
}

function kona3tryAutologin () {
    const data = localStorage.getItem(AUTOLOGIN_KEY)
    if (!data) { return false }
    const {email, token, time} = JSON.parse(data)
    const email3 = xorToken(email, KONA3SALT)
    const token3 = xorToken(token, KONA3SALT)
    kona3login(email3, token3)
    return true;
}

function kona3login (email, token) {
    const href = location.href + `&a_mode=autologin&token=${encodeURIComponent(token)}&email=${email}`
    fetch(href)
        .then((response) => response.json())
        .then((data) => {
            if (data.token) {
                kona3setAutoLogin(email, data.token)
                if (data.nextUrl) {
                    location.href = data.nextUrl
                }
            }
        });
}

function xorToken (token, salt) {
    var result = "";
    for (var i = 0; i < token.length; i++) {
        result += String.fromCharCode(token.charCodeAt(i) ^ salt.charCodeAt(i % salt.length))
    }
    return result;
}
