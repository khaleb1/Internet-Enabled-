<?php
function sendAppointmentNotification($email, $type, $details) {
    $subject = '';
    $message = '';
    
    switch ($type) {
        case 'confirmation':
            $subject = "Appointment Confirmation - Hospital System";
            $message = "
                <h3>Appointment Confirmed</h3>
                <p>Your appointment has been scheduled:</p>
                <ul>
                    <li>Doctor: {$details['doctor']}</li>
                    <li>Date: {$details['date']}</li>
                    <li>Time: {$details['time']}</li>
                </ul>
            ";
            break;
            
        case 'reminder':
            $subject = "Appointment Reminder - Hospital System";
            $message = "
                <h3>Appointment Reminder</h3>
                <p>Your appointment is scheduled for tomorrow:</p>
                <!-- Reminder content -->
            ";
            break;
    }
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: appointments@hospital.com" . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}
?>