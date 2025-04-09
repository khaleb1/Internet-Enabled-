<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

$doctorId = (int)$_GET['doctor_id'] ?? 0;
$availableSlots = [];

if ($doctorId) {
    $availabilityQuery = $conn->query("
        SELECT available_date, start_time, end_time 
        FROM doctor_availability 
        WHERE doctor_id = $doctorId 
        AND available_date >= CURDATE()
    ");
    
    while ($slot = $availabilityQuery->fetch_assoc()) {
        $availableSlots[$slot['available_date']][] = [
            'start' => $slot['start_time'],
            'end' => $slot['end_time']
        ];
    }
}

echo json_encode($availableSlots);
?>