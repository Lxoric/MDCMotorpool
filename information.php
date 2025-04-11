<?php
session_start();
require 'db_connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$whereClause = "";
$searchParams = [];

if (!empty($_GET)) { // Get values from search form
    foreach ($_GET as $column => $value) {
        if (!empty($value)) {
            $whereClause .= ($whereClause ? " AND " : " WHERE ") . "`$column` LIKE ?";
            $searchParams[] = "%" . $value . "%";
        }
    }
}

// Prepare SQL statement
$sql = "SELECT * FROM devices" . $whereClause;
$stmt = $conn->prepare($sql);

// Check if prepare() succeeded
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

if ($searchParams) {
    $types = str_repeat("s", count($searchParams));
    $stmt->bind_param($types, ...$searchParams);
}

$stmt->execute();
$result = $stmt->get_result();

// Fetch overdue vehicles for warning popup
$overdueVehicles = [];
$overdueQuery = "SELECT target_name, license_plate_no, days_elapsed, days_contract FROM devices WHERE days_elapsed > days_contract";
$overdueResult = $conn->query($overdueQuery);

if ($overdueResult) {
    while ($row = $overdueResult->fetch_assoc()) {
        $overdueVehicles[] = $row;
    }
} else {
    die("Overdue query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Information</title>
	<style>
	body { 
	font-family: Arial, sans-serif; 
	background-color: #f4f4f4; margin: 0; 
	padding: 20px; 
	display: flex; 
	flex-direction: column; 
	align-items: center; 
	}
	
	h2 {
        color: #007bff;
        margin-bottom: 15px;
    }

    .table-container {
        width: 100%;
        max-width: 95vw;
        max-height: 700px;
        overflow-x: auto;
        overflow-y: auto;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        padding: 10px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }

    th, td {
        padding: 12px;
        text-align: center;
        border: 1px solid #ddd;
        word-wrap: break-word;
        white-space: normal;
    }

    th {
        background-color: #007bff;
        color: white;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    tbody tr:nth-child(even) {
        background-color: #f8f9fa;
    }

    tbody tr:hover {
        background-color: #e2e6ea;
        cursor: pointer;
    }

    .btn-container {
        margin-bottom: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 12px 20px;
        font-size: 16px;
        font-weight: bold;
        border-radius: 8px;
        text-decoration: none;
        text-align: center;
        transition: all 0.3s ease-in-out;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
    }

    .find-button {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
        border: none;
    }

    .find-button:hover {
        background: linear-gradient(135deg, #0056b3, #003d80);
        transform: scale(1.05);
    }

    .back-button {
        background: linear-gradient(135deg, #28a745, #1e7e34);
        color: white;
        border: none;
    }

    .back-button:hover {
        background: linear-gradient(135deg, #1e7e34, #155d27);
        transform: scale(1.05);
    }

    .edit-btn {
        background-color: #28a745;
        color: white;
        padding: 6px 12px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
        transition: 0.3s;
    }

    .edit-btn:hover {
        background-color: #218838;
    }

    /* Highlight expired contracts */
    .highlight-red {
        background-color: #ffcccc !important; /* Light red */
        color: #d80000; /* Dark red text */
        font-weight: bold;
    }
</style>
</head>
<body>

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

<h2>Device Information</h2>

<div class="btn-container">
    <button class="btn find-button" onclick="openFindModal()">🔍 Find</button>
    <a href="dashboard.php" class="btn back-button">🏠 Home</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <?php
                $query = "SHOW COLUMNS FROM devices";
                $columnsResult = $conn->query($query);
                if ($columnsResult) {
                    while ($column = $columnsResult->fetch_assoc()) {
                        echo "<th>" . strtoupper(str_replace('_', ' ', $column['Field'])) . "</th>";
                    }
                } else {
                    die("Columns query failed: " . $conn->error);
                }
                ?>
                <th>ACTIONS</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): 
                $daysElapsed = (int)$row['days_elapsed'];
                $daysContract = (int)$row['days_contract'];
                //$highlightClass = ($daysElapsed < $daysContract) ? 'highlight-red' : ''; // Apply red highlight if overdue
            ?>
                <tr class="<?php echo $highlightClass; ?>">
                    <?php foreach ($row as $value): ?>
                        <td><?php echo htmlspecialchars($value); ?></td>
                    <?php endforeach; ?>
                    <td>
                        <?php if ($_SESSION['role'] !== 'User '): ?>
                            <a class="edit-btn" href="edit.php?id=<?php echo $row['id']; ?>">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
function openFindModal() {
    window.open("find.php", "FindWindow", "width=800,height=600,resizable=yes,scrollbars=yes");
}

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
                        dropdown.innerHTML += `<div style="padding: 5px 0;"><strong>Maintenance:</strong> ${item.target_name} (${item.equipment_type})</div>`;
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

<?php 
$stmt->close(); 
$conn->close(); 
?>