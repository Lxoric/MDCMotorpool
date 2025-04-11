<?php
session_start();
require 'db_connect.php';

// Fetch all vehicles
$query = "SELECT id, date_transferred, date_ended FROM devices";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $date_transferred = $row['date_transferred'];
        $date_ended = $row['date_ended'];

        // Check if date_transferred and date_ended are valid
        if ($date_transferred !== NULL && $date_transferred !== '0000-00-00' && 
            $date_ended !== NULL && $date_ended !== '0000-00-00') {
            
            // Calculate days_elapsed
            $days_elapsed = (new DateTime())->diff(new DateTime($date_transferred))->days;

            // Calculate days_lapses
            $days_lapses = (new DateTime())->diff(new DateTime($date_ended))->days;
            if (new DateTime() < new DateTime($date_ended)) {
                $days_lapses = 0; // Set to 0 if not yet lapsed
            }

            // Update the database
            $updateQuery = "UPDATE devices SET days_elapsed = ?, days_lapses = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("iii", $days_elapsed, $days_lapses, $id);
            $updateStmt->execute();
        }
    }
    echo "Update completed successfully.";
} else {
    echo "Error fetching vehicles: " . $conn->error;
}

$conn->close();
?>