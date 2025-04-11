<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$limit = 10;
$offset = ($page - 1) * $limit;

// Get the last check timestamp from client
$lastCheck = isset($_GET['lastCheck']) ? intval($_GET['lastCheck']) : 0;

// Initialize arrays for vehicles
$contractVehicles = [];
$maintenanceVehicles = [];

// Build base query for contract vehicles (excluding maintenance vehicles)
$contractQuery = "SELECT *, 
                  DATEDIFF(CURDATE(), date_transferred) AS days_elapsed, 
                  CASE 
                      WHEN CURDATE() < date_ended THEN 0 
                      ELSE DATEDIFF(CURDATE(), date_ended) 
                  END AS days_lapses,
                  DATEDIFF(date_ended, date_transferred) AS days_contract,
                  UNIX_TIMESTAMP(last_updated) AS last_updated_timestamp
                  FROM devices 
                  WHERE physical_status != 'Breakdown'";

// Build base query for maintenance vehicles (both breakdown and operational)
$maintenanceQuery = "SELECT *, 
                     UNIX_TIMESTAMP(last_updated) AS last_updated_timestamp
                     FROM devices 
                     WHERE physical_status IN ('Breakdown', 'Operational')";

// Add filter if provided
if (!empty($filter)) {
    $contractQuery .= " AND equipment_type = ?";
    $maintenanceQuery .= " AND equipment_type = ?";
}

// Add pagination and sorting
$contractQuery .= " ORDER BY last_updated DESC LIMIT ? OFFSET ?";
$maintenanceQuery .= " ORDER BY last_updated DESC LIMIT ? OFFSET ?";

// Prepare and execute contract vehicles query
$contractStmt = $conn->prepare($contractQuery);
if (!$contractStmt) {
    die("Database query failed: " . $conn->error);
}

if (!empty($filter)) {
    $contractStmt->bind_param("sii", $filter, $limit, $offset);
} else {
    $contractStmt->bind_param("ii", $limit, $offset);
}
$contractStmt->execute();
$contractResult = $contractStmt->get_result();

while ($row = $contractResult->fetch_assoc()) {
    $row['is_updated'] = ($row['last_updated_timestamp'] > (time() - 10));
    $row['is_overdue'] = ($row['days_lapses'] > 0);
    $contractVehicles[] = $row;
}

// Prepare and execute maintenance vehicles query
$maintenanceStmt = $conn->prepare($maintenanceQuery);
if (!$maintenanceStmt) {
    die("Database query failed: " . $conn->error);
}

if (!empty($filter)) {
    $maintenanceStmt->bind_param("sii", $filter, $limit, $offset);
} else {
    $maintenanceStmt->bind_param("ii", $limit, $offset);
}
$maintenanceStmt->execute();
$maintenanceResult = $maintenanceStmt->get_result();

while ($row = $maintenanceResult->fetch_assoc()) {
    $row['is_updated'] = ($row['last_updated_timestamp'] > (time() - 10));
    $row['is_breakdown'] = ($row['physical_status'] == 'Breakdown');
    $maintenanceVehicles[] = $row;
}

// Return JSON response
echo json_encode([
    'contractVehicles' => $contractVehicles,
    'maintenanceVehicles' => $maintenanceVehicles,
    'page' => $page,
    'totalContractVehicles' => count($contractVehicles),
    'totalMaintenanceVehicles' => count($maintenanceVehicles),
    'serverTime' => time()
]);

$contractStmt->close();
$maintenanceStmt->close();
$conn->close();
?>