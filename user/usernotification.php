<?php
session_start();
include '../config/db_connection.php';

function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
        header("Location: ../login/login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
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

// Fetch notifications specific to the logged-in user
$query = "SELECT notification_id, type, message, created_at, is_read 
          FROM notifications 
          WHERE user_id = ? 
          ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}
$stmt->close();

// Fetch user requests and their statuses
$requestQuery = "SELECT request_id, item_name, status, admin_reason 
                 FROM user_requests 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC";
$stmt = $conn->prepare($requestQuery);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$requestResult = $stmt->get_result();

$userRequests = [];
if ($requestResult) {
    while ($row = $requestResult->fetch_assoc()) {
        $userRequests[] = $row;
    }
}
$stmt->close();

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
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
                <img src="../assets/img/Logo.png" alt="UCGS Inventory Logo" class="logo">
                <span class="website-name">UCGS Inventory | Notification</span>
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
            <?php if(count($notifications) > 0): ?>
                <?php foreach($notifications as $notification): ?>
                    <div class="notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?>" 
                         data-notification-id="<?= $notification['id'] ?>">
                        <div class="notification-content">
                            <span class="notification-icon">
                                <i class="fa-solid <?= getNotificationIcon($notification['type']) ?>"></i>
                            </span>
                            <div class="notification-text">
                                <p><?= htmlspecialchars($notification['message']) ?></p>
                                <span class="notification-date">
                                    <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if(!$notification['is_read']): ?>
                                <button class="btn mark-read" onclick="handleMarkRead(this)">
                                    <span class="button-text">Mark as Read</span>
                                    <div class="loading" style="display: none;"></div>
                                </button>
                            <?php endif; ?>
                            <button class="btn delete-notification" onclick="handleDeleteNotification(this)">
                                <i class="fa-solid fa-trash"></i>
                                <div class="loading" style="display: none;"></div>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <div class="pagination">
                    <?php if($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="btn">Previous</a>
                    <?php endif; ?>
                    
                    <?php if(($page * $limit) < $totalNotifications): ?>
                        <a href="?page=<?= $page + 1 ?>" class="btn">Next</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-notifications">
                    <i class="fa-regular fa-bell-slash"></i>
                    <p>No notifications found</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="user-requests">
            <h2>Your Requests</h2>
            <?php if(count($userRequests) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Item Name</th>
                            <th>Status</th>
                            <th>Admin Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($userRequests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['request_id']) ?></td>
                                <td><?= htmlspecialchars($request['item_name']) ?></td>
                                <td><?= htmlspecialchars($request['status']) ?></td>
                                <td><?= htmlspecialchars($request['admin_reason'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No requests found.</p>
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