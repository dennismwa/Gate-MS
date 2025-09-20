/**
 * Main Application JavaScript
 * GatePass Pro - Smart Gate Management System
 */

// Application State
const AppState = {
    currentUser: null,
    isAuthenticated: false,
    permissions: [],
    settings: {},
    currentPage: 'dashboard',
    notifications: [],
    offlineQueue: [],
    isOnline: navigator.onLine
};

// API Service
class ApiService {
    constructor() {
        this.baseUrl = window.APP_CONFIG?.apiUrl || '/api/';
        this.token = localStorage.getItem('auth_token');
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };

        if (this.token) {
            config.headers['Authorization'] = `Bearer ${this.token}`;
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }

            return data;
        } catch (error) {
            if (!navigator.onLine) {
                // Queue for offline processing
                this.queueOfflineRequest(endpoint, options);
                throw new Error('You are offline. Request queued for when connection is restored.');
            }
            throw error;
        }
    }

    queueOfflineRequest(endpoint, options) {
        AppState.offlineQueue.push({
            endpoint,
            options,
            timestamp: Date.now()
        });
        OfflineManager.saveQueue();
    }

    async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }

    async post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    async put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }

    setToken(token) {
        this.token = token;
        if (token) {
            localStorage.setItem('auth_token', token);
        } else {
            localStorage.removeItem('auth_token');
        }
    }
}

// Initialize API service
const api = new ApiService();

// Authentication Manager
class AuthManager {
    static async login(username, password) {
        try {
            const response = await api.post('login', { username, password });
            
            if (response.success) {
                AppState.currentUser = response.user;
                AppState.isAuthenticated = true;
                AppState.permissions = response.user.permissions || [];
                
                api.setToken(response.token);
                localStorage.setItem('user_data', JSON.stringify(response.user));
                
                EventBus.emit('auth:login', response.user);
                return response;
            }
            
            throw new Error(response.message || 'Login failed');
        } catch (error) {
            NotificationManager.show(error.message, 'error');
            throw error;
        }
    }

    static async logout() {
        try {
            if (AppState.isAuthenticated) {
                await api.post('logout');
            }
        } catch (error) {
            console.warn('Logout request failed:', error);
        } finally {
            AppState.currentUser = null;
            AppState.isAuthenticated = false;
            AppState.permissions = [];
            
            api.setToken(null);
            localStorage.removeItem('user_data');
            localStorage.removeItem('auth_token');
            
            EventBus.emit('auth:logout');
        }
    }

    static async validateSession() {
        try {
            const token = localStorage.getItem('auth_token');
            const userData = localStorage.getItem('user_data');
            
            if (!token || !userData) {
                return false;
            }

            // Validate token with server
            const response = await api.get('profile');
            
            if (response.success) {
                AppState.currentUser = response.user;
                AppState.isAuthenticated = true;
                AppState.permissions = response.user.permissions || [];
                return true;
            }
            
            return false;
        } catch (error) {
            AuthManager.logout();
            return false;
        }
    }

    static hasPermission(permission) {
        if (!AppState.isAuthenticated) return false;
        if (AppState.permissions.includes('all')) return true;
        return AppState.permissions.includes(permission);
    }

    static requirePermission(permission) {
        if (!AuthManager.hasPermission(permission)) {
            NotificationManager.show('Access denied. Insufficient permissions.', 'error');
            throw new Error('Access denied');
        }
    }
}

// Event Bus
class EventBus {
    static listeners = {};

    static on(event, callback) {
        if (!EventBus.listeners[event]) {
            EventBus.listeners[event] = [];
        }
        EventBus.listeners[event].push(callback);
    }

    static off(event, callback) {
        if (EventBus.listeners[event]) {
            EventBus.listeners[event] = EventBus.listeners[event].filter(
                listener => listener !== callback
            );
        }
    }

    static emit(event, data) {
        if (EventBus.listeners[event]) {
            EventBus.listeners[event].forEach(callback => callback(data));
        }
    }
}

// Notification Manager
class NotificationManager {
    static show(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type} notification-enter`;
        
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            warning: 'bg-yellow-500',
            info: 'bg-blue-500'
        };

        notification.innerHTML = `
            <div class="fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 max-w-sm">
                <div class="flex items-center justify-between">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(notification);

        if (duration > 0) {
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, duration);
        }

        return notification;
    }

    static async fetchNotifications() {
        try {
            const response = await api.get('notifications');
            if (response.success) {
                AppState.notifications = response.data;
                this.updateNotificationBadge();
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        }
    }

    static updateNotificationBadge() {
        const badge = document.querySelector('.notification-badge');
        const unreadCount = AppState.notifications.filter(n => !n.is_read).length;
        
        if (badge) {
            badge.textContent = unreadCount;
            badge.style.display = unreadCount > 0 ? 'flex' : 'none';
        }
    }

    static async markAsRead(notificationId) {
        try {
            await api.put('notifications', { notification_id: notificationId });
            AppState.notifications = AppState.notifications.map(n => 
                n.id === notificationId ? { ...n, is_read: true } : n
            );
            this.updateNotificationBadge();
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }
}

// Offline Manager
class OfflineManager {
    static init() {
        window.addEventListener('online', () => {
            AppState.isOnline = true;
            this.processOfflineQueue();
            NotificationManager.show('Connection restored', 'success');
        });

        window.addEventListener('offline', () => {
            AppState.isOnline = false;
            NotificationManager.show('You are offline. Some features may be limited.', 'warning');
        });

        this.loadQueue();
    }

    static saveQueue() {
        localStorage.setItem('offline_queue', JSON.stringify(AppState.offlineQueue));
    }

    static loadQueue() {
        try {
            const saved = localStorage.getItem('offline_queue');
            if (saved) {
                AppState.offlineQueue = JSON.parse(saved);
            }
        } catch (error) {
            console.error('Failed to load offline queue:', error);
            AppState.offlineQueue = [];
        }
    }

    static async processOfflineQueue() {
        if (AppState.offlineQueue.length === 0) return;

        NotificationManager.show('Syncing offline data...', 'info');

        const processed = [];
        for (const request of AppState.offlineQueue) {
            try {
                await api.request(request.endpoint, request.options);
                processed.push(request);
            } catch (error) {
                console.error('Failed to process offline request:', error);
            }
        }

        // Remove processed requests
        AppState.offlineQueue = AppState.offlineQueue.filter(
            request => !processed.includes(request)
        );
        
        this.saveQueue();

        if (processed.length > 0) {
            NotificationManager.show(`Synced ${processed.length} offline actions`, 'success');
        }
    }
}

// QR Scanner Manager
class QRScannerManager {
    static currentStream = null;
    static isScanning = false;

    static async startCamera() {
        try {
            const constraints = {
                video: {
                    facingMode: 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            };

            this.currentStream = await navigator.mediaDevices.getUserMedia(constraints);
            return this.currentStream;
        } catch (error) {
            console.error('Camera access error:', error);
            throw new Error('Camera access denied or not available');
        }
    }

    static stopCamera() {
        if (this.currentStream) {
            this.currentStream.getTracks().forEach(track => track.stop());
            this.currentStream = null;
        }
        this.isScanning = false;
    }

    static async scanQRCode(imageData) {
        try {
            // This would integrate with a QR scanning library
            // For now, simulate QR code detection
            return new Promise((resolve) => {
                setTimeout(() => {
                    resolve('SAMPLE_QR_CODE_DATA');
                }, 1000);
            });
        } catch (error) {
            throw new Error('QR code scanning failed');
        }
    }

    static async processQRCode(qrData) {
        try {
            const response = await api.post('qr-verify', { qr_code: qrData });
            
            if (response.success) {
                EventBus.emit('qr:scanned', response.visit);
                return response;
            } else {
                throw new Error(response.message || 'Invalid QR code');
            }
        } catch (error) {
            NotificationManager.show(error.message, 'error');
            throw error;
        }
    }
}

// Form Validator
class FormValidator {
    static validate(form, rules) {
        const errors = {};
        
        for (const [field, rule] of Object.entries(rules)) {
            const element = form.querySelector(`[name="${field}"]`);
            const value = element ? element.value.trim() : '';
            
            if (rule.required && !value) {
                errors[field] = `${rule.label || field} is required`;
                continue;
            }
            
            if (value && rule.email && !this.isValidEmail(value)) {
                errors[field] = `${rule.label || field} must be a valid email`;
            }
            
            if (value && rule.phone && !this.isValidPhone(value)) {
                errors[field] = `${rule.label || field} must be a valid phone number`;
            }
            
            if (value && rule.minLength && value.length < rule.minLength) {
                errors[field] = `${rule.label || field} must be at least ${rule.minLength} characters`;
            }
            
            if (value && rule.maxLength && value.length > rule.maxLength) {
                errors[field] = `${rule.label || field} must not exceed ${rule.maxLength} characters`;
            }
        }
        
        return errors;
    }

    static displayErrors(form, errors) {
        // Clear previous errors
        form.querySelectorAll('.error-message').forEach(el => el.remove());
        form.querySelectorAll('.border-red-500').forEach(el => {
            el.classList.remove('border-red-500');
        });

        // Display new errors
        for (const [field, message] of Object.entries(errors)) {
            const element = form.querySelector(`[name="${field}"]`);
            if (element) {
                element.classList.add('border-red-500');
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message text-red-500 text-sm mt-1';
                errorDiv.textContent = message;
                
                element.parentNode.appendChild(errorDiv);
            }
        }
    }

    static isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    static isValidPhone(phone) {
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,20}$/;
        return phoneRegex.test(phone);
    }
}

// Data Manager
class DataManager {
    static cache = new Map();
    static cacheExpiry = new Map();

    static async get(key, fetcher, ttl = 300000) { // 5 minutes default TTL
        const now = Date.now();
        
        if (this.cache.has(key) && this.cacheExpiry.get(key) > now) {
            return this.cache.get(key);
        }

        try {
            const data = await fetcher();
            this.cache.set(key, data);
            this.cacheExpiry.set(key, now + ttl);
            return data;
        } catch (error) {
            // Return cached data if available, even if expired
            if (this.cache.has(key)) {
                return this.cache.get(key);
            }
            throw error;
        }
    }

    static invalidate(key) {
        this.cache.delete(key);
        this.cacheExpiry.delete(key);
    }

    static clear() {
        this.cache.clear();
        this.cacheExpiry.clear();
    }
}

// Utility Functions
const Utils = {
    formatDate(date, format = 'Y-m-d H:i:s') {
        if (!date) return '';
        const d = new Date(date);
        
        const formats = {
            'Y': d.getFullYear(),
            'm': String(d.getMonth() + 1).padStart(2, '0'),
            'd': String(d.getDate()).padStart(2, '0'),
            'H': String(d.getHours()).padStart(2, '0'),
            'i': String(d.getMinutes()).padStart(2, '0'),
            's': String(d.getSeconds()).padStart(2, '0')
        };

        return format.replace(/[Ymdxis]/g, match => formats[match] || match);
    },

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    generateId() {
        return Math.random().toString(36).substr(2, 9);
    },

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
    },

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    copyToClipboard(text) {
        if (navigator.clipboard) {
            return navigator.clipboard.writeText(text);
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            return Promise.resolve();
        }
    },

    downloadFile(url, filename) {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
};

// Application Router
class Router {
    static routes = new Map();
    static currentRoute = null;

    static addRoute(path, handler) {
        this.routes.set(path, handler);
    }

    static navigate(path) {
        if (this.routes.has(path)) {
            this.currentRoute = path;
            AppState.currentPage = path;
            this.routes.get(path)();
            
            // Update URL without page reload
            history.pushState({ path }, '', `#${path}`);
            
            EventBus.emit('route:changed', path);
        } else {
            console.warn(`Route not found: ${path}`);
        }
    }

    static init() {
        // Handle browser back/forward
        window.addEventListener('popstate', (event) => {
            const path = event.state?.path || 'dashboard';
            this.navigate(path);
        });

        // Handle hash changes
        window.addEventListener('hashchange', () => {
            const path = window.location.hash.slice(1) || 'dashboard';
            this.navigate(path);
        });

        // Initialize with current hash
        const initialPath = window.location.hash.slice(1) || 'dashboard';
        this.navigate(initialPath);
    }
}

// Loading Manager
class LoadingManager {
    static activeLoaders = new Set();

    static show(id = 'default') {
        this.activeLoaders.add(id);
        this.updateUI();
    }

    static hide(id = 'default') {
        this.activeLoaders.delete(id);
        this.updateUI();
    }

    static updateUI() {
        const isLoading = this.activeLoaders.size > 0;
        const loadingElements = document.querySelectorAll('.loading-overlay');
        
        loadingElements.forEach(element => {
            element.style.display = isLoading ? 'flex' : 'none';
        });

        // Update cursor
        document.body.style.cursor = isLoading ? 'wait' : '';
    }
}

// Error Handler
class ErrorHandler {
    static handle(error, context = '') {
        console.error(`Error in ${context}:`, error);
        
        let message = 'An unexpected error occurred';
        
        if (error.message) {
            message = error.message;
        } else if (typeof error === 'string') {
            message = error;
        }

        NotificationManager.show(message, 'error');
        
        // Log to server if in production
        if (window.APP_CONFIG?.environment === 'production') {
            this.logToServer(error, context);
        }
    }

    static async logToServer(error, context) {
        try {
            await api.post('log-error', {
                message: error.message || error,
                stack: error.stack,
                context: context,
                url: window.location.href,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString()
            });
        } catch (logError) {
            console.error('Failed to log error to server:', logError);
        }
    }
}

// Print Manager
class PrintManager {
    static async printVisitorCard(visitData) {
        try {
            const printWindow = window.open('', '_blank');
            const cardHtml = await this.generateVisitorCard(visitData);
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Visitor Card</title>
                    <style>
                        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
                        .card { width: 400px; border: 2px solid #333; padding: 20px; margin: 0 auto; }
                        .qr-code { text-align: center; margin: 20px 0; }
                        @media print {
                            body { padding: 0; }
                            .card { border: 1px solid #333; }
                        }
                    </style>
                </head>
                <body>
                    ${cardHtml}
                    <script>
                        window.onload = function() {
                            window.print();
                            window.onafterprint = function() {
                                window.close();
                            };
                        };
                    </script>
                </body>
                </html>
            `);
            
            printWindow.document.close();
        } catch (error) {
            ErrorHandler.handle(error, 'PrintManager.printVisitorCard');
        }
    }

    static async generateVisitorCard(visitData) {
        // Generate QR code
        const qrCanvas = document.createElement('canvas');
        QRCode.toCanvas(qrCanvas, visitData.visit_code, { width: 150 });
        const qrDataUrl = qrCanvas.toDataURL();

        return `
            <div class="card">
                <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px;">
                    <h2 style="margin: 0; color: #333;">VISITOR PASS</h2>
                    <p style="margin: 5px 0; color: #666;">GatePass Pro</p>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <p><strong>Name:</strong> ${visitData.visitor_name}</p>
                    <p><strong>Company:</strong> ${visitData.company || 'N/A'}</p>
                    <p><strong>Host:</strong> ${visitData.host_name}</p>
                    <p><strong>Date:</strong> ${Utils.formatDate(visitData.visit_date, 'Y-m-d')}</p>
                    <p><strong>Badge #:</strong> ${visitData.badge_number}</p>
                </div>
                
                <div class="qr-code">
                    <img src="${qrDataUrl}" alt="QR Code" />
                    <p style="font-size: 12px; margin: 10px 0 0 0;">Visit Code: ${visitData.visit_code}</p>
                </div>
                
                <div style="border-top: 1px solid #ccc; padding-top: 10px; font-size: 10px; color: #666;">
                    <p>Please wear this badge at all times during your visit.</p>
                    <p>Return to reception when leaving.</p>
                </div>
            </div>
        `;
    }
}

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', async function() {
    try {
        // Initialize managers
        OfflineManager.init();
        Router.init();
        
        // Check authentication
        const isAuthenticated = await AuthManager.validateSession();
        
        if (isAuthenticated) {
            // Load application data
            await Promise.all([
                NotificationManager.fetchNotifications(),
                loadApplicationSettings()
            ]);
            
            // Show main application
            showMainApplication();
        } else {
            // Show login screen
            showLoginScreen();
        }
        
        // Set up event listeners
        setupEventListeners();
        
        // Hide loading screen
        hideLoadingScreen();
        
    } catch (error) {
        ErrorHandler.handle(error, 'Application initialization');
        hideLoadingScreen();
        showLoginScreen();
    }
});

// Application setup functions
function setupEventListeners() {
    // Authentication events
    EventBus.on('auth:login', (user) => {
        showMainApplication();
        NotificationManager.show(`Welcome back, ${user.full_name}!`, 'success');
    });

    EventBus.on('auth:logout', () => {
        showLoginScreen();
        NotificationManager.show('You have been logged out', 'info');
    });

    // QR Scanner events
    EventBus.on('qr:scanned', (visit) => {
        NotificationManager.show('QR code scanned successfully', 'success');
        // Handle the scanned visit data
    });

    // Route change events
    EventBus.on('route:changed', (path) => {
        updateActiveNavigation(path);
    });

    // Global keyboard shortcuts
    document.addEventListener('keydown', (event) => {
        // Ctrl/Cmd + K for quick search
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            openQuickSearch();
        }
        
        // Escape to close modals
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });

    // Auto-save forms
    document.addEventListener('input', Utils.debounce((event) => {
        if (event.target.closest('[data-auto-save]')) {
            autoSaveForm(event.target.closest('form'));
        }
    }, 1000));
}

async function loadApplicationSettings() {
    try {
        const response = await api.get('settings');
        if (response.success) {
            AppState.settings = response.settings;
        }
    } catch (error) {
        console.warn('Failed to load application settings:', error);
    }
}

function showMainApplication() {
    const loginScreen = document.getElementById('loginScreen');
    const mainApp = document.getElementById('mainApp');
    
    if (loginScreen) loginScreen.classList.add('hidden');
    if (mainApp) mainApp.classList.remove('hidden');
}

function showLoginScreen() {
    const loginScreen = document.getElementById('loginScreen');
    const mainApp = document.getElementById('mainApp');
    
    if (mainApp) mainApp.classList.add('hidden');
    if (loginScreen) loginScreen.classList.remove('hidden');
}

function hideLoadingScreen() {
    const loadingScreen = document.getElementById('loadingScreen');
    if (loadingScreen) {
        loadingScreen.classList.add('hidden');
    }
}

function updateActiveNavigation(currentPath) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active', 'bg-blue-50', 'text-blue-600');
    });
    
    const activeItem = document.querySelector(`[data-route="${currentPath}"]`);
    if (activeItem) {
        activeItem.classList.add('active', 'bg-blue-50', 'text-blue-600');
    }
}

function openQuickSearch() {
    // Implementation for quick search modal
    console.log('Quick search opened');
}

function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.add('hidden');
    });
}

function autoSaveForm(form) {
    if (!form) return;
    
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Save to localStorage as backup
    localStorage.setItem(`form_backup_${form.id}`, JSON.stringify({
        data,
        timestamp: Date.now()
    }));
}

// Global error handling
window.addEventListener('error', (event) => {
    ErrorHandler.handle(event.error, 'Global error handler');
});

window.addEventListener('unhandledrejection', (event) => {
    ErrorHandler.handle(event.reason, 'Unhandled promise rejection');
    event.preventDefault();
});

// Export for global access
window.GatePassApp = {
    ApiService,
    AuthManager,
    EventBus,
    NotificationManager,
    OfflineManager,
    QRScannerManager,
    FormValidator,
    DataManager,
    Router,
    LoadingManager,
    ErrorHandler,
    PrintManager,
    Utils,
    AppState
};