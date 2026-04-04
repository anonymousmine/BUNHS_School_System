#!/bin/bash

echo "🚀 Railway Deployment Script - BUNHS School System"
echo "=================================================="

# Add timestamp to force cache invalidation
echo "📅 Adding deployment timestamp..."
date > deployment_timestamp.txt

# Stage all changes
echo "📦 Staging changes..."
git add .

# Commit with timestamp to force new deployment
echo "💾 Committing changes..."
git commit -m "Fix Railway deployment - $(date '+%Y-%m-%d %H:%M:%S')

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
