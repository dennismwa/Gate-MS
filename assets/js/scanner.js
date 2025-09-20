/**
 * QR Scanner Implementation
 * GatePass Pro - Smart Gate Management System
 */

class QRScanner {
    constructor() {
        this.stream = null;
        this.video = null;
        this.canvas = null;
        this.context = null;
        this.scanning = false;
        this.scanInterval = null;
        this.onScanSuccess = null;
        this.onScanError = null;
    }

    async initialize(videoElement, options = {}) {
        try {
            this.video = videoElement;
            this.canvas = document.createElement('canvas');
            this.context = this.canvas.getContext('2d');
            
            // Set up video constraints
            const constraints = {
                video: {
                    facingMode: options.facingMode || 'environment',
                    width: { ideal: options.width || 1280 },
                    height: { ideal: options.height || 720 }
                }
            };

            // Request camera access
            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.video.srcObject = this.stream;
            
            return new Promise((resolve, reject) => {
                this.video.onloadedmetadata = () => {
                    this.video.play();
                    this.canvas.width = this.video.videoWidth;
                    this.canvas.height = this.video.videoHeight;
                    resolve();
                };
                
                this.video.onerror = reject;
            });
            
        } catch (error) {
            throw new Error(`Failed to initialize camera: ${error.message}`);
        }
    }

    startScanning(onSuccess, onError) {
        if (this.scanning) return;
        
        this.onScanSuccess = onSuccess;
        this.onScanError = onError;
        this.scanning = true;
        
        // Start scanning loop
        this.scanInterval = setInterval(() => {
            this.scanFrame();
        }, 100); // Scan every 100ms
    }

    stopScanning() {
        if (!this.scanning) return;
        
        this.scanning = false;
        
        if (this.scanInterval) {
            clearInterval(this.scanInterval);
            this.scanInterval = null;
        }
        
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        
        if (this.video) {
            this.video.srcObject = null;
        }
    }

    scanFrame() {
        if (!this.video || !this.canvas || !this.scanning) return;
        
        try {
            // Draw current video frame to canvas
            this.context.drawImage(
                this.video, 
                0, 0, 
                this.canvas.width, 
                this.canvas.height
            );
            
            // Get image data
            const imageData = this.context.getImageData(
                0, 0, 
                this.canvas.width, 
                this.canvas.height
            );
            
            // Try to decode QR code
            const result = this.decodeQRCode(imageData);
            
            if (result) {
                this.onScanSuccess?.(result);
                this.stopScanning();
            }
            
        } catch (error) {
            console.warn('Scan frame error:', error);
        }
    }

    decodeQRCode(imageData) {
        try {
            // This is a simplified QR code detection
            // In a real implementation, you would use a library like jsQR
            return this.simpleQRDetection(imageData);
        } catch (error) {
            return null;
        }
    }

    simpleQRDetection(imageData) {
        // This is a placeholder implementation
        // Replace with actual QR detection library like jsQR
        // For demo purposes, we'll simulate detection
        
        const { data, width, height } = imageData;
        
        // Look for high contrast patterns that might indicate QR code
        let patternScore = 0;
        const sampleSize = 20;
        
        for (let y = 0; y < height; y += sampleSize) {
            for (let x = 0; x < width; x += sampleSize) {
                const i = (y * width + x) * 4;
                const brightness = (data[i] + data[i + 1] + data[i + 2]) / 3;
                
                if (brightness < 50 || brightness > 200) {
                    patternScore++;
                }
            }
        }
        
        // If we find enough contrast patterns, simulate QR code detection
        if (patternScore > 100) {
            // Return a simulated QR code for demo
            return this.generateDemoQRCode();
        }
        
        return null;
    }

    generateDemoQRCode() {
        // Generate a demo QR code that looks realistic
        const codes = [
            'VIS20240315001',
            'REG20240315002',
            'PRE20240315003'
        ];
        
        return codes[Math.floor(Math.random() * codes.length)];
    }

    async scanFromFile(file) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            
            img.onload = () => {
                canvas.width = img.width;
                canvas.height = img.height;
                context.drawImage(img, 0, 0);
                
                const imageData = context.getImageData(0, 0, img.width, img.height);
                const result = this.decodeQRCode(imageData);
                
                if (result) {
                    resolve(result);
                } else {
                    reject(new Error('No QR code found in image'));
                }
            };
            
            img.onerror = () => {
                reject(new Error('Failed to load image'));
            };
            
            const reader = new FileReader();
            reader.onload = (e) => {
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    static async checkCameraSupport() {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            return devices.some(device => device.kind === 'videoinput');
        } catch (error) {
            return false;
        }
    }

    static async requestCameraPermission() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            stream.getTracks().forEach(track => track.stop());
            return true;
        } catch (error) {
            return false;
        }
    }
}

// Enhanced QR Scanner with UI integration
class QRScannerUI {
    constructor() {
        this.scanner = new QRScanner();
        this.modal = null;
        this.video = null;
        this.isOpen = false;
        this.onResult = null;
    }

    async show(options = {}) {
        if (this.isOpen) return;
        
        this.onResult = options.onResult;
        this.createModal();
        this.isOpen = true;
        
        try {
            await this.initializeCamera();
            this.startScanning();
        } catch (error) {
            this.showError(error.message);
        }
    }

    hide() {
        if (!this.isOpen) return;
        
        this.scanner.stopScanning();
        this.removeModal();
        this.isOpen = false;
    }

    createModal() {
        this.modal = document.createElement('div');
        this.modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50';
        this.modal.innerHTML = `
            <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900">QR Code Scanner</h2>
                    <button class="close-scanner text-gray-500 hover:text-gray-700">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
                
                <div class="scanner-container">
                    <div class="relative bg-gray-100 rounded-lg overflow-hidden" style="aspect-ratio: 4/3;">
                        <video class="scanner-video w-full h-full object-cover" autoplay playsinline></video>
                        <div class="scanner-overlay absolute inset-0 flex items-center justify-center">
                            <div class="scan-area border-2 border-blue-500 w-48 h-48 relative">
                                <div class="scanner-line absolute top-0 left-0 w-full h-0.5 bg-blue-500 animate-pulse"></div>
                                <!-- Corner indicators -->
                                <div class="absolute top-0 left-0 w-6 h-6 border-t-4 border-l-4 border-blue-500"></div>
                                <div class="absolute top-0 right-0 w-6 h-6 border-t-4 border-r-4 border-blue-500"></div>
                                <div class="absolute bottom-0 left-0 w-6 h-6 border-b-4 border-l-4 border-blue-500"></div>
                                <div class="absolute bottom-0 right-0 w-6 h-6 border-b-4 border-r-4 border-blue-500"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="scanner-status mt-4 text-center">
                        <p class="text-gray-600">Position QR code within the frame</p>
                        <div class="scanner-loading hidden">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mt-2"></div>
                        </div>
                    </div>
                </div>
                
                <div class="scanner-error hidden mt-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                    <div class="flex items-center">
                        <i class="ri-error-warning-line mr-2"></i>
                        <span class="error-message"></span>
                    </div>
                </div>
                
                <div class="mt-6 flex space-x-3">
                    <button class="flex-1 upload-qr bg-gray-600 text-white py-2 rounded-lg hover:bg-gray-700 transition duration-200">
                        <i class="ri-upload-line mr-2"></i>
                        Upload Image
                    </button>
                    <button class="flex-1 toggle-camera bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="ri-camera-switch-line mr-2"></i>
                        Switch Camera
                    </button>
                </div>
                
                <input type="file" class="file-input hidden" accept="image/*">
            </div>
        `;

        document.body.appendChild(this.modal);
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Close button
        this.modal.querySelector('.close-scanner').addEventListener('click', () => {
            this.hide();
        });

        // Upload QR code
        this.modal.querySelector('.upload-qr').addEventListener('click', () => {
            this.modal.querySelector('.file-input').click();
        });

        // File input
        this.modal.querySelector('.file-input').addEventListener('change', (e) => {
            if (e.target.files[0]) {
                this.scanFromFile(e.target.files[0]);
            }
        });

        // Switch camera
        this.modal.querySelector('.toggle-camera').addEventListener('click', () => {
            this.switchCamera();
        });

        // Close on background click
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.hide();
            }
        });
    }

    async initializeCamera() {
        this.video = this.modal.querySelector('.scanner-video');
        this.showLoading();
        
        try {
            await this.scanner.initialize(this.video, {
                facingMode: 'environment'
            });
            this.hideLoading();
        } catch (error) {
            this.hideLoading();
            throw error;
        }
    }

    startScanning() {
        this.scanner.startScanning(
            (result) => this.handleScanSuccess(result),
            (error) => this.handleScanError(error)
        );
    }

    async switchCamera() {
        try {
            this.scanner.stopScanning();
            this.showLoading();
            
            // Toggle between front and back camera
            const currentFacing = this.scanner.facingMode || 'environment';
            const newFacing = currentFacing === 'environment' ? 'user' : 'environment';
            
            await this.scanner.initialize(this.video, {
                facingMode: newFacing
            });
            
            this.scanner.facingMode = newFacing;
            this.hideLoading();
            this.startScanning();
            
        } catch (error) {
            this.hideLoading();
            this.showError('Failed to switch camera');
        }
    }

    async scanFromFile(file) {
        try {
            this.showLoading('Scanning image...');
            const result = await this.scanner.scanFromFile(file);
            this.hideLoading();
            this.handleScanSuccess(result);
        } catch (error) {
            this.hideLoading();
            this.showError('No QR code found in image');
        }
    }

    handleScanSuccess(result) {
        // Add visual feedback
        this.showScanSuccess();
        
        // Call result handler
        if (this.onResult) {
            this.onResult(result);
        }
        
        // Close scanner after a brief delay
        setTimeout(() => {
            this.hide();
        }, 1000);
    }

    handleScanError(error) {
        this.showError(error.message);
    }

    showLoading(message = 'Initializing camera...') {
        const status = this.modal.querySelector('.scanner-status p');
        const loading = this.modal.querySelector('.scanner-loading');
        
        status.textContent = message;
        loading.classList.remove('hidden');
    }

    hideLoading() {
        const status = this.modal.querySelector('.scanner-status p');
        const loading = this.modal.querySelector('.scanner-loading');
        
        status.textContent = 'Position QR code within the frame';
        loading.classList.add('hidden');
    }

    showError(message) {
        const errorDiv = this.modal.querySelector('.scanner-error');
        const errorMessage = this.modal.querySelector('.error-message');
        
        errorMessage.textContent = message;
        errorDiv.classList.remove('hidden');
        
        // Hide error after 5 seconds
        setTimeout(() => {
            errorDiv.classList.add('hidden');
        }, 5000);
    }

    showScanSuccess() {
        const overlay = this.modal.querySelector('.scanner-overlay');
        overlay.innerHTML = `
            <div class="flex items-center justify-center h-full">
                <div class="bg-green-500 text-white p-4 rounded-lg">
                    <i class="ri-check-circle-line text-4xl"></i>
                    <p class="mt-2">QR Code Detected!</p>
                </div>
            </div>
        `;
    }

    removeModal() {
        if (this.modal && this.modal.parentNode) {
            this.modal.parentNode.removeChild(this.modal);
            this.modal = null;
        }
    }
}

// QR Code Generator
class QRCodeGenerator {
    static generate(text, options = {}) {
        const canvas = document.createElement('canvas');
        const size = options.size || 200;
        
        // Use QRCode library if available
        if (window.QRCode) {
            QRCode.toCanvas(canvas, text, {
                width: size,
                height: size,
                margin: options.margin || 2,
                color: {
                    dark: options.foreground || '#000000',
                    light: options.background || '#FFFFFF'
                },
                errorCorrectionLevel: options.errorCorrectionLevel || 'M'
            });
        } else {
            // Fallback: create a simple placeholder
            this.createPlaceholder(canvas, size, text);
        }
        
        return canvas;
    }

    static createPlaceholder(canvas, size, text) {
        canvas.width = size;
        canvas.height = size;
        
        const ctx = canvas.getContext('2d');
        
        // Fill background
        ctx.fillStyle = '#f0f0f0';
        ctx.fillRect(0, 0, size, size);
        
        // Draw border
        ctx.strokeStyle = '#ccc';
        ctx.lineWidth = 2;
        ctx.strokeRect(1, 1, size - 2, size - 2);
        
        // Draw text
        ctx.fillStyle = '#666';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('QR Code', size / 2, size / 2 - 10);
        ctx.fillText(text.substring(0, 20), size / 2, size / 2 + 10);
    }

    static download(canvas, filename = 'qrcode.png') {
        const link = document.createElement('a');
        link.download = filename;
        link.href = canvas.toDataURL();
        link.click();
    }

    static async generateVisitorCard(visitorData) {
        const card = document.createElement('div');
        card.className = 'visitor-card bg-white p-6 rounded-lg shadow-lg';
        card.style.width = '400px';
        
        // Generate QR code
        const qrCanvas = this.generate(visitorData.visitCode, { size: 120 });
        
        card.innerHTML = `
            <div class="card-header text-center mb-4 pb-4 border-b">
                <h2 class="text-xl font-bold text-blue-600">VISITOR PASS</h2>
                <p class="text-gray-600">GatePass Pro</p>
            </div>
            
            <div class="card-body grid grid-cols-2 gap-4 mb-4">
                <div>
                    <p class="text-sm text-gray-600">Visitor Name</p>
                    <p class="font-semibold">${visitorData.name}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Company</p>
                    <p class="font-semibold">${visitorData.company || 'N/A'}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Host</p>
                    <p class="font-semibold">${visitorData.host}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Visit Date</p>
                    <p class="font-semibold">${visitorData.date}</p>
                </div>
            </div>
            
            <div class="card-qr text-center">
                <div class="qr-container inline-block p-2 bg-gray-50 rounded"></div>
                <p class="text-xs text-gray-600 mt-2">Visit Code: ${visitorData.visitCode}</p>
            </div>
            
            <div class="card-footer text-center mt-4 pt-4 border-t text-xs text-gray-500">
                <p>Please wear this badge at all times during your visit</p>
            </div>
        `;
        
        // Append QR code
        card.querySelector('.qr-container').appendChild(qrCanvas);
        
        return card;
    }
}

// Barcode Scanner (for vehicle license plates, etc.)
class BarcodeScanner {
    constructor() {
        this.stream = null;
        this.video = null;
        this.scanning = false;
    }

    async initialize(videoElement) {
        this.video = videoElement;
        
        const constraints = {
            video: {
                facingMode: 'environment',
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };

        this.stream = await navigator.mediaDevices.getUserMedia(constraints);
        this.video.srcObject = this.stream;
        
        return new Promise((resolve) => {
            this.video.onloadedmetadata = () => {
                this.video.play();
                resolve();
            };
        });
    }

    startScanning(onSuccess, onError) {
        this.scanning = true;
        this.scanLoop(onSuccess, onError);
    }

    stopScanning() {
        this.scanning = false;
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
    }

    scanLoop(onSuccess, onError) {
        if (!this.scanning) return;
        
        // This would use a barcode detection library
        // For now, just simulate detection
        setTimeout(() => {
            if (this.scanning && Math.random() > 0.95) {
                onSuccess('ABC-123'); // Simulated license plate
                return;
            }
            
            if (this.scanning) {
                this.scanLoop(onSuccess, onError);
            }
        }, 100);
    }
}

// Scanner Utils
const ScannerUtils = {
    async checkPermissions() {
        try {
            const result = await navigator.permissions.query({ name: 'camera' });
            return result.state === 'granted';
        } catch (error) {
            // Fallback: try to access camera
            return await QRScanner.requestCameraPermission();
        }
    },

    async getAvailableCameras() {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            return devices.filter(device => device.kind === 'videoinput');
        } catch (error) {
            return [];
        }
    },

    formatScanResult(result) {
        // Parse different QR code formats
        if (result.startsWith('VIS')) {
            return {
                type: 'visit',
                code: result,
                data: this.parseVisitCode(result)
            };
        } else if (result.startsWith('REG')) {
            return {
                type: 'registration',
                code: result,
                data: this.parseRegistrationCode(result)
            };
        } else if (result.startsWith('http')) {
            return {
                type: 'url',
                code: result,
                data: { url: result }
            };
        } else {
            return {
                type: 'text',
                code: result,
                data: { text: result }
            };
        }
    },

    parseVisitCode(code) {
        // Extract information from visit code
        // Format: VIS + YYYYMMDD + NNN
        const dateStr = code.substring(3, 11);
        const sequence = code.substring(11);
        
        return {
            date: dateStr,
            sequence: sequence
        };
    },

    parseRegistrationCode(code) {
        // Extract information from registration code
        // Format: REG + YYYYMMDD + NNN
        const dateStr = code.substring(3, 11);
        const sequence = code.substring(11);
        
        return {
            date: dateStr,
            sequence: sequence
        };
    }
};

// Export for global access
window.QRScanner = QRScanner;
window.QRScannerUI = QRScannerUI;
window.QRCodeGenerator = QRCodeGenerator;
window.BarcodeScanner = BarcodeScanner;
window.ScannerUtils = ScannerUtils;