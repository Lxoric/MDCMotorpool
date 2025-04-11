<?php
require 'db_connect.php';

// Ensure notification uniqueness logic based on vehicle ID and type
function insertNotification($conn, $vehicleId, $type, $message) {
    $stmt = $conn->prepare("SELECT 1 FROM notifications WHERE vehicle_id = ? AND type = ?");
    $stmt->bind_param("is", $vehicleId, $type);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $insert = $conn->prepare("INSERT INTO notifications (vehicle_id, type, message) VALUES (?, ?, ?)");
        $insert->bind_param("iss", $vehicleId, $type, $message);
        $insert->execute();
    }

    $stmt->close();
}

// Check and insert overdue notifications
$overdueQuery = "SELECT id, target_name, equipment_type, days_lapses 
                 FROM devices 
                 WHERE days_lapses >= 1"; // Notify only if days_lapses >= 1
$overdueResult = $conn->query($overdueQuery);

while ($row = $overdueResult->fetch_assoc()) {
    $msg = "🚨 Vehicle <b>{$row['target_name']}</b> ({$row['equipment_type']}) is overdue!";
    insertNotification($conn, $row['id'], 'overdue', $msg);
}

// Check and insert maintenance notifications
$maintenanceQuery = "SELECT id, target_name, equipment_type 
                     FROM devices 
                     WHERE physical_status = 'Breakdown'";
$maintenanceResult = $conn->query($maintenanceQuery);

while ($row = $maintenanceResult->fetch_assoc()) {
    $msg = "🛠 Vehicle <b>{$row['target_name']}</b> ({$row['equipment_type']}) needs maintenance.";
    insertNotification($conn, $row['id'], 'maintenance', $msg);
}

// Fetch grouped notifications with additional fields
$notifications = [
    'overdue' => [],
    'maintenance' => []
];

$notifQuery = "
    SELECT n.type, n.message, d.target_name, d.equipment_type 
    FROM notifications n 
    JOIN devices d ON n.vehicle_id = d.id 
    WHERE n.is_read = 0 
    ORDER BY n.created_at DESC 
    LIMIT 20
";

$result = $conn->query($notifQuery);
while ($row = $result->fetch_assoc()) {
    if ($row['type'] === 'overdue') {
        $notifications['overdue'][] = [
            'type' => $row['type'],
            'message' => $row['message'],
            'target_name' => $row['target_name'],
            'equipment_type' => $row['equipment_type']
        ];
    } elseif ($row['type'] === 'maintenance') {
        $notifications['maintenance'][] = [
            'type' => $row['type'],
            'message' => $row['message'],
            'target_name' => $row['target_name'],
            'equipment_type' => $row['equipment_type']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($notifications);
?>