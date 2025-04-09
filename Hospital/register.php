<?php
require_once './includes/auth.php';
require_once './includes/header.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = registerUser(
        $_POST['username'],
        $_POST['password'],
        $_POST['full_name'],
        $_POST['email'],
        $_POST['phone'],
        'patient'
    );
    
    if ($response['success']) {
        header("Location: login.php?registered=success");
        exit();
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card" style="background-color: rgba(255, 255, 255, 0.8);">
            <div class="card-header" style="background-color: rgba(13, 110, 253, 0.0);">
                <h4 class="mb-0 text-primary">Patient Registration</h4>
            </div>
            <div class="card-body">
                <?php if (isset($response['error'])): ?>
                <div class="alert alert-danger"><?= $response['error'] ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once './includes/footer.php'; ?>