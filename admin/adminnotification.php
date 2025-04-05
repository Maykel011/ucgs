<?php
include '../config/db_connection.php';
session_start();

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// Get logged-in user details
$loggedInUser = getLoggedInUser($conn);
if (!$loggedInUser || $loggedInUser['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

$accountName = $loggedInUser['username'];
$accountRole = $loggedInUser['role'];
$loggedInUserId = $loggedInUser['user_id']; // Get the logged-in user's ID

// Handle form submission for new item requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_new_item_request'])) {
    $itemId = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    $purpose = trim($_POST['purpose']);
    $notes = trim($_POST['notes']);

    // Validate input
    if ($itemId > 0 && $quantity > 0 && !empty($purpose)) {
        $stmt = $conn->prepare("INSERT INTO new_item_requests (user_id, item_id, quantity, purpose, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $loggedInUserId, $itemId, $quantity, $purpose, $notes);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "New item request submitted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to submit the request. Please try again.";
        }

        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Invalid input. Please fill out all required fields.";
    }

    header("Location: adminnotification.php");
    exit();
}

// Handle form submission for borrow requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_borrow_request'])) {
    $itemId = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    $dateNeeded = $_POST['date_needed'];
    $returnDate = $_POST['return_date'];
    $purpose = trim($_POST['purpose']);
    $notes = trim($_POST['notes']);

    // Validate input
    if ($itemId > 0 && $quantity > 0 && !empty($dateNeeded) && !empty($returnDate) && !empty($purpose)) {
        $stmt = $conn->prepare("INSERT INTO borrow_requests (user_id, item_id, quantity, date_needed, return_date, purpose, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissss", $loggedInUserId, $itemId, $quantity, $dateNeeded, $returnDate, $purpose, $notes);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Borrow request submitted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to submit the request. Please try again.";
        }

        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Invalid input. Please fill out all required fields.";
    }

    header("Location: adminnotification.php");
    exit();
}

// Handle form submission for return requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_return_request'])) {
    $borrowId = intval($_POST['borrow_id']);
    $returnDate = $_POST['return_date'];
    $itemCondition = $_POST['item_condition'];
    $notes = trim($_POST['notes']);

    // Validate input
    if ($borrowId > 0 && !empty($returnDate) && !empty($itemCondition)) {
        $stmt = $conn->prepare("INSERT INTO return_requests (borrow_id, return_date, item_condition, notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $borrowId, $returnDate, $itemCondition, $notes);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Return request submitted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to submit the request. Please try again.";
        }

        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Invalid input. Please fill out all required fields.";
    }

    header("Location: adminnotification.php");
    exit();
}

// Fetch user requests from the new_item_requests table
$newItemRequestsQuery = "SELECT r.request_id, r.quantity, r.purpose, r.notes, r.status, r.request_date, 
                                u.username, i.item_name 
                         FROM new_item_requests r
                         JOIN users u ON r.user_id = u.user_id
                         JOIN items i ON r.item_id = i.item_id
                         ORDER BY r.request_date DESC";
$newItemRequestsResult = $conn->query($newItemRequestsQuery);

$newItemRequests = [];
if ($newItemRequestsResult && $newItemRequestsResult->num_rows > 0) {
    while ($row = $newItemRequestsResult->fetch_assoc()) {
        $newItemRequests[] = $row;
    }
}

// Fetch borrow requests from the borrow_requests table
$borrowRequestsQuery = "SELECT b.borrow_id, b.quantity, b.date_needed, b.return_date, b.purpose, b.notes, b.status, b.request_date, 
                               u.username, i.item_name 
                        FROM borrow_requests b
                        JOIN users u ON b.user_id = u.user_id
                        JOIN items i ON b.item_id = i.item_id
                        ORDER BY b.request_date DESC";
$borrowRequestsResult = $conn->query($borrowRequestsQuery);

$borrowRequests = [];
if ($borrowRequestsResult && $borrowRequestsResult->num_rows > 0) {
    while ($row = $borrowRequestsResult->fetch_assoc()) {
        $borrowRequests[] = $row;
    }
}

// Fetch return requests from the return_requests table
$returnRequestsQuery = "SELECT r.return_id, r.return_date, r.item_condition, r.notes, r.status, r.created_at, 
                               b.borrow_id, u.username, i.item_name 
                        FROM return_requests r
                        JOIN borrow_requests b ON r.borrow_id = b.borrow_id
                        JOIN users u ON b.user_id = u.user_id
                        JOIN items i ON b.item_id = i.item_id
                        ORDER BY r.created_at DESC";
$returnRequestsResult = $conn->query($returnRequestsQuery);

$returnRequests = [];
if ($returnRequestsResult && $returnRequestsResult->num_rows > 0) {
    while ($row = $returnRequestsResult->fetch_assoc()) {
        $returnRequests[] = $row;
    }
}

// Combine all requests for display
$allRequests = [
    'new_item_requests' => $newItemRequests,
    'borrow_requests' => $borrowRequests,
    'return_requests' => $returnRequests
];

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$totalNotifications = count($allRequests);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Add meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Notifications</title>
    <link rel="stylesheet" href="../css/notification.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Add loading animation CSS -->
    <style>
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,.3);
            border-radius: 50%;
            border-top-color: #000;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
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
                        <a href="#"><img src="../assets/img/logout.png" alt="Logout Icon" class="dropdown-icon"> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar">
    <ul>
        <li><a href="AdminDashboard.php"><img src="../assets/img/dashboards.png" alt="Dashboard Icon" class="sidebar-icon"> Dashboard</a></li>

        <li><a href="ItemRecords.php"><img src="../assets/img/list-items.png" alt="Items Icon" class="sidebar-icon">Item Records</i></a></li>

        <!-- Request Record with Dropdown -->
        <li class="dropdown">
            <a href="#" class="dropdown-btn">
                <img src="../assets/img/request-for-proposal.png" alt="Request Icon" class="sidebar-icon">
                <span class="text">Request Record</span>
                <i class="fa-solid fa-chevron-down arrow-icon"></i>
            </a>
            <ul class="dropdown-content">
                <li><a href="ItemRequest.php"><i class=""></i> Item Request by User</a></li>
                <li><a href="ItemBorrowed.php"><i class=""></i> Item Borrow</a></li>
                <li><a href="ItemReturned.php"><i class=""></i> Item Returned</a></li>
            </ul>
        </li>

        <li><a href="Reports.php"><img src="../assets/img/reports.png" alt="Reports Icon" class="sidebar-icon"> Reports</a></li>
        <li><a href="UserManagement.php"><img src="../assets/img/user-management.png" alt="User Management Icon" class="sidebar-icon"> User Management</a></li>
    </ul>
</aside>

    <div class="main-content">
        <div class="notification-header">
            <h2>Notifications</h2>
            <div class="notification-actions">
                <button class="btn mark-all-read" onclick="handleMarkAllRead()">
                    <span class="button-text">Mark All as Read</span>
                    <div class="loading" style="display: none;"></div>
                </button>
                <button class="btn delete-all" onclick="handleDeleteAll()">
                    <span class="button-text">Delete All</span>
                    <div class="loading" style="display: none;"></div>
                </button>
            </div>
        </div>
        
        <div class="notification-list">
            <?php if (!empty($allRequests)): ?>
                <!-- New Item Requests -->
                <h3>New Item Requests</h3>
                <?php foreach ($allRequests['new_item_requests'] as $request): ?>
                    <div class="notification-item <?= $request['status'] === 'Pending' ? 'unread' : 'read' ?>" 
                         data-request-id="<?= $request['request_id'] ?>">
                        <div class="notification-content">
                            <span class="notification-icon">
                                <i class="fa-solid fa-box"></i>
                            </span>
                            <div class="notification-text">
                                <p><strong>User:</strong> <?= htmlspecialchars($request['username']) ?></p>
                                <p><strong>Item:</strong> <?= htmlspecialchars($request['item_name']) ?></p>
                                <p><strong>Quantity:</strong> <?= htmlspecialchars($request['quantity']) ?></p>
                                <p><strong>Purpose:</strong> <?= htmlspecialchars($request['purpose']) ?></p>
                                <p><strong>Notes:</strong> <?= htmlspecialchars($request['notes'] ?? 'N/A') ?></p>
                                <span class="notification-date">
                                    <?= date('M j, Y g:i A', strtotime($request['request_date'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if ($request['status'] === 'Pending'): ?>
                                <button class="btn approve-request" onclick="handleApproveRequest(this)">
                                    <span class="button-text">Approve</span>
                                    <div class="loading" style="display: none;"></div>
                                </button>
                                <button class="btn reject-request" onclick="handleRejectRequest(this)">
                                    <span class="button-text">Reject</span>
                                    <div class="loading" style="display: none;"></div>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Borrow Requests -->
                <h3>Borrow Requests</h3>
                <?php foreach ($allRequests['borrow_requests'] as $request): ?>
                    <div class="notification-item <?= $request['status'] === 'Pending' ? 'unread' : 'read' ?>" 
                         data-borrow-id="<?= $request['borrow_id'] ?>">
                        <div class="notification-content">
                            <span class="notification-icon">
                                <i class="fa-solid fa-hand-holding"></i>
                            </span>
                            <div class="notification-text">
                                <p><strong>User:</strong> <?= htmlspecialchars($request['username']) ?></p>
                                <p><strong>Item:</strong> <?= htmlspecialchars($request['item_name']) ?></p>
                                <p><strong>Quantity:</strong> <?= htmlspecialchars($request['quantity']) ?></p>
                                <p><strong>Date Needed:</strong> <?= htmlspecialchars($request['date_needed']) ?></p>
                                <p><strong>Return Date:</strong> <?= htmlspecialchars($request['return_date']) ?></p>
                                <p><strong>Purpose:</strong> <?= htmlspecialchars($request['purpose']) ?></p>
                                <p><strong>Notes:</strong> <?= htmlspecialchars($request['notes'] ?? 'N/A') ?></p>
                                <span class="notification-date">
                                    <?= date('M j, Y g:i A', strtotime($request['request_date'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if ($request['status'] === 'Pending'): ?>
                                <button class="btn approve-request" onclick="handleApproveBorrowRequest(this)">
                                    <span class="button-text">Approve</span>
                                    <div class="loading" style="display: none;"></div>
                                </button>
                                <button class="btn reject-request" onclick="handleRejectBorrowRequest(this)">
                                    <span class="button-text">Reject</span>
                                    <div class="loading" style="display: none;"></div>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Return Requests -->
                <h3>Return Requests</h3>
                <?php foreach ($allRequests['return_requests'] as $request): ?>
                    <div class="notification-item <?= $request['status'] === 'Pending' ? 'unread' : 'read' ?>" 
                         data-return-id="<?= $request['return_id'] ?>">
                        <div class="notification-content">
                            <span class="notification-icon">
                                <i class="fa-solid fa-undo"></i>
                            </span>
                            <div class="notification-text">
                                <p><strong>User:</strong> <?= htmlspecialchars($request['username']) ?></p>
                                <p><strong>Item:</strong> <?= htmlspecialchars($request['item_name']) ?></p>
                                <p><strong>Return Date:</strong> <?= htmlspecialchars($request['return_date']) ?></p>
                                <p><strong>Condition:</strong> <?= htmlspecialchars($request['item_condition']) ?></p>
                                <p><strong>Notes:</strong> <?= htmlspecialchars($request['notes'] ?? 'N/A') ?></p>
                                <span class="notification-date">
                                    <?= date('M j, Y g:i A', strtotime($request['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if ($request['status'] === 'Pending'): ?>
                                <button class="btn approve-request" onclick="handleApproveReturnRequest(this)">
                                    <span class="button-text">Approve</span>
                                    <div class="loading" style="display: none;"></div>
                                </button>
                                <button class="btn reject-request" onclick="handleRejectReturnRequest(this)">
                                    <span class="button-text">Reject</span>
                                    <div class="loading" style="display: none;"></div>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-notifications">
                    <i class="fa-regular fa-bell-slash"></i>
                    <p>No requests found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add CSRF token meta tag -->
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">

    <script src="../js/admind.js"></script>
    <script>
        // AJAX Helper Function
        async function makeRequest(url, method = 'POST', data = {}) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const headers = {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            };

            const response = await fetch(url, {
                method: method,
                headers: headers,
                body: method !== 'GET' ? JSON.stringify(data) : null
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response.json();
        }

        // Notification Actions
        async function handleNotificationAction(button, endpoint, notificationId = null) {
            const buttonText = button.querySelector('.button-text');
            const loader = button.querySelector('.loading');
            
            try {
                button.disabled = true;
                buttonText.style.display = 'none';
                loader.style.display = 'inline-block';

                const data = notificationId ? { notificationId } : {};
                const response = await makeRequest(endpoint, 'POST', data);

                if (response.success) {
                    // Handle UI update
                    if (endpoint.includes('delete')) {
                        button.closest('.notification-item').remove();
                    } else if (endpoint.includes('read')) {
                        button.closest('.notification-item').classList.add('read');
                        button.remove();
                    }
                } else {
                    alert(response.message || 'Action failed');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            } finally {
                button.disabled = false;
                buttonText.style.display = 'inline-block';
                loader.style.display = 'none';
            }
        }

        // Event Handlers
        function handleMarkRead(button) {
            const notificationId = button.closest('.notification-item').dataset.notificationId;
            handleNotificationAction(button, 'api/mark_read.php', notificationId);
        }

        function handleDeleteNotification(button) {
            const notificationId = button.closest('.notification-item').dataset.notificationId;
            if (confirm('Are you sure you want to delete this notification?')) {
                handleNotificationAction(button, 'api/delete_notification.php', notificationId);
            }
        }

        function handleMarkAllRead() {
            if (confirm('Mark all notifications as read?')) {
                const button = document.querySelector('.mark-all-read');
                handleNotificationAction(button, 'api/mark_all_read.php');
            }
        }

        function handleDeleteAll() {
            if (confirm('Permanently delete all notifications?')) {
                const button = document.querySelector('.delete-all');
                handleNotificationAction(button, 'api/delete_all_notifications.php');
            }
        }

        async function handleApproveRequest(button) {
            const requestId = button.closest('.notification-item').dataset.requestId;
            if (confirm('Approve this request?')) {
                handleNotificationAction(button, 'api/approve_request.php', requestId);
            }
        }

        async function handleRejectRequest(button) {
            const requestId = button.closest('.notification-item').dataset.requestId;
            if (confirm('Reject this request?')) {
                handleNotificationAction(button, 'api/reject_request.php', requestId);
            }
        }

        async function handleApproveBorrowRequest(button) {
            const borrowId = button.closest('.notification-item').dataset.borrowId;
            if (confirm('Approve this borrow request?')) {
                handleNotificationAction(button, 'api/approve_borrow_request.php', borrowId);
            }
        }

        async function handleRejectBorrowRequest(button) {
            const borrowId = button.closest('.notification-item').dataset.borrowId;
            if (confirm('Reject this borrow request?')) {
                handleNotificationAction(button, 'api/reject_borrow_request.php', borrowId);
            }
        }

        async function handleApproveReturnRequest(button) {
            const returnId = button.closest('.notification-item').dataset.returnId;
            if (confirm('Approve this return request?')) {
                handleNotificationAction(button, 'api/approve_return_request.php', returnId);
            }
        }

        async function handleRejectReturnRequest(button) {
            const returnId = button.closest('.notification-item').dataset.returnId;
            if (confirm('Reject this return request?')) {
                handleNotificationAction(button, 'api/reject_return_request.php', returnId);
            }
        }
    </script>
</body>
</html>