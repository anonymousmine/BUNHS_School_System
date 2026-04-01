# BUNHS School System

A comprehensive web-based information management system for Buyoan National High School, designed to streamline administrative processes, enhance communication, and improve overall school management efficiency.

## 🏫 Features

### Core Features
- **Responsive Web Design** - Mobile-friendly interface
- **Admin Dashboard** - Complete administrative control
- **User Management** - Student, teacher, and staff management
- **News & Announcements** - Dynamic content management
- **Event Management** - School calendar and event tracking
- **Academic Management** - Grade and subject management
- **File Management** - Document upload and sharing
- **Messaging System** - Internal communication
- **Reports & Analytics** - Data-driven insights

### Technical Features
- **Secure Authentication** - OTP-based login system
- **Role-Based Access** - Admin, sub-admin, and user roles
- **Database Caching** - APCu for performance optimization
- **Session Management** - Secure session handling
- **CSRF Protection** - Cross-site request forgery prevention
- **Email Notifications** - PHPMailer integration
- **File Uploads** - Secure file management

## 🛠 Technology Stack

### Backend
- **PHP 8.3** - Core programming language
- **MySQL 8.0** - Database management
- **Composer** - Dependency management
- **APCu** - Caching system

### Frontend
- **Bootstrap 5.3** - CSS framework
- **Font Awesome** - Icon library
- **Swiper.js** - Carousel/slider
- **GLightbox** - Lightbox gallery
- **Vanilla JavaScript** - Interactive features

### Development Tools
- **Git** - Version control
- **Railway** - Cloud deployment platform
- **GitHub** - Code repository

## 📋 Requirements

### Server Requirements
- PHP 8.0 or higher
- MySQL 8.0 or MariaDB 10.3+
- Apache/Nginx web server
- Composer package manager
- SSL certificate (recommended)

### PHP Extensions Required
- `mysqli` - Database connectivity
- `pdo_mysql` - Database abstraction
- `mbstring` - Multi-byte string handling
- `zip` - File compression
- `gd` - Image processing
- `curl` - HTTP client
- `json` - JSON handling
- `session` - Session management

### Optional Extensions
- `apcu` - Caching (recommended for performance)
- `openssl` - Secure communications

## 🚀 Quick Start

### Local Development

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/anonymousmine/BUNHS_school_system.git
   cd BUNHS_school_system
   ```

2. **Install Dependencies:**
   ```bash
   composer install
   ```

3. **Database Setup:**
   ```bash
   # Import the database schema
   mysql -u root -p bunhs_db_important < database_setup_fixed.sql
   ```

4. **Configure Environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

5. **Start Development Server:**
   ```bash
   php -S localhost:8000
   ```

6. **Access the Application:**
   - Open your browser to `http://localhost:8000`
   - Default admin credentials will be in the database

### Production Deployment

See [RAILWAY_DEPLOYMENT.md](RAILWAY_DEPLOYMENT.md) for detailed deployment instructions.

## 📁 Project Structure

```
BUNHS_School_System/
├── admin_account/              # Admin panel and management
│   ├── admin_dashboard.php     # Main admin interface
│   ├── admin_profile.php       # Admin profile management
│   ├── announcements/          # Announcement management
│   ├── api/                   # Admin API endpoints
│   └── settings.php           # System settings
├── user_account/              # User dashboard
│   ├── Dashboard.php          # User interface
│   ├── chatbox.php           # Messaging system
│   └── api/                 # User API endpoints
├── assets/                   # Static assets
│   ├── css/                  # Stylesheets
│   ├── js/                   # JavaScript files
│   ├── img/                  # Images
│   └── vendor/               # Third-party libraries
├── logs/                     # Application logs
├── uploads/                  # User uploaded files
├── vendor/                   # Composer dependencies
├── index.php                 # Main entry point
├── db_connection.php         # Database configuration
├── session_config.php        # Session management
├── composer.json             # PHP dependencies
├── Procfile                 # Railway deployment config
└── README.md               # This file
```

## 🔧 Configuration

### Database Configuration
Edit `db_connection.php` or set environment variables:

```php
$host    = getenv('DB_HOST')    ?: 'localhost';
$db_user = getenv('DB_USER')    ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME')    ?: 'bunhs_db_important';
$db_port = getenv('DB_PORT')    ?: null;
```

### Email Configuration
Configure email settings in `email_config.php` or environment variables:

```php
SMTP_HOST=your-smtp-host
SMTP_PORT=587
SMTP_USERNAME=your-email
SMTP_PASSWORD=your-app-password
```

## 👥 User Roles

### Administrator
- Full system access
- User management
- System configuration
- Content management
- Report generation

### Sub-Administrator
- Limited administrative access
- Specific module management
- Content creation/editing
- Basic reporting

### Student/User
- Personal profile management
- View announcements
- Access resources
- Submit forms

## 🔒 Security Features

- **CSRF Protection** - Prevents cross-site request forgery
- **Session Security** - Secure session management with regeneration
- **Input Validation** - Sanitizes all user inputs
- **SQL Injection Protection** - Prepared statements for all queries
- **XSS Prevention** - Output encoding and content security policy
- **Rate Limiting** - Login attempt protection
- **Secure File Uploads** - File type and size validation

## 📊 Database Schema

The system uses 33+ tables including:

- **Users**: `admin`, `sub_admin`, `students`, `teachers`
- **Content**: `news`, `events`, `school_announcements`
- **System**: `school_settings`, `homepage_cards`, `activity_logs`
- **Communication**: `chat_messages`, `admin_notifications`
- **Analytics**: `event_views`, `school_ratings`

## 🧪 Testing

### Running Tests
```bash
# Check PHP syntax
php -l index.php

# Test database connection
php -r "require 'db_connection.php'; echo 'DB OK';"

# Verify session configuration
php -r "require 'session_config.php'; echo 'Session OK';"
```

### Browser Testing
- Chrome/Chromium (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## 📝 Logging

The system maintains comprehensive logs:

- **Login Attempts**: `logs/login_attempts.log`
- **PHP Errors**: `logs/php_errors.log`
- **Signup Attempts**: `logs/signup_attempts.log`
- **Session Events**: Logged to database

## 🔄 Backup and Recovery

### Database Backup
```bash
mysqldump -u root -p bunhs_db_important > backup.sql
```

### File Backup
```bash
tar -czf uploads_backup.tar.gz uploads/
```

### Automated Backups
- Railway provides automatic database backups
- Configure backup retention in Railway dashboard

## 🚀 Performance Optimization

### Caching
- APCu for database query caching
- Static asset caching via headers
- Session caching for frequently accessed data

### Database Optimization
- Indexed columns for fast queries
- Optimized joins and relationships
- Query result caching

### Asset Optimization
- Minified CSS and JavaScript
- Optimized images
- CDN-ready asset structure

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

For support and questions:

- **Documentation**: Check this README and deployment guides
- **Issues**: Report bugs via GitHub Issues
- **Community**: Join our development community

## 🗺 Roadmap

### Upcoming Features
- [ ] Mobile app integration
- [ ] Advanced analytics dashboard
- [ ] SMS notifications
- [ ] Multi-language support
- [ ] API for third-party integrations

### Technical Improvements
- [ ] Microservices architecture
- [ ] Redis caching
- [ ] Load balancing
- [ ] Advanced security features

---

**Developed by:** Bibert Ribano, Georgie-Anne Cabarubia, and Wendellyn A. Gaviola  
**Maintained by:** BUNHS Development Team  
**Version:** 2.0.0  
**Last Updated:** 2025
