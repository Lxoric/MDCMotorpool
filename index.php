<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

// Check if the logged-in user is the Main Admin
$is_main_admin = isset($_SESSION['username']) && $_SESSION['username'] === 'Lxoric';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | Maxipro Development Corporation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
			background: url('assets/images/Designer.jpeg') no-repeat center center fixed;
			background-size: cover;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            text-align: center;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
            animation: fadeIn 1s ease-in-out;
            width: 400px;
            position: relative;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px;
        }

        .logo-container img {
            width: 110px;
            animation: lightReflection 2.5s infinite alternate ease-in-out;
        }

        @keyframes lightReflection {
            from { filter: brightness(1); }
            to { filter: brightness(1.2); }
        }

        h1 {
            color: #007bff;
            font-size: 22px;
            margin-top: 10px;
        }

        p {
            font-size: 18px;
            color: #333;
        }

        .btn-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            width: 90%;
        }

        .btn i {
            margin-right: 8px;
        }

        .dashboard-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }

        .dashboard-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: scale(1.05);
        }

        .logout-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #c82333, #a71d2a);
            transform: scale(1.05);
        }

        .admin-btn {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: black;
        }

        .admin-btn:hover {
            background: linear-gradient(135deg, #e0a800, #c69500);
            transform: scale(1.05);
        }

        /* 🔔 Notification Styles */
        #notification-container {
            position: absolute;
            top: 15px;
            right: 15px;
        }

        #notification-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            position: relative;
        }

        #notification-count {
            background: red;
            color: white;
            border-radius: 50%;
            padding: 3px 8px;
            font-size: 14px;
            position: absolute;
            top: -5px;
            right: -5px;
            display: none;
        }

        #notification-dropdown {
            position: absolute;
            top: 35px;
            right: 0;
            width: 250px;
            background: white;
            border-radius: 5px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.2);
            display: none;
            z-index: 1000;
        }

        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-footer {
            text-align: center;
            padding: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- 🔔 Notification Bell Icon -->
<div id="notificationWrapper" style="position: fixed; top: 20px; right: 30px; z-index: 1000;">
    <div id="notificationIcon" style="cursor: pointer; position: relative;">
        🔔
        <span id="notificationCount" style="
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: red;
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 50%;
            display: none;
        ">0</span>
    </div>
    <div id="notificationDropdown" style="
        display: none;
        position: absolute;
        top: 25px;
        right: 0;
        background: white;
        border: 1px solid #ccc;
        border-radius: 5px;
        width: 300px;
        max-height: 300px;
        overflow-y: auto;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        padding: 10px;
        font-size: 14px;
        color: #333;
    "></div>
</div>

    <div class="logo-container">
        <img src="resized_logo.jpg" alt="Company Logo">
    </div>
    <h1>Maxipro Development Corporation</h1>
    <p>Welcome, <b><?php echo htmlspecialchars($_SESSION['username']); ?></b>!</p>

    <div class="btn-container">
        <a href="dashboard.php" class="btn dashboard-btn"><i class="fas fa-chart-line"></i> Go to Dashboard</a>
        <?php if ($is_main_admin): ?>
            <a href="admin_panel.php" class="btn admin-btn"><i class="fas fa-user-shield"></i> User Management</a>
        <?php endif; ?>
        <a href="logout.php" class="btn logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const bellIcon = document.getElementById('notificationIcon');
    const dropdown = document.getElementById('notificationDropdown');
    const countSpan = document.getElementById('notificationCount');

    function fetchNotifications() {
        fetch('fetch_notifications.php')
            .then(response => response.json())
            .then(data => {
                dropdown.innerHTML = '';
                let count = 0;

                if (data.overdue && data.overdue.length > 0) {
                    count += data.overdue.length;
                    data.overdue.forEach(item => {
                        dropdown.innerHTML += `<div style="padding: 5px 0;"><strong>Overdue:</strong> ${item.target_name} (${item.equipment_type})</div>`;
                    });
                }

                if (data.maintenance && data.maintenance.length > 0) {
                    count += data.maintenance.length;
                    data.maintenance.forEach(item => {
                        dropdown.innerHTML += `<div style="padding: 5px 0;"><strong>Maintenance:</strong> ${item.target_name} (${item.license_plate_no})</div>`;
                    });
                }

                countSpan.style.display = count > 0 ? 'inline' : 'none';
                countSpan.textContent = count;
            });
    }

    bellIcon.addEventListener('click', () => {
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    });

    setInterval(fetchNotifications, 5000); // every 10 seconds
    fetchNotifications(); // initial fetch
});
</script>

</body>
</html>
