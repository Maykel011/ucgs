<?php
session_start();
include '../config/db_connection.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Database connection
    $conn = new mysqli('localhost', 'root', '', 'ucgs');
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    // Query the users table
    $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? AND status = "Active"');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Successful login
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role']; // Assuming 'role' column exists in the database

            // Redirect based on role
            if ($user['role'] === 'Administrator') {
                header('Location: ../admin/AdminDashboard.php');
            } elseif ($user['role'] === 'User') {
                header('Location: ../user/UserDashboard.php'); // Ensure this path is correct for user side
            } else {
                header('Location: ../guest/GuestDashboard.php'); // Optional: Handle other roles if needed
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Invalid email or password.';
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Church Community Login</title>
    <link rel="stylesheet" href="../css/signin.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="hero-section">
            <img src="../assets/img/BG.jpg" alt="Church Community Illustration" class="hero-image">
            <h2>Welcome to Our Church Community</h2>
            <p>Connect, share, and grow together in faith</p>
        </div>

        <div class="login-container">
            <img src="../assets/img/Logo.png" alt="Church Logo" class="logo">
            <h1 class="form-title">UCGS Member Login</h1>

            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?= htmlspecialchars($email) ?>" 
                           required
                           autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" 
                           required
                           autocomplete="current-password">
                    <span class="password-toggle" onclick="togglePassword()">👁️</span>
                </div>

                <div class="form-group">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        Remember my email
                    </label>
                </div>

                <button type="submit" class="btn">Sign In</button>
            </form>
        </div>
    </div>
<script src="../js/signin.js"></script>
</body>
</html>