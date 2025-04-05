-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 04, 2025 at 02:52 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ucgs`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 2, 'Item Borrowed', 'Borrow ID: 1, Item ID: 2, Quantity: 1, Purpose: asdas', '2025-03-31 13:26:23'),
(2, 5, 'Item Borrowed', 'Borrow ID: 2, Item ID: 3, Quantity: 4, Purpose: ads', '2025-04-04 11:12:46');

-- --------------------------------------------------------

--
-- Table structure for table `borrowed_items`
--

CREATE TABLE `borrowed_items` (
  `borrow_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `actual_return_date` date DEFAULT NULL,
  `item_condition` enum('Good','Damaged','Lost') DEFAULT NULL,
  `return_notes` text DEFAULT NULL,
  `status` enum('Borrowed','Returned','Overdue') NOT NULL DEFAULT 'Borrowed',
  `user_id` int(11) NOT NULL,
  `borrow_date` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowed_items`
--

INSERT INTO `borrowed_items` (`borrow_id`, `request_id`, `item_id`, `actual_return_date`, `item_condition`, `return_notes`, `status`, `user_id`, `borrow_date`) VALUES
(3, 1, 1, NULL, NULL, NULL, 'Borrowed', 2, '0000-00-00');

-- --------------------------------------------------------

--
-- Table structure for table `borrow_requests`
--

CREATE TABLE `borrow_requests` (
  `borrow_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `date_needed` date NOT NULL,
  `return_date` date NOT NULL,
  `purpose` text NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `transaction_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrow_requests`
--

INSERT INTO `borrow_requests` (`borrow_id`, `user_id`, `item_id`, `quantity`, `date_needed`, `return_date`, `purpose`, `notes`, `status`, `request_date`, `transaction_id`, `item_name`) VALUES
(1, 2, 2, 1, '2025-03-30', '2025-03-31', 'asdas', 'asda', 'Pending', '2025-03-31 13:26:23', NULL, NULL),
(2, 5, 3, 4, '2025-04-05', '2025-04-06', 'ads', '', 'Pending', '2025-04-04 11:12:46', NULL, NULL);

--
-- Triggers `borrow_requests`
--
DELIMITER $$
CREATE TRIGGER `check_borrow_quantity` BEFORE INSERT ON `borrow_requests` FOR EACH ROW BEGIN
    DECLARE available_quantity INT;
    SELECT quantity INTO available_quantity FROM items WHERE item_id = NEW.item_id;
    IF NEW.quantity > available_quantity THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Borrow quantity exceeds available stock.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_borrow_request_status_change` AFTER UPDATE ON `borrow_requests` FOR EACH ROW BEGIN
    IF NEW.status IN ('Approved', 'Rejected') THEN
        INSERT INTO audit_logs (user_id, action, details)
        VALUES (NEW.user_id, CONCAT('Borrow Request ', NEW.status), CONCAT('Request ID: ', NEW.borrow_id, ', Item ID: ', NEW.item_id, ', Quantity: ', NEW.quantity));
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_item_borrow` AFTER INSERT ON `borrow_requests` FOR EACH ROW BEGIN
    INSERT INTO audit_logs (user_id, action, details)
    VALUES (NEW.user_id, 'Item Borrowed', CONCAT('Borrow ID: ', NEW.borrow_id, ', Item ID: ', NEW.item_id, ', Quantity: ', NEW.quantity, ', Purpose: ', NEW.purpose));
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_item_quantity_on_approval` AFTER UPDATE ON `borrow_requests` FOR EACH ROW BEGIN
    IF NEW.status = 'Approved' THEN
        UPDATE items
        SET quantity = quantity - NEW.quantity
        WHERE item_id = NEW.item_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `item_no` varchar(50) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `status` enum('Available','Out of Stock','Low Stock') NOT NULL,
  `reorder_point` int(11) DEFAULT 0,
  `model_no` varchar(50) DEFAULT NULL,
  `item_category` varchar(50) DEFAULT NULL,
  `item_location` varchar(50) DEFAULT NULL,
  `expiration` date DEFAULT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `supplier` varchar(50) DEFAULT NULL,
  `price_per_item` decimal(10,2) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `last_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `item_no`, `item_name`, `description`, `quantity`, `unit`, `status`, `reorder_point`, `model_no`, `item_category`, `item_location`, `expiration`, `brand`, `supplier`, `price_per_item`, `deleted_at`, `last_updated`, `created_at`) VALUES
(1, 'ITEM001', 'Laptop', 'High-performance laptop', 10, 'pcs', 'Available', 5, 'MOD123', 'Electronics', 'Warehouse A', '2025-12-31', 'Dell', 'TechSupplier Inc.', 1200.00, NULL, '2025-03-30 00:05:54', '2025-04-02 18:24:03'),
(2, 'ITEM002', 'Projector', '4K resolution projector', 4, 'pcs', 'Low Stock', 2, 'MOD456', 'Electronics', 'Warehouse B', NULL, 'Epson', 'AV Supplies Co.', 800.00, NULL, '2025-03-31 13:26:23', '2025-04-02 18:24:03'),
(3, 'ITEM003', 'Office Chair', 'Ergonomic office chair', 16, 'pcs', 'Available', 10, 'CHAIR789', 'Furniture', 'Warehouse C', NULL, 'Ikea', 'Furniture World', 150.00, NULL, '2025-04-04 11:12:46', '2025-04-02 18:24:03'),
(4, 'ITEM004', 'Laptop', 'High-performance laptop', 10, 'pcs', 'Available', 5, 'LPT1001', 'Electronics', 'Shelf A', '2026-12-31', 'TechBrand', 'Supplier A', 1200.50, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(5, 'ITEM005', 'Mouse', 'Wireless mouse', 50, 'pcs', 'Available', 20, 'MSE2001', 'Accessories', 'Shelf B', '2025-11-30', 'ClickTech', 'Supplier B', 25.99, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(6, 'ITEM006', 'Keyboard', 'Mechanical keyboard', 30, 'pcs', 'Available', 10, 'KB3001', 'Accessories', 'Shelf C', NULL, 'TypeMaster', 'Supplier C', 45.00, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(7, 'ITEM007', 'Monitor', '24-inch display', 15, 'pcs', 'Low Stock', 10, 'MN4001', 'Electronics', 'Shelf D', NULL, 'DisplayPro', 'Supplier A', 210.75, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(8, 'ITEM008', 'External Drive', '1TB external hard drive', 20, 'pcs', 'Available', 5, 'EXT5001', 'Storage', 'Shelf E', '2027-03-15', 'DriveKing', 'Supplier D', 95.20, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(9, 'ITEM009', 'Webcam', '1080p HD webcam', 12, 'pcs', 'Available', 4, 'WC6001', 'Accessories', 'Shelf F', NULL, 'ZoomCam', 'Supplier E', 60.99, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(10, 'ITEM010', 'USB Cable', 'Type-C to USB-A cable', 100, 'pcs', 'Available', 30, 'USB7001', 'Cables', 'Shelf G', NULL, 'CableFast', 'Supplier F', 9.99, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(11, 'ITEM011', 'Tablet', '10-inch tablet', 8, 'pcs', 'Low Stock', 3, 'TB8001', 'Electronics', 'Shelf H', '2025-08-20', 'TabSphere', 'Supplier G', 299.99, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(12, 'ITEM012', 'Printer', 'Wireless printer', 5, 'pcs', 'Out of Stock', 2, 'PRN9001', 'Office Equipment', 'Shelf I', NULL, 'PrintWell', 'Supplier H', 150.00, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(13, 'ITEM013', 'Scanner', 'Flatbed scanner', 7, 'pcs', 'Available', 2, 'SCN1001', 'Office Equipment', 'Shelf J', NULL, 'ScanXpert', 'Supplier I', 120.49, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(14, 'ITEM014', 'Smartphone', 'High-end smartphone', 25, 'pcs', 'Available', 10, 'SP1101', 'Mobile Devices', 'Shelf K', '2025-12-25', 'PhoneMax', 'Supplier J', 999.50, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(15, 'ITEM015', 'Power Bank', '10000mAh power bank', 40, 'pcs', 'Available', 15, 'PB1201', 'Accessories', 'Shelf L', '2027-01-01', 'ChargeGo', 'Supplier K', 29.99, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(16, 'ITEM016', 'Speaker', 'Bluetooth speaker', 18, 'pcs', 'Available', 5, 'SPK1301', 'Audio', 'Shelf M', NULL, 'SoundWave', 'Supplier L', 55.00, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(17, 'ITEM017', 'Headphones', 'Noise-cancelling headphones', 22, 'pcs', 'Available', 8, 'HP1401', 'Audio', 'Shelf N', '2026-10-10', 'AudioMaster', 'Supplier M', 150.75, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(18, 'ITEM018', 'Router', 'Wireless router', 10, 'pcs', 'Available', 4, 'RTR1501', 'Networking', 'Shelf O', NULL, 'NetConnect', 'Supplier N', 89.99, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(19, 'ITEM019', 'Switch', '8-port network switch', 6, 'pcs', 'Low Stock', 3, 'SW1601', 'Networking', 'Shelf P', NULL, 'SwitchGear', 'Supplier O', 45.20, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(20, 'ITEM020', 'Adapter', 'Universal power adapter', 35, 'pcs', 'Available', 10, 'AD1701', 'Accessories', 'Shelf Q', '2028-02-01', 'PowerAll', 'Supplier P', 15.99, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(21, 'ITEM021', 'Projector', 'HD projector', 4, 'pcs', 'Low Stock', 2, 'PJ1801', 'Office Equipment', 'Shelf R', NULL, 'ViewPro', 'Supplier Q', 499.00, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(22, 'ITEM022', 'Battery', 'Rechargeable AA batteries (4-pack)', 80, 'packs', 'Available', 25, 'BT1901', 'Power', 'Shelf S', '2026-06-30', 'BatteryLife', 'Supplier R', 12.49, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(23, 'ITEM023', 'Software License', 'Antivirus software (1 year)', 60, 'pcs', 'Available', 20, 'SL2001', 'Software', 'Shelf T', '2026-09-15', 'SecureShield', 'Supplier S', 19.99, NULL, '2025-04-01 21:18:07', '2025-04-02 18:24:03'),
(24, '', 'Candles', NULL, 100, NULL, 'Available', 0, NULL, 'Consumables', NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-03 02:08:21', '2025-04-03 02:08:21');

--
-- Triggers `items`
--
DELIMITER $$
CREATE TRIGGER `prevent_negative_quantity` BEFORE UPDATE ON `items` FOR EACH ROW BEGIN
    IF NEW.quantity < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Item quantity cannot be negative.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `item_returns`
--

CREATE TABLE `item_returns` (
  `return_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `item_condition` enum('Good','Damaged','Lost') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `new_item_requests`
--

CREATE TABLE `new_item_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_category` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `item_unit` varchar(50) NOT NULL,
  `purpose` text NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `ministry` enum('UCM','CWA','CHOIR','PWT','CYF') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `new_item_requests`
--

INSERT INTO `new_item_requests` (`request_id`, `user_id`, `item_name`, `item_category`, `quantity`, `item_unit`, `purpose`, `notes`, `status`, `request_date`, `ministry`) VALUES
(1, 2, 'Candles', 'Consumables', 100, '', 'asdasd', 'd', 'Rejected', '2025-03-29 22:17:23', 'CHOIR'),
(2, 2, 'Candles', 'Consumables', 100, '', 'asdasd', 'asdasd', 'Pending', '2025-03-29 22:17:25', 'CHOIR'),
(3, 2, 'Item Sample 1', 'Stationery', 12312, '', 'asdasd', 'asdasd', 'Pending', '2025-03-29 22:51:17', 'CHOIR'),
(4, 2, 'Monoblock Chair', 'Furniture', 200, 'Piece', 'Activity', 'N/A', 'Pending', '2025-04-01 21:13:59', 'CHOIR'),
(5, 2, 'Couch', 'Furniture', 5, 'Box', 'Sanctuary and Lounge', 'Choose Bright Colours', 'Pending', '2025-04-02 15:01:00', 'CHOIR'),
(6, 2, 'Bible (NIV)', 'Stationery', 30, 'Piece', 'For Activities', 'Small Bible', 'Pending', '2025-04-02 15:14:50', 'CHOIR'),
(7, 2, 'White Wine', 'Consumables', 10, 'Piece', 'Communal Services', 'Lowest Quality', 'Pending', '2025-04-02 18:48:30', 'CHOIR'),
(8, 5, 'Cardboard', 'Stationery', 4, 'Bundle', 'we', '', 'Pending', '2025-04-03 12:50:30', 'CYF');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `type` enum('Info','Warning','Error') DEFAULT 'Info'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `item_no` varchar(50) NOT NULL,
  `last_updated` datetime NOT NULL,
  `model_no` varchar(50) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `item_category` varchar(50) DEFAULT NULL,
  `item_location` varchar(50) DEFAULT NULL,
  `expiration` date DEFAULT NULL,
  `brand` varchar(50) DEFAULT NULL,
  `supplier` varchar(50) DEFAULT NULL,
  `price_per_item` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `status` enum('Available','Out of Stock','Low Stock') DEFAULT NULL,
  `reorder_point` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `request_date` datetime DEFAULT current_timestamp(),
  `request_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `returned_items`
--

CREATE TABLE `returned_items` (
  `return_id` int(11) NOT NULL,
  `borrow_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `item_condition` enum('Good','Damaged','Lost') NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_requests`
--

CREATE TABLE `return_requests` (
  `return_id` int(11) NOT NULL,
  `borrow_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `item_condition` enum('Good','Damaged','Lost') NOT NULL,
  `quantity` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `admin_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `item_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `status` enum('Pending','Completed','Failed') DEFAULT 'Pending',
  `item_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `user_id`, `action`, `details`, `created_at`, `item_name`, `quantity`, `status`, `item_id`) VALUES
(1, 2, 'Borrow', 'Borrowed 1 of item \'Projector\' in electronics category.', '2025-03-31 13:26:23', 'Projector', 1, 'Pending', 2),
(2, 2, 'New Item Request', '', '2025-04-02 15:14:50', 'Bible (NIV)', 30, 'Pending', NULL),
(3, 2, 'New Item Request', '', '2025-04-02 18:48:30', 'White Wine', 10, 'Pending', NULL),
(4, 5, 'New Item Request', 'Requested 4 of item \'Cardboard\' in Stationery category.', '2025-04-03 12:50:30', 'Cardboard', 4, 'Pending', 8),
(5, 5, 'Borrow', 'Borrowed 4 of item \'Office Chair\' in furniture category.', '2025-04-04 11:12:46', 'Office Chair', 4, 'Pending', 3);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('User','Administrator') NOT NULL,
  `ministry` enum('UCM','CWA','CHOIR','PWT','CYF') NOT NULL,
  `status` enum('Active','Deactivated') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deactivation_end` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `ministry`, `status`, `created_at`, `updated_at`, `deactivation_end`) VALUES
(1, 'Jay Neri Gasilao', 'jnanunuevo@gmail.com', '$2y$10$Q.x02m1V1W15gHKTgJuKveTjDmFNzwYrdEyq3yDKNOk4GuP/FTcXy', 'Administrator', 'CHOIR', 'Active', '2025-03-24 16:53:33', '2025-04-04 09:37:45', NULL),
(2, 'Susan Gasilao', 'susangasilao@yahoo.com', '$2y$10$OnO3uDHU8Gg4udHRomlsTOkWro7MUNAWXXL1s1KWuqygZLtoLsf16', 'User', 'CHOIR', 'Active', '2025-03-24 16:53:54', '2025-04-04 14:02:06', '2025-04-11 02:44:13'),
(4, 'John Michael Montes', 'johnmichaelmontes@gmail.com', '$2y$10$l/xrWyNSJGkWXRLo2iWpAehf5HWWfkuc.100CpxzhYu3Gjp2xUcAe', 'Administrator', 'PWT', 'Active', '2025-03-24 17:03:17', NULL, NULL),
(5, 'Nerizza Joy Mabazza', 'nerijoyanonuevo@gmail.com', '$2y$10$EZ9UDHzES2QxPA9NHe/xJuV1rbeRpWrnhqgthliWBxQN9s5MdhTNy', 'User', 'CYF', 'Active', '2025-03-24 19:31:24', '2025-04-04 11:35:04', NULL),
(6, 'Benito Mussolini', 'benitomussolini@gmail.com', '$2y$10$7zQYaKZi2mO2bgL2wT1poe2hI/ygl0Q8YJtYzV3o9GPsRaz8F2Fdu', 'User', 'UCM', 'Active', '2025-03-24 20:05:50', '2025-04-04 11:47:29', NULL),
(7, 'Peanut', 'peanutbutter@gmail.com', '$2y$10$qhyLUSurql.bMWZqZymFFeJxJ81oIMKqYVuilVaSaaLR0ezN5QBxm', 'Administrator', 'CWA', 'Active', '2025-03-24 20:48:33', NULL, NULL),
(10, 'SampleAccountName2', 'SampleAccountEmail2@mail.com', '$2y$10$NA3tbWKXYouOfVA0Zo3S...dulnLzOUZ2LBeQTShkbRNQTZEz5iA6', 'Administrator', 'PWT', 'Active', '2025-03-25 08:09:44', NULL, NULL),
(11, 'SampleAccountName100', 'SampleEmail100@gmail.com', '$2y$10$iNBZVU2zeamez.VTPZ12YuGCwOsEk8FVU0Prqs1.tXeloES326nMe', 'Administrator', 'UCM', 'Active', '2025-04-02 01:53:43', '2025-04-03 10:20:51', NULL),
(12, 'Sample11', 'sample111@gmail.com', '$2y$10$g/D7fK6O7jpn96UU5IpeiO8dbZph2dLlMvrKeeMZHEysoVzTHZTy2', 'User', 'PWT', 'Active', '2025-04-02 18:47:18', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_requests`
--

CREATE TABLE `user_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `admin_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_audit_logs_user_id` (`user_id`);

--
-- Indexes for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  ADD PRIMARY KEY (`borrow_id`),
  ADD KEY `fk_borrowed_items_request_id` (`request_id`),
  ADD KEY `fk_borrowed_items_user_id` (`user_id`),
  ADD KEY `fk_borrowed_items_item_id` (`item_id`);

--
-- Indexes for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  ADD PRIMARY KEY (`borrow_id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`item_id`,`status`),
  ADD KEY `fk_borrow_requests_item_id` (`item_id`),
  ADD KEY `idx_borrow_requests_status` (`status`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `item_no` (`item_no`),
  ADD KEY `idx_items_item_name` (`item_name`),
  ADD KEY `idx_items_status` (`status`),
  ADD KEY `idx_items_item_category` (`item_category`),
  ADD KEY `idx_items_item_location` (`item_location`);

--
-- Indexes for table `item_returns`
--
ALTER TABLE `item_returns`
  ADD PRIMARY KEY (`return_id`),
  ADD KEY `fk_item_returns_user_id` (`user_id`),
  ADD KEY `fk_item_returns_item_id` (`item_id`);

--
-- Indexes for table `new_item_requests`
--
ALTER TABLE `new_item_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_new_item_requests_user_id` (`user_id`),
  ADD KEY `idx_new_item_requests_status` (`status`),
  ADD KEY `idx_new_item_requests_ministry` (`ministry`),
  ADD KEY `idx_new_item_requests_request_date` (`request_date`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notifications_user_id` (`user_id`),
  ADD KEY `idx_notifications_is_read` (`is_read`),
  ADD KEY `idx_notifications_type` (`type`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `fk_reports_item_no` (`item_no`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `returned_items`
--
ALTER TABLE `returned_items`
  ADD PRIMARY KEY (`return_id`),
  ADD KEY `fk_returned_items_borrow_id` (`borrow_id`),
  ADD KEY `fk_returned_items_processed_by` (`processed_by`);

--
-- Indexes for table `return_requests`
--
ALTER TABLE `return_requests`
  ADD PRIMARY KEY (`return_id`),
  ADD UNIQUE KEY `borrow_id` (`borrow_id`),
  ADD KEY `idx_return_requests_status` (`status`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `fk_transactions_user_id` (`user_id`),
  ADD KEY `fk_transactions_item_id` (`item_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_requests`
--
ALTER TABLE `user_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_user_requests_user_id` (`user_id`),
  ADD KEY `idx_user_requests_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  MODIFY `borrow_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  MODIFY `borrow_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `item_returns`
--
ALTER TABLE `item_returns`
  MODIFY `return_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `new_item_requests`
--
ALTER TABLE `new_item_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `returned_items`
--
ALTER TABLE `returned_items`
  MODIFY `return_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `return_requests`
--
ALTER TABLE `return_requests`
  MODIFY `return_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_requests`
--
ALTER TABLE `user_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  ADD CONSTRAINT `fk_borrowed_items_item_id` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  ADD CONSTRAINT `fk_borrowed_items_request_id` FOREIGN KEY (`request_id`) REFERENCES `borrow_requests` (`borrow_id`),
  ADD CONSTRAINT `fk_borrowed_items_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `borrow_requests`
--
ALTER TABLE `borrow_requests`
  ADD CONSTRAINT `fk_borrow_requests_item_id` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  ADD CONSTRAINT `fk_borrow_requests_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `item_returns`
--
ALTER TABLE `item_returns`
  ADD CONSTRAINT `fk_item_returns_item_id` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  ADD CONSTRAINT `fk_item_returns_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `new_item_requests`
--
ALTER TABLE `new_item_requests`
  ADD CONSTRAINT `fk_new_item_requests_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_item_no` FOREIGN KEY (`item_no`) REFERENCES `items` (`item_no`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `returned_items`
--
ALTER TABLE `returned_items`
  ADD CONSTRAINT `fk_returned_items_borrow_id` FOREIGN KEY (`borrow_id`) REFERENCES `borrow_requests` (`borrow_id`),
  ADD CONSTRAINT `fk_returned_items_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `return_requests`
--
ALTER TABLE `return_requests`
  ADD CONSTRAINT `fk_return_requests_borrow_id` FOREIGN KEY (`borrow_id`) REFERENCES `borrow_requests` (`borrow_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_item_id` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`),
  ADD CONSTRAINT `fk_transactions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `user_requests`
--
ALTER TABLE `user_requests`
  ADD CONSTRAINT `fk_user_requests_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
