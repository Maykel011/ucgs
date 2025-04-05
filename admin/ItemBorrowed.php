<?php
include '../config/db_connection.php';
session_start();

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// API endpoint for AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'approve') {
        $requestId = intval($_POST['request_id']);
        $stmt = $conn->prepare("UPDATE borrow_requests SET status = 'Approved', processed_at = NOW() WHERE borrow_id = ?");
        $stmt->bind_param("i", $requestId);
        $success = $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => $success]);
        exit();
    }
    elseif ($_POST['action'] === 'reject') {
        $requestId = intval($_POST['request_id']);
        $reason = $_POST['reason'] ?? '';
        $stmt = $conn->prepare("UPDATE borrow_requests SET status = 'Rejected', rejection_reason = ?, processed_at = NOW() WHERE borrow_id = ?");
        $stmt->bind_param("si", $reason, $requestId);
        $success = $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => $success]);
        exit();
    }
    elseif ($_POST['action'] === 'refresh') {
        $query = "SELECT br.borrow_id AS request_id, u.username, i.item_name, i.item_category,
                 br.date_needed, br.return_date, br.quantity, br.purpose, br.notes, 
                 br.status, br.request_date, br.processed_at, br.rejection_reason
          FROM borrow_requests br 
          JOIN users u ON br.user_id = u.user_id
          JOIN items i ON br.item_id = i.item_id
          ORDER BY br.request_date DESC";
        $result = $conn->query($query);
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        echo json_encode(['rows' => $rows]);
        exit();
    }
}

// Regular page load
$currentAdminId = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $currentAdminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();
$stmt->close();

$accountName = $currentAdmin['username'] ?? 'User';
$accountEmail = $currentAdmin['email'] ?? '';
if (empty($accountName)) {
    $accountName = 'Admin';
}

$accountName = htmlspecialchars($accountName);
$accountRole = 'Administrator';

// Fetch initial data
$query = "SELECT br.borrow_id AS request_id, u.username, i.item_name, i.item_category,
                 br.date_needed, br.return_date, br.quantity, br.purpose, br.notes, 
                 br.status, br.request_date, br.processed_at, br.rejection_reason
          FROM borrow_requests br 
          JOIN users u ON br.user_id = u.user_id
          JOIN items i ON br.item_id = i.item_id
          ORDER BY br.request_date DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Item Borrowed</title>
    <link rel="stylesheet" href="../css/ItemBorrowedss.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
<header class="header">
    <div class="header-content">
        <div class="left-side">
            <img src="../assets/img/Logo.png" alt="Logo" class="logo">
            <span class="website-name">UCGS Inventory</span>
        </div>
        <div class="right-side">
        <div class="user">
<img src="../assets/img/users.png" alt="User" class="icon" id="userIcon">
<span class="admin-text"><?php echo htmlspecialchars($accountName); ?> (<?php echo htmlspecialchars($accountRole); ?>)</span>
<div class="user-dropdown" id="userDropdown">
    <a href="adminprofile.php"><img src="../assets/img/updateuser.png" alt="Profile Icon" class="dropdown-icon"> Profile</a>
    <a href="adminnotification.php"><img src="../assets/img/notificationbell.png" alt="Notification Icon" class="dropdown-icon"> Notification</a>
    <a href="../login/logout.php"><img src="../assets/img/logout.png" alt="Logout Icon" class="dropdown-icon"> Logout</a>
</div>
</div>
        </div>
    </div>
</header>

<aside class="sidebar">
    <ul>
        <li><a href="AdminDashboard.php"><img src="../assets/img/dashboards.png" alt="Dashboard Icon" class="sidebar-icon"> Dashboard</a></li>
        <li><a href="#"><img src="../assets/img/list-items.png" alt="Items Icon" class="sidebar-icon"><span class="text">Item Records</span></a></li>
        <li class="dropdown">
            <a href="#" class="dropdown-btn">
                <img src="../assets/img/request-for-proposal.png" alt="Request Icon" class="sidebar-icon">
                <span class="text">Request Record</span>
                <i class="fa-solid fa-chevron-down arrow-icon"></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="ItemRequest.php"> Item Request by User</a></li>
                <li><a href="ItemBorrowed.php"> Item Borrowed</a></li>
                <li><a href="ItemReturned.php"> Item Returned</a></li>
            </ul>
        </li>
        <li><a href="Reports.php"><img src="../assets/img/reports.png" alt="Reports Icon" class="sidebar-icon"> Reports</a></li>
        <li><a href="UserManagement.php"><img src="../assets/img/user-management.png" alt="User Management Icon" class="sidebar-icon"> User Management</a></li>
    </ul>
</aside>

<div class="main-content">
    <h2>Item Borrowed</h2>
    
    <div class="filter-container">
    <div class="search-wrapper">
        <i class="fas fa-search"></i>
        <input type="text" id="search-box" placeholder="Search...">
    </div>
    <label for="start-date">Date Range:</label>
    <input type="date" id="start-date">
    <label for="end-date">To:</label>
    <input type="date" id="end-date">
</div>

    <table class="item-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Item Name</th>
                <th>Item Type</th>
                <th>Date Needed</th>
                <th>Return Date</th>
                <th>Quantity</th>
                <th>Purpose</th>
                <th>Notes</th>
                <th>Status</th>
                <th>Request Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="item-table-body">
<?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr data-request-id="<?php echo $row['request_id']; ?>">
        <td><?php echo htmlspecialchars($row['username']); ?></td>
        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
        <td><?php echo htmlspecialchars($row['item_category']); ?></td>
        <td><?php echo htmlspecialchars($row['date_needed']); ?></td>
        <td><?php echo htmlspecialchars($row['return_date']); ?></td>
        <td><?php echo htmlspecialchars($row['quantity']); ?></td>
        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
        <td><?php echo htmlspecialchars($row['notes']); ?></td>
        <td class="status-cell <?php echo strtolower($row['status']); ?>">
    <?php 
    if ($row['status'] === 'Approved') {
        echo '<span class="status-approved">Approved</span>';
        echo '<span class="processed-time">' . htmlspecialchars($row['processed_at']) . '</span>';
    } elseif ($row['status'] === 'Rejected') {
        echo '<span class="status-rejected">Rejected</span>';
        echo '<span class="processed-time">' . htmlspecialchars($row['processed_at']) . '</span>';
    } else {
        echo htmlspecialchars($row['status']);
    }
    ?>
</td>
        <td><?php echo htmlspecialchars($row['request_date']); ?></td>
        <td class="action-cell">
            <?php if ($row['status'] === 'Pending'): ?>
                <button class="approve-btn" data-request-id="<?php echo $row['request_id']; ?>">Approve</button>
                <button class="reject-btn" data-request-id="<?php echo $row['request_id']; ?>">Reject</button>
            <?php else: ?>
                <span class="processed-label">Processed</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="11">No requests found.</td>
    </tr>
<?php endif; ?>
</tbody>
    </table>
    <div class="pagination">
    <button onclick="prevPage()" id="prev-btn">Previous</button>
    <span id="page-number">Page 1</span>
    <button onclick="nextPage()" id="next-btn">Next</button>
</div>
</div>

<!-- Rejection Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Reject Request</h3>
        <textarea id="rejectionReason" rows="4" placeholder="Enter reason..."></textarea>
        <p id="error-message" style="color: red; font-size: 14px; margin-top: 5px;"></p>
        <div class="modal-buttons">
            <button id="confirmReject" class="confirm-btn">Confirm</button>
            <button id="cancelReject" class="cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script src="../js/Itmborrow.js"></script>
</body>
</html>