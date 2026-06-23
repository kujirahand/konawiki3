const DB_NAME = 'kona3_offline_db';
const DB_VERSION = 1;
const ASSET_CACHE_NAME = 'kona3-assets-v1';

// Service Worker lifecycle
self.addEventListener('install', function(event) {
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  event.waitUntil(self.clients.claim());
});

// Helper to open IndexedDB in Service Worker
function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onupgradeneeded = function(e) {
      const db = e.target.result;
      if (!db.objectStoreNames.contains('settings')) {
        db.createObjectStore('settings', { keyPath: 'key' });
      }
      if (!db.objectStoreNames.contains('pages')) {
        db.createObjectStore('pages', { keyPath: 'url' });
      }
    };
    request.onsuccess = function(e) {
      resolve(e.target.result);
    };
    request.onerror = function(e) {
      reject(e.target.error);
    };
  });
}

function getSetting(key) {
  return openDB().then(db => {
    return new Promise((resolve, reject) => {
      const tx = db.transaction('settings', 'readonly');
      const store = tx.objectStore('settings');
      const req = store.get(key);
      req.onsuccess = function() {
        resolve(req.result ? req.result.value : false);
      };
      req.onerror = function() {
        reject(req.error);
      };
    });
  });
}

function getPageCache(url) {
  return openDB().then(db => {
    return new Promise((resolve, reject) => {
      const tx = db.transaction('pages', 'readonly');
      const store = tx.objectStore('pages');
      const req = store.get(url);
      req.onsuccess = function() {
        resolve(req.result || null);
      };
      req.onerror = function() {
        reject(req.error);
      };
    });
  });
}

function formatTimestamp(ts) {
  const d = new Date(ts);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const date = String(d.getDate()).padStart(2, '0');
  const h = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${y}/${m}/${date} ${h}:${min}`;
}

const OFFLINE_MSG = {
  ja: '（オフラインのためキャッシュを表示中: %s に保存）',
  en: '(Showing offline cache: saved at %s)'
};

// Fetch event listener to serve offline cache
self.addEventListener('fetch', function(event) {
  const request = event.request;
  const urlObj = new URL(request.url);
  
  // Only intercept document navigations (HTML pages)
  if (request.method === 'GET' && request.mode === 'navigate') {
    event.respondWith(
      getSetting('offline_cache').then(enabled => {
        if (!enabled) {
          return fetch(request);
        }
        
        // Try network first
        return fetch(request).catch(err => {
          // Network failed (offline), check database cache
          const cacheKey = urlObj.pathname + urlObj.search;
          return Promise.all([
            getPageCache(cacheKey),
            getSetting('shell_html')
          ]).then(([cache, shellHtml]) => {
            if (cache && shellHtml) {
              const lang = cache.lang || 'en';
              let msg = cache.msg_tpl || OFFLINE_MSG[lang] || OFFLINE_MSG['en'];
              msg = msg.replace('%s', formatTimestamp(cache.timestamp));
              
              // Injects the warning banner immediately after the main container or body start
              const bannerHtml = `<div class="kona3_offline_banner" style="background-color: #fff3cd; color: #856404; padding: 10px; border: 1px solid #ffeeba; margin-bottom: 15px; border-radius: 4px; font-size: 0.9em; text-align: center;">${msg}</div>`;
              
              const contentWithBanner = bannerHtml + cache.html;
              let modifiedHtml = shellHtml;
              
              if (modifiedHtml.indexOf('<!-- KONA3_CONTENT -->') !== -1) {
                modifiedHtml = modifiedHtml.replace('<!-- KONA3_CONTENT -->', contentWithBanner);
              } else {
                // Fallback: If placeholder is missing, try to append content somewhere
                const targetStr = '<div id="kona3_main">';
                const index = modifiedHtml.indexOf(targetStr);
                if (index !== -1) {
                  modifiedHtml = modifiedHtml.substring(0, index + targetStr.length) + contentWithBanner + modifiedHtml.substring(index + targetStr.length);
                } else {
                  const bodyIndex = modifiedHtml.indexOf('<body>');
                  if (bodyIndex !== -1) {
                    modifiedHtml = modifiedHtml.substring(0, bodyIndex + 6) + contentWithBanner + modifiedHtml.substring(bodyIndex + 6);
                  }
                }
              }
              
              return new Response(modifiedHtml, {
                headers: { 'Content-Type': 'text/html; charset=utf-8' }
              });
            }
            throw err; // No cache, let the browser show default error page
          });
        });
      })
    );
    return;
  }

  // Assets (CSS, JS, fonts. Images are NOT cached per issue #152)
  const isImage = (request.destination === 'image') || 
    /\.(png|jpg|jpeg|gif|svg|ico)(?:\?.*)?$/.test(urlObj.pathname) ||
    /\.(png|jpg|jpeg|gif|svg|ico)(?:&|\?|$)/.test(urlObj.search);

  const isAsset = (request.method === 'GET') && (urlObj.origin === self.location.origin) && !isImage && (
    request.destination === 'style' ||
    request.destination === 'script' ||
    request.destination === 'font' ||
    /\.(css|js|woff2?)(?:\?.*)?$/.test(urlObj.pathname) ||
    /(?:\?|&)(?:resource|skin)(?:&|$)/.test(urlObj.search)
  );

  if (isAsset) {
    event.respondWith(
      getSetting('offline_cache').then(enabled => {
        if (!enabled) {
          return fetch(request);
        }
        
        // Network-First strategy
        return fetch(request).then(response => {
          if (response.ok && response.status === 200) {
            const responseClone = response.clone();
            caches.open(ASSET_CACHE_NAME).then(cache => {
              cache.put(request, responseClone);
            });
          }
          return response;
        }).catch(() => {
          return caches.match(request).then(cachedResponse => {
            if (cachedResponse) {
              return cachedResponse;
            }
            return Promise.reject(new Error('Asset not found in cache and network offline'));
          });
        });
      })
    );
  }
});
