<?php
session_start();
include '../config/db_connection.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
        header("Location: ../login/login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email, role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user;
}

$currentUser = getCurrentUser($conn);
$accountName = htmlspecialchars($currentUser['username'] ?? 'User');
$accountEmail = htmlspecialchars($currentUser['email'] ?? '');
$accountRole = htmlspecialchars($currentUser['role'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }

    // Sanitize inputs
    $updatedUsername = trim(htmlspecialchars($_POST['username']));
    $updatedEmail = trim(htmlspecialchars($_POST['email']));
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    $errors = [];
    
    if (empty($updatedUsername)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($updatedUsername) > 50) {
        $errors[] = 'Username must be less than 50 characters.';
    }
    
    if (empty($updatedEmail)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($updatedEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    } elseif (strlen($updatedEmail) > 100) {
        $errors[] = 'Email must be less than 100 characters.';
    } else {
        // Check if email is already in use
        $emailCheck = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $emailCheck->bind_param("si", $updatedEmail, $_SESSION['user_id']);
        $emailCheck->execute();
        if ($emailCheck->get_result()->num_rows > 0) {
            $errors[] = 'This email is already in use by another account.';
        }
        $emailCheck->close();
    }
    
    // Password change validation (only if new password provided)
    if (!empty($newPassword)) {
        // 1. Check if current password was provided
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required to change your password.';
        }
        // 2. Check new password length
        elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        }
        // 3. Check password complexity
        elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        elseif (!preg_match('/[a-z]/', $newPassword)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        elseif (!preg_match('/[0-9]/', $newPassword)) {
            $errors[] = 'Password must contain at least one number.';
        }
        // 4. Check password match
        elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        }
        // 5. Verify current password is correct
        else {
            try {
                // Get current password hash from database
                $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $errors[] = 'User not found.';
                } else {
                    $user = $result->fetch_assoc();
                    if (!password_verify($currentPassword, $user['password'])) {
                        $errors[] = 'Current password is incorrect.';
                    }
                }
                $stmt->close();
            } catch (Exception $e) {
                $errors[] = 'Error verifying current password.';
                error_log("Password verification error: " . $e->getMessage());
            }
        }
    }
    
    // If there are errors, store them and redirect back
    if (!empty($errors)) {
        $_SESSION['update_errors'] = $errors;
        header("Location: userprofile.php");
        exit();
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // 1. Update username and email
        $updateStmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
        if (!$updateStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $updateStmt->bind_param("ssi", $updatedUsername, $updatedEmail, $_SESSION['user_id']);
        if (!$updateStmt->execute()) {
            throw new Exception("Execute failed: " . $updateStmt->error);
        }
        $updateStmt->close();
        
        // 2. Update password if provided and validated
        if (!empty($newPassword)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $passStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            if (!$passStmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $passStmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
            if (!$passStmt->execute()) {
                throw new Exception("Execute failed: " . $passStmt->error);
            }
            $passStmt->close();
            
            // Regenerate session ID after password change
            session_regenerate_id(true);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['update_success'] = !empty($newPassword) 
            ? 'Profile and password updated successfully!' 
            : 'Profile updated successfully!';
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Log the error
        error_log("Profile update error: " . $e->getMessage());
        
        $_SESSION['update_errors'] = ['An error occurred while updating your profile.'];
    }
    
    // Redirect back to profile page
    header("Location: userprofile.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Profile</title>
    <link rel="stylesheet" href="../css/userprof.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<header class="header">
        <div class="header-content">
            <div class="left-side">
                <img src="../assets/img/Logo.png" alt="UCGS Inventory Logo" class="logo">
                <span class="website-name">UCGS Inventory</span>
            </div>
            <div class="right-side">
                <div class="user">
                    <img src="../assets/img/users.png" alt="User profile" class="icon" id="userIcon">
                    <span class="user-text"><?php echo htmlspecialchars($accountName); ?></span>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="userprofile.php"><img src="../assets/img/updateuser.png" alt="Profile" class="dropdown-icon"> Profile</a>
                        <a href="usernotification.php"><img src="../assets/img/notificationbell.png" alt="Notification Icon" class="dropdown-icon"> Notification</a>
                        <a href="../login/logout.php"><img src="../assets/img/logout.png" alt="Logout" class="dropdown-icon"> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar">
        <ul>
            <li><a href="Userdashboard.php"><img src="../assets/img/dashboards.png" alt="Dashboard Icon" class="sidebar-icon"> Dashboard</a></li>
            <li><a href="UserItemRecords.php"><img src="../assets/img/list-items.png" alt="Items Icon" class="sidebar-icon"> Item Records</a></li>
            <li class="dropdown">
                <a href="#" class="dropdown-btn">
                    <img src="../assets/img/request-for-proposal.png" alt="Request Icon" class="sidebar-icon">
                    <span class="text">Request Record</span>
                    <i class="fa-solid fa-chevron-down arrow-icon"></i>
                </a>
                <ul class="dropdown-content">
                    <li><a href="UserItemRequests.php">New Item Request</a></li>
                    <li><a href="UserItemBorrow.php">Borrow Item Request</a></li>
                    <li><a href="UserItemReturned.php">Return Item Request</a></li>
                </ul>
            </li>
            <li><a href="UserTransaction.php"><img src="../assets/img/time-management.png" alt="Reports Icon" class="sidebar-icon">Transaction Records</a></li>
        </ul>
    </aside>

    <div class="main-content">
        <h2 class="profile-title">User Profile</h2>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['update_success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['update_success']); ?></div>
            <?php unset($_SESSION['update_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['update_errors'])): ?>
            <div class="alert alert-danger">
                <?php foreach ($_SESSION['update_errors'] as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
            <?php unset($_SESSION['update_errors']); ?>
        <?php endif; ?>
        
        <form class="profile-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Username / Account Name</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($accountName); ?>" required maxlength="50">
                </div>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($accountEmail); ?>" required maxlength="100">
            </div>

            <div class="form-group">
                <h3>Change Password</h3>
                <label>Enter Current Password</label>
                <div class="password-wrapper">
                    <input type="password" name="current_password" id="current_password">
                    <button type="button" class="toggle-password" aria-label="Show password">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
                <label>New Password (leave blank to keep current)</label>
                <div class="password-wrapper">
                    <input type="password" name="new_password" id="new_password" 
                           pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" 
                           title="Must contain at least one number, one uppercase and lowercase letter, and at least 8 or more characters">
                    <button type="button" class="toggle-password" aria-label="Show password">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
                <div id="password-strength" style="height: 5px; margin-top: -15px; margin-bottom: 15px; background: #eee;"></div>
                <div id="password-strength-text" style="font-size: 0.8em; color: #666; margin-top: -10px; margin-bottom: 15px;"></div>
                
                <label>Confirm New Password</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password">
                    <button type="button" class="toggle-password" aria-label="Show password">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn save-btn">Save Changes</button>
        </form>
    </div>

    <script src="../js/userprof.js"></script>
    <script>
    // Password toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                const isPassword = input.type === 'password';
                
                input.type = isPassword ? 'text' : 'password';
                icon.classList.toggle('fa-eye', isPassword);
                icon.classList.toggle('fa-eye-slash', !isPassword);
                this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        });

        // Password strength meter
        document.getElementById('new_password')?.addEventListener('input', function() {
            const strengthBar = document.getElementById('password-strength');
            const strengthText = document.getElementById('password-strength-text');
            const strength = checkPasswordStrength(this.value);
            
            strengthBar.style.width = strength.percent + '%';
            strengthBar.style.background = strength.color;
            strengthText.textContent = strength.text;
        });

        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            
            const percent = strength * 20;
            const colors = ['#ff0000', '#ff5a00', '#ff9a00', '#ffcc00', '#00ff00'];
            const texts = ['Very Weak', 'Weak', 'Medium', 'Strong', 'Very Strong'];
            
            return {
                percent: percent,
                color: colors[strength - 1] || '#ccc',
                text: texts[strength - 1] || ''
            };
        }

        // Form submission loading state
        document.querySelector('.profile-form')?.addEventListener('submit', function() {
            const btn = this.querySelector('.btn');
            btn.classList.add('loading');
            btn.innerHTML = '<span style="visibility: hidden;">' + btn.textContent + '</span>';
        });

        // Sidebar dropdown functionality
        document.querySelectorAll('.dropdown-btn').forEach(button => {
            button.addEventListener('click', () => {
                const dropdownContent = button.nextElementSibling;
                dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
            });
        });

        // Profile dropdown functionality
        const userIcon = document.getElementById('userIcon');
        const userDropdown = document.getElementById('userDropdown');
        userIcon.addEventListener('click', () => {
            userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
        });

        // Close dropdown if clicked outside
        document.addEventListener('click', (event) => {
            if (!userIcon.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>