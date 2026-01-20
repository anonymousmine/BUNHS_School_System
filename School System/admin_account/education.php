<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Admin Dashboard</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div id="navigation-container"></div>

    <div class="page-content">
        <div class="chart-container">
            <canvas id="literacyChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="filipinoLiteracyChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="numeracyChart"></canvas>
        </div>
    </div>

    <script>
        // Load navigation from admin_nav.html
        fetch('admin_nav.html')
            .then(response => response.text())
            .then(data => {
                document.getElementById('navigation-container').innerHTML = data;

                // Move page content to .main div
                const mainDiv = document.querySelector('.main');
                const pageContent = document.querySelector('.page-content');
                if (mainDiv && pageContent) {
                    mainDiv.appendChild(pageContent);
                }

                // Initialize dropdown functionality after navigation loads
                initializeDropdowns();
            })
            .catch(error => console.error('Error loading navigation:', error));

        // Dropdown initialization function
        function initializeDropdowns() {
            // Dropdown toggle functionality
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                toggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const dropdown = this.closest('.dropdown');
                    const isActive = dropdown.classList.contains('active');

                    // Close all dropdowns
                    document.querySelectorAll('.dropdown').forEach(d => {
                        d.classList.remove('active');
                    });

                    // Toggle the clicked dropdown
                    if (!isActive) {
                        dropdown.classList.add('active');
                    }
                });
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown').forEach(dropdown => {
                        dropdown.classList.remove('active');
                    });
                }
            });
        }

        // Initialize the literacy chart
        const ctx = document.getElementById('literacyChart').getContext('2d');
        const literacyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'],
                datasets: [
                    {
                        label: 'Frustration',
                        data: [20, 15, 10, 5], // Sample data
                        backgroundColor: 'rgba(255, 99, 132, 0.8)',
                        stack: 'Stack 0'
                    },
                    {
                        label: 'Instructional',
                        data: [30, 35, 40, 45], // Sample data
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        stack: 'Stack 0'
                    },
                    {
                        label: 'Independent',
                        data: [25, 30, 35, 40], // Sample data
                        backgroundColor: 'rgba(255, 205, 86, 0.8)',
                        stack: 'Stack 0'
                    },
                    {
                        label: 'Grade Level',
                        data: [25, 20, 15, 10], // Sample data
                        backgroundColor: 'rgba(75, 192, 192, 0.8)',
                        stack: 'Stack 0'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Percentage (%)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Literacy Rate - English (Grades 7-10)'
                    }
                }
            }
        });

        // Initialize the Filipino literacy chart
        const ctxFilipino = document.getElementById('filipinoLiteracyChart').getContext('2d');
        const filipinoLiteracyChart = new Chart(ctxFilipino, {
            type: 'bar',
            data: {
                labels: ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'],
                datasets: [
                    {
                        label: 'Frustration',
                        data: [15, 10, 8, 5], // Sample data
                        backgroundColor: 'rgba(255, 99, 132, 0.8)',
                        stack: 'Stack 1'
                    },
                    {
                        label: 'Instructional',
                        data: [35, 40, 45, 50], // Sample data
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        stack: 'Stack 1'
                    },
                    {
                        label: 'Independent',
                        data: [30, 35, 40, 35], // Sample data
                        backgroundColor: 'rgba(255, 205, 86, 0.8)',
                        stack: 'Stack 1'
                    },
                    {
                        label: 'Grade Level',
                        data: [20, 15, 7, 10], // Sample data
                        backgroundColor: 'rgba(75, 192, 192, 0.8)',
                        stack: 'Stack 1'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Percentage (%)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Literacy Rate - Filipino (Grades 7-10)'
                    }
                }
            }
        });

        // Initialize the numeracy chart
        const ctxNumeracy = document.getElementById('numeracyChart').getContext('2d');
        const numeracyChart = new Chart(ctxNumeracy, {
            type: 'bar',
            data: {
                labels: ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'],
                datasets: [
                    {
                        label: 'Not Proficient',
                        data: [10, 8, 5, 3], // Sample data
                        backgroundColor: 'rgba(255, 99, 132, 0.8)',
                        stack: 'Stack 2'
                    },
                    {
                        label: 'Low Proficient',
                        data: [20, 25, 30, 35], // Sample data
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        stack: 'Stack 2'
                    },
                    {
                        label: 'Nearly Proficient',
                        data: [30, 35, 40, 45], // Sample data
                        backgroundColor: 'rgba(255, 205, 86, 0.8)',
                        stack: 'Stack 2'
                    },
                    {
                        label: 'Proficient',
                        data: [25, 20, 15, 10], // Sample data
                        backgroundColor: 'rgba(75, 192, 192, 0.8)',
                        stack: 'Stack 2'
                    },
                    {
                        label: 'At grade level',
                        data: [15, 12, 10, 7], // Sample data
                        backgroundColor: 'rgba(153, 102, 255, 0.8)',
                        stack: 'Stack 2'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Percentage (%)'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Numeracy Rate (Grades 7-10)'
                    }
                }
            }
        });
    </script>

    <script src="admin_assets/js/admin_script.js"></script>
</body>

</html>
