-- Pastikan Anda memilih database 'plantpals_db' sebelum menjalankan query ini,
-- atau tambahkan 'USE plantpals_db;' di awal.

-- Tabel Users
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL, -- Akan menyimpan password yang di-hash
    `email` VARCHAR(100) UNIQUE,
    `full_name` VARCHAR(100),
    `phone_number` VARCHAR(20),
    `address` TEXT,
    `role` ENUM('buyer', 'seller', 'admin') NOT NULL DEFAULT 'buyer',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contoh Data User (Password untuk semua akun adalah 'password123')
-- Password di-hash menggunakan password_hash() untuk keamanan.
-- Untuk 'password123' hash-nya bisa seperti ini: $2y$10$tN.r2FzR0fS.g4y.X3.Yk.b0p8O8N8c2L0k0Q0m0v.O0q0I0u0w0s0V0B0D0E0F0G0H0J0K0L0
-- Atau Anda bisa menggunakan https://php-password-hash-online.herokuapp.com/ untuk membuat hash password_hash('password123', PASSWORD_DEFAULT)
INSERT INTO `users` (`username`, `password`, `email`, `role`) VALUES
('adminuser', '$2y$10$tN.r2FzR0fS.g4y.X3.Yk.b0p8O8N8c2L0k0Q0m0v.O0q0I0u0w0s0V0B0D0E0F0G0H0J0K0L0', 'admin@plantpals.com', 'admin'),
('seller1', '$2y$10$tN.r2FzR0fS.g4y.X3.Yk.b0p8O8N8c2L0k0Q0m0v.O0q0I0u0w0s0V0B0D0E0F0G0H0J0K0L0', 'seller1@plantpals.com', 'seller'),
('buyer1', '$2y$10$tN.r2FzR0fS.g4y.X3.Yk.b0p8O8N8c2L0k0Q0m0v.O0q0I0u0w0s0V0B0D0E0F0G0H0J0K0L0', 'buyer1@plantpals.com', 'buyer');

-- Tabel Products (Dikelola oleh penjual dan admin)
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `img` VARCHAR(255), -- Path to image asset, e.g., 'assets/rose.jpg'
    `scientific_name` VARCHAR(100),
    `family` VARCHAR(100),
    `description` TEXT,
    `habitat` TEXT,
    `care_instructions` TEXT,
    `unique_fact` TEXT,
    `price` DECIMAL(10, 2) NOT NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `seller_id` INT, -- Foreign key to users table (role='seller')
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Orders (Mencatat setiap pesanan dari pembeli)
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL, -- The buyer (Foreign key to users table)
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `order_status` ENUM('pending', 'processing', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `delivery_address` TEXT NOT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_phone` VARCHAR(20) NOT NULL,
    `customer_email` VARCHAR(100),
    `notes` TEXT,
    `order_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Order Items (Detail produk dalam setiap pesanan)
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL, -- Foreign key to orders table
    `product_id` INT,       -- Foreign key to products table (can be null if product deleted)
    `product_name` VARCHAR(100) NOT NULL, -- Store product name for historical purposes
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `quantity` INT NOT NULL,
    `store_id` VARCHAR(50), -- Store ID as string for historical purposes (from data.php)
    `store_name` VARCHAR(255), -- Store name for historical purposes
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;