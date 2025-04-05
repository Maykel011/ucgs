<?php
include '../config/db_connection.php';
session_start();

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// Check if the getLoggedInUser function is already defined
if (!function_exists('getLoggedInUser')) {
    function getLoggedInUser($conn) {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $userId = intval($_SESSION['user_id']);
        $stmt = $conn->prepare("SELECT username, role FROM users WHERE user_id = ?");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }
}

// Fetch currently logged-in admin details
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

// Restrict access and display the logged-in user's role
$loggedInUser = getLoggedInUser($conn);
if (!$loggedInUser || $loggedInUser['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

$accountName = htmlspecialchars($loggedInUser['username']);
$accountRole = htmlspecialchars($loggedInUser['role']);

// Fetch borrow requests
$query = "SELECT br.borrow_id AS request_id, u.username, i.item_name, i.item_category,
                 br.date_needed, br.return_date, br.quantity, br.purpose, br.notes, 
                 br.status, br.request_date 
          FROM borrow_requests br 
          JOIN users u ON br.user_id = u.user_id
          JOIN items i ON br.item_id = i.item_id
          WHERE br.status = 'Pending'";
$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . htmlspecialchars($conn->error));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Item Borrowed</title>
    <link rel="stylesheet" href="../css/ItemBorrowed.css">
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
        <input type="text" id="search-box" placeholder="Search...">
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
    <tr>
        <td><?php echo htmlspecialchars($row['username']); ?></td>
        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
        <td><?php echo htmlspecialchars($row['item_category']); ?></td>
        <td><?php echo htmlspecialchars($row['date_needed']); ?></td>
        <td><?php echo htmlspecialchars($row['return_date']); ?></td>
        <td><?php echo htmlspecialchars($row['quantity']); ?></td>
        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
        <td><?php echo htmlspecialchars($row['notes']); ?></td>
        <td><?php echo htmlspecialchars($row['status']); ?></td>
        <td><?php echo htmlspecialchars($row['request_date']); ?></td>
        <td>
            <button class="approve-btn" data-request-id="<?php echo $row['request_id']; ?>">Approve</button>
            <button class="reject-btn" data-request-id="<?php echo $row['request_id']; ?>">Reject</button>
        </td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr>
        <td colspan="11">No pending requests found.</td>
    </tr>
<?php endif; ?>
</tbody>
    </table>
    <div class="pagination">
    <button onclick="prevPage()" id="prev-btn" style="font-family:'Akrobat', sans-serif;">Previous</button>
    <span id="page-number" style="font-family:'Akrobat', sans-serif;">Page 1</span>
    <button onclick="nextPage()" id="next-btn" style="font-family:'Akrobat', sans-serif;">Next</button>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Handle Approve buttons
    document.querySelectorAll('.approve-btn').forEach(button => {
        button.addEventListener('click', async function () {
            const requestId = this.getAttribute('data-request-id');
            const row = this.closest('tr');
            
            if (confirm('Are you sure you want to approve this request?')) {
                try {
                    const response = await fetch('updateRequestStatusApprove.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ request_id: requestId, status: 'Approved' })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update the status in the table
                        row.cells[8].textContent = 'Approved';
                        row.cells[8].style.color = 'green';
                        // Remove the action buttons
                        row.cells[10].innerHTML = '';
                    } else {
                        alert('Error: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while approving the request.');
                }
            }
        });
    });

    // Handle Reject buttons
    document.querySelectorAll('.reject-btn').forEach(button => {
        button.addEventListener('click', function () {
            const requestId = this.getAttribute('data-request-id');
            const row = this.closest('tr');
            
            // Show modal for rejection reason
            const modal = document.getElementById('rejectModal');
            modal.style.display = 'flex';
            
            // Set up modal buttons
            document.getElementById('confirmReject').onclick = async function() {
                const reason = document.getElementById('rejectionReason').value.trim();
                
                if (!reason) {
                    document.getElementById('error-message').textContent = 'Please provide a reason for rejection.';
                    return;
                }
                
                try {
                    const response = await fetch('updateRequestStatusReject.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ 
                            request_id: requestId, 
                            status: 'Rejected',
                            reason: reason 
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update the status in the table
                        row.cells[8].textContent = 'Rejected';
                        row.cells[8].style.color = 'red';
                        // Remove the action buttons
                        row.cells[10].innerHTML = '';
                        modal.style.display = 'none';
                    } else {
                        alert('Error: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while rejecting the request.');
                }
            };
            
            // Cancel button
            document.getElementById('cancelReject').onclick = function() {
                modal.style.display = 'none';
                document.getElementById('rejectionReason').value = '';
                document.getElementById('error-message').textContent = '';
            };
        });
    });

    // Close modal when clicking X
    document.querySelector('.close').addEventListener('click', function() {
        document.getElementById('rejectModal').style.display = 'none';
        document.getElementById('rejectionReason').value = '';
        document.getElementById('error-message').textContent = '';
    });

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === document.getElementById('rejectModal')) {
            document.getElementById('rejectModal').style.display = 'none';
            document.getElementById('rejectionReason').value = '';
            document.getElementById('error-message').textContent = '';
        }
    });

    // Pagination and filtering functionality
    const rowsPerPage = 7;
    let currentPage = 1;
    const tableBody = document.querySelector(".item-table tbody");
    const rows = Array.from(tableBody.getElementsByTagName("tr"));
    let filteredRows = [...rows];
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage);

    function showPage(page) {
        if (filteredRows.length === 0) {
            tableBody.innerHTML = "<tr><td colspan='11'>No results found</td></tr>";
            document.getElementById("page-number").innerText = "No results";
            document.getElementById("prev-btn").disabled = true;
            document.getElementById("next-btn").disabled = true;
            return;
        }

        rows.forEach(row => (row.style.display = "none"));
        let start = (page - 1) * rowsPerPage;
        let end = start + rowsPerPage;
        filteredRows.slice(start, end).forEach(row => (row.style.display = "table-row"));

        document.getElementById("page-number").innerText = `Page ${page} of ${totalPages}`;
        document.getElementById("prev-btn").disabled = page === 1;
        document.getElementById("next-btn").disabled = page === totalPages;
    }

    function nextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    }

    function filterTable() {
        const searchQuery = document.getElementById("search-box").value.toLowerCase();
        const startDate = document.getElementById("start-date").value;
        const endDate = document.getElementById("end-date").value;

        filteredRows = rows.filter(row => {
            let rowData = row.innerText.toLowerCase();
            let dateCell = row.children[3].innerText;

            let matchesSearch = rowData.includes(searchQuery);
            let matchesDate = true;

            if (startDate && endDate) {
                matchesDate = dateCell >= startDate && dateCell <= endDate;
            }

            return matchesSearch && matchesDate;
        });

        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        currentPage = 1;
        showPage(currentPage);
    }

    document.getElementById("search-box").addEventListener("input", filterTable);
    document.getElementById("start-date").addEventListener("change", filterTable);
    document.getElementById("end-date").addEventListener("change", filterTable);
    document.getElementById("prev-btn").addEventListener("click", prevPage);
    document.getElementById("next-btn").addEventListener("click", nextPage);

    showPage(currentPage);
});
</script>
</body>
</html>