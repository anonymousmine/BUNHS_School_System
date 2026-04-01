# Railway Deployment Guide - BUNHS School System

## Overview
This guide provides step-by-step instructions for deploying the BUNHS School System on Railway using GitHub integration.

## Prerequisites
- GitHub repository with the code
- Railway account
- MySQL database on Railway

## Step 1: Database Setup on Railway

1. **Create a MySQL Service:**
   - Go to Railway dashboard
   - Click "New Project" → "Add a Service"
   - Select "MySQL" from the database options
   - Give it a name (e.g., `bunhs-database`)
   - Click "Add MySQL"

2. **Get Database Credentials:**
   - Once the MySQL service is created, click on it
   - Go to the "Connect" tab
   - Note down the following:
     - Host
     - Port
     - Username
     - Password
     - Database name

3. **Import Database Schema:**
   - Use Railway's MySQL viewer or connect locally
   - Import the database schema from `database_setup_fixed.sql`
   - Verify all tables are created

## Step 2: Environment Variables Setup

1. **Set Environment Variables:**
   - Go to your project settings on Railway
   - Click on "Variables" tab
   - Add the following environment variables:

   ```
   DB_HOST=your-mysql-host.railway.app
   DB_PORT=your-mysql-port
   DB_USER=your-mysql-username
   DB_PASSWORD=your-mysql-password
   DB_NAME=bunhs_db_important
   APP_DEBUG=false
   ```

2. **Email Configuration (Optional):**
   ```
   SMTP_HOST=your-smtp-host
   SMTP_PORT=587
   SMTP_USERNAME=your-email
   SMTP_PASSWORD=your-app-password
   SMTP_FROM_EMAIL=noreply@yourdomain.com
   SMTP_FROM_NAME=BUNHS School System
   ```

## Step 3: GitHub Integration

1. **Connect GitHub Repository:**
   - In Railway project, click "New Project" → "Deploy from GitHub repo"
   - Select your BUNHS_school_system repository
   - Choose the branch (usually `main`)
   - Click "Deploy Now"

2. **Configure Build Settings:**
   - Railway will automatically detect the PHP project
   - Ensure the `Procfile` is in the root directory
   - The build process will install Composer dependencies

## Step 4: Verify Deployment

1. **Check Build Logs:**
   - Monitor the build process in Railway dashboard
   - Ensure Composer dependencies install successfully
   - Verify no PHP errors during startup

2. **Test the Application:**
   - Once deployed, visit the provided Railway URL
   - Test basic functionality:
     - Homepage loads correctly
     - Navigation works
     - Login/Signup forms appear
     - Admin dashboard accessible (with credentials)

## Step 5: Post-Deployment Configuration

1. **Admin Account Setup:**
   - Access the admin dashboard via `/login.php`
   - Create initial admin account if needed
   - Configure school settings in admin panel

2. **File Uploads Directory:**
   - Ensure the `uploads/` directory is writable
   - Railway automatically handles this, but verify permissions

3. **SSL Certificate:**
   - Railway automatically provides SSL
   - Your site will be accessible via HTTPS

## Troubleshooting

### Common Issues:

1. **Database Connection Errors:**
   - Verify environment variables are correct
   - Check MySQL service is running
   - Ensure database name matches exactly

2. **Permission Errors:**
   - Check file permissions for uploads directory
   - Verify logs directory is writable

3. **Build Failures:**
   - Check `composer.json` is valid
   - Ensure all PHP extensions are available
   - Review build logs for specific errors

### Debug Mode:
To enable debug mode temporarily:
```
APP_DEBUG=true
```
Remember to disable in production!

## File Structure After Deployment

```
.
├── index.php                 # Entry point
├── admin_account/            # Admin panel
├── user_account/            # User dashboard
├── assets/                  # CSS, JS, images
├── vendor/                  # Composer dependencies
├── uploads/                 # User uploads
├── logs/                    # Application logs
├── db_connection.php        # Database configuration
├── session_config.php       # Session management
├── Procfile                 # Railway process configuration
├── railway.json            # Railway deployment config
└── composer.json           # PHP dependencies
```

## Performance Optimization

1. **Enable Caching:**
   - The system includes APCu caching
   - Ensure PHP OPcache is enabled on Railway

2. **Database Optimization:**
   - Monitor slow queries
   - Add indexes as needed

3. **Asset Optimization:**
   - CSS and JS are already minified
   - Images should be optimized before upload

## Security Notes

1. **Environment Variables:**
   - Never commit sensitive data to Git
   - Use Railway's encrypted environment variables

2. **Database Security:**
   - MySQL on Railway is isolated and secure
   - Regular backups are automatically created

3. **Application Security:**
   - CSRF protection is enabled
   - Session management is secure
   - Input sanitization is implemented

## Monitoring and Maintenance

1. **Logs:**
   - Check Railway logs for errors
   - Monitor application performance

2. **Backups:**
   - Railway automatically backs up MySQL
   - Consider exporting regular backups

3. **Updates:**
   - Update dependencies via Composer
   - Test updates before deploying to production

## Support

For issues specific to Railway deployment:
- Check Railway documentation: https://docs.railway.app/
- Contact Railway support through dashboard

For application-specific issues:
- Review the application logs
- Check the GitHub issues for known problems
