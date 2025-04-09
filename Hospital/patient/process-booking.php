<?php
require_once '../includes/auth.php';
require_once '../includes/email_template.php';

if (!checkRole('patient')) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_SESSION['user_id'];
    $doctor_id = (int)$_POST['doctor_id'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $reason = htmlspecialchars($_POST['reason']);

    // Check availability
    $stmt = $conn->prepare("SELECT * FROM availability 
                          WHERE doctor_id = ? 
                          AND DAYOFWEEK(?) = day_of_week
                          AND ? BETWEEN start_time AND end_time");
    $stmt->bind_param("iss", $doctor_id, $date, $time);
    $stmt->execute();
    $available = $stmt->get_result()->num_rows > 0;

    if (!$available) {
        header("Location: book-appointment.php?error=slot_taken");
        exit();
    }

    // Insert appointment
    $stmt = $conn->prepare("INSERT INTO appointments 
                          (patient_id, doctor_id, appointment_date, appointment_time, reason) 
                          VALUES ((SELECT patient_id FROM patients WHERE user_id = ?), ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $patient_id, $doctor_id, $date, $time, $reason);

    if ($stmt->execute()) {
        // Get doctor details
        $doctor = $conn->query("
            SELECT u.full_name, u.email 
            FROM users u
            JOIN doctors d ON u.user_id = d.user_id
            WHERE d.doctor_id = $doctor_id
        ")->fetch_assoc();

        // Send notification
        $details = [
            'doctor' => $doctor['full_name'],
            'date' => date('F j, Y', strtotime($date)),
            'time' => date('g:i a', strtotime($time))
        ];
        sendAppointmentNotification($_SESSION['email'], 'confirmation', $details);

        header("Location: dashboard.php?success=booking_created");
        exit();
    } else {
        header("Location: book-appointment.php?error=database_error");
        exit();
    }
}