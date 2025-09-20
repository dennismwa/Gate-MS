/**
 * Service Worker for GatePass Pro
 * Provides offline functionality and caching
 */

const CACHE_NAME = 'gatepass-pro-v1.0.0';
const urlsToCache = [
    '/',
    '/index.php',
    '/assets/css/style.css',
    '/assets/js/app.js',
    'https://cdn.tailwindcss.com',
    'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css',
    'https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.5.3/qrcode.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/qr-scanner/1.4.2/qr-scanner.umd.min.js'
];

// Install Service Worker
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
    );
});

// Fetch event
self.addEventListener('fetch', function(event) {
    event.respondWith(
        caches.match(event.request)
            .then(function(response) {
                // Return cached version or fetch from network
                if (response) {
                    return response;
                }

                return fetch(event.request).then(
                    function(response) {
                        // Check if we received a valid response
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        // Clone the response
                        var responseToCache = response.clone();

                        caches.open(CACHE_NAME)
                            .then(function(cache) {
                                cache.put(event.request, responseToCache);
                            });

                        return response;
                    }
                );
            })
    );
});

// Activate event
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Background sync for offline actions
self.addEventListener('sync', function(event) {
    if (event.tag === 'background-checkin') {
        event.waitUntil(syncCheckins());
    } else if (event.tag === 'background-checkout') {
        event.waitUntil(syncCheckouts());
    }
});

// Push notification handling
self.addEventListener('push', function(event) {
    const options = {
        body: event.data ? event.data.text() : 'New notification from GatePass Pro',
        icon: '/assets/icons/icon-192x192.png',
        badge: '/assets/icons/badge-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            url: '/'
        }
    };

    event.waitUntil(
        self.registration.showNotification('GatePass Pro', options)
    );
});

// Notification click handling
self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    event.waitUntil(
        clients.matchAll().then(function(clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url === event.notification.data.url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(event.notification.data.url);
            }
        })
    );
});

// Helper functions for background sync
function syncCheckins() {
    return new Promise(function(resolve, reject) {
        // Get pending check-ins from IndexedDB
        const request = indexedDB.open('gatepass-offline', 1);
        
        request.onsuccess = function(event) {
            const db = event.target.result;
            const transaction = db.transaction(['checkins'], 'readonly');
            const store = transaction.objectStore('checkins');
            const getAllRequest = store.getAll();
            
            getAllRequest.onsuccess = function() {
                const checkins = getAllRequest.result;
                
                // Sync each check-in
                const syncPromises = checkins.map(checkin => {
                    return fetch('/api/checkin', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(checkin.data)
                    }).then(response => {
                        if (response.ok) {
                            // Remove from offline storage
                            const deleteTransaction = db.transaction(['checkins'], 'readwrite');
                            const deleteStore = deleteTransaction.objectStore('checkins');
                            deleteStore.delete(checkin.id);
                        }
                    });
                });
                
                Promise.all(syncPromises).then(() => resolve()).catch(() => reject());
            };
        };
        
        request.onerror = function() {
            reject();
        };
    });
}

function syncCheckouts() {
    return new Promise(function(resolve, reject) {
        // Similar implementation for check-outs
        const request = indexedDB.open('gatepass-offline', 1);
        
        request.onsuccess = function(event) {
            const db = event.target.result;
            const transaction = db.transaction(['checkouts'], 'readonly');
            const store = transaction.objectStore('checkouts');
            const getAllRequest = store.getAll();
            
            getAllRequest.onsuccess = function() {
                const checkouts = getAllRequest.result;
                
                const syncPromises = checkouts.map(checkout => {
                    return fetch('/api/checkout', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(checkout.data)
                    }).then(response => {
                        if (response.ok) {
                            const deleteTransaction = db.transaction(['checkouts'], 'readwrite');
                            const deleteStore = deleteTransaction.objectStore('checkouts');
                            deleteStore.delete(checkout.id);
                        }
                    });
                });
                
                Promise.all(syncPromises).then(() => resolve()).catch(() => reject());
            };
        };
        
        request.onerror = function() {
            reject();
        };
    });
}

// Initialize IndexedDB for offline storage
self.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'INIT_DB') {
        initializeOfflineDB();
    }
});

function initializeOfflineDB() {
    const request = indexedDB.open('gatepass-offline', 1);
    
    request.onupgradeneeded = function(event) {
        const db = event.target.result;
        
        if (!db.objectStoreNames.contains('checkins')) {
            const checkinStore = db.createObjectStore('checkins', { keyPath: 'id', autoIncrement: true });
            checkinStore.createIndex('timestamp', 'timestamp', { unique: false });
        }
        
        if (!db.objectStoreNames.contains('checkouts')) {
            const checkoutStore = db.createObjectStore('checkouts', { keyPath: 'id', autoIncrement: true });
            checkoutStore.createIndex('timestamp', 'timestamp', { unique: false });
        }
        
        if (!db.objectStoreNames.contains('visitors')) {
            const visitorStore = db.createObjectStore('visitors', { keyPath: 'id' });
            visitorStore.createIndex('name', 'full_name', { unique: false });
            visitorStore.createIndex('phone', 'phone', { unique: false });
        }
        
        if (!db.objectStoreNames.contains('vehicles')) {
            const vehicleStore = db.createObjectStore('vehicles', { keyPath: 'id' });
            vehicleStore.createIndex('plate', 'plate_number', { unique: false });
        }
    };
}

// Cache API responses for offline use
self.addEventListener('fetch', function(event) {
    // Only cache GET requests to our API
    if (event.request.url.includes('/api/') && event.request.method === 'GET') {
        event.respondWith(
            caches.open('api-cache').then(function(cache) {
                return cache.match(event.request).then(function(response) {
                    if (response) {
                        // Serve from cache
                        fetch(event.request).then(function(fetchResponse) {
                            cache.put(event.request, fetchResponse.clone());
                        }).catch(function() {
                            // Network error, but we have cache
                        });
                        return response;
                    }
                    
                    // Fetch from network and cache
                    return fetch(event.request).then(function(fetchResponse) {
                        cache.put(event.request, fetchResponse.clone());
                        return fetchResponse;
                    }).catch(function() {
                        // Return offline message
                        return new Response(JSON.stringify({
                            error: 'Network unavailable',
                            offline: true
                        }), {
                            headers: { 'Content-Type': 'application/json' }
                        });
                    });
                });
            })
        );
    }
});

// Periodic background sync
self.addEventListener('periodicsync', function(event) {
    if (event.tag === 'content-sync') {
        event.waitUntil(syncOfflineData());
    }
});

function syncOfflineData() {
    return Promise.all([
        syncCheckins(),
        syncCheckouts(),
        updateCachedData()
    ]);
}

function updateCachedData() {
    // Update cached visitor and vehicle data
    return fetch('/api/visitors')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                return caches.open('api-cache').then(cache => {
                    cache.put('/api/visitors', new Response(JSON.stringify(data)));
                });
            }
        })
        .catch(() => {
            // Silently fail if network is unavailable
        });
}