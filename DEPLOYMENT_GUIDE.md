# 🚀 BUNHS School System - Railway Deployment Guide

## 📋 Prerequisites

1. **GitHub Repository**: https://github.com/anonymousmine/BUNHS_school_system
2. **Railway Account**: Sign up at https://railway.app
3. **MySQL Database**: Railway MySQL addon
4. **Domain**: Optional custom domain

---

## 🛠️ Step-by-Step Deployment

### 1. **Repository Setup**
```bash
# Clone the repository
git clone https://github.com/anonymousmine/BUNHS_school_system.git
cd BUNHS_school_system

# Install dependencies
composer install
```

### 2. **Railway Project Setup**

1. **Create New Project**
   - Login to Railway dashboard
   - Click "New Project" → "Deploy from GitHub repo"
   - Select `BUNHS_school_system` repository

2. **Add MySQL Database**
   - In project dashboard, click "+ New"
   - Select "MySQL" addon
   - Choose plan (Free tier available)
   - Note the database credentials

### 3. **Environment Variables Configuration**

In Railway project → Variables tab, add these variables:

```bash
# Database (Auto-filled by Railway MySQL addon)
DB_HOST=${RAILWAY_PRIVATE_DOMAIN}
DB_USER=${MYSQLUSER}
DB_PASSWORD=${MYSQLPASSWORD}
DB_NAME=${MYSQLDATABASE}
DB_PORT=${MYSQLPORT}

# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=${RAILWAY_PUBLIC_DOMAIN}

# Security
JWT_SECRET=your-unique-jwt-secret-key
CSRF_SECRET=your-unique-csrf-secret-key

# Email (Optional)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

### 4. **Database Initialization**

1. **Access Railway Shell**
   - Project → MySQL addon → "Connect" → "Open Shell"
   
2. **Import Database Schema**
   ```sql
   -- Copy contents from bunhs_database_export.sql
   -- Paste and execute in MySQL shell
   ```

3. **Verify Tables**
   ```sql
   SHOW TABLES;
   SELECT COUNT(*) FROM admin; -- Should show 1 (default admin)
   ```

### 5. **Deploy Configuration**

The project includes:
- ✅ `Procfile` (Railway start command)
- ✅ `railway.json` (Build configuration)
- ✅ `.env.example` (Environment template)
- ✅ `composer.json` (PHP dependencies)

### 6. **Deployment Process**

1. **Automatic Deployment**
   - Railway automatically detects changes
   - Builds and deploys on each push to main branch

2. **Manual Deployment**
   ```bash
   git add .
   git commit -m "Deployment ready"
   git push origin main
   ```

### 7. **Post-Deployment Checks**

1. **Application Health**
   - Visit `${RAILWAY_PUBLIC_DOMAIN}`
   - Should see homepage with stats
   - Check for database errors

2. **Admin Access**
   - URL: `${RAILWAY_PUBLIC_DOMAIN}/admin_login.php`
   - Default credentials:
     - Username: `admin`
     - Password: `admin123`

3. **Test Key Features**
   - ✅ Homepage loads with statistics
   - ✅ Navigation works
   - ✅ Login/Signup modals
   - ✅ News and Events pages
   - ✅ Admin dashboard access

---

## 🔧 Troubleshooting

### Common Issues & Solutions

#### 1. **Database Connection Error**
```
Error: mysqli extension not loaded
```
**Solution**: Railway PHP image includes mysqli by default. Check build logs.

#### 2. **White Screen/500 Error**
**Causes**:
- Missing database tables
- Incorrect environment variables
- PHP syntax errors

**Solutions**:
1. Check Railway logs: Project → Logs
2. Verify database schema imported
3. Confirm environment variables set correctly

#### 3. **Login Not Working**
**Check**:
- Admin table exists and has records
- Password hashing works correctly
- Session configuration

#### 4. **Images/Assets Not Loading**
**Solution**:
- Verify `uploads/` directory permissions
- Check asset paths in CSS/JS
- Ensure images exist in `assets/img/`

### Debug Commands

```bash
# Check PHP extensions
railway logs | grep "mysqli"

# View environment variables
railway variables

# Access MySQL shell
railway mysql shell

# Restart application
railway restart
```

---

## 📊 Performance Optimization

### Built-in Optimizations
- ✅ Database connection pooling
- ✅ Query result caching
- ✅ Asset compression
- ✅ Session management

### Recommended Settings
```bash
# Railway Variables
CACHE_TTL_STATS=300
CACHE_TTL_CREDENTIALS=3600
MAX_FILE_SIZE=5242880
SESSION_LIFETIME=7200
```

---

## 🔒 Security Considerations

### ✅ Implemented
- CSRF protection
- SQL injection prevention
- Session security
- Input validation
- File upload security

### 🚨 Important Security Steps
1. **Change Default Admin Password**
   - Login to admin dashboard
   - Update admin credentials immediately

2. **Set Custom JWT/CSRF Secrets**
   - Use strong, unique secrets
   - Rotate periodically

3. **Configure HTTPS**
   - Railway provides automatic SSL
   - Enforce HTTPS in application

4. **Database Security**
   - Railway MySQL is isolated
   - Use strong database password
   - Limit database user permissions

---

## 📈 Monitoring & Maintenance

### Health Monitoring
- Railway provides built-in monitoring
- Check logs regularly
- Monitor database performance

### Backup Strategy
- Railway MySQL: Automatic backups
- File uploads: Consider cloud storage
- Code: Version control (GitHub)

### Updates & Maintenance
```bash
# Update dependencies
composer update

# Deploy updates
git add .
git commit -m "Update dependencies"
git push origin main
```

---

## 🎯 Success Criteria

✅ **Deployment Successful When**:
- Homepage loads without errors
- Database statistics display correctly
- Admin login works
- All navigation links functional
- No PHP errors in logs
- Images and assets load properly

---

## 📞 Support

### Resources
- **Railway Documentation**: https://docs.railway.app
- **GitHub Repository**: https://github.com/anonymousmine/BUNHS_school_system
- **Issue Reporting**: GitHub Issues

### Quick Commands
```bash
# View logs
railway logs

# Access database
railway mysql shell

# Restart service
railway restart

# Check status
railway status
```

---

## 🎉 Deployment Complete!

Your BUNHS School System is now live on Railway! 🚀

**Next Steps**:
1. Customize school information
2. Upload actual school images
3. Configure email notifications
4. Set up custom domain (optional)
5. Train staff and students

**Live URL**: `${RAILWAY_PUBLIC_DOMAIN}`
**Admin URL**: `${RAILWAY_PUBLIC_DOMAIN}/admin_login.php`

---

*Last Updated: <?php echo date('Y-m-d H:i:s'); ?>*
*Version: 1.0 - Railway Ready*
