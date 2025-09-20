# GatePass Pro - Smart Gate Management System

A comprehensive, professional gate management system with QR code scanning, visitor tracking, vehicle management, and real-time reporting capabilities.

## 🚀 Features

### Core Functionality
- **QR Code Scanning** - Fast visitor check-in/out with camera or upload
- **Visitor Management** - Complete visitor registration and tracking
- **Vehicle Tracking** - License plate management and parking control
- **Pre-Registration** - Allow visitors to register before arrival
- **Real-time Dashboard** - Live statistics and activity monitoring
- **Digital Visitor Cards** - Printable badges with QR codes
- **Multi-role Access** - Admin, Security, Receptionist role management

### Advanced Features
- **Offline Support** - PWA with offline functionality
- **Email Notifications** - Automated alerts for hosts and visitors
- **Blacklist Management** - Security control for unwanted visitors
- **Comprehensive Reports** - Detailed analytics and export capabilities
- **Mobile Responsive** - Works perfectly on all devices
- **Activity Logging** - Complete audit trail of all actions
- **File Upload** - Photo management for visitors and vehicles

## 📋 Requirements

- **PHP** 7.4 or higher
- **MySQL** 5.7 or higher
- **Apache** with mod_rewrite enabled
- **SSL Certificate** (recommended for production)
- **Camera access** for QR scanning (mobile/desktop)

## 🛠 Installation

### Quick Installation

1. **Upload Files**
   ```bash
   # Upload all files to your web server
   # Ensure proper file permissions (755 for directories, 644 for files)
   ```

2. **Run Installation**
   ```
   Navigate to: https://yourdomain.com/install.php
   Follow the installation wizard
   ```

3. **Database Setup**
   - Use provided database credentials:
     - **Host:** localhost
     - **Database:** vxjtgclw_gatepass
     - **Username:** vxjtgclw_gatepass
     - **Password:** nS%?A,O?AO]41!C6

4. **Complete Setup**
   - Create admin account
   - Configure company settings
   - Delete install.php after completion

### Manual Installation

1. **Database Import**
   ```sql
   -- Import the SQL file
   mysql -u username -p database_name < gatepass_database.sql
   ```

2. **Configuration**
   ```php
   // Update config/database.php with your credentials
   private $host = 'localhost';
   private $db_name = 'vxjtgclw_gatepass';
   private $username = 'vxjtgclw_gatepass';
   private $password = 'nS%?A,O?AO]41!C6';
   ```

3. **Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 qrcodes/
   chmod 644 config/database.php
   ```

## 📁 File Structure

```
gatepass-pro/
├── api/                    # API endpoints
│   └── index.php
├── assets/                 # Static assets
│   ├── css/
│   ├── js/
│   └── icons/
├── classes/               # PHP classes
│   ├── VisitorManager.php
│   ├── VehicleManager.php
│   ├── VisitManager.php
│   ├── QRCodeGenerator.php
│   └── NotificationManager.php
├── config/                # Configuration files
│   └── database.php
├── uploads/               # File uploads
│   ├── visitors/
│   └── vehicles/
├── qrcodes/              # Generated QR codes
├── logs/                 # Error logs
├── index.php             # Main application
├── install.php           # Installation wizard
├── auth.php              # Authentication system
├── manifest.json         # PWA manifest
├── sw.js                 # Service worker
├── .htaccess             # Apache configuration
└── README.md             # Documentation
```

## 🔑 Default Login

After installation, use these credentials:

- **Username:** admin
- **Email:** admin@example.com  
- **Password:** admin123

**⚠️ Change the default password immediately after first login!**

## 📱 Usage Guide

### Dashboard
- View real-time visitor statistics
- Quick access to scanning and check-in
- Monitor current occupancy
- Review recent activity

### Visitor Management
- Add new visitors with photos
- Search and filter visitor database
- View visit history and ratings
- Manage blacklist and security alerts

### QR Code Scanning
- Scan QR codes with device camera
- Upload QR code images for processing
- Verify visitor credentials instantly
- Process check-in/out operations

### Pre-Registration
- Allow visitors to register online
- Approve/reject registration requests
- Generate QR codes for approved visits
- Send email confirmations automatically

### Reporting
- Generate visitor traffic reports
- Analyze peak hours and patterns
- Export data in various formats
- Track security incidents

## 🔧 Configuration

### Email Settings
```php
// Configure SMTP in system settings
SMTP_HOST = 'smtp.gmail.com'
SMTP_PORT = 587
SMTP_USERNAME = 'your-email@gmail.com'
SMTP_PASSWORD = 'your-app-password'
```

### Security Settings
```php
// Adjust security parameters
MAX_LOGIN_ATTEMPTS = 5
LOGIN_LOCKOUT_TIME = 300  // 5 minutes
SESSION_LIFETIME = 3600   // 1 hour
PASSWORD_MIN_LENGTH = 6
```

### File Upload Limits
```php
// Customize upload settings
MAX_FILE_SIZE = 5 * 1024 * 1024  // 5MB
ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif']
```

## 👥 User Roles

### Super Admin
- Full system access
- User management
- System configuration
- Security settings

### Admin
- Visitor and vehicle management
- Reports and analytics
- Staff management
- System settings

### Security
- Visitor check-in/out
- QR code scanning
- Dashboard monitoring
- Basic reporting

### Receptionist
- Visitor registration
- Pre-registration approval
- Basic reporting
- Dashboard access

## 🔒 Security Features

- **Authentication** - Secure login with session management
- **Authorization** - Role-based access control
- **Data Protection** - SQL injection prevention
- **File Security** - Safe upload handling
- **Activity Logging** - Complete audit trail
- **Rate Limiting** - API abuse prevention
- **HTTPS Support** - SSL/TLS encryption
- **CSRF Protection** - Cross-site request forgery prevention

## 🔧 API Documentation

### Authentication
```javascript
// Login
POST /api/login
{
  "username": "admin",
  "password": "admin123"
}

// Response
{
  "success": true,
  "user": {...},
  "token": "session_token"
}
```

### Visitor Management
```javascript
// Get visitors
GET /api/visitors?page=1&limit=20&search=john

// Create visitor
POST /api/visitors
{
  "full_name": "John Doe",
  "phone": "+1234567890",
  "email": "john@example.com",
  "company": "ABC Corp"
}

// Update visitor
PUT /api/visitors
{
  "id": 1,
  "full_name": "John Smith"
}
```

### Check-in/out
```javascript
// Check-in
POST /api/checkin
{
  "qr_code": "VIS12345678",
  "temperature_reading": 36.5,
  "health_declaration": true
}

// Check-out
POST /api/checkout
{
  "visit_id": 123,
  "rating": 5,
  "feedback": "Great experience"
}
```

## 🎨 Customization

### Themes and Colors
- Modify primary colors in system settings
- Customize logo and branding
- Adjust layout preferences
- Configure notification styles

### QR Code Templates
- Design custom visitor card layouts
- Add company branding
- Configure data fields
- Set printing preferences

### Email Templates
- Customize notification messages
- Add company branding
- Configure automatic sending
- Set up approval workflows

## 📊 Performance Optimization

### Caching
- Browser caching for static assets
- API response caching
- Database query optimization
- CDN integration support

### Database
- Indexed search fields
- Optimized queries
- Regular cleanup routines
- Backup procedures

### Mobile Performance
- Progressive Web App (PWA)
- Offline functionality
- Fast loading times
- Touch-optimized interface

## 🐛 Troubleshooting

### Common Issues

**QR Scanner Not Working**
- Check camera permissions
- Ensure HTTPS connection
- Update browser to latest version
- Try uploading QR image instead

**Email Notifications Not Sending**
- Verify SMTP settings
- Check firewall/security settings
- Ensure email credentials are correct
- Test with different email provider

**Database Connection Errors**
- Verify database credentials
- Check database server status
- Ensure database exists
- Review error logs

**File Upload Issues**
- Check file size limits
- Verify upload directory permissions
- Ensure allowed file types
- Review PHP upload settings

### Debug Mode
```php
// Enable debug mode (development only)
define('DEBUG_MODE', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📞 Support

### Documentation
- In-app help guides
- Video tutorials (coming soon)
- FAQ section
- Best practices guide

### Technical Support
- GitHub issues for bug reports
- Community forum discussions
- Email support for enterprise users
- Custom development services

## 🔄 Updates

### Version History
- **v1.0.0** - Initial release with core features
- **v1.1.0** - Enhanced QR scanning and PWA support
- **v1.2.0** - Advanced reporting and analytics (planned)

### Update Process
1. Backup database and files
2. Download latest version
3. Upload new files (excluding config)
4. Run database migrations if needed
5. Clear browser cache
6. Test functionality

## 📄 License

This project is proprietary software. All rights reserved.

**Commercial License**
- Use in commercial environments
- Technical support included
- Custom development available
- White-label options

## 🤝 Contributing

We welcome contributions to improve GatePass Pro:

1. **Bug Reports** - Submit detailed issue reports
2. **Feature Requests** - Suggest new functionality
3. **Code Contributions** - Submit pull requests
4. **Documentation** - Help improve guides
5. **Testing** - Report compatibility issues

### Development Setup
```bash
# Clone repository
git clone https://github.com/yourusername/gatepass-pro.git

# Set up local environment
composer install
npm install

# Configure database
cp config/database.example.php config/database.php

# Run tests
php vendor/bin/phpunit
```

## 🙏 Acknowledgments

- **Tailwind CSS** - For the beautiful UI framework
- **RemixIcon** - For the comprehensive icon set
- **QR Code Libraries** - For QR generation and scanning
- **PHPMailer** - For reliable email functionality
- **Open Source Community** - For inspiration and tools

---

**GatePass Pro** - Professional Gate Management Made Simple

*For enterprise solutions and custom development, contact our team.*

File structure 
gatepass-pro/
├── 📄 index.php                    ✅ Main application entry point
├── 📄 install.php                  ✅ Installation wizard
├── 📄 register.php                 ✅ Public visitor registration
├── 📄 verify.php                   ✅ QR code verification page
├── 📄 auth.php                     ✅ Authentication system
├── 📄 .htaccess                    ✅ Apache configuration
├── 📄 manifest.json                ✅ PWA manifest
├── 📄 sw.js                        ✅ Service worker
├── 📄 README.md                    ✅ Documentation
├── 📄 gatepass_database.sql        ✅ Complete database schema
│
├── 📁 config/
│   ├── 📄 database.php             ✅ Database configuration
│   ├── 📄 app.php                  ✅ App configuration
│   └── 📄 installed.lock           (auto-generated after install)
│
├── 📁 api/
│   └── 📄 index.php                ✅ Complete API router
│
├── 📁 classes/
│   ├── 📄 VisitorManager.php       ✅ Visitor management
│   ├── 📄 VehicleManager.php       ✅ Vehicle management
│   ├── 📄 VisitManager.php         ✅ Visit tracking
│   ├── 📄 QRCodeGenerator.php      ✅ QR code handling
│   ├── 📄 NotificationManager.php  ✅ Email notifications
│   ├── 📄 ReportManager.php        ✅ Report generation
│   └── 📄 SettingsManager.php      ✅ System settings
│
├── 📁 assets/
│   ├── 📁 js/
│   │   ├── 📄 app.js               ✅ Main application JS
│   │   ├── 📄 scanner.js           ✅ QR scanner functionality
│   │   └── 📄 offline.js           ✅ Offline functionality
│   └── 📁 css/
│       └── 📄 style.css            (can be created with custom styles)
│
├── 📁 uploads/                     
│   ├── 📁 visitors/
│   ├── 📁 vehicles/
│   └── 📁 documents/
│
├── 📁 qrcodes/                     
├── 📁 exports/                     
└── 📁 logs/                        