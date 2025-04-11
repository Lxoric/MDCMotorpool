<?php
require 'db_connect.php';

// Ensure notification uniqueness logic based on vehicle ID
function insertNotification($conn, $vehicleId, $message) {
    $stmt = $conn->prepare("SELECT 1 FROM notifications WHERE vehicle_id = ?");
    $stmt->bind_param("i", $vehicleId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $insert = $conn->prepare("INSERT INTO notifications (vehicle_id, message) VALUES (?, ?)");
        $insert->bind_param("is", $vehicleId, $message);
        $insert->execute();
    }

    $stmt->close();
}

// Fetch vehicles with overdue contracts
$overdueQuery = "SELECT id, target_name, equipment_type, days_lapses 
                 FROM devices 
                 WHERE days_lapses >= 1"; // Notify only if days_lapses >= 1
$overdueResult = $conn->query($overdueQuery);

$overdueVehicles = [];
while ($row = $overdueResult->fetch_assoc()) {
    $overdueVehicles[$row['id']] = $row;
}

// Fetch vehicles with maintenance issues
$maintenanceQuery = "SELECT id, target_name, equipment_type 
                     FROM devices 
                     WHERE physical_status = 'Breakdown'";
$maintenanceResult = $conn->query($maintenanceQuery);

$maintenanceVehicles = [];
while ($row = $maintenanceResult->fetch_assoc()) {
    $maintenanceVehicles[$row['id']] = $row;
}

// Combine notifications for vehicles with both overdue and maintenance issues
$notifications = [];
foreach ($overdueVehicles as $id => $vehicle) {
    if (isset($maintenanceVehicles[$id])) {
        // Vehicle has both overdue and maintenance issues
        $msg = "🚨🛠 Vehicle <b>{$vehicle['target_name']}</b> ({$vehicle['equipment_type']}) has an overdue contract and requires maintenance.";
        insertNotification($conn, $id, $msg);
        unset($maintenanceVehicles[$id]); // Remove from maintenance list to avoid duplication
    } else {
        // Vehicle has only overdue issues
        $msg = "🚨 Vehicle <b>{$vehicle['target_name']}</b> ({$vehicle['equipment_type']}) has an overdue contract.";
        insertNotification($conn, $id, $msg);
    }
}

// Add remaining vehicles with only maintenance issues
foreach ($maintenanceVehicles as $id => $vehicle) {
    $msg = "🛠 Vehicle <b>{$vehicle['target_name']}</b> ({$vehicle['equipment_type']}) requires maintenance.";
    insertNotification($conn, $id, $msg);
}

// Fetch grouped notifications with additional fields
$notifQuery = "
    SELECT n.message, d.target_name, d.equipment_type 
    FROM notifications n 
    JOIN devices d ON n.vehicle_id = d.id 
    WHERE n.is_read = 0 
    ORDER BY n.created_at DESC 
    LIMIT 20
";

$result = $conn->query($notifQuery);
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'message' => $row['message'],
        'target_name' => $row['target_name'],
        'equipment_type' => $row['equipment_type']
    ];
}

header('Content-Type: application/json');
echo json_encode($notifications);

$conn->close();
?>