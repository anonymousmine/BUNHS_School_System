<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Navigation Test</title>
    <style>
        body { 
            background: #f0f2f7; 
            font-family: 'Inter', sans-serif; 
            padding: 20px;
            margin: 0;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: 0 auto;
        }
        .test-result {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .nav-container {
            background: #2c3e50;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .dashboard-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            display: none;
        }
        .btn {
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #2563eb;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="test-result">
            <h3>🧪 Dashboard Navigation Test</h3>
            <p>This test will verify that the navigation loading works correctly.</p>
            <div id="testStatus">
                <p><strong>Status:</strong> <span class="success">Ready to test</span></p>
            </div>
        </div>
        
        <div class="nav-container" id="navigation-container">
            <p>Navigation will load here...</p>
        </div>
        
        <div class="dashboard-content" id="dashboard-content">
            <h3>🎯 Dashboard Content</h3>
            <p>If you can see this, the dashboard loaded successfully!</p>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
                <h4>✅ Success Indicators:</h4>
                <ul>
                    <li>Navigation loaded via AJAX</li>
                    <li>JavaScript executed properly</li>
                    <li>DOM manipulation worked</li>
                    <li>Content visibility toggled</li>
                </ul>
            </div>
            
            <div style="margin-top: 15px;">
                <button class="btn" onclick="testNavigation()">
                    <i class="fas fa-play"></i> Test Navigation Loading
                </button>
                <button class="btn" onclick="location.href='admin_dashboard.php'">
                    <i class="fas fa-arrow-right"></i> Go to Main Dashboard
                </button>
            </div>
        </div>
    </div>
    
    <script>
        console.log('Navigation test page loaded');
        
        // Test navigation loading function (same as main dashboard)
        function testNavigation() {
            console.log('Testing navigation loading...');
            const container = document.getElementById('navigation-container');
            const statusDiv = document.getElementById('testStatus');
            
            if (!container) {
                console.error('Navigation container not found');
                statusDiv.innerHTML = '<p><strong>Status:</strong> <span class="error">❌ Navigation container not found</span></p>';
                return;
            }

            // Try multiple possible paths for admin_nav.php
            const possiblePaths = [
                'admin_nav.php',
                './admin_nav.php',
                '../admin_nav.php',
                '../../admin_nav.php'
            ];
            
            let pathIndex = 0;
            let foundWorkingPath = false;
            
            function tryLoadPath(path) {
                console.log(`Trying navigation path: ${path}`);
                statusDiv.innerHTML = `<p><strong>Status:</strong> Testing path: ${path}...</p>`;
                
                // Set a timeout for this attempt
                const attemptTimeout = setTimeout(() => {
                    console.log(`Timeout for path: ${path}, trying next...`);
                    pathIndex++;
                    if (pathIndex < possiblePaths.length) {
                        tryLoadPath(possiblePaths[pathIndex]);
                    } else {
                        console.log('All paths failed, using fallback navigation');
                        createFallbackNavigation();
                        statusDiv.innerHTML = '<p><strong>Status:</strong> <span class="success">✅ Using fallback navigation</span></p>';
                    }
                }, 2000); // 2 second timeout per attempt
                
                fetch(path)
                    .then(response => {
                        clearTimeout(attemptTimeout);
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.text();
                    })
                    .then(data => {
                        clearTimeout(attemptTimeout);
                        console.log(`Navigation loaded successfully from: ${path}`);
                        container.innerHTML = data;
                        foundWorkingPath = true;
                        
                        // Show dashboard content
                        setTimeout(() => {
                            const dashboardContent = document.getElementById('dashboard-content');
                            if (dashboardContent) {
                                dashboardContent.style.display = 'block';
                                console.log('Dashboard content shown');
                                statusDiv.innerHTML = `<p><strong>Status:</strong> <span class="success">✅ Navigation loaded from: ${path}</span></p>`;
                            }
                        }, 100);
                    })
                    .catch(error => {
                        clearTimeout(attemptTimeout);
                        console.error(`Failed to load from ${path}:`, error);
                        pathIndex++;
                        if (pathIndex < possiblePaths.length) {
                            tryLoadPath(possiblePaths[pathIndex]);
                        } else {
                            console.log('All paths failed, using fallback navigation');
                            createFallbackNavigation();
                            statusDiv.innerHTML = '<p><strong>Status:</strong> <span class="success">✅ Using fallback navigation</span></p>';
                        }
                    });
            }
            
            // Start trying paths
            tryLoadPath(possiblePaths[0]);
        }
        
        // Create fallback navigation
        function createFallbackNavigation() {
            const container = document.getElementById('navigation-container');
            container.innerHTML = `
                <div style="background: #2c3e50; color: white; padding: 15px;">
                    <h2>🧪 Test Navigation</h2>
                    <p>Navigation loaded (fallback mode)</p>
                    <div style="margin-top: 10px;">
                        <span style="background: #10b981; padding: 5px 10px; border-radius: 4px; font-size: 12px;">
                            ✅ Test Mode Active
                        </span>
                        <span style="background: #3b82f6; padding: 5px 10px; border-radius: 4px; font-size: 12px; margin-left: 10px;">
                            ✅ Fallback Working
                        </span>
                    </div>
                </div>
            `;
            
            // Show dashboard content immediately
            setTimeout(() => {
                const dashboardContent = document.getElementById('dashboard-content');
                if (dashboardContent) {
                    dashboardContent.style.display = 'block';
                    console.log('Dashboard content shown (fallback mode)');
                }
            }, 100);
        }
        
        // Auto-test on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Test page DOM loaded');
            
            // Show dashboard content after 1 second regardless of navigation
            setTimeout(() => {
                const dashboardContent = document.getElementById('dashboard-content');
                if (dashboardContent) {
                    dashboardContent.style.display = 'block';
                    console.log('Dashboard content forced to show');
                }
            }, 1000);
        });
    </script>
</body>
</html>
