-- ========================================================
-- Clothes Shop Retail Management System Database Schema
-- Database: clothes_retail_db
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `cash_transactions`;
DROP TABLE IF EXISTS `cash_registers`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `expense_categories`;
DROP TABLE IF EXISTS `stock_adjustment_items`;
DROP TABLE IF EXISTS `stock_adjustments`;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `supplier_ledger`;
DROP TABLE IF EXISTS `customer_ledger`;
DROP TABLE IF EXISTS `sales_return_items`;
DROP TABLE IF EXISTS `sales_returns`;
DROP TABLE IF EXISTS `sale_payments`;
DROP TABLE IF EXISTS `sale_items`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `purchase_payments`;
DROP TABLE IF EXISTS `purchase_items`;
DROP TABLE IF EXISTS `purchases`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `colors`;
DROP TABLE IF EXISTS `sizes`;
DROP TABLE IF EXISTS `brands`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Settings Table
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'manager', 'cashier', 'inventory', 'accountant') NOT NULL DEFAULT 'cashier',
  `phone` VARCHAR(25) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Categories Table
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Brands Table
CREATE TABLE `brands` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Sizes Table
CREATE TABLE `sizes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `size_group` VARCHAR(50) NOT NULL DEFAULT 'Standard', -- Standard, Numeric, Kids, Shoes, Custom
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Colors Table
CREATE TABLE `colors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `hex_code` VARCHAR(20) NOT NULL DEFAULT '#000000',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Products Table (Master Item)
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `category_id` INT NULL,
  `brand_id` INT NULL,
  `season` VARCHAR(50) NOT NULL DEFAULT 'All Season',
  `rack_location` VARCHAR(50) NULL,
  `description` TEXT NULL,
  `alert_quantity` INT NOT NULL DEFAULT 5,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Product Variants Table (Size & Color combinations with unique stock & barcodes)
CREATE TABLE `product_variants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `size_id` INT NOT NULL,
  `color_id` INT NOT NULL,
  `sku` VARCHAR(100) NOT NULL UNIQUE,
  `barcode` VARCHAR(100) NOT NULL UNIQUE,
  `purchase_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`size_id`) REFERENCES `sizes`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`color_id`) REFERENCES `colors`(`id`) ON DELETE RESTRICT,
  UNIQUE KEY `product_size_color_uniq` (`product_id`, `size_id`, `color_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Suppliers Table
CREATE TABLE `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `company_name` VARCHAR(100) NULL,
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `opening_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `current_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Customers Table (Khata & Regular)
CREATE TABLE `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `address` TEXT NULL,
  `credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `opening_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `current_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `loyalty_points` INT NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Purchases Table
CREATE TABLE `purchases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_number` VARCHAR(50) NOT NULL UNIQUE,
  `supplier_id` INT NOT NULL,
  `purchase_date` DATE NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `freight_charges` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance_due` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('paid', 'partial', 'due') NOT NULL DEFAULT 'paid',
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'cash',
  `notes` TEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Purchase Items Table
CREATE TABLE `purchase_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `variant_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL,
  `total_cost` DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Cash Registers Table (Shifts)
CREATE TABLE `cash_registers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cashier_id` INT NOT NULL,
  `opening_time` DATETIME NOT NULL,
  `closing_time` DATETIME NULL,
  `opening_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `closing_balance` DECIMAL(12,2) NULL,
  `expected_cash` DECIMAL(12,2) NULL,
  `cash_difference` DECIMAL(12,2) NULL,
  `total_sales_cash` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_payments_received` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_expenses_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_supplier_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_refunds_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  `notes` TEXT NULL,
  FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Sales Table
CREATE TABLE `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT NULL,
  `sale_date` DATETIME NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `item_discount_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `bill_discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `net_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `change_returned` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance_due` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('paid', 'partial', 'due') NOT NULL DEFAULT 'paid',
  `payment_method` ENUM('cash', 'card', 'bank', 'jazzcash', 'easypaisa', 'split') NOT NULL DEFAULT 'cash',
  `sale_status` ENUM('completed', 'held', 'cancelled', 'returned') NOT NULL DEFAULT 'completed',
  `cashier_id` INT NULL,
  `register_id` INT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Sale Items Table
CREATE TABLE `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `variant_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(12,2) NOT NULL,
  `is_returned` TINYINT(1) NOT NULL DEFAULT 0,
  `returned_quantity` INT NOT NULL DEFAULT 0,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Sale Payments Table (Supports Split Payments)
CREATE TABLE `sale_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `reference_note` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Sales Returns Table (Returns & Exchanges)
CREATE TABLE `sales_returns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_number` VARCHAR(50) NOT NULL UNIQUE,
  `sale_id` INT NOT NULL,
  `customer_id` INT NULL,
  `return_date` DATETIME NOT NULL,
  `refund_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `refund_method` VARCHAR(50) NOT NULL DEFAULT 'cash',
  `return_type` ENUM('refund', 'exchange') NOT NULL DEFAULT 'refund',
  `exchange_sale_id` INT NULL,
  `reason` VARCHAR(255) NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. Sales Return Items Table
CREATE TABLE `sales_return_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_id` INT NOT NULL,
  `sale_item_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `variant_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `refund_subtotal` DECIMAL(12,2) NOT NULL,
  `condition_type` ENUM('restock', 'defective') NOT NULL DEFAULT 'restock',
  FOREIGN KEY (`return_id`) REFERENCES `sales_returns`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. Customer Ledger (Khata)
CREATE TABLE `customer_ledger` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `entry_date` DATETIME NOT NULL,
  `transaction_type` ENUM('opening', 'sale', 'payment', 'return_refund', 'exchange_diff', 'adjustment') NOT NULL,
  `reference_id` INT NULL,
  `reference_no` VARCHAR(50) NULL,
  `debit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- Money customer owes (increases balance)
  `credit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- Money customer paid (decreases balance)
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- Running balance
  `notes` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. Supplier Ledger
CREATE TABLE `supplier_ledger` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT NOT NULL,
  `entry_date` DATETIME NOT NULL,
  `transaction_type` ENUM('opening', 'purchase', 'payment', 'return', 'adjustment') NOT NULL,
  `reference_id` INT NULL,
  `reference_no` VARCHAR(50) NULL,
  `debit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- Payout to supplier (decreases payable)
  `credit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- Bill from supplier (increases payable)
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- Running balance
  `notes` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. Stock Movements Table (Comprehensive Audit Trail)
CREATE TABLE `stock_movements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `variant_id` INT NOT NULL,
  `movement_date` DATETIME NOT NULL,
  `movement_type` ENUM('opening', 'purchase', 'sale', 'sale_return', 'purchase_return', 'exchange_in', 'exchange_out', 'adjustment_plus', 'adjustment_minus', 'damage') NOT NULL,
  `reference_no` VARCHAR(50) NULL,
  `qty_in` INT NOT NULL DEFAULT 0,
  `qty_out` INT NOT NULL DEFAULT 0,
  `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` VARCHAR(255) NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 22. Stock Adjustments Table
CREATE TABLE `stock_adjustments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `adjustment_number` VARCHAR(50) NOT NULL UNIQUE,
  `adjustment_date` DATE NOT NULL,
  `reason` ENUM('Damaged', 'Defective', 'Lost', 'Found', 'Physical Count', 'Other') NOT NULL,
  `notes` TEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 23. Stock Adjustment Items
CREATE TABLE `stock_adjustment_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `adjustment_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `variant_id` INT NOT NULL,
  `adjustment_type` ENUM('add', 'subtract') NOT NULL,
  `quantity` INT NOT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`adjustment_id`) REFERENCES `stock_adjustments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 24. Expense Categories
CREATE TABLE `expense_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 25. Expenses Table
CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `expense_date` DATE NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL DEFAULT 'cash',
  `reference_no` VARCHAR(100) NULL,
  `register_id` INT NULL,
  `notes` TEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 26. Audit Logs Table
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- SEED DATA INSERTION
-- ========================================================

-- Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('shop_name', 'Al-Madina Fashion & Garments'),
('tagline', 'Premium Clothing Retail & Wholesale'),
('phone', '+92 300 1234567'),
('email', 'info@almadinafashion.pk'),
('address', 'Shop #12-15, Main Anarkali Bazaar, Lahore, Pakistan'),
('currency', 'Rs.'),
('tax_percent', '0'),
('invoice_prefix', 'AMF-'),
('footer_text', 'Thank you for shopping with us! Exchange within 7 days with original invoice.'),
('thermal_width', '80mm'),
('low_stock_threshold', '5'),
('return_validity_days', '7');

-- Users (admin: admin123, cashier: cashier123)
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `phone`, `status`) VALUES
('admin', '$2y$10$MH.TH0/udpMYC//1w0kgbeb35dDagaYAJ32G44QhFFEo03d5uYNJG', 'Tariq Mehmood (Proprietor)', 'admin', '+92 300 1112233', 'active'),
('cashier', '$2y$10$NuxGmONj2Pzfndk4bCWtreCotO4Kol9Qn4sLbyyzay2Kdb2Gbtcuy', 'Hamza Ali (Cashier)', 'cashier', '+92 321 4445566', 'active'),
('manager', '$2y$10$MH.TH0/udpMYC//1w0kgbeb35dDagaYAJ32G44QhFFEo03d5uYNJG', 'Kashif Raza (Floor Manager)', 'manager', '+92 333 7778899', 'active'),
('accountant', '$2y$10$MH.TH0/udpMYC//1w0kgbeb35dDagaYAJ32G44QhFFEo03d5uYNJG', 'Naveed Akhtar (Accountant)', 'accountant', '+92 345 9998877', 'active');

-- Categories
INSERT INTO `categories` (`name`, `slug`, `description`) VALUES
('Men\'s Wear', 'mens-wear', 'Men formal, casual and ethnic clothing'),
('Women\'s Wear', 'womens-wear', 'Women pret, kurtis and unstitched suits'),
('Kids\' Wear', 'kids-wear', 'Boys and girls apparel'),
('Shalwar Kameez', 'shalwar-kameez', 'Traditional ethnic suits and kurtas'),
('Shirts & T-Shirts', 'shirts-tshirts', 'Casual tees, polo shirts and button downs'),
('Jeans & Trousers', 'jeans-trousers', 'Denim jeans, cotton pants and chinos'),
('Kurtis & Stitched', 'kurtis-stitched', 'Ready to wear ladies kurtis and 2-piece sets'),
('Jackets & Hoodies', 'jackets-hoodies', 'Winter outer wear, fleece hoodies and coats');

-- Brands
INSERT INTO `brands` (`name`) VALUES
('Al-Karam Studio'),
('Gul Ahmed'),
('Junaid Jamshed (J.)'),
('Outfitters'),
('Diners'),
('Bonanza Satrangi'),
('Levis Standard'),
('Royal Garments');

-- Sizes
INSERT INTO `sizes` (`name`, `size_group`, `sort_order`) VALUES
('S', 'Standard', 1),
('M', 'Standard', 2),
('L', 'Standard', 3),
('XL', 'Standard', 4),
('XXL', 'Standard', 5),
('30', 'Numeric', 10),
('32', 'Numeric', 11),
('34', 'Numeric', 12),
('36', 'Numeric', 13),
('38', 'Numeric', 14),
('4-5Y', 'Kids', 20),
('6-7Y', 'Kids', 21),
('8-9Y', 'Kids', 22);

-- Colors
INSERT INTO `colors` (`name`, `hex_code`) VALUES
('Black', '#1a1a1a'),
('White', '#ffffff'),
('Navy Blue', '#001f3f'),
('Royal Blue', '#4169e1'),
('Maroon', '#800000'),
('Emerald Green', '#097969'),
('Heather Grey', '#808080'),
('Beige / Khaki', '#c8ad7f'),
('Mustard', '#e1ad01');

-- Expense Categories
INSERT INTO `expense_categories` (`name`, `description`) VALUES
('Shop Rent', 'Monthly premises rental'),
('Staff Salaries', 'Employee payroll & bonuses'),
('Electricity & Generator', 'WAPDA bill and fuel expenses'),
('Packaging & Shopping Bags', 'Printed boutique shopping bags & hangers'),
('Tea & Refreshments', 'Daily customer and staff refreshments'),
('Maintenance & Repairs', 'Lighting, AC, shop fixtures'),
('Marketing & Social Media', 'Local advertisements and flyers'),
('Miscellaneous', 'Other incidental store expenses');

-- Suppliers
INSERT INTO `suppliers` (`name`, `company_name`, `phone`, `email`, `address`, `opening_balance`, `current_balance`) VALUES
('Haji Farooq Fabrics', 'Farooq Textile Mills Faisalabad', '+92 300 8654321', 'sales@farooqtextiles.com', 'Clock Tower Cloth Market, Faisalabad', 0.00, 45000.00),
('Usman Garments Wholesale', 'Usman Apparel House Lahore', '+92 321 9876543', 'usman.apparel@gmail.com', 'Azam Cloth Market, Lahore', 0.00, 28000.00),
('Karachi Denim Hub', 'Denim Masters International', '+92 333 2223344', 'karachi.denim@yahoo.com', 'Bolton Market, Karachi', 0.00, 0.00);

-- Customers
INSERT INTO `customers` (`customer_code`, `name`, `phone`, `address`, `credit_limit`, `opening_balance`, `current_balance`, `loyalty_points`) VALUES
('CUST-001', 'Walk-in Customer (Cash)', '+92 300 0000000', 'Counter Sales', 0.00, 0.00, 0.00, 0),
('CUST-002', 'Chaudhry Bilal Aslam', '+92 301 5556677', 'House 45, Model Town, Lahore', 50000.00, 0.00, 12500.00, 150),
('CUST-003', 'Mrs. Samina Waqas', '+92 322 8889900', 'DHA Phase 5, Lahore', 40000.00, 0.00, 6800.00, 95),
('CUST-004', 'Doctor Zafar Iqbal', '+92 333 4443322', 'Johar Town, Lahore', 30000.00, 0.00, 0.00, 40);

-- Initial Products
INSERT INTO `products` (`id`, `product_code`, `name`, `category_id`, `brand_id`, `season`, `rack_location`, `description`, `alert_quantity`) VALUES
(1, 'PRD-1001', 'Classic Polo Cotton T-Shirt', 5, 4, 'Summer 2026', 'Rack A-1', '100% pique combed cotton breathable polo shirt with ribbed collar', 5),
(2, 'PRD-1002', 'Men Premium Slim Stretch Jeans', 6, 7, 'All Season', 'Rack B-3', 'High quality 12oz denim with 2% elastane for comfort fit', 6),
(3, 'PRD-1003', 'Embroidered Lawn Kurti (Stitched)', 7, 2, 'Summer 2026', 'Rack C-2', 'Digital printed and neck-embroidered breathable lawn kurti', 4),
(4, 'PRD-1004', 'Men Formal Cotton Shalwar Kameez', 4, 3, 'Eid Collection', 'Rack D-1', 'Premium Egyptian cotton fabric with classic cuff sleeves and band collar', 5),
(5, 'PRD-1005', 'Fleece Zip-up Winter Hoodie', 8, 4, 'Winter Collection', 'Rack E-2', '320 GSM heavy fleece hoodie with kangaroo pockets and lined hood', 3);

-- Product Variants with Matrix data (Size & Color)
-- Product 1: Classic Polo T-Shirt (Sizes: S, M, L, XL; Colors: Black, Navy Blue, White)
INSERT INTO `product_variants` (`id`, `product_id`, `size_id`, `color_id`, `sku`, `barcode`, `purchase_price`, `selling_price`, `wholesale_price`, `stock_quantity`) VALUES
(1, 1, 1, 1, 'POLO-S-BLK', '890100101', 850.00, 1450.00, 1200.00, 12),
(2, 1, 2, 1, 'POLO-M-BLK', '890100102', 850.00, 1450.00, 1200.00, 20),
(3, 1, 3, 1, 'POLO-L-BLK', '890100103', 850.00, 1450.00, 1200.00, 18),
(4, 1, 4, 1, 'POLO-XL-BLK', '890100104', 850.00, 1450.00, 1200.00, 8),

(5, 1, 1, 3, 'POLO-S-NVY', '890100105', 850.00, 1450.00, 1200.00, 10),
(6, 1, 2, 3, 'POLO-M-NVY', '890100106', 850.00, 1450.00, 1200.00, 22),
(7, 1, 3, 3, 'POLO-L-NVY', '890100107', 850.00, 1450.00, 1200.00, 15),
(8, 1, 4, 3, 'POLO-XL-NVY', '890100108', 850.00, 1450.00, 1200.00, 6),

(9, 1, 2, 2, 'POLO-M-WHT', '890100109', 850.00, 1450.00, 1200.00, 14),
(10, 1, 3, 2, 'POLO-L-WHT', '890100110', 850.00, 1450.00, 1200.00, 16);

-- Product 2: Men Slim Stretch Jeans (Sizes: 30, 32, 34, 36; Colors: Navy Blue, Black)
INSERT INTO `product_variants` (`id`, `product_id`, `size_id`, `color_id`, `sku`, `barcode`, `purchase_price`, `selling_price`, `wholesale_price`, `stock_quantity`) VALUES
(11, 2, 6, 3, 'JNS-30-NVY', '890200201', 1600.00, 2800.00, 2400.00, 8),
(12, 2, 7, 3, 'JNS-32-NVY', '890200202', 1600.00, 2800.00, 2400.00, 15),
(13, 2, 8, 3, 'JNS-34-NVY', '890200203', 1600.00, 2800.00, 2400.00, 14),
(14, 2, 9, 3, 'JNS-36-NVY', '890200204', 1600.00, 2800.00, 2400.00, 5),

(15, 2, 7, 1, 'JNS-32-BLK', '890200205', 1600.00, 2800.00, 2400.00, 12),
(16, 2, 8, 1, 'JNS-34-BLK', '890200206', 1600.00, 2800.00, 2400.00, 10);

-- Product 3: Embroidered Lawn Kurti (Sizes: S, M, L; Colors: Maroon, Emerald Green, Mustard)
INSERT INTO `product_variants` (`id`, `product_id`, `size_id`, `color_id`, `sku`, `barcode`, `purchase_price`, `selling_price`, `wholesale_price`, `stock_quantity`) VALUES
(17, 3, 1, 5, 'KRT-S-MRN', '890300301', 1250.00, 2250.00, 1900.00, 7),
(18, 3, 2, 5, 'KRT-M-MRN', '890300302', 1250.00, 2250.00, 1900.00, 11),
(19, 3, 3, 5, 'KRT-L-MRN', '890300303', 1250.00, 2250.00, 1900.00, 9),

(20, 3, 2, 6, 'KRT-M-GRN', '890300304', 1250.00, 2250.00, 1900.00, 8),
(21, 3, 3, 6, 'KRT-L-GRN', '890300305', 1250.00, 2250.00, 1900.00, 6),
(22, 3, 2, 9, 'KRT-M-MST', '890300306', 1250.00, 2250.00, 1900.00, 10);

-- Product 4: Men Formal Shalwar Kameez (Sizes: S, M, L, XL; Colors: White, Beige)
INSERT INTO `product_variants` (`id`, `product_id`, `size_id`, `color_id`, `sku`, `barcode`, `purchase_price`, `selling_price`, `wholesale_price`, `stock_quantity`) VALUES
(23, 4, 1, 2, 'SK-S-WHT', '890400401', 2500.00, 4200.00, 3600.00, 6),
(24, 4, 2, 2, 'SK-M-WHT', '890400402', 2500.00, 4200.00, 3600.00, 14),
(25, 4, 3, 2, 'SK-L-WHT', '890400403', 2500.00, 4200.00, 3600.00, 12),
(26, 4, 4, 2, 'SK-XL-WHT', '890400404', 2500.00, 4200.00, 3600.00, 5),

(27, 4, 2, 8, 'SK-M-BGE', '890400405', 2500.00, 4200.00, 3600.00, 9),
(28, 4, 3, 8, 'SK-L-BGE', '890400406', 2500.00, 4200.00, 3600.00, 7);

-- Product 5: Fleece Winter Hoodie (Sizes: M, L, XL; Colors: Black, Heather Grey)
INSERT INTO `product_variants` (`id`, `product_id`, `size_id`, `color_id`, `sku`, `barcode`, `purchase_price`, `selling_price`, `wholesale_price`, `stock_quantity`) VALUES
(29, 5, 2, 1, 'HOD-M-BLK', '890500501', 1900.00, 3400.00, 2900.00, 8),
(30, 5, 3, 1, 'HOD-L-BLK', '890500502', 1900.00, 3400.00, 2900.00, 10),
(31, 5, 4, 1, 'HOD-XL-BLK', '890500503', 1900.00, 3400.00, 2900.00, 3),

(32, 5, 2, 7, 'HOD-M-GRY', '890500504', 1900.00, 3400.00, 2900.00, 7),
(33, 5, 3, 7, 'HOD-L-GRY', '890500505', 1900.00, 3400.00, 2900.00, 6);

-- Sample Stock Movements (Opening)
INSERT INTO `stock_movements` (`product_id`, `variant_id`, `movement_date`, `movement_type`, `reference_no`, `qty_in`, `qty_out`, `unit_cost`, `notes`)
SELECT `product_id`, `id`, NOW(), 'opening', 'OPENING-STOCK', `stock_quantity`, 0, `purchase_price`, 'Initial Inventory Setup'
FROM `product_variants`;

-- Customer Initial Ledger Entries
INSERT INTO `customer_ledger` (`customer_id`, `entry_date`, `transaction_type`, `reference_no`, `debit`, `credit`, `balance`, `notes`) VALUES
(2, DATE_SUB(NOW(), INTERVAL 5 DAY), 'opening', 'INIT-002', 12500.00, 0.00, 12500.00, 'Previous Month Eid Clothes Balance'),
(3, DATE_SUB(NOW(), INTERVAL 3 DAY), 'opening', 'INIT-003', 6800.00, 0.00, 6800.00, 'Ladies Stitched Suits Due Balance');

-- Supplier Initial Ledger Entries
INSERT INTO `supplier_ledger` (`supplier_id`, `entry_date`, `transaction_type`, `reference_no`, `debit`, `credit`, `balance`, `notes`) VALUES
(1, DATE_SUB(NOW(), INTERVAL 10 DAY), 'opening', 'SUP-OP-01', 0.00, 45000.00, 45000.00, 'Summer Fabric Consignment Balance'),
(2, DATE_SUB(NOW(), INTERVAL 7 DAY), 'opening', 'SUP-OP-02', 0.00, 28000.00, 28000.00, 'Ready-to-wear Kurti Batch Invoice #8841');

-- Sample Completed Sales for reporting & charts
INSERT INTO `sales` (`id`, `invoice_number`, `customer_id`, `sale_date`, `subtotal`, `item_discount_total`, `bill_discount`, `tax_amount`, `net_total`, `paid_amount`, `change_returned`, `balance_due`, `payment_status`, `payment_method`, `sale_status`, `cashier_id`) VALUES
(1, 'AMF-10001', 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 5700.00, 0.00, 200.00, 0.00, 5500.00, 6000.00, 500.00, 0.00, 'paid', 'cash', 'completed', 2),
(2, 'AMF-10002', 2, DATE_SUB(NOW(), INTERVAL 1 DAY), 7000.00, 0.00, 0.00, 0.00, 7000.00, 3000.00, 0.00, 4000.00, 'partial', 'cash', 'completed', 2),
(3, 'AMF-10003', 3, NOW(), 4500.00, 0.00, 0.00, 0.00, 4500.00, 4500.00, 0.00, 0.00, 'paid', 'jazzcash', 'completed', 2);

-- Sample Sale Items
INSERT INTO `sale_items` (`sale_id`, `product_id`, `variant_id`, `quantity`, `unit_price`, `cost_price`, `discount`, `total_price`) VALUES
(1, 1, 2, 2, 1450.00, 850.00, 0.00, 2900.00), -- Polo M Blk
(1, 2, 12, 1, 2800.00, 1600.00, 0.00, 2800.00), -- Jeans 32 Nvy
(2, 4, 24, 1, 4200.00, 2500.00, 0.00, 4200.00), -- Shalwar Kameez M Wht
(2, 2, 15, 1, 2800.00, 1600.00, 0.00, 2800.00), -- Jeans 32 Blk
(3, 3, 18, 2, 2250.00, 1250.00, 0.00, 4500.00); -- Kurti M Mrn

-- Sample Sale Payments
INSERT INTO `sale_payments` (`sale_id`, `payment_method`, `amount`, `reference_note`) VALUES
(1, 'cash', 5500.00, 'Received Rs 6000, change Rs 500'),
(2, 'cash', 3000.00, 'Partial cash, remaining 4000 added to Khata'),
(3, 'jazzcash', 4500.00, 'Trx ID: JC-9921448');

-- Sample Expenses
INSERT INTO `expenses` (`category_id`, `expense_date`, `title`, `amount`, `payment_method`, `reference_no`, `notes`, `created_by`) VALUES
(3, DATE_SUB(NOW(), INTERVAL 4 DAY), 'Shop Electricity Bill (LESCO)', 14500.00, 'bank', 'LESCO-6612', 'Paid via online banking', 1),
(4, DATE_SUB(NOW(), INTERVAL 3 DAY), '1000 Custom Printed Boutique Bags', 8500.00, 'cash', 'PKG-114', 'Premium matte gold printed bags', 1),
(5, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Staff & Guest Tea/Bakery Refreshment', 950.00, 'cash', 'TEA-21', 'Daily refreshments', 2),
(6, NOW(), 'Changing Room LED Light Replacement', 1200.00, 'cash', 'ELEC-90', 'Replaced 2 LED fixtures', 2);
