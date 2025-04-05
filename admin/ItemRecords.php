<?php
session_start();
include '../config/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// Define the getLoggedInUser function
function getLoggedInUser($conn) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user;
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

// Ensure $query is defined with a valid SQL statement
$query = "SELECT * FROM items"; // Replace 'items' with the correct table name if needed

// Execute the query
$result = $conn->query($query);

// Check for errors in query execution
if (!$result) {
    die("Query failed: " . $conn->error);
}

$loggedInUser = getLoggedInUser($conn);
if (!$loggedInUser || $loggedInUser['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

$accountName = $loggedInUser['username'];
$accountRole = $loggedInUser['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create-item'])) {
        // Handle item creation
        $itemNo = $_POST['item_no'] ?? null;
        $itemName = $_POST['item_name'] ?? null;
        $description = $_POST['description'] ?? null;
        $quantity = $_POST['quantity'] ?? null;
        $unit = $_POST['unit'] ?? null;
        $status = $_POST['status'] ?? null;
        $modelNo = $_POST['model_no'] ?? null;
        $itemCategory = $_POST['item_category'] ?? null;
        $itemLocation = $_POST['item_location'] ?? null;

        // Validate required fields
        if (!$itemNo || !$itemName || !$quantity || !$status) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit();
        }

        // Insert the new item into the database
        $stmt = $conn->prepare("INSERT INTO items (item_no, item_name, description, quantity, unit, status, model_no, item_category, item_location, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssissssss", $itemNo, $itemName, $description, $quantity, $unit, $status, $modelNo, $itemCategory, $itemLocation);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Item created successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create item.']);
        }

        $stmt->close();
        exit();
    }

    if (isset($_POST['delete-item'])) {
        // Handle item deletion
        $itemNo = $_POST['item_no'] ?? null;

        if (!$itemNo) {
            echo json_encode(['success' => false, 'message' => 'Item number is required for deletion.']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM items WHERE item_no = ?");
        $stmt->bind_param("s", $itemNo);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Item deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete item.']);
        }

        $stmt->close();
        exit();
    }

    if (isset($_POST['update-item'])) {
        // Handle item update
        $itemNo = $_POST['item_no'] ?? null;
        $itemName = $_POST['item_name'] ?? null;
        $description = $_POST['description'] ?? null;
        $quantity = $_POST['quantity'] ?? null;
        $unit = $_POST['unit'] ?? null;
        $status = $_POST['status'] ?? null;
        $modelNo = $_POST['model_no'] ?? null;
        $itemCategory = $_POST['item_category'] ?? null;
        $itemLocation = $_POST['item_location'] ?? null;

        // Validate required fields
        if (!$itemNo || !$itemName || !$quantity || !$status) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit();
        }

        // Prepare and execute the update query
        $stmt = $conn->prepare("UPDATE items SET item_name = ?, description = ?, quantity = ?, unit = ?, status = ?, model_no = ?, item_category = ?, item_location = ?, last_updated = NOW() WHERE item_no = ?");
        $stmt->bind_param("ssisssssi", $itemName, $description, $quantity, $unit, $status, $modelNo, $itemCategory, $itemLocation, $itemNo);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Item updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update item.']);
        }

        $stmt->close();
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Item Records</title>
    <link rel="stylesheet" href="../css/records.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Hide modals by default */
        .modal {
            display: none;
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
    <h2>Item Records</h2>
    <!-- Search and Filter Form -->
<div class="search-form">
    <input type="text" id="search-input" placeholder="Search...">
    <p style = "font-family: 'Akrobat', sans-serif;">Date Range:</p>
    <input type="date" id="start-date">
    <p style = "font-family: 'Akrobat', sans-serif;">To</p>
    <input type="date" id="end-date">
    <button class="search-btn" onclick="searchTable()">Search</button>
    <button class="reset-btn" onclick="resetSearch()">Reset</button>
    <button class="create-btn" onclick="openCreateModal()">Create New Item</button>
    <button class="delete-selected-btn" onclick="deleteSelected()">Delete Selected</button>
</div>


    <!-- Item Records Table -->
    <form id="item-form">
        <table class="item-table">
            <thead>
                <tr>
                    <th>Select All <input type="checkbox" class="select-all" onclick="toggleSelectAll(this)"></th>
                    <th>Item Name</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Expiration</th>
                    <th>Last Updated</th>
                    <th>Model No</th>
                    <th>Item Category</th>
                    <th>Item Location</th>
                    <th>
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody id="item-table-body">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><input type="checkbox" class="select-item"></td>
                            <td><?= htmlspecialchars($row['item_name']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td><?= htmlspecialchars($row['quantity']) ?></td>
                            <td><?= htmlspecialchars($row['unit']) ?></td>
                            <td><?= htmlspecialchars($row['status']) ?></td>
                            <td><?= htmlspecialchars($row['expiration']) ?></td>
                            <td><?= htmlspecialchars($row['last_updated']) ?></td>
                            <td><?= htmlspecialchars($row['model_no']) ?></td>
                            <td><?= htmlspecialchars($row['item_category']) ?></td>
                            <td><?= htmlspecialchars($row['item_location']) ?></td>
                            <td>
                                <button type="button" class="update-btn" onclick="openUpdateModal(this)">Update</button>
                                <button type="button" class="delete-btn" onclick="openDeleteModal(this)">Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12">No records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
    <div class="pagination">
    <button onclick="prevPage()" id="prev-btn" style = "font-family:'Akrobat', sans-serif;">Previous</button>
    <span id="page-number" style = "font-family:'Akrobat', sans-serif;">Page 1</span>
    <button onclick="nextPage()" id="next-btn" style = "font-family:'Akrobat', sans-serif;">Next</button>
</div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <p>Are you sure you want to delete this item?</p>
        <div class="modal-buttons">
            <button id="confirmDelete" class="delete-btn">Yes</button>
            <button id="cancelDelete" class="cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<!-- Update Modal -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <h2>Update Item</h2>
        <form id="update-form">
            <input type="hidden" id="update-item-no"> <!-- Hidden input for item_no -->
            <label for="update-item-name">Item Name</label>
            <input type="text" id="update-item-name" required>
            <label for="update-description">Description</label>
            <textarea id="update-description" required></textarea>
            <label for="update-quantity">Quantity</label>
            <input type="number" id="update-quantity" required>
            <label for="update-unit">Unit</label>
            <input type="text" id="update-unit" required>
            <label for="update-status">Status</label>
            <input type="text" id="update-status" required>
            <label for="update-model-no">Model No</label>
            <input type="text" id="update-model-no" required>
            <label for="update-item-category">Item Category</label>
            <input type="text" id="update-item-category" required>
            <label for="update-item-location">Item Location</label>
            <input type="text" id="update-item-location" required>
            <button type="submit">Save</button>
            <button type="button" id="cancelUpdate">Cancel</button>
        </form>
    </div>
</div>

<!-- Create New Item Modal -->
<div id="create-Item-modal" class="modal"> <!-- Corrected modal ID -->
    <div class="modal-content">
        <h2>Create New Item</h2>
        <form id="account-form">
            <input type="hidden" id="user-id"> <!-- Hidden input for user_id -->
            <label for="item-name">Item Name</label>
            <input type="text" id="item-name" required>

            <label for="description">Description</label>
            <input type="description" id="description" required>

            <label for="quantity">Quantity</label>
            <input type="quantity" id="quantity" required>

            <label for="model-no">Model No.</label>
            <input type="model-no" id="model-no" required>

            <label for="status">Status</label>
            <input type="status" id="status" required>

            <label for="unit">Unit</label>
            <select id="unit">
                <option value="Choose">-- Select Units --</option>
                <option value="pcs">Pcs</option>
                <option value="bx">Bx</option>
                <option value="pr">Pr</option>
                <option value="bdl">Bdl</option>
            </select>

            <label for="item-category">Item Category</label>
            <select id="item-category">
            <option value="Choose">-- Select Category --</option>
            <option value="electronics">Electronics</option>
                <option value="stationary">Stationary</option>
                <option value="furniture">Furniture</option>
                <option value="accesories">Accessories</option>
                <option value="consumables">Consumables</option>
            </select>
            <button type="submit">Submit</button>
            <button type="button" id="cancel-btn">Cancel</button>
        </form>
    </div>
</div>
<!-- Ensure modal is placed near the table for proper positioning -->

    <script src="../js/records.js"></script>

</body>
</html>
