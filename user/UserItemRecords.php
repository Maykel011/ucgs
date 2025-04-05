<?php
include '../config/db_connection.php';
session_start();

// Verify User session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    header("Location: ../login/login.php");
    exit();
}

// Fetch current user details
function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
        header("Location: ../login/login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
    if (!$stmt) {
        error_log("Database error: " . $conn->error);
        die("An error occurred. Please try again later.");
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user ?: ['username' => 'User', 'email' => ''];
}

$currentUser = getCurrentUser($conn);
$accountName = htmlspecialchars($currentUser['username'] ?? 'User');
$accountEmail = htmlspecialchars($currentUser['email'] ?? '');

// Pagination logic
$itemsPerPage = 10; // Number of items per page
$page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure the page is at least 1

// Count total items
$totalItemsQuery = "SELECT COUNT(*) as total FROM items WHERE deleted_at IS NULL";
$totalItemsResult = $conn->query($totalItemsQuery);
if (!$totalItemsResult) {
    error_log("Database error: " . $conn->error);
    die("An error occurred. Please try again later.");
}
$totalItemsRow = $totalItemsResult->fetch_assoc();
$totalItems = $totalItemsRow['total'] ?? 0;

// Calculate total pages
$totalPages = ceil($totalItems / $itemsPerPage);

// Ensure the current page is not greater than total pages
if ($page > $totalPages) {
    $page = $totalPages > 0 ? $totalPages : 1;
}

// Calculate offset for pagination
$offset = ($page - 1) * $itemsPerPage;

// Fetch items for the current page
$itemsQuery = "SELECT item_name, description, quantity, unit, status, created_at,last_updated, model_no, item_category, item_location 
               FROM items 
               WHERE deleted_at IS NULL 
               LIMIT ?, ?";
$stmt = $conn->prepare($itemsQuery);
if (!$stmt) {
    error_log("Database error: " . $conn->error);
    die("An error occurred. Please try again later.");
}
$stmt->bind_param("ii", $offset, $itemsPerPage);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Item Records</title>
    <link rel="stylesheet" href="../css/records.css">
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

    <div class="main-content">
    <h2>Item Records</h2>
    <div class="search-form">
        <input type="text" id="search-input" placeholder="Search..." oninput="searchTable()">
    </div>

    <table class="item-table">
        <thead>
            <tr>
                <th>Item Name</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Status</th>
                <th>Last Updated</th>
                <th>Created At</th>
                <th>Model No</th>
                <th>Item Category</th>
                <th>Item Location</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): ?>
                <?php foreach ($items as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['item_name']); ?></td>
                        <td><?= htmlspecialchars($row['description']); ?></td>
                        <td><?= htmlspecialchars($row['quantity']); ?></td>
                        <td><?= htmlspecialchars($row['unit']); ?></td>
                        <td><?= htmlspecialchars($row['status']); ?></td>
                        <td><?= htmlspecialchars($row['last_updated']); ?></td>
                        <td><?= htmlspecialchars($row['created_at']); ?></td>
                        <td><?= htmlspecialchars($row['model_no']); ?></td>
                        <td><?= htmlspecialchars($row['item_category']); ?></td>
                        <td><?= htmlspecialchars($row['item_location']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align: center;">No records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
        <button onclick="changePage(<?php echo max(1, $page - 1); ?>)" <?php echo ($page <= 1) ? 'disabled' : ''; ?>>Previous</button>
        <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
        <button onclick="changePage(<?php echo min($totalPages, $page + 1); ?>)" <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>>Next</button>
    </div>
</div>

<script>
function changePage(page) {
    window.location.href = `UserItemRecords.php?page=${page}`;
}
</script>
</body>
</html>
