<?php
include '../config/db_connection.php';
session_start();

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// Fetch currently logged-in admin details
$currentAdminId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $currentAdminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();
$stmt->close();

// Pass the current admin details to the frontend
$accountName = $currentAdmin['username'] ?? 'User';
$accountEmail = $currentAdmin['email'] ?? '';
$accountRole = $_SESSION['role'];

// Fetch item requests data
$query = "SELECT r.request_id, u.username, r.item_name, r.item_category, r.purpose, r.request_date, r.quantity, r.status 
          FROM new_item_requests r
          JOIN users u ON r.user_id = u.user_id";
$result = $conn->query($query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $requestId = intval($_POST['request_id'] ?? 0);

    if ($action === 'approve') {
        $conn->begin_transaction();

        try {
            // Validate request ID
            if ($requestId <= 0) {
                throw new Exception('Invalid request ID');
            }

            // Get request details with FOR UPDATE to lock the row
            $query = "SELECT item_name, item_category, quantity 
                      FROM new_item_requests 
                      WHERE request_id = ? AND status = 'Pending' FOR UPDATE";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            
            $stmt->bind_param("i", $requestId);
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $request = $result->fetch_assoc();
            $stmt->close();

            if (!$request) {
                throw new Exception('Request not found or already processed');
            }

            // Validate item data
            if (empty($request['item_name']) || empty($request['item_category'])) {
                throw new Exception('Item name and category cannot be empty');
            }

            if (!is_numeric($request['quantity']) || $request['quantity'] <= 0) {
                throw new Exception('Quantity must be a positive number');
            }

            // Generate unique item number
            $item_no = 'ITEM-' . strtoupper(uniqid());

            // Insert into items table
            $insertItemQuery = "INSERT INTO items 
                              (item_no, item_name, item_category, quantity, status) 
                              VALUES (?, ?, ?, ?, 'Available')";
            $insertItemStmt = $conn->prepare($insertItemQuery);
            if (!$insertItemStmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            
            $insertItemStmt->bind_param("sssi", 
                $item_no,
                $request['item_name'], 
                $request['item_category'], 
                $request['quantity']
            );
            
            if (!$insertItemStmt->execute()) {
                throw new Exception('Failed to add item: ' . $insertItemStmt->error);
            }

            $itemId = $conn->insert_id;
            $insertItemStmt->close();

            // Update request status
            $updateRequestQuery = "UPDATE new_item_requests 
                                 SET status = 'Approved', item_id = ? 
                                 WHERE request_id = ?";
            $updateRequestStmt = $conn->prepare($updateRequestQuery);
            if (!$updateRequestStmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            
            $updateRequestStmt->bind_param("ii", $itemId, $requestId);
            
            if (!$updateRequestStmt->execute()) {
                throw new Exception('Failed to update request: ' . $updateRequestStmt->error);
            }

            $updateRequestStmt->close();
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Request approved and item added to inventory.',
                'item_no' => $item_no,
                'item_id' => $itemId
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Approval Error [Request ID: $requestId]: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'An error occurred while approving the request.',
                'error_details' => $e->getMessage(),
                'db_error' => $conn->error
            ]);
        }
        exit;
    }
    elseif ($action === 'reject') {
        // Handle reject action
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        
        if (empty($reason)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Rejection reason is required.'
            ]);
            exit;
        }

        $conn->begin_transaction();
        
        try {
            $updateQuery = "UPDATE new_item_requests 
                          SET status = 'Rejected', notes = ?
                          WHERE request_id = ? AND status = 'Pending'";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("si", $reason, $requestId);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to reject request: ' . $stmt->error);
            }
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('Request not found or already processed');
            }
            
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Request rejected successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Rejection Error [Request ID: $requestId]: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Failed to reject request',
                'error_details' => $e->getMessage()
            ]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Item Request</title>
    <link rel="stylesheet" href="../css/Itmreqs.css">
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
    <h2>Item Request List</h2>
    <table class="item-table">
    <div class="filter-container">
    <input type="text" id="search-box" placeholder="Search...">
    <label for="start-date">Date Range:</label>
    <input type="date" id="start-date">
    <label for="end-date">To:</label>
    <input type="date" id="end-date">
</div>

    <thead>
        <tr>
            <th>Username</th>
            <th>Requested Item</th>
            <th>Item Category</th>
            <th>Purpose</th>
            <th>Request Date</th>
            <th>Quantity</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr data-request-id="<?php echo $row['request_id']; ?>">
            <td><?php echo htmlspecialchars($row['username']); ?></td>
            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
            <td><?php echo htmlspecialchars($row['item_category']); ?></td>
            <td><?php echo htmlspecialchars($row['purpose']); ?></td>
            <td><?php echo htmlspecialchars($row['request_date']); ?></td>
            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
            <td class="status-cell"><?php echo htmlspecialchars($row['status']); ?></td>
            <td>
                <div class="action-buttons">
                    <button class="approve-btn" <?php echo $row['status'] !== 'Pending' ? 'disabled' : ''; ?>>Approve</button>
                    <button class="reject-btn" <?php echo $row['status'] !== 'Pending' ? 'disabled' : ''; ?>>Reject</button>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

    
    <div class="pagination">
    <button onclick="prevPage()" id="prev-btn" style = "font-family:'Akrobat', sans-serif;">Previous</button>
    <span id="page-number" style = "font-family:'Akrobat', sans-serif;">Page 1</span>
    <button onclick="nextPage()" id="next-btn" style = "font-family:'Akrobat', sans-serif;">Next</button>
</div>
</div>

<!-- Rejection Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <span class="close"></span>
        <h3>Reject Request</h3>
        <textarea id="rejectionReason" rows="4" placeholder="Enter reason..."></textarea>
        <p id="error-message" style="color: red; font-size: 14px; margin-top: 5px;"></p>
        <div class="modal-buttons">
            <button id="confirmReject" class="confirm-btn">Confirm</button>
            <button id="cancelReject" class="cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script src="../js/Itmreqs.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const approveButtons = document.querySelectorAll(".approve-btn");
    const rejectButtons = document.querySelectorAll(".reject-btn");
    const rejectModal = document.getElementById("rejectModal");
    const rejectionReason = document.getElementById("rejectionReason");
    const errorMessage = document.getElementById("error-message");
    const confirmReject = document.getElementById("confirmReject");
    const cancelReject = document.getElementById("cancelReject");
    let currentRequestId = null;

    approveButtons.forEach(button => {
    button.addEventListener("click", function () {
        const requestId = this.closest("tr").dataset.requestId;
        const row = this.closest("tr");
        const statusCell = row.querySelector(".status-cell");
        
        if (statusCell.textContent !== 'Pending') {
            alert('This request has already been processed.');
            return;
        }

        if (!confirm('Are you sure you want to approve this request?')) {
            return;
        }

        fetch("ItemRequest.php", {
            method: "POST",
            headers: { 
                "Content-Type": "application/x-www-form-urlencoded",
                "X-Requested-With": "XMLHttpRequest"
            },
            body: `action=approve&request_id=${requestId}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                statusCell.textContent = 'Approved';
                row.querySelector(".approve-btn").disabled = true;
                row.querySelector(".reject-btn").disabled = true;
                alert(data.message);
            } else {
                alert(`Error: ${data.message}\nDetails: ${data.error_details || 'Unknown error'}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error occurred. Please check console for details.');
        });
    });
});

    // Reject button functionality
    rejectButtons.forEach(button => {
        button.addEventListener("click", function () {
            currentRequestId = this.closest("tr").dataset.requestId;
            rejectionReason.value = "";
            errorMessage.textContent = "";
            rejectModal.style.display = "block";
        });
    });

    confirmReject.addEventListener("click", function () {
        const reason = rejectionReason.value.trim();
        const row = document.querySelector(`tr[data-request-id="${currentRequestId}"]`);
        const statusCell = row.querySelector(".status-cell");

        if (!reason) {
            errorMessage.textContent = "Rejection reason is required.";
            return;
        }

        fetch("ItemRequest.php", {
            method: "POST",
            headers: { 
                "Content-Type": "application/x-www-form-urlencoded",
                "X-Requested-With": "XMLHttpRequest"
            },
            body: `action=reject&request_id=${currentRequestId}&reason=${encodeURIComponent(reason)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update the UI immediately
                statusCell.textContent = 'Rejected';
                row.querySelector(".approve-btn").disabled = true;
                row.querySelector(".reject-btn").disabled = true;
                alert(data.message);
                rejectModal.style.display = "none";
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while rejecting the request.');
        });
    });

    cancelReject.addEventListener("click", function () {
        rejectModal.style.display = "none";
    });

    window.addEventListener("click", function (event) {
        if (event.target === rejectModal) {
            rejectModal.style.display = "none";
        }
    });
});
</script>
</body>
</html>