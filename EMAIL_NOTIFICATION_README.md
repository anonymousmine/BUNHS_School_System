# Email Notification System for BUNHS School System

## Overview
This email notification system allows users to subscribe to receive email notifications about upcoming events and news announcements from Buyoan National High School.

## Features Implemented

### ✅ Completed Features
1. **Database Setup**: Created tables for email subscribers and notification logs
2. **Gmail Input Field**: Added email subscription option to the reminder settings modal
3. **API Endpoint**: Created REST API for managing email subscriptions
4. **Email Notifications**: Implemented email sending for both events and news
5. **Event Notifications**: Automatic email sending when new events are created
6. **News Notifications**: Automatic email sending when news announcements are published

## How to Use

### For Users (Email Subscription)
1. Go to the Events page (`events.php`)
2. In the sidebar, find the "Reminders" section
3. Click the **Settings** button (gear icon)
4. Enter your Gmail address in the "Email Notifications" field
5. Click **Save** to subscribe

### For Administrators
1. **Configure Email Settings** (see setup instructions below)
2. Create events or news announcements as usual
3. Email notifications will automatically be sent to all subscribed users

## Setup Instructions

### 1. Database Setup
Run the SQL file to create the necessary tables:
```sql
-- Execute this file in your MySQL database
database_setup_email_notifications.sql
```

### 2. Email Configuration
Edit the `email_config.php` file with your Gmail credentials:

```php
// Replace these values with your actual Gmail settings
define('SMTP_USERNAME', 'your-school-email@gmail.com');
define('SMTP_PASSWORD', 'your-gmail-app-password');
define('FROM_EMAIL', 'your-school-email@gmail.com');
```

### 3. Gmail App Password Setup
1. Go to your Google Account: https://myaccount.google.com/
2. Enable 2-Step Verification if not already enabled
3. Go to Security → 2-Step Verification → App passwords
4. Generate a new app password for "Mail" on "Other (Custom name)"
5. Copy the 16-character password and use it in `email_config.php`

## File Structure

```
├── database_setup_email_notifications.sql    # Database setup script
├── email_config.php                         # Email configuration
├── email_notification_functions.php          # Core email functions
├── api/
│   └── email_subscription_api.php          # REST API for subscriptions
├── events.php                               # Updated with email input field
├── admin_account/announcements/
│   ├── create_announcement.php             # Updated with event notifications
│   └── create_new.php                      # Updated with news notifications
```

## API Endpoints

### Email Subscription API
**Endpoint**: `POST /api/email_subscription_api.php`

**Subscribe**:
```json
{
    "action": "subscribe",
    "email": "user@gmail.com"
}
```

**Unsubscribe**:
```json
{
    "action": "unsubscribe", 
    "email": "user@gmail.com"
}
```

**Responses**:
- Success: `{"status": "success", "message": "Email subscribed successfully"}`
- Info: `{"status": "info", "message": "Email already subscribed"}`
- Error: `{"status": "error", "message": "Error message"}`

## Email Templates

### Event Notification Email
- **Subject**: "New Event: [Event Title]"
- **Content**: Event title, date, category, description
- **Footer**: Subscription notice

### News Notification Email
- **Subject**: "News: [News Title]"
- **Content**: News title, author, date, category, short description
- **Footer**: Subscription notice

## Database Tables

### email_subscribers
- `id` - Primary key
- `email` - Subscriber email (unique)
- `is_active` - Subscription status (1=active, 0=inactive)
- `subscribed_at` - When they subscribed
- `updated_at` - Last update time

### email_notification_logs
- `id` - Primary key
- `subscriber_id` - Foreign key to subscribers
- `notification_type` - 'event' or 'news'
- `item_id` - Event ID or News ID
- `sent_at` - When notification was sent
- `status` - 'sent', 'failed', 'pending'
- `error_message` - Error details if failed

## Troubleshooting

### Emails Not Sending
1. Check PHP error logs for PHPMailer errors
2. Verify Gmail credentials in `email_config.php`
3. Ensure PHPMailer is properly installed in `vendor/` directory
4. Test with a simple email script first

### Common Issues
- **"Authentication failed"**: Check Gmail app password
- **"Connection refused"**: Check SMTP settings and firewall
- **"Email not found"**: Verify subscriber exists in database

### Testing the System
1. Subscribe with your own email address
2. Create a test event or news announcement
3. Check if you receive the email notification
4. Check the `email_notification_logs` table for delivery status

## Security Considerations

- ✅ Email validation and sanitization
- ✅ SQL injection protection with prepared statements
- ✅ CSRF protection in forms
- ⚠️ Store Gmail credentials securely (consider environment variables for production)
- ⚠️ Never commit actual email credentials to version control

## Future Enhancements

Potential improvements for the future:
- Email template customization
- Unsubscribe link in emails
- Email digest options (daily/weekly)
- Bounce handling and cleanup
- Email analytics and open tracking
- Multiple notification types (reminders, updates)

## Support

If you encounter issues:
1. Check the PHP error logs
2. Verify database tables were created
3. Test email configuration with a simple script
4. Review the troubleshooting section above

---

**Note**: This system requires PHPMailer to be installed via Composer. If it's not already available, run:
```bash
composer require phpmailer/phpmailer
```
