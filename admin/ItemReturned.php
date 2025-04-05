<?php
include '../config/db_connection.php';
session_start();

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// Fetch the logged-in admin's details
$currentAdminId = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT username, email, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $currentAdminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();
$stmt->close();

// Pass admin details to the frontend
$accountName = htmlspecialchars($currentAdmin['username'] ?? 'User');
$accountEmail = htmlspecialchars($currentAdmin['email'] ?? '');
$accountRole = htmlspecialchars($currentAdmin['role'] ?? '');

// Fetch returned items from item_returns table
$query = "SELECT ir.return_id, ir.return_date, i.item_name, ir.quantity, 
                 ir.item_condition, ir.notes, ir.created_at, u.username
          FROM item_returns ir
          JOIN items i ON ir.item_id = i.item_id
          JOIN users u ON ir.user_id = u.user_id
          ORDER BY ir.created_at DESC";

$result = $conn->query($query);

if (!$result) {
    die("Error fetching returned items: " . htmlspecialchars($conn->error));
}

// Handle return approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['action']) || !isset($input['return_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }

    $returnId = intval($input['return_id']);
    $action = $input['action'];
    $notes = isset($input['notes']) ? $conn->real_escape_string($input['notes']) : '';

    if ($action === 'approve') {
        // For approval, just update notes if needed
        $stmt = $conn->prepare("UPDATE item_returns SET notes = ? WHERE return_id = ?");
        $stmt->bind_param("si", $notes, $returnId);
    } elseif ($action === 'reject') {
        // For rejection, update condition and notes
        $stmt = $conn->prepare("UPDATE item_returns SET item_condition = 'Damaged', notes = ? WHERE return_id = ?");
        $stmt->bind_param("si", $notes, $returnId);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Action completed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to process request']);
    }

    $stmt->close();
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Item Returns</title>
    <link rel="stylesheet" href="../css/ItmReturned.css">
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
                    <span class="admin-text"><?php echo $accountName; ?> (<?php echo $accountRole; ?>)</span>
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
            <li><a href="ItemRecords.php"><img src="../assets/img/list-items.png" alt="Items Icon" class="sidebar-icon">Item Records</a></li>
            <li class="dropdown">
                <a href="#" class="dropdown-btn">
                    <img src="../assets/img/request-for-proposal.png" alt="Request Icon" class="sidebar-icon">
                    <span class="text">Request Record</span>
                    <i class="fa-solid fa-chevron-down arrow-icon"></i>
                </a>
                <ul class="dropdown-content">
                    <li><a href="ItemRequest.php">Item Request by User</a></li>
                    <li><a href="ItemBorrowed.php">Item Borrow</a></li>
                    <li><a href="ItemReturned.php">Item Returned</a></li>
                </ul>
            </li>
            <li><a href="Reports.php"><img src="../assets/img/reports.png" alt="Reports Icon" class="sidebar-icon"> Reports</a></li>
            <li><a href="UserManagement.php"><img src="../assets/img/user-management.png" alt="User Management Icon" class="sidebar-icon"> User Management</a></li>
        </ul>
    </aside>

    <div class="main-content">
        <div class="table-container">
            <h2>Item Returned</h2>
            <div class="filter-container">
                <input type="text" id="search-input" placeholder="Search..." oninput="searchTable()">
                <label for="start-date">Date Range:</label>
                <input type="date" id="start-date" onchange="filterByDate()">
                <label for="end-date">To:</label>
                <input type="date" id="end-date" onchange="filterByDate()">
            </div>

            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Item Name</th>
                        <th>Return Date</th>
                        <th>Quantity</th>
                        <th>Condition</th>
                        <th>Notes</th>
                        <th>Request Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="item-table-body">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr data-return-id="<?php echo $row['return_id']; ?>">
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['return_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_condition']); ?></td>
                            <td><?php echo htmlspecialchars($row['notes']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                            <td>
                                <button class="approve-btn" onclick="handleAction(this, 'approve')">Approve</button>
                                <button class="reject-btn" onclick="handleAction(this, 'reject')">Reject</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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

        <script src="../js/ItReturned.js"></script>
</body>
</html>