/**
 * Complete Offline Functionality
 * GatePass Pro - Smart Gate Management System
 */

// IndexedDB Manager for offline storage
class OfflineDB {
    constructor() {
        this.db = null;
        this.dbName = 'GatePassOffline';
        this.version = 1;
    }

    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.version);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Create object stores
                if (!db.objectStoreNames.contains('visitors')) {
                    const visitorsStore = db.createObjectStore('visitors', { keyPath: 'id' });
                    visitorsStore.createIndex('phone', 'phone', { unique: false });
                    visitorsStore.createIndex('email', 'email', { unique: false });
                    visitorsStore.createIndex('full_name', 'full_name', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('visits')) {
                    const visitsStore = db.createObjectStore('visits', { keyPath: 'id', autoIncrement: true });
                    visitsStore.createIndex('visitor_id', 'visitor_id', { unique: false });
                    visitsStore.createIndex('status', 'status', { unique: false });
                    visitsStore.createIndex('timestamp', 'timestamp', { unique: false });
                    visitsStore.createIndex('visit_code', 'visit_code', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('vehicles')) {
                    const vehiclesStore = db.createObjectStore('vehicles', { keyPath: 'id' });
                    vehiclesStore.createIndex('plate_number', 'plate_number', { unique: false });
                    vehiclesStore.createIndex('owner_type', 'owner_type', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('offline_queue')) {
                    const queueStore = db.createObjectStore('offline_queue', { keyPath: 'id', autoIncrement: true });
                    queueStore.createIndex('timestamp', 'timestamp', { unique: false });
                    queueStore.createIndex('type', 'type', { unique: false });
                    queueStore.createIndex('priority', 'priority', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('settings')) {
                    db.createObjectStore('settings', { keyPath: 'key' });
                }
                
                if (!db.objectStoreNames.contains('user_data')) {
                    db.createObjectStore('user_data', { keyPath: 'key' });
                }
                
                if (!db.objectStoreNames.contains('form_data')) {
                    const formStore = db.createObjectStore('form_data', { keyPath: 'form_id' });
                    formStore.createIndex('timestamp', 'timestamp', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('qr_cache')) {
                    const qrStore = db.createObjectStore('qr_cache', { keyPath: 'code' });
                    qrStore.createIndex('timestamp', 'timestamp', { unique: false });
                }
            };
        });
    }

    async get(storeName, key) {
        const transaction = this.db.transaction([storeName], 'readonly');
        const store = transaction.objectStore(storeName);
        const request = store.get(key);
        
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async put(storeName, data) {
        const transaction = this.db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);
        const request = store.put(data);
        
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async add(storeName, data) {
        const transaction = this.db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);
        const request = store.add(data);
        
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async delete(storeName, key) {
        const transaction = this.db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);
        const request = store.delete(key);
        
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async getAll(storeName, indexName = null, range = null) {
        const transaction = this.db.transaction([storeName], 'readonly');
        const store = transaction.objectStore(storeName);
        const source = indexName ? store.index(indexName) : store;
        const request = source.getAll(range);
        
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async clear(storeName) {
        const transaction = this.db.transaction([storeName], 'readwrite');
        const store = transaction.objectStore(storeName);
        const request = store.clear();
        
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async count(storeName, indexName = null, range = null) {
        const transaction = this.db.transaction([storeName], 'readonly');
        const store = transaction.objectStore(storeName);
        const source = indexName ? store.index(indexName) : store;
        const request = source.count(range);
        
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
}

// Offline Data Manager
class OfflineDataManager {
    constructor() {
        this.db = new OfflineDB();
        this.syncInProgress = false;
        this.lastSync = null;
        this.syncQueue = [];
        this.maxRetries = 3;
        this.retryDelay = 5000; // 5 seconds
    }

    async init() {
        await this.db.init();
        this.lastSync = await this.getSetting('last_sync');
        
        // Set up periodic sync
        this.setupPeriodicSync();
        
        // Load cached data
        await this.loadCachedData();
        
        console.log('OfflineDataManager initialized');
    }

    async loadCachedData() {
        try {
            // Load essential data for offline use
            const [visitors, vehicles, settings] = await Promise.all([
                this.db.getAll('visitors'),
                this.db.getAll('vehicles'),
                this.db.getAll('settings')
            ]);
            
            // Store in memory for quick access
            this.cachedVisitors = visitors;
            this.cachedVehicles = vehicles;
            this.cachedSettings = settings.reduce((acc, setting) => {
                acc[setting.key] = setting.value;
                return acc;
            }, {});
            
        } catch (error) {
            console.error('Failed to load cached data:', error);
        }
    }

    async cacheVisitor(visitor) {
        try {
            await this.db.put('visitors', {
                ...visitor,
                cached_at: Date.now()
            });
            
            // Update memory cache
            const index = this.cachedVisitors.findIndex(v => v.id === visitor.id);
            if (index >= 0) {
                this.cachedVisitors[index] = visitor;
            } else {
                this.cachedVisitors.push(visitor);
            }
            
        } catch (error) {
            console.error('Failed to cache visitor:', error);
        }
    }

    async getCachedVisitor(id) {
        try {
            return await this.db.get('visitors', id);
        } catch (error) {
            console.error('Failed to get cached visitor:', error);
            return null;
        }
    }

    async searchCachedVisitors(query) {
        try {
            const visitors = this.cachedVisitors || await this.db.getAll('visitors');
            const searchTerm = query.toLowerCase();
            
            return visitors.filter(visitor => 
                visitor.full_name.toLowerCase().includes(searchTerm) ||
                visitor.phone.includes(searchTerm) ||
                (visitor.email && visitor.email.toLowerCase().includes(searchTerm)) ||
                (visitor.company && visitor.company.toLowerCase().includes(searchTerm))
            ).slice(0, 20); // Limit results
            
        } catch (error) {
            console.error('Failed to search cached visitors:', error);
            return [];
        }
    }

    async cacheVehicle(vehicle) {
        try {
            await this.db.put('vehicles', {
                ...vehicle,
                cached_at: Date.now()
            });
            
            // Update memory cache
            const index = this.cachedVehicles.findIndex(v => v.id === vehicle.id);
            if (index >= 0) {
                this.cachedVehicles[index] = vehicle;
            } else {
                this.cachedVehicles.push(vehicle);
            }
            
        } catch (error) {
            console.error('Failed to cache vehicle:', error);
        }
    }

    async searchCachedVehicles(plateNumber) {
        try {
            const vehicles = this.cachedVehicles || await this.db.getAll('vehicles');
            return vehicles.filter(vehicle => 
                vehicle.plate_number.toUpperCase().includes(plateNumber.toUpperCase())
            );
        }
    }

    showOfflineSubmissionNotification() {
        if (window.GatePassApp?.NotificationManager) {
            window.GatePassApp.NotificationManager.show(
                'Form submitted offline. Will sync when connection is restored.',
                'warning'
            );
        }
    }

    showErrorNotification(message) {
        if (window.GatePassApp?.NotificationManager) {
            window.GatePassApp.NotificationManager.show(message, 'error');
        }
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Get all forms with unsaved data
    async getUnsavedForms() {
        const unsaved = [];
        
        for (const [formId] of this.forms) {
            try {
                let saved = null;
                
                if (this.offlineDataManager?.db) {
                    saved = await this.offlineDataManager.db.get('form_data', formId);
                } else {
                    const localData = localStorage.getItem(`form_data_${formId}`);
                    if (localData) {
                        saved = JSON.parse(localData);
                    }
                }
                
                if (saved && Object.keys(saved.data).length > 0) {
                    unsaved.push({
                        formId,
                        timestamp: saved.timestamp,
                        dataKeys: Object.keys(saved.data)
                    });
                }
            } catch (error) {
                console.error(`Failed to check form data for ${formId}:`, error);
            }
        }
        
        return unsaved;
    }
}

// Offline Network Monitor
class NetworkMonitor {
    constructor() {
        this.isOnline = navigator.onLine;
        this.listeners = [];
        this.connectionHistory = [];
        this.setupEventListeners();
        this.recordConnectionStatus();
    }

    setupEventListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.recordConnectionStatus();
            this.notifyListeners('online');
            this.handleOnline();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.recordConnectionStatus();
            this.notifyListeners('offline');
            this.handleOffline();
        });

        // Additional connectivity check
        this.startConnectivityCheck();
    }

    startConnectivityCheck() {
        setInterval(async () => {
            const wasOnline = this.isOnline;
            const actuallyOnline = await this.checkConnectivity();
            
            if (wasOnline !== actuallyOnline) {
                this.isOnline = actuallyOnline;
                this.recordConnectionStatus();
                this.notifyListeners(actuallyOnline ? 'online' : 'offline');
                
                if (actuallyOnline) {
                    this.handleOnline();
                } else {
                    this.handleOffline();
                }
            }
        }, 30000); // Check every 30 seconds
    }

    async checkConnectivity() {
        try {
            // Try to fetch a small resource from our server
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);
            
            const response = await fetch('/api/ping', {
                method: 'HEAD',
                cache: 'no-cache',
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            return response.ok;
        } catch (error) {
            return false;
        }
    }

    recordConnectionStatus() {
        this.connectionHistory.push({
            status: this.isOnline ? 'online' : 'offline',
            timestamp: Date.now()
        });
        
        // Keep only last 100 entries
        if (this.connectionHistory.length > 100) {
            this.connectionHistory = this.connectionHistory.slice(-100);
        }
    }

    onStatusChange(callback) {
        this.listeners.push(callback);
    }

    offStatusChange(callback) {
        this.listeners = this.listeners.filter(listener => listener !== callback);
    }

    notifyListeners(status) {
        this.listeners.forEach(callback => {
            try {
                callback(status);
            } catch (error) {
                console.error('Error in network status listener:', error);
            }
        });
    }

    handleOnline() {
        this.updateUI('online');
        this.showConnectionNotification('online');
        
        // Trigger sync
        if (window.offlineDataManager) {
            setTimeout(() => {
                window.offlineDataManager.syncOfflineActions();
            }, 1000);
        }
    }

    handleOffline() {
        this.updateUI('offline');
        this.showConnectionNotification('offline');
    }

    updateUI(status) {
        // Update network status indicator
        const indicator = document.querySelector('.network-status');
        if (indicator) {
            indicator.className = `network-status ${status}`;
            indicator.innerHTML = status === 'online' 
                ? '<i class="ri-wifi-line text-green-500"></i> Online'
                : '<i class="ri-wifi-off-line text-red-500"></i> Offline';
        }

        // Update app state
        if (window.GatePassApp?.AppState) {
            window.GatePassApp.AppState.isOnline = status === 'online';
        }

        // Update body class
        document.body.classList.toggle('offline-mode', status === 'offline');
    }

    showConnectionNotification(status) {
        const message = status === 'online' 
            ? 'Connection restored' 
            : 'You are offline. Some features may be limited.';
        
        const type = status === 'online' ? 'success' : 'warning';
        
        if (window.GatePassApp?.NotificationManager) {
            window.GatePassApp.NotificationManager.show(message, type, 3000);
        }
    }

    getConnectionStats() {
        const now = Date.now();
        const last24Hours = now - (24 * 60 * 60 * 1000);
        const recentHistory = this.connectionHistory.filter(entry => entry.timestamp > last24Hours);
        
        let onlineTime = 0;
        let offlineTime = 0;
        let disconnections = 0;
        
        for (let i = 0; i < recentHistory.length; i++) {
            const entry = recentHistory[i];
            const nextEntry = recentHistory[i + 1];
            const duration = nextEntry ? (nextEntry.timestamp - entry.timestamp) : (now - entry.timestamp);
            
            if (entry.status === 'online') {
                onlineTime += duration;
            } else {
                offlineTime += duration;
                if (i > 0 && recentHistory[i - 1].status === 'online') {
                    disconnections++;
                }
            }
        }
        
        return {
            currentStatus: this.isOnline ? 'online' : 'offline',
            onlineTime,
            offlineTime,
            disconnections,
            uptime: onlineTime / (onlineTime + offlineTime) * 100
        };
    }
}

// Offline Cache Manager
class CacheManager {
    constructor() {
        this.cacheName = 'gatepass-v1';
        this.apiCacheName = 'gatepass-api-v1';
        this.staticAssets = [
            '/',
            '/index.php',
            '/manifest.json',
            '/assets/js/app.js',
            '/assets/js/scanner.js',
            '/assets/js/offline.js',
            'https://cdn.tailwindcss.com',
            'https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css'
        ];
    }

    async init() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js');
                console.log('Service Worker registered:', registration);
                
                // Listen for updates
                registration.addEventListener('updatefound', () => {
                    this.handleServiceWorkerUpdate(registration);
                });
                
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        }
    }

    handleServiceWorkerUpdate(registration) {
        const newWorker = registration.installing;
        
        newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                // New version available
                this.showUpdateNotification();
            }
        });
    }

    showUpdateNotification() {
        if (window.GatePassApp?.NotificationManager) {
            const notification = window.GatePassApp.NotificationManager.show(
                'A new version is available. Refresh to update.',
                'info',
                0 // Don't auto-dismiss
            );
            
            // Add refresh button
            const refreshBtn = document.createElement('button');
            refreshBtn.textContent = 'Refresh';
            refreshBtn.className = 'ml-3 bg-white text-blue-600 px-3 py-1 rounded text-sm';
            refreshBtn.onclick = () => window.location.reload();
            
            notification.querySelector('span').appendChild(refreshBtn);
        }
    }

    async cacheStaticAssets() {
        if ('caches' in window) {
            try {
                const cache = await caches.open(this.cacheName);
                await cache.addAll(this.staticAssets);
                console.log('Static assets cached');
            } catch (error) {
                console.error('Failed to cache static assets:', error);
            }
        }
    }

    async cacheApiResponse(url, response) {
        if ('caches' in window && response.ok) {
            try {
                const cache = await caches.open(this.apiCacheName);
                
                // Only cache GET requests
                if (url.includes('?') || url.includes('GET')) {
                    await cache.put(url, response.clone());
                }
            } catch (error) {
                console.error('Failed to cache API response:', error);
            }
        }
    }

    async getCachedResponse(url) {
        if ('caches' in window) {
            try {
                const cache = await caches.open(this.apiCacheName);
                const cachedResponse = await cache.match(url);
                
                if (cachedResponse) {
                    // Check if cache is still fresh (5 minutes for API responses)
                    const cacheDate = new Date(cachedResponse.headers.get('date'));
                    const now = new Date();
                    const ageInMinutes = (now - cacheDate) / (1000 * 60);
                    
                    if (ageInMinutes < 5) {
                        return cachedResponse;
                    } else {
                        // Remove stale cache
                        cache.delete(url);
                    }
                }
                
                return null;
            } catch (error) {
                console.error('Failed to get cached response:', error);
                return null;
            }
        }
        return null;
    }

    async clearCache() {
        if ('caches' in window) {
            try {
                const cacheNames = await caches.keys();
                await Promise.all(
                    cacheNames.map(name => caches.delete(name))
                );
                console.log('Cache cleared');
            } catch (error) {
                console.error('Failed to clear cache:', error);
            }
        }
    }

    async getCacheStats() {
        if ('caches' in window) {
            try {
                const cacheNames = await caches.keys();
                const stats = {};
                
                for (const name of cacheNames) {
                    const cache = await caches.open(name);
                    const requests = await cache.keys();
                    stats[name] = requests.length;
                }
                
                return stats;
            } catch (error) {
                console.error('Failed to get cache stats:', error);
                return {};
            }
        }
        return {};
    }
}

// Offline UI Manager
class OfflineUIManager {
    constructor() {
        this.offlineIndicator = null;
        this.syncIndicator = null;
        this.createIndicators();
    }

    createIndicators() {
        this.createOfflineIndicator();
        this.createSyncIndicator();
    }

    createOfflineIndicator() {
        this.offlineIndicator = document.createElement('div');
        this.offlineIndicator.className = 'offline-indicator fixed top-0 left-0 right-0 bg-yellow-500 text-white text-center py-2 z-50 hidden';
        this.offlineIndicator.innerHTML = `
            <div class="flex items-center justify-center px-4">
                <i class="ri-wifi-off-line mr-2"></i>
                <span>You are offline. Some features may be limited.</span>
                <button class="ml-4 underline text-sm" onclick="this.parentElement.parentElement.style.display='none'">
                    Dismiss
                </button>
            </div>
        `;
        
        document.body.appendChild(this.offlineIndicator);
    }

    createSyncIndicator() {
        this.syncIndicator = document.createElement('div');
        this.syncIndicator.className = 'sync-indicator fixed bottom-4 right-4 bg-white border rounded-lg px-3 py-2 shadow-lg text-sm z-40 hidden';
        this.syncIndicator.innerHTML = '<i class="ri-cloud-line"></i> Offline';
        
        document.body.appendChild(this.syncIndicator);
    }

    showOfflineMode() {
        this.offlineIndicator.classList.remove('hidden');
        this.syncIndicator.classList.remove('hidden');
        document.body.classList.add('offline-mode');
        
        // Update sync indicator
        this.syncIndicator.innerHTML = '<i class="ri-cloud-off-line text-red-500"></i> Offline';
        
        // Disable certain UI elements
        this.toggleOfflineElements(true);
    }

    hideOfflineMode() {
        this.offlineIndicator.classList.add('hidden');
        document.body.classList.remove('offline-mode');
        
        // Update sync indicator
        this.syncIndicator.innerHTML = '<i class="ri-cloud-line text-green-500"></i> Online';
        
        // Re-enable UI elements
        this.toggleOfflineElements(false);
    }

    toggleOfflineElements(isOffline) {
        // Disable/enable buttons that require network
        const networkButtons = document.querySelectorAll('[data-requires-network]');
        networkButtons.forEach(button => {
            button.disabled = isOffline;
            if (isOffline) {
                button.classList.add('opacity-50', 'cursor-not-allowed');
                button.title = 'This feature requires internet connection';
            } else {
                button.classList.remove('opacity-50', 'cursor-not-allowed');
                button.title = '';
            }
        });

        // Show/hide offline-only elements
        const offlineElements = document.querySelectorAll('[data-offline-only]');
        offlineElements.forEach(element => {
            element.style.display = isOffline ? 'block' : 'none';
        });

        // Show/hide online-only elements
        const onlineElements = document.querySelectorAll('[data-online-only]');
        onlineElements.forEach(element => {
            element.style.display = isOffline ? 'none' : 'block';
        });

        // Update form placeholders
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.dataset.originalText || submitBtn.textContent;
                submitBtn.dataset.originalText = originalText;
                
                if (isOffline) {
                    submitBtn.innerHTML = '<i class="ri-save-line mr-2"></i>Save Offline';
                } else {
                    submitBtn.textContent = originalText;
                }
            }
        });
    }

    showSyncStatus(status) {
        if (!this.syncIndicator) return;
        
        this.syncIndicator.classList.remove('hidden');
        
        switch (status) {
            case 'syncing':
                this.syncIndicator.innerHTML = '<i class="ri-refresh-line animate-spin text-blue-500"></i> Syncing...';
                this.syncIndicator.className = 'sync-indicator fixed bottom-4 right-4 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 shadow-lg text-sm z-40';
                break;
            case 'synced':
                this.syncIndicator.innerHTML = '<i class="ri-check-line text-green-500"></i> Synced';
                this.syncIndicator.className = 'sync-indicator fixed bottom-4 right-4 bg-green-50 border border-green-200 rounded-lg px-3 py-2 shadow-lg text-sm z-40';
                
                // Hide after 3 seconds
                setTimeout(() => {
                    this.syncIndicator.classList.add('hidden');
                }, 3000);
                break;
            case 'error':
                this.syncIndicator.innerHTML = '<i class="ri-error-warning-line text-red-500"></i> Sync Error';
                this.syncIndicator.className = 'sync-indicator fixed bottom-4 right-4 bg-red-50 border border-red-200 rounded-lg px-3 py-2 shadow-lg text-sm z-40';
                break;
            default:
                this.syncIndicator.classList.add('hidden');
        }
    }

    showOfflineStats(stats) {
        // Create or update offline stats panel
        let statsPanel = document.querySelector('.offline-stats-panel');
        
        if (!statsPanel) {
            statsPanel = document.createElement('div');
            statsPanel.className = 'offline-stats-panel fixed bottom-20 right-4 bg-white border rounded-lg p-4 shadow-lg text-sm z-40 hidden';
            document.body.appendChild(statsPanel);
        }
        
        statsPanel.innerHTML = `
            <h4 class="font-semibold mb-2">Offline Status</h4>
            <div class="space-y-1 text-xs">
                <div>Cached Visitors: ${stats.cached_visitors}</div>
                <div>Cached Vehicles: ${stats.cached_vehicles}</div>
                <div>Pending Sync: ${stats.pending_sync}</div>
                ${stats.failed_sync > 0 ? `<div class="text-red-600">Failed: ${stats.failed_sync}</div>` : ''}
                ${stats.storage_used ? `<div>Storage: ${stats.storage_used.used_mb}MB / ${stats.storage_used.available_mb}MB</div>` : ''}
                ${stats.last_sync ? `<div>Last Sync: ${new Date(stats.last_sync).toLocaleTimeString()}</div>` : ''}
            </div>
            <button onclick="this.parentElement.classList.add('hidden')" class="mt-2 text-gray-500 hover:text-gray-700">
                <i class="ri-close-line"></i>
            </button>
        `;
        
        statsPanel.classList.remove('hidden');
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            statsPanel.classList.add('hidden');
        }, 10000);
    }
}

// Initialize offline functionality
let offlineDataManager;
let offlineFormManager;
let networkMonitor;
let cacheManager;
let offlineUIManager;

// Initialization function
async function initializeOfflineSystem() {
    try {
        console.log('Initializing offline system...');
        
        // Initialize managers
        offlineDataManager = new OfflineDataManager();
        await offlineDataManager.init();
        
        offlineFormManager = new OfflineFormManager();
        offlineFormManager.setOfflineDataManager(offlineDataManager);
        
        networkMonitor = new NetworkMonitor();
        cacheManager = new CacheManager();
        offlineUIManager = new OfflineUIManager();
        
        // Initialize cache
        await cacheManager.init();
        await cacheManager.cacheStaticAssets();
        
        // Set up network monitoring
        networkMonitor.onStatusChange((status) => {
            if (status === 'online') {
                offlineUIManager.hideOfflineMode();
            } else {
                offlineUIManager.showOfflineMode();
            }
        });
        
        // Listen for sync status updates
        if (window.GatePassApp?.EventBus) {
            window.GatePassApp.EventBus.on('sync:status', (status) => {
                offlineUIManager.showSyncStatus(status);
            });
        }
        
        // Register important forms for offline functionality
        setTimeout(() => {
            const formsToRegister = [
                { id: 'quickCheckinForm', action: 'checkin', priority: 3 },
                { id: 'visitorForm', action: 'visitor_create', priority: 2 },
                { id: 'vehicleForm', action: 'vehicle_create', priority: 2 },
                { id: 'preRegistrationForm', action: 'pre_registration', priority: 1 }
            ];
            
            formsToRegister.forEach(({ id, action, priority }) => {
                if (document.getElementById(id)) {
                    offlineFormManager.registerForm(id, {
                        autosave: true,
                        offlineQueue: true,
                        offlineAction: action,
                        priority: priority
                    });
                }
            });
        }, 1000);
        
        // Set initial offline state
        if (!navigator.onLine) {
            offlineUIManager.showOfflineMode();
        }
        
        // Add global keyboard shortcut for offline stats
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'O') {
                e.preventDefault();
                showOfflineStatsDialog();
            }
        });
        
        console.log('Offline system initialized successfully');
        
    } catch (error) {
        console.error('Failed to initialize offline system:', error);
    }
}

// Show offline statistics dialog
async function showOfflineStatsDialog() {
    if (!offlineDataManager) return;
    
    try {
        const stats = await offlineDataManager.getOfflineStats();
        const connectionStats = networkMonitor?.getConnectionStats();
        
        if (stats) {
            offlineUIManager.showOfflineStats(stats);
        }
    } catch (error) {
        console.error('Failed to show offline stats:', error);
    }
}

// Initialize when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeOfflineSystem);
} else {
    initializeOfflineSystem();
}

// Export for global access
window.offlineDataManager = offlineDataManager;
window.OfflineDataManager = OfflineDataManager;
window.OfflineFormManager = OfflineFormManager;
window.NetworkMonitor = NetworkMonitor;
window.CacheManager = CacheManager;
window.OfflineUIManager = OfflineUIManager;
window.initializeOfflineSystem = initializeOfflineSystem;
window.showOfflineStatsDialog = showOfflineStatsDialog;
        } catch (error) {
            console.error('Failed to search cached vehicles:', error);
            return [];
        }
    }

    async queueOfflineAction(type, data, priority = 1) {
        try {
            const action = {
                type,
                data,
                priority,
                timestamp: Date.now(),
                retry_count: 0,
                status: 'pending'
            };
            
            const id = await this.db.add('offline_queue', action);
            action.id = id;
            
            console.log('Offline action queued:', action);
            
            // Try to sync immediately if online
            if (navigator.onLine) {
                setTimeout(() => this.syncOfflineActions(), 1000);
            }
            
            return action;
        } catch (error) {
            console.error('Failed to queue offline action:', error);
            throw error;
        }
    }

    async syncOfflineActions() {
        if (this.syncInProgress || !navigator.onLine) {
            return;
        }
        
        this.syncInProgress = true;
        this.updateSyncStatus('syncing');
        
        try {
            const actions = await this.db.getAll('offline_queue');
            const pendingActions = actions.filter(a => a.status === 'pending');
            
            if (pendingActions.length === 0) {
                this.updateSyncStatus('synced');
                return;
            }
            
            // Sort by priority and timestamp
            pendingActions.sort((a, b) => {
                if (a.priority !== b.priority) {
                    return b.priority - a.priority; // Higher priority first
                }
                return a.timestamp - b.timestamp; // Older first
            });
            
            const processed = [];
            const failed = [];
            
            for (const action of pendingActions) {
                try {
                    await this.processOfflineAction(action);
                    processed.push(action.id);
                    
                    // Update status
                    action.status = 'completed';
                    await this.db.put('offline_queue', action);
                    
                } catch (error) {
                    console.error('Failed to process offline action:', error);
                    
                    // Increment retry count
                    action.retry_count++;
                    action.last_error = error.message;
                    action.last_retry = Date.now();
                    
                    // Remove if too many retries
                    if (action.retry_count >= this.maxRetries) {
                        action.status = 'failed';
                        failed.push(action);
                    } else {
                        action.status = 'retry';
                    }
                    
                    await this.db.put('offline_queue', action);
                }
            }
            
            // Remove completed actions
            for (const id of processed) {
                await this.db.delete('offline_queue', id);
            }
            
            if (processed.length > 0) {
                this.showSyncNotification(processed.length, failed.length);
            }
            
            // Update last sync time
            this.lastSync = Date.now();
            await this.setSetting('last_sync', this.lastSync);
            
            this.updateSyncStatus('synced');
            
        } catch (error) {
            console.error('Sync failed:', error);
            this.updateSyncStatus('error');
        } finally {
            this.syncInProgress = false;
        }
    }

    async processOfflineAction(action) {
        const api = window.GatePassApp?.ApiService || window.api;
        
        if (!api) {
            throw new Error('API service not available');
        }
        
        switch (action.type) {
            case 'checkin':
                return await api.post('checkin', action.data);
                
            case 'checkout':
                return await api.post('checkout', action.data);
                
            case 'visitor_create':
                const visitorResult = await api.post('visitors', action.data);
                if (visitorResult.success && visitorResult.visitor_id) {
                    // Update cached visitor with server ID
                    const visitor = { ...action.data, id: visitorResult.visitor_id };
                    await this.cacheVisitor(visitor);
                }
                return visitorResult;
                
            case 'visitor_update':
                const updateResult = await api.put('visitors', action.data);
                if (updateResult.success) {
                    await this.cacheVisitor(action.data);
                }
                return updateResult;
                
            case 'vehicle_create':
                const vehicleResult = await api.post('vehicles', action.data);
                if (vehicleResult.success && vehicleResult.vehicle_id) {
                    const vehicle = { ...action.data, id: vehicleResult.vehicle_id };
                    await this.cacheVehicle(vehicle);
                }
                return vehicleResult;
                
            case 'vehicle_update':
                const vehicleUpdateResult = await api.put('vehicles', action.data);
                if (vehicleUpdateResult.success) {
                    await this.cacheVehicle(action.data);
                }
                return vehicleUpdateResult;
                
            case 'pre_registration':
                return await api.post('pre-registrations', action.data);
                
            default:
                throw new Error(`Unknown action type: ${action.type}`);
        }
    }

    async cacheBulkData(type, data) {
        try {
            const storeName = this.getStoreNameForType(type);
            
            // Clear existing data
            await this.db.clear(storeName);
            
            // Cache new data
            for (const item of data) {
                await this.db.put(storeName, {
                    ...item,
                    cached_at: Date.now()
                });
            }
            
            await this.setSetting(`${type}_cache_time`, Date.now());
            
            // Update memory cache
            if (type === 'visitors') {
                this.cachedVisitors = data;
            } else if (type === 'vehicles') {
                this.cachedVehicles = data;
            }
            
        } catch (error) {
            console.error(`Failed to cache ${type} data:`, error);
        }
    }

    getStoreNameForType(type) {
        const mapping = {
            'visitors': 'visitors',
            'vehicles': 'vehicles',
            'visits': 'visits',
            'settings': 'settings'
        };
        
        return mapping[type] || type;
    }

    async getSetting(key) {
        try {
            const result = await this.db.get('settings', key);
            return result ? result.value : null;
        } catch (error) {
            return null;
        }
    }

    async setSetting(key, value) {
        try {
            await this.db.put('settings', { key, value });
        } catch (error) {
            console.error('Failed to set setting:', error);
        }
    }

    async cacheQRCode(code, data) {
        try {
            await this.db.put('qr_cache', {
                code,
                data,
                timestamp: Date.now()
            });
        } catch (error) {
            console.error('Failed to cache QR code:', error);
        }
    }

    async getCachedQRCode(code) {
        try {
            const cached = await this.db.get('qr_cache', code);
            
            // Check if cache is still valid (24 hours)
            if (cached && (Date.now() - cached.timestamp) < 24 * 60 * 60 * 1000) {
                return cached.data;
            }
            
            return null;
        } catch (error) {
            console.error('Failed to get cached QR code:', error);
            return null;
        }
    }

    setupPeriodicSync() {
        // Sync every 5 minutes when online
        setInterval(() => {
            if (navigator.onLine && !this.syncInProgress) {
                this.syncOfflineActions();
            }
        }, 5 * 60 * 1000);
        
        // Retry failed actions every minute
        setInterval(() => {
            if (navigator.onLine) {
                this.retryFailedActions();
            }
        }, 60 * 1000);
    }

    async retryFailedActions() {
        try {
            const actions = await this.db.getAll('offline_queue');
            const retryActions = actions.filter(a => 
                a.status === 'retry' && 
                (Date.now() - a.last_retry) > this.retryDelay
            );
            
            for (const action of retryActions) {
                action.status = 'pending';
                await this.db.put('offline_queue', action);
            }
            
            if (retryActions.length > 0) {
                this.syncOfflineActions();
            }
        } catch (error) {
            console.error('Failed to retry actions:', error);
        }
    }

    updateSyncStatus(status) {
        // Update UI sync indicator
        const syncIndicator = document.querySelector('.sync-indicator');
        if (syncIndicator) {
            syncIndicator.className = `sync-indicator ${status}`;
            
            switch (status) {
                case 'syncing':
                    syncIndicator.innerHTML = '<i class="ri-refresh-line animate-spin"></i> Syncing...';
                    break;
                case 'synced':
                    syncIndicator.innerHTML = '<i class="ri-check-line text-green-500"></i> Synced';
                    break;
                case 'error':
                    syncIndicator.innerHTML = '<i class="ri-error-warning-line text-red-500"></i> Sync Error';
                    break;
                default:
                    syncIndicator.innerHTML = '<i class="ri-cloud-line"></i> Offline';
            }
        }
        
        // Emit event for other components
        if (window.GatePassApp?.EventBus) {
            window.GatePassApp.EventBus.emit('sync:status', status);
        }
    }

    showSyncNotification(successCount, failedCount = 0) {
        let message = `Synced ${successCount} offline action${successCount > 1 ? 's' : ''}`;
        
        if (failedCount > 0) {
            message += `, ${failedCount} failed`;
        }
        
        if (window.GatePassApp?.NotificationManager) {
            const type = failedCount > 0 ? 'warning' : 'success';
            window.GatePassApp.NotificationManager.show(message, type);
        }
    }

    async getOfflineStats() {
        try {
            const [visitors, vehicles, visits, queue] = await Promise.all([
                this.db.count('visitors'),
                this.db.count('vehicles'),
                this.db.count('visits'),
                this.db.count('offline_queue')
            ]);
            
            const pendingQueue = await this.db.getAll('offline_queue');
            const pendingCount = pendingQueue.filter(a => a.status === 'pending').length;
            const failedCount = pendingQueue.filter(a => a.status === 'failed').length;
            
            return {
                cached_visitors: visitors,
                cached_vehicles: vehicles,
                cached_visits: visits,
                pending_sync: pendingCount,
                failed_sync: failedCount,
                total_queue: queue,
                last_sync: this.lastSync,
                storage_used: await this.getStorageUsage()
            };
        } catch (error) {
            console.error('Failed to get offline stats:', error);
            return null;
        }
    }

    async getStorageUsage() {
        if ('storage' in navigator && 'estimate' in navigator.storage) {
            try {
                const estimate = await navigator.storage.estimate();
                return {
                    used: estimate.usage,
                    available: estimate.quota,
                    percentage: Math.round((estimate.usage / estimate.quota) * 100),
                    used_mb: Math.round(estimate.usage / 1024 / 1024),
                    available_mb: Math.round(estimate.quota / 1024 / 1024)
                };
            } catch (error) {
                return null;
            }
        }
        return null;
    }

    async clearOfflineData() {
        try {
            await Promise.all([
                this.db.clear('visitors'),
                this.db.clear('vehicles'),
                this.db.clear('visits'),
                this.db.clear('qr_cache'),
                this.db.clear('form_data')
            ]);
            
            // Reset memory cache
            this.cachedVisitors = [];
            this.cachedVehicles = [];
            this.cachedSettings = {};
            
            console.log('Offline data cleared');
        } catch (error) {
            console.error('Failed to clear offline data:', error);
        }
    }
}

// Offline Form Manager
class OfflineFormManager {
    constructor() {
        this.forms = new Map();
        this.autosaveInterval = 30000; // 30 seconds
        this.offlineDataManager = null;
    }

    setOfflineDataManager(manager) {
        this.offlineDataManager = manager;
    }

    registerForm(formId, options = {}) {
        const form = document.getElementById(formId);
        if (!form) {
            console.warn(`Form not found: ${formId}`);
            return;
        }
        
        this.forms.set(formId, {
            element: form,
            options: {
                autosave: options.autosave !== false,
                offlineQueue: options.offlineQueue !== false,
                offlineAction: options.offlineAction || 'form_submit',
                priority: options.priority || 1,
                ...options
            }
        });
        
        this.setupFormListeners(formId);
        this.restoreFormData(formId);
        
        console.log(`Form registered for offline: ${formId}`);
    }

    setupFormListeners(formId) {
        const formData = this.forms.get(formId);
        const form = formData.element;
        
        // Auto-save on input
        if (formData.options.autosave) {
            const debouncedSave = this.debounce(() => {
                this.saveFormData(formId);
            }, 2000);
            
            form.addEventListener('input', debouncedSave);
            form.addEventListener('change', debouncedSave);
        }
        
        // Handle form submission
        form.addEventListener('submit', (e) => {
            if (!navigator.onLine && formData.options.offlineQueue) {
                e.preventDefault();
                this.handleOfflineSubmission(formId);
            } else {
                // Clear saved data on successful online submission
                this.clearFormData(formId);
            }
        });
        
        // Add offline indicator to form
        this.addOfflineIndicator(form);
    }

    addOfflineIndicator(form) {
        const indicator = document.createElement('div');
        indicator.className = 'offline-form-indicator hidden bg-yellow-100 border border-yellow-400 text-yellow-700 px-3 py-2 rounded mb-4';
        indicator.innerHTML = `
            <div class="flex items-center">
                <i class="ri-wifi-off-line mr-2"></i>
                <span>You are offline. Form will be submitted when connection is restored.</span>
            </div>
        `;
        
        form.insertBefore(indicator, form.firstChild);
        
        // Show/hide based on network status
        const updateIndicator = () => {
            if (navigator.onLine) {
                indicator.classList.add('hidden');
            } else {
                indicator.classList.remove('hidden');
            }
        };
        
        window.addEventListener('online', updateIndicator);
        window.addEventListener('offline', updateIndicator);
        updateIndicator();
    }

    async saveFormData(formId) {
        try {
            const form = this.forms.get(formId)?.element;
            if (!form) return;
            
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            if (this.offlineDataManager?.db) {
                await this.offlineDataManager.db.put('form_data', {
                    form_id: formId,
                    data,
                    timestamp: Date.now()
                });
            } else {
                // Fallback to localStorage
                localStorage.setItem(`form_data_${formId}`, JSON.stringify({
                    data,
                    timestamp: Date.now()
                }));
            }
            
            // Show saved indicator briefly
            this.showSaveIndicator(form);
            
        } catch (error) {
            console.error('Failed to save form data:', error);
        }
    }

    async restoreFormData(formId) {
        try {
            let saved = null;
            
            // Try IndexedDB first
            if (this.offlineDataManager?.db) {
                saved = await this.offlineDataManager.db.get('form_data', formId);
            }
            
            // Fallback to localStorage
            if (!saved) {
                const localData = localStorage.getItem(`form_data_${formId}`);
                if (localData) {
                    saved = JSON.parse(localData);
                    saved.form_id = formId;
                }
            }
            
            if (!saved) return;
            
            const form = this.forms.get(formId)?.element;
            if (!form) return;
            
            // Only restore if saved within last hour
            if (Date.now() - saved.timestamp < 3600000) {
                for (const [name, value] of Object.entries(saved.data)) {
                    const field = form.querySelector(`[name="${name}"]`);
                    if (field) {
                        field.value = value;
                        
                        // Trigger change event for any listeners
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                
                this.showRestoreNotification(formId);
            }
        } catch (error) {
            console.error('Failed to restore form data:', error);
        }
    }

    async clearFormData(formId) {
        try {
            // Clear from IndexedDB
            if (this.offlineDataManager?.db) {
                await this.offlineDataManager.db.delete('form_data', formId);
            }
            
            // Clear from localStorage
            localStorage.removeItem(`form_data_${formId}`);
            
        } catch (error) {
            console.error('Failed to clear form data:', error);
        }
    }

    async handleOfflineSubmission(formId) {
        const formData = this.forms.get(formId);
        const form = formData.element;
        
        try {
            // Get form data
            const data = new FormData(form);
            const submitData = Object.fromEntries(data.entries());
            
            // Add form metadata
            submitData._form_id = formId;
            submitData._submitted_at = Date.now();
            
            // Queue for offline processing
            if (this.offlineDataManager) {
                await this.offlineDataManager.queueOfflineAction(
                    formData.options.offlineAction,
                    submitData,
                    formData.options.priority
                );
                
                this.showOfflineSubmissionNotification();
                this.clearFormData(formId);
                
                // Reset form if successful
                form.reset();
                
                // Trigger custom event
                form.dispatchEvent(new CustomEvent('offline-submit', {
                    detail: { formId, data: submitData }
                }));
            } else {
                throw new Error('Offline data manager not available');
            }
            
        } catch (error) {
            console.error('Failed to handle offline submission:', error);
            this.showErrorNotification('Failed to queue form for offline submission');
        }
    }

    showSaveIndicator(form) {
        let indicator = form.querySelector('.save-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'save-indicator fixed top-4 right-4 bg-blue-500 text-white px-3 py-1 rounded text-sm z-50 opacity-0 transition-opacity';
            indicator.innerHTML = '<i class="ri-save-line mr-1"></i> Saved';
            document.body.appendChild(indicator);
        }
        
        indicator.style.opacity = '1';
        
        setTimeout(() => {
            indicator.style.opacity = '0';
        }, 2000);
    }

    showRestoreNotification(formId) {
        if (window.GatePassApp?.NotificationManager) {
            window.GatePassApp.NotificationManager.show(
                'Form data restored from previous session',
                'info'