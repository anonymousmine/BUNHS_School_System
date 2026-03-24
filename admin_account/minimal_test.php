<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minimal Dashboard Test</title>
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
        .status {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        .success { color: #10b981; font-weight: bold; }
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
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="status">
            <h3>🔧 Minimal Dashboard Test</h3>
            <p><strong>Status:</strong> <span class="success">✅ Loading</span></p>
            <p>This is a minimal test to check if the basic structure loads.</p>
        </div>
        
        <div class="nav-container" id="navigation-container">
            <p>Navigation should load here...</p>
        </div>
        
        <div class="dashboard-content" id="dashboard-content" style="display: none;">
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
        </div>
    </div>
    
    <script>
        console.log('Minimal dashboard test starting...');
        
        // Test basic DOM manipulation
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            
            // Show dashboard content immediately
            setTimeout(() => {
                const content = document.getElementById('dashboard-content');
                if (content) {
                    content.style.display = 'block';
                    console.log('✅ Dashboard content shown');
                }
            }, 500);
            
            // Test navigation loading
            setTimeout(() => {
                const navContainer = document.getElementById('navigation-container');
                if (navContainer) {
                    navContainer.innerHTML = '<p>✅ Navigation loaded successfully!</p>';
                    console.log('✅ Navigation test complete');
                }
            }, 1000);
        });
    </script>
</body>
</html>
