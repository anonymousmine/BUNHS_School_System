#!/bin/bash

echo "🚀 Railway MySQL Integration Deployment"
echo "======================================="

# Add timestamp to force cache invalidation
echo "📅 Adding deployment timestamp..."
date > deployment_timestamp.txt

# Verify MySQL integration files exist
echo "🔍 Verifying MySQL integration files..."
if [ -f "config/database.php" ]; then
    echo "✅ config/database.php found"
else
    echo "❌ config/database.php missing"
    exit 1
fi

if [ -f "railway-mysql.yml" ]; then
    echo "✅ railway-mysql.yml found"
else
    echo "❌ railway-mysql.yml missing"
    exit 1
fi

# Stage all changes
echo "📦 Staging changes..."
git add .

# Commit with timestamp to force new deployment
echo "💾 Committing changes..."
git commit -m "Integrate Railway MySQL service - $(date '+%Y-%m-%d %H:%M:%S')

Changes:
- Unified database configuration (config/database.php)
- Railway MySQL service definition (railway-mysql.yml)
- Updated db_connection.php for Railway integration
- No more local database dependencies
- Automatic environment detection

# Push to Railway
echo "🚀 Pushing to Railway..."
git push railway main

echo "✅ Deployment initiated!"
echo "📊 Monitor deployment at: https://railway.app"
echo "⏱️  Wait 2-3 minutes for deployment to complete"
echo ""
echo "🔍 Test your Railway URL after deployment:"
echo "   - Main app: https://bunhs-web-based-information-system.up.railway.app"
echo "   - Test endpoint: https://bunhs-web-based-information-system.up.railway.app/test_railway.php"
echo ""
echo "📝 Next Steps:"
echo "   1. Create Railway MySQL service (if not already done)"
echo "   2. Set environment variables in Railway dashboard"
echo "   3. Import database schema to Railway MySQL"
echo "   4. Test complete application functionality"
