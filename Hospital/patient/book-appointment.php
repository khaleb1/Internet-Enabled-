<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/auth.php';
require_once '../includes/header.php';

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!checkRole('patient')) {
    header("Location: ../index.php");
    exit();
}

// Get patient ID from session
$userId = $_SESSION['user_id'];
$patientQuery = $conn->query("SELECT patient_id FROM patients WHERE user_id = $userId");
$patientData = $patientQuery->fetch_assoc();
$patientId = $patientData['patient_id'];

// Get doctor ID from GET parameter
$doctorId = $_GET['doctor_id'] ?? null;
$doctorId = $_POST['doctor_id'] ?? null;

// Should be changed to:
$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : null;
$doctorId = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : $doctorId;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctorId = $_POST['doctor_id'] ?? null;
    $appointmentDate = $_POST['appointment_date'] ?? null;
    $appointmentTime = $_POST['appointment_time'] ?? null;
    $reason = $_POST['reason'] ?? null;
    
    // Validate inputs
    if (empty($doctorId) || empty($appointmentDate) || empty($appointmentTime) || empty($reason)) {
        $error = "All fields are required.";
    } elseif (strlen($reason) > 500) {
        $error = "Reason must be less than 500 characters";
    } elseif (!preg_match("/^[a-zA-Z0-9 ,.'-]+$/", $reason)) {
        $error = "Invalid characters in reason field";
    }
    
    if (!isset($error)) {
        // Free any previous results
        while ($conn->more_results()) {
            $conn->next_result();
            if ($res = $conn->store_result()) {
                $res->free();
            }
        }
        
        // Check appointment availability
        $checkQuery = $conn->prepare("SELECT * FROM appointments 
                                    WHERE doctor_id = ? 
                                    AND appointment_date = ? 
                                    AND appointment_time = ?
                                    AND status != 'cancelled'");
        $checkQuery->bind_param("iss", $doctorId, $appointmentDate, $appointmentTime);
        $checkQuery->execute();
        $checkResult = $checkQuery->get_result();
        
        if ($checkResult->num_rows > 0) {
            $error = "This appointment slot is already booked. Please select another time.";
        } else {
            // Insert the appointment
            $insertQuery = $conn->prepare("INSERT INTO appointments 
                                         (patient_id, doctor_id, appointment_date, appointment_time, reason, status) 
                                         VALUES (?, ?, ?, ?, ?, 'scheduled')");
            $insertQuery->bind_param("iisss", $patientId, $doctorId, $appointmentDate, $appointmentTime, $reason);
            
            if ($insertQuery->execute()) {
                $_SESSION['success_message'] = "Appointment booked successfully!";
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Error booking appointment: " . $conn->error;
            }
        }
        
        $checkQuery->close();
        if (isset($insertQuery)) {
            $insertQuery->close();
        }
    }
}

// Get available doctors
$availableDoctors = $conn->query("
    SELECT d.doctor_id, u.full_name, d.specialization
    FROM doctors d
    JOIN users u ON d.user_id = u.user_id
    WHERE u.status = 'active'
");

// Get available dates for selected doctor
if (isset($_GET['doctor_id'])) {
    $doctorId = (int)$_GET['doctor_id'];
    $availableDates = $conn->query("
        SELECT DISTINCT available_date 
        FROM doctor_availability 
        WHERE doctor_id = $doctorId 
        AND available_date >= CURDATE()
        ORDER BY available_date
    ");
}

// Get selected doctor details
if ($doctorId) {
    $doctorQuery = $conn->query("
        SELECT d.doctor_id, u.full_name, d.specialization
        FROM doctors d
        JOIN users u ON d.user_id = u.user_id
        WHERE d.doctor_id = $doctorId
    ");
    $selectedDoctor = $doctorQuery->fetch_assoc();
}

// Get doctor availability
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
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card" style="background-color: rgba(255, 255, 255, 0.8);">
                <div class="card-header" style="background-color: rgba(13, 110, 253, 0.0);">
                    <h5 class="mb-0 text-primary">Book an Appointment</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="book-appointment.php">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Select Doctor</label>
                                <select class="form-select" id="doctor_id" name="doctor_id" required>
                                    <option value="">-- Select Doctor --</option>
                                    <?php while ($doctor = $availableDoctors->fetch_assoc()): ?>
                                        <option value="<?= $doctor['doctor_id'] ?>" <?= isset($_POST['doctor_id']) && $_POST['doctor_id'] == $doctor['doctor_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($doctor['full_name']) ?> (<?= htmlspecialchars($doctor['specialization']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Appointment Date</label>
                                <select class="form-select" id="appointment_date" name="appointment_date" required>
                                    <option value="">-- Select Date --</option>
                                    <?php foreach(array_keys($availableSlots) as $date): ?>
                                        <option value="<?= $date ?>"><?= date('l, F j, Y', strtotime($date)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Appointment Time</label>
                                <select class="form-select" id="appointment_time" name="appointment_time" required>
                                    <option value="">-- Select Time --</option>
                                    <!-- Times will be populated via JavaScript -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Visit</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="submit" class="btn btn-primary">Confirm Booking</button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateSelect = document.getElementById('appointment_date');
    const timeSelect = document.getElementById('appointment_time');
    const doctorSelect = document.getElementById('doctor_id');
    
    if (!dateSelect || !timeSelect || !doctorSelect) {
        console.error('Form elements not found');
        return;
    }

    // Function to load available dates and times
    function loadAvailability(doctorId) {
        dateSelect.innerHTML = '<option value="">-- Select Date --</option>';
        timeSelect.innerHTML = '<option value="">-- Select Time --</option>';
        dateSelect.disabled = true;
        timeSelect.disabled = true;
        
        if (!doctorId) return;

        fetch(`get-doctor-availability.php?doctor_id=${doctorId}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                console.log('Available slots:', data);
                
                // Populate dates
                Object.keys(data).forEach(date => {
                    const option = new Option(
                        new Date(date).toLocaleDateString('en-US', { 
                            weekday: 'long', 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric' 
                        }),
                        date
                    );
                    dateSelect.add(option);
                });
                
                dateSelect.disabled = false;
                
                // If there's a pre-selected date, load its times
                if (dateSelect.value) {
                    loadTimeSlots(data, dateSelect.value);
                }
            })
            .catch(error => {
                console.error('Error loading availability:', error);
                dateSelect.disabled = false;
            });
    }

    // Function to load time slots for selected date
    function loadTimeSlots(availabilityData, selectedDate) {
        timeSelect.innerHTML = '<option value="">-- Select Time --</option>';
        timeSelect.disabled = true;
        
        if (!selectedDate || !availabilityData[selectedDate]) {
            console.log('No time slots available for selected date');
            return;
        }

        availabilityData[selectedDate].forEach(slot => {
            const formattedStart = formatTime(slot.start);
            const formattedEnd = formatTime(slot.end);
            const option = new Option(`${formattedStart} - ${formattedEnd}`, slot.start);
            timeSelect.add(option);
        });
        
        timeSelect.disabled = false;
    }

    // Helper function to format time
    function formatTime(timeString) {
        const [hours, minutes] = timeString.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    }

    // Doctor change handler
    doctorSelect.addEventListener('change', function() {
        loadAvailability(this.value);
    });

    // Date change handler
    dateSelect.addEventListener('change', function() {
        fetch(`get-doctor-availability.php?doctor_id=${doctorSelect.value}`)
            .then(response => response.json())
            .then(data => loadTimeSlots(data, this.value))
            .catch(error => console.error('Error:', error));
    });

    // Initialize if doctor is pre-selected
    if (doctorSelect.value) {
        loadAvailability(doctorSelect.value);
    }
});
</script>

