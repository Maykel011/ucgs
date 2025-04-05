<?php
// Include database connection with error handling
include '../config/db_connection.php';

// Improved error handling for database connection
if (!$conn) {
    die("Database connection failed: " . htmlspecialchars(mysqli_connect_error()));
}

session_start();

function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
        header("Location: ../login/login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email, ministry FROM users WHERE user_id = ? AND role = 'User'");
    if (!$stmt) {
        die("Database error: " . htmlspecialchars($conn->error));
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user;
}

$currentUser = getCurrentUser($conn);
$accountName = htmlspecialchars($currentUser['username'] ?? '');
$userMinistry = htmlspecialchars($currentUser['ministry'] ?? '');

// If user details are not found, redirect to login
if (!$currentUser) {
    header("Location: ../login/login.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages
$errorMessage = '';
$successMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        $errorMessage = 'Invalid CSRF token.';
    } else {
        // Sanitize and validate input fields
        $itemName = htmlspecialchars(trim($_POST['item-name'] ?? ''));
        $itemCategory = htmlspecialchars(trim($_POST['item-category'] ?? ''));
        $quantity = filter_var($_POST['quantity'] ?? 0, FILTER_VALIDATE_INT);
        $itemUnit = htmlspecialchars(trim($_POST['item-unit'] ?? ''));
        $purpose = htmlspecialchars(trim($_POST['purpose'] ?? ''));
        $notes = isset($_POST['notes']) ? htmlspecialchars(trim($_POST['notes'])) : null;

        // Enhanced validation
        if (empty($itemName) || strlen($itemName) > 255) {
            $errorMessage = 'Item Name is required and must not exceed 255 characters.';
        } elseif (empty($itemCategory)) {
            $errorMessage = 'Item Category is required.';
        } elseif ($quantity === false || $quantity <= 0) {
            $errorMessage = 'Quantity must be a positive integer.';
        } elseif (empty($itemUnit)) {
            $errorMessage = 'Item Unit is required.';
        } elseif (empty($purpose) || strlen($purpose) > 500) {
            $errorMessage = 'Purpose is required and must not exceed 500 characters.';
        } else {
            // Check if the item already exists in the new_item_requests table
            $checkQuery = "SELECT COUNT(*) AS count FROM new_item_requests WHERE item_name = ? AND user_id = ? AND status = 'Pending'";
            $checkStmt = $conn->prepare($checkQuery);
            if ($checkStmt) {
                $checkStmt->bind_param("si", $itemName, $_SESSION['user_id']);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $row = $checkResult->fetch_assoc();
                $checkStmt->close();

                if ($row['count'] > 0) {
                    $errorMessage = 'You already have a pending request for this item.';
                } else {
                    // Insert the new item request into the new_item_requests table
                    $insertQuery = "INSERT INTO new_item_requests (user_id, item_name, item_category, quantity, item_unit, purpose, notes, status, ministry) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)";
                    $insertStmt = $conn->prepare($insertQuery);

                    if ($insertStmt) {
                        $insertStmt->bind_param("ississss", $_SESSION['user_id'], $itemName, $itemCategory, $quantity, $itemUnit, $purpose, $notes, $userMinistry);
                        if ($insertStmt->execute()) {
                            if ($insertStmt->affected_rows > 0) {
                                // Log the transaction in the transactions table
                                $transactionQuery = "INSERT INTO transactions (user_id, action, details, item_id, quantity, status, item_name) VALUES (?, 'New Item Request', ?, ?, ?, 'Pending', ?)";
                                $transactionStmt = $conn->prepare($transactionQuery);
                                if (!$transactionStmt) {
                                    die('Database error: ' . $conn->error);
                                }
                                $details = "Requested $quantity of item '$itemName' in $itemCategory category.";
                                $itemId = $conn->insert_id; // Assuming the last inserted ID corresponds to the item
                                $transactionStmt->bind_param("isiss", $_SESSION['user_id'], $details, $itemId, $quantity, $itemName);
                                $transactionStmt->execute();
                                $transactionStmt->close();

                                header('Location: UserTransaction.php?success=1');
                                exit();
                            } else {
                                $errorMessage = 'Failed to submit your request. Please try again.';
                            }
                        } else {
                            $errorMessage = 'Database error: ' . htmlspecialchars($insertStmt->error);
                        }
                        $insertStmt->close();
                    } else {
                        $errorMessage = 'Database error: Unable to prepare statement. ' . htmlspecialchars($conn->error);
                    }
                }
            } else {
                $errorMessage = 'Database error: Unable to prepare statement. ' . htmlspecialchars($conn->error);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="UCGS Inventory Management System - New Item Request">
    <title>UCGS Inventory | New Item Request</title>
    <link rel="stylesheet" href="../css/UserRequests.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" 
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" 
          crossorigin="anonymous" referrerpolicy="no-referrer">
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
                    <span class="user-text"><?php echo htmlspecialchars($accountName); ?></span> <!-- Display logged-in user's username -->
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

    <main class="main-content">
        <div id="new-request" class="tab-content active">
            <h1>New Item Request</h1>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert error">
                    <p><?php echo htmlspecialchars($errorMessage); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($successMessage)): ?>
                <div class="alert success">
                    <p><?php echo htmlspecialchars($successMessage); ?></p>
                </div>
            <?php endif; ?>

            <form id="requestForm" class="request-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="item-name">Item Name:</label>
                        <input type="text" id="item-name" name="item-name" required>
                    </div>
                    <div class="form-group">
                        <label for="item-category">Category:</label>
                        <select id="item-category" name="item-category" required>
                            <option value="">Select Category</option>
                            <option value="electronics">Electronics</option>
                            <option value="stationary">Stationary</option>
                            <option value="furniture">Furniture</option>
                            <option value="accesories">Accessories</option>
                            <option value="consumables">Consumables</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity">Quantity:</label>
                        <input type="number" id="quantity" name="quantity" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="item-unit">Item Unit:</label>
                        <select id="item-unit" name="item-unit" required>
                            <option value="">Select Unit</option>
                            <option value="Piece">Pcs</option>
                            <option value="Box">Bx</option>
                            <option value="Pair">Pr</option>
                            <option value="Bundle">Bdl</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="purpose">Purpose:</label>
                    <textarea id="purpose" name="purpose" rows="3" required></textarea>
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes:</label>
                    <textarea id="notes" name="notes" rows="2"></textarea>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="submit-btn">Submit Request</button>
                    <button type="reset" class="reset-btn">Clear Form</button>
                </div>
            </form>
        </div>
    </main>

    <script src="../js/usereqs.js"></script>
    <script>
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
    </script>
</body>
</html>