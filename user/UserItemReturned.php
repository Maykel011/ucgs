<?php
include '../config/db_connection.php';
session_start();

function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
        header("Location: ../login/login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
    if (!$stmt) {
        die('Database error: ' . $conn->error);
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user ?: [];
}

$currentUser = getCurrentUser($conn);
$accountName = htmlspecialchars($currentUser['username']);
$accountEmail = htmlspecialchars($currentUser['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemCategory = htmlspecialchars($_POST['item_category'] ?? '');
    $itemId = intval($_POST['item_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $dateNeeded = $_POST['date_needed'] ?? '';
    $returnDate = $_POST['return_date'] ?? '';
    $purpose = htmlspecialchars($_POST['purpose'] ?? '');
    $notes = htmlspecialchars($_POST['notes'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;

    // Validate required fields
    if (!$itemCategory || !$itemId || !$quantity || !$dateNeeded || !$returnDate || !$purpose || !$userId) {
        die('All required fields must be filled out.');
    }

    // Validate quantity
    if ($quantity <= 0) {
        die('Quantity must be a positive number.');
    }

    // Validate dates
    if (strtotime($dateNeeded) === false || strtotime($returnDate) === false) {
        die('Invalid date format.');
    }
    if (strtotime($dateNeeded) > strtotime($returnDate)) {
        die('Return date must be after the date needed.');
    }

    // Fetch item details
    $itemCheckStmt = $conn->prepare("SELECT item_name, quantity, item_category FROM items WHERE item_id = ? AND item_category = ? AND quantity > 0");
    if (!$itemCheckStmt) {
        die('Database error: ' . $conn->error);
    }
    $itemCheckStmt->bind_param("is", $itemId, $itemCategory);
    $itemCheckStmt->execute();
    $itemResult = $itemCheckStmt->get_result();
    $itemData = $itemResult->fetch_assoc();
    $itemCheckStmt->close();

    if (!$itemData || $itemData['quantity'] < $quantity) {
        die('Insufficient item quantity available or item does not exist in the selected category.');
    }

    $itemName = htmlspecialchars($itemData['item_name']);

    // Check if the item already exists in the new_item_requests table
    $checkQuery = "SELECT COUNT(*) AS count FROM new_item_requests WHERE item_name = ? AND user_id = ? AND status = 'Pending'";
    $checkStmt = $conn->prepare($checkQuery);
    if ($checkStmt) {
        $checkStmt->bind_param("si", $itemName, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $row = $checkResult->fetch_assoc();
        $checkStmt->close();

        if ($row['count'] > 0) {
            die('You already have a pending request for this item.');
        }
    } else {
        die('Database error: Unable to prepare statement. ' . htmlspecialchars($conn->error));
    }

    // Insert borrow request
    $stmt = $conn->prepare("INSERT INTO borrow_requests (user_id, item_id, quantity, date_needed, return_date, purpose, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
    if (!$stmt) {
        die('Database error: ' . $conn->error);
    }
    $stmt->bind_param("iiissss", $userId, $itemId, $quantity, $dateNeeded, $returnDate, $purpose, $notes);
    $success = $stmt->execute();

    if ($success) {
        
        // Save transaction history
        $transactionQuery = "INSERT INTO transactions (user_id, action, details, item_id, quantity, status, item_name) VALUES (?, 'Borrow', ?, ?, ?, 'Pending', ?)";
        $transactionStmt = $conn->prepare($transactionQuery);
        if (!$transactionStmt) {
            die('Database error: ' . $conn->error);
        }
        $details = "Borrowed $quantity of item '$itemName' in $itemCategory category.";
        $transactionStmt->bind_param("isiss", $userId, $details, $itemId, $quantity, $itemName);
        if (!$transactionStmt->execute()) {
            die('Failed to save transaction history: ' . $transactionStmt->error);
        }
        $transactionStmt->close();

        // Redirect to transaction page with success message
        header('Location: UserTransaction.php?success=1');
        exit();
    } else {
        die('Failed to submit the request: ' . $stmt->error);
    }
    $stmt->close();
} 

// Fetch items based on category for dynamic population
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['item_category'])) {
    $category = htmlspecialchars($_GET['item_category']);

    // Validate category input
    if (empty($category)) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Item category is required']);
        exit();
    }

    // Prepare and execute the query
    $stmt = $conn->prepare("SELECT item_id, item_name FROM items WHERE item_category = ? AND quantity > 0");
    if (!$stmt) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch items and build the response
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    // Return the items as JSON
    header('Content-Type: application/json');
    echo json_encode($items);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetch_approved_requests') {
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        header('Content-Type: application/json', true, 401);
        echo json_encode(['error' => 'Unauthorized access.']);
        exit();
    }

    $stmt = $conn->prepare("SELECT br.request_id, br.item_id, i.item_name, br.quantity, br.date_needed, br.return_date 
                            FROM borrow_requests br
                            JOIN items i ON br.item_id = i.item_id
                            WHERE br.user_id = ? AND br.status = 'Approve'");
    if (!$stmt) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $approvedRequests = [];
    while ($row = $result->fetch_assoc()) {
        $approvedRequests[] = $row;
    }
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode($approvedRequests);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="UCGS Inventory Management System - New Item Request">
    <title>UCGS Inventory | Return Item Request</title>
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
            <h1>Return Item Request</h1>
            
            <form id="requestForm" class="request-form" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="item-category">Item Category:</label>
                        <select id="item-category" name="item_category" required>
                            <option value="" disabled selected>Select a Category</option>
                            <option value="electronics">Electronics</option>
                            <option value="furniture">Furniture</option>
                            <option value="stationery">Stationery</option>
                            <option value="accesories">Accessories</option>
                            <option value="consumables">Consumables</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="item-id">Select Item:</label>
                        <select id="item-id" name="item_id" required>
                            <option value="" disabled selected>Select an Item</option>
                            <!-- Items will be dynamically populated based on category -->
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity">Quantity:</label>
                        <input type="number" id="quantity" name="quantity" 
                               min="1" required placeholder="Enter quantity">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_needed">Date Needed:</label>
                        <input type="date" id="date_needed" name="date_needed" required>
                    </div>
                    <div class="form-group">
                        <label for="return_date">Return Date:</label>
                        <input type="date" id="return_date" name="return_date" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="purpose">Purpose:</label>
                    <input id="purpose" name="purpose" rows="3" required placeholder="Enter the purpose of borrowing"></textarea>
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes:</label>
                    <input id="notes" name="notes" rows="2" placeholder="Enter any additional notes (optional)"></textarea>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="submit-btn">Submit Request</button>
                    <button type="reset" class="reset-btn">Clear Form</button>
                </div>
            </form>
        </div>
    </main>

    <script src="../js/usereturn.js"></script>
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