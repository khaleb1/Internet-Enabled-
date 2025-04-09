<?php
// Only start session if one doesn't exist already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the database connection
require_once 'db.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user has specific role
function checkRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

// Register a new user
function registerUser($username, $password, $full_name, $email, $phone, $role = 'patient') {
    global $conn;
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'error' => 'Username or email already exists'];
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $username, $hashed_password, $full_name, $email, $phone, $role);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        
        // If patient, create patient record
        if ($role === 'patient') {
            $stmt = $conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
        
        return ['success' => true, 'user_id' => $user_id];
    } else {
        return ['success' => false, 'error' => 'Registration failed'];
    }
}

// Login user
function loginUser($username, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT user_id, password, full_name, role FROM users WHERE username = ? AND status = 'active'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            return ['success' => true];
        }
    }
    
    return ['success' => false, 'error' => 'Invalid username or password'];
}

// Logout user
function logoutUser() {
    session_unset();
    session_destroy();
}
?>