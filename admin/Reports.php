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

$accountName = $currentAdmin['username'] ?? 'User';
$accountEmail = $currentAdmin['email'] ?? '';
$accountRole = $_SESSION['role'];

// Check if the 'reports' table exists
$tableCheckQuery = "SHOW TABLES LIKE 'items'";
$tableCheckResult = $conn->query($tableCheckQuery);

if (!$tableCheckResult || $tableCheckResult->num_rows === 0) {
    echo "<p style='color: red; text-align: center;'>Error: The 'items' table does not exist in the database.</p>";
    exit();
}

// Fetch reports data
$query = "SELECT * FROM items";
$result = $conn->query($query);

if (!$result) {
    echo "<p style='color: red; text-align: center;'>Error fetching items: " . htmlspecialchars($conn->error) . "</p>";
    exit();
}

// Handle report download requests
// Handle report download requests
if (isset($_GET['download'])) {
    $downloadType = $_GET['download'];
    $selectedItems = isset($_POST['selectedItems']) ? json_decode($_POST['selectedItems'], true) : [];

    if (in_array($downloadType, ['pdf', 'xlsx'])) {
        $data = [];
        if (!empty($selectedItems)) {
            // Fetch only the selected items from database
            $placeholders = implode(',', array_fill(0, count($selectedItems), '?'));
            $stmt = $conn->prepare("SELECT * FROM items WHERE item_no IN ($placeholders)");
            $types = str_repeat('s', count($selectedItems)); // 's' for string parameters
            $stmt->bind_param($types, ...$selectedItems);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        } else {
            // If nothing selected, fetch all items
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        if (empty($data)) {
            echo "<p style='color: red; text-align: center;'>No data available for download.</p>";
            exit();
        }

        if ($downloadType === 'xlsx') {
            // Generate CSV (as simple XLSX alternative)
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="inventory_report_'.date('Y-m-d').'.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Header row
            fputcsv($output, [
                'Item No', 'Last Updated', 'Model No', 'Item Name', 
                'Description', 'Item Category', 'Item Location', 
                'Expiration', 'Brand', 'Supplier', 'Price Per Item',
                'Quantity', 'Unit', 'Status', 'Reorder Point'
            ]);
            
            // Data rows
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['item_no'] ?? '',
                    $row['last_updated'] ?? '',
                    $row['model_no'] ?? '',
                    $row['item_name'] ?? '',
                    $row['description'] ?? '',
                    $row['item_category'] ?? '',
                    $row['item_location'] ?? '',
                    $row['expiration'] ?? '',
                    $row['brand'] ?? '',
                    $row['supplier'] ?? '',
                    $row['price_per_item'] ?? '',
                    $row['quantity'] ?? '',
                    $row['unit'] ?? '',
                    $row['status'] ?? '',
                    $row['reorder_point'] ?? ''
                ]);
            }
            
            fclose($output);
            exit;
        }

        if ($downloadType === 'pdf') {
            // Generate HTML that browsers can print as PDF
            $html = '<!DOCTYPE html>
            <html>
            <head>
                <title>Inventory Report</title>
                <style>
                    body { font-family: Arial; margin: 20px; }
                    h1 { color: #333; text-align: center; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th { background-color: #f2f2f2; text-align: left; }
                    th, td { border: 1px solid #ddd; padding: 8px; }
                    .header { display: flex; justify-content: space-between; }
                    @media print {
                        @page { size: A4 landscape; margin: 1cm; }
                        body { font-size: 10pt; }
                        table { page-break-inside: auto; }
                        tr { page-break-inside: avoid; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>UCGS Inventory Report</h1>
                    <div>Generated: '.date('Y-m-d H:i:s').'</div>
                </div>
                <table>
                    <tr>
                        <th>Item No</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Qty</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Price</th>
                        <th>Supplier</th>
                    </tr>';
            
            foreach ($data as $row) {
                $html .= '<tr>
                        <td>'.htmlspecialchars($row['item_no']).'</td>
                        <td>'.htmlspecialchars($row['item_name']).'</td>
                        <td>'.htmlspecialchars($row['item_category']).'</td>
                        <td>'.htmlspecialchars($row['quantity']).' '.htmlspecialchars($row['unit']).'</td>
                        <td>'.htmlspecialchars($row['item_location']).'</td>
                        <td>'.htmlspecialchars($row['status']).'</td>
                        <td>'.htmlspecialchars($row['price_per_item']).'</td>
                        <td>'.htmlspecialchars($row['supplier']).'</td>
                    </tr>';
            }
            
            $html .= '</table>
            </body>
            </html>';
        
            // Output with instructions
            header('Content-Type: text/html');
            echo $html;
            echo '<script>
                setTimeout(function(){
                    window.print();
                    setTimeout(function(){
                        window.close();
                    }, 1000);
                }, 500);
            </script>';
            exit;
        }
    }

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Dashboard</title>
    <link rel="stylesheet" href="../css/Report.css">
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
        <h2>Reports</h2>
        <div class="download-options" style="text-align: right;">
            <a href="?download=pdf" class="download-pdf">
                <img src="../assets/FileIcon/pdf.png" alt="PDF Icon" width="20"> PDF
            </a>
            <a href="?download=xlsx" class="download-xlsx">
                <img src="../assets/FileIcon/xlsx.png" alt="XLSX Icon" width="20"> XLSX
            </a>
        </div>
        <table class="report-table">
    <thead>
        <tr>
            <th>Select All <input type="checkbox" class="select-all" onclick="toggleSelectAll(this)"></th>
            <th>Item No</th>
            <th>Last Updated</th>
            <th>Model No</th>
            <th>Item Name</th>
            <th>Description</th>
            <th>Item Category</th>
            <th>Item Location</th>
            <th>Expiration</th>
            <th>Brand</th>
            <th>Supplier</th>
            <th>Price Per Item</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Status</th>
            <th>Reorder Point</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><input type="checkbox" class="select-checkbox" value="<?php echo htmlspecialchars($row['item_no']); ?>"></td>
                    <td><?php echo htmlspecialchars($row['item_no']); ?></td>
                    <td><?php echo htmlspecialchars($row['last_updated']); ?></td>
                    <td><?php echo htmlspecialchars($row['model_no']); ?></td>
                    <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                    <td><?php echo htmlspecialchars($row['item_category']); ?></td>
                    <td><?php echo htmlspecialchars($row['item_location']); ?></td>
                    <td><?php echo htmlspecialchars($row['expiration']); ?></td>
                    <td><?php echo htmlspecialchars($row['brand']); ?></td>
                    <td><?php echo htmlspecialchars($row['supplier']); ?></td>
                    <td><?php echo htmlspecialchars($row['price_per_item']); ?></td>
                    <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($row['unit']); ?></td>
                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                    <td><?php echo htmlspecialchars($row['reorder_point']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr class="no-data">
                <td colspan="16" style="text-align:center; padding: 10px;">No data available</td>
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

    <!-- Add a form to handle selected items -->
    <form id="downloadForm" method="POST" action="">
        <input type="hidden" name="selectedItems" id="selectedItems">
    </form>

    <script>
document.addEventListener("DOMContentLoaded", function () {
    const downloadLinks = document.querySelectorAll(".download-pdf, .download-xlsx");
    const selectedItemsInput = document.getElementById("selectedItems");

    downloadLinks.forEach(link => {
        link.addEventListener("click", function (event) {
            event.preventDefault();
            const selectedCheckboxes = document.querySelectorAll(".select-checkbox:checked");
            const selectedValues = Array.from(selectedCheckboxes).map(cb => cb.value);

            if (selectedValues.length === 0) {
                alert("Please select at least one item to download.");
                return;
            }

            // Create a simple array of selected item_no values
            selectedItemsInput.value = JSON.stringify(selectedValues);
            const form = document.getElementById("downloadForm");
            form.action = this.href;
            form.submit();
        });
    });
});
    </script>

    <script src="../js/report.js"></script>
</body>
</html>