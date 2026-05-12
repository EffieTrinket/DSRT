-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 17, 2026 at 04:51 PM
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
-- Database: `disaster_relief_tracker`
--

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `barangay_id` int(11) NOT NULL,
  `barangay_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`barangay_id`, `barangay_name`) VALUES
(1, 'Arena Blanco'),
(2, 'Ayala'),
(3, 'Baliwasan'),
(4, 'Baluno'),
(5, 'Boalan'),
(6, 'Bolong'),
(7, 'Buenavista'),
(8, 'Bunguiao'),
(9, 'Busay'),
(10, 'Cabaluay'),
(11, 'Cabatangan'),
(12, 'Cacao'),
(13, 'Calabasa'),
(14, 'Calarian'),
(15, 'Camino Nuevo'),
(16, 'Campo Islam'),
(17, 'Canelar'),
(18, 'Capisan'),
(19, 'Cawit'),
(20, 'Culianan'),
(21, 'Curuan'),
(22, 'Daap'),
(23, 'Dita'),
(24, 'Divisoria'),
(25, 'Dulian (Bunguiao)'),
(26, 'Dulian (Upper Pasonanca)'),
(27, 'Guisao'),
(28, 'Guiwan'),
(29, 'Kasanyangan'),
(30, 'La Paz'),
(31, 'Labuan'),
(32, 'Lamisahan'),
(33, 'Landang Gua'),
(34, 'Landang Laum'),
(35, 'Lanzones'),
(36, 'Lapakan'),
(37, 'Latuan (Curuan)'),
(38, 'Licomo'),
(39, 'Limaong'),
(40, 'Limpapa'),
(41, 'Lubigan'),
(42, 'Lumayang'),
(43, 'Lumbangan'),
(44, 'Lunzuran'),
(45, 'Maasin'),
(46, 'Malagutay'),
(47, 'Mampang'),
(48, 'Manalipa'),
(49, 'Mangusu'),
(50, 'Manicahan'),
(51, 'Mariki'),
(52, 'Mercedes'),
(53, 'Muti'),
(54, 'Pamucutan'),
(55, 'Pangapuyan'),
(56, 'Panubigan'),
(57, 'Pasilmanta'),
(58, 'Pasobolong'),
(59, 'Pasonanca'),
(60, 'Patalon'),
(61, 'Putik'),
(62, 'Quiniput'),
(63, 'Recodo'),
(64, 'Rio Hondo'),
(65, 'Salaan'),
(66, 'San Jose Cawa-Cawa'),
(67, 'San Jose Gusu'),
(68, 'San Ramon'),
(69, 'San Roque'),
(70, 'Sangali'),
(71, 'Sibulao (Curuan)'),
(72, 'Sinubong'),
(73, 'Sinunuc'),
(74, 'Sta. Barbara'),
(75, 'Sta. Catalina'),
(76, 'Sta. Maria'),
(77, 'Sto. Niño'),
(78, 'Tagasilay'),
(79, 'Taguiti'),
(80, 'Talabaan'),
(81, 'Talisayan'),
(82, 'Talon-Talon'),
(83, 'Taluksangay'),
(84, 'Tetuan'),
(85, 'Tictabon'),
(86, 'Tictapul'),
(87, 'Tigbalabag'),
(88, 'Tolosa'),
(89, 'Tugbungan'),
(90, 'Tulungatung'),
(91, 'Tumaga'),
(92, 'Tumalutab'),
(93, 'Tumitus'),
(94, 'Victoria'),
(95, 'Vitali'),
(96, 'Zambowood'),
(97, 'Zone I'),
(98, 'Zone II'),
(99, 'Zone III'),
(100, 'Zone IV');

-- --------------------------------------------------------

--
-- Table structure for table `condition_statuses`
--

CREATE TABLE `condition_statuses` (
  `condition_id` int(11) NOT NULL,
  `condition_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `condition_statuses`
--

INSERT INTO `condition_statuses` (`condition_id`, `condition_name`) VALUES
(1, 'Safe'),
(2, 'Injured'),
(3, 'Evacuated'),
(4, 'Missing'),
(5, 'Casualty');

-- --------------------------------------------------------

--
-- Table structure for table `disaster_statuses`
--

CREATE TABLE `disaster_statuses` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disaster_statuses`
--

INSERT INTO `disaster_statuses` (`status_id`, `status_name`) VALUES
(1, 'Ongoing'),
(2, 'Resolved');

-- --------------------------------------------------------

--
-- Table structure for table `disasters`
--

CREATE TABLE `disasters` (
  `disaster_id` int(11) NOT NULL,
  `disaster_type_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status_id` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disasters`
--

INSERT INTO `disasters` (`disaster_id`, `disaster_type_id`, `date`, `status_id`) VALUES
(1, 1, '2026-04-10', 2),
(2, 2, '2026-04-12', 1),
(3, 3, '2026-04-15', 1);

-- --------------------------------------------------------

--
-- Table structure for table `disaster_impact`
--

CREATE TABLE `disaster_impact` (
  `impact_id` int(11) NOT NULL,
  `disaster_id` int(11) NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `affected_residents` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disaster_impact`
--

INSERT INTO `disaster_impact` (`impact_id`, `disaster_id`, `barangay_id`, `affected_residents`) VALUES
(1, 1, 15, 25),
(2, 2, 82, 100),
(3, 3, 91, 50);

-- --------------------------------------------------------

--
-- Table structure for table `disaster_types`
--

CREATE TABLE `disaster_types` (
  `disaster_type_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disaster_types`
--

INSERT INTO `disaster_types` (`disaster_type_id`, `type_name`) VALUES
(1, 'Fire'),
(2, 'Flood'),
(3, 'Typhoon / Strong Winds'),
(4, 'Earthquake'),
(5, 'Landslide');

-- --------------------------------------------------------

--
-- Table structure for table `distributions`
--

CREATE TABLE `distributions` (
  `distribution_id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `package_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `date_distributed` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `distributions`
--

INSERT INTO `distributions` (`distribution_id`, `resident_id`, `package_id`, `user_id`, `quantity`, `date_distributed`) VALUES
(1, 1, 1, 1, 2, '2026-04-11'),
(2, 2, 4, 1, 1, '2026-04-13'),
(3, 3, 5, 1, 3, '2026-04-16');

-- --------------------------------------------------------

--
-- Table structure for table `relief_packages`
--

CREATE TABLE `relief_packages` (
  `package_id` int(11) NOT NULL,
  `package_name` varchar(50) NOT NULL,
  `stock` int(11) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `relief_packages`
--

INSERT INTO `relief_packages` (`package_id`, `package_name`, `stock`, `description`) VALUES
(1, 'Package A', 100, 'food: noodles, can goods, rice, etc'),
(2, 'Package B', 100, 'Clothing'),
(3, 'Package C', 100, 'medicine, essential kits'),
(4, 'Package D', 100, 'mixture of Package A and Package B'),
(5, 'Package E', 100, 'mixture of Package A and C'),
(6, 'Package F', 100, 'mixture of Package A, B, and C');

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `resident_id` int(11) NOT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `address` varchar(150) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`resident_id`, `barangay_id`, `name`, `age`, `address`, `contact`) VALUES
(1, 15, 'Juan Dela Cruz', 45, 'Purok 1', '09123456789'),
(2, 82, 'Maria Santos', 32, 'Zone 3', '09987654321'),
(3, 91, 'Pedro Garcia', 50, 'Riverside', '09223334455');

-- --------------------------------------------------------

--
-- Table structure for table `resident_disasters`
--

CREATE TABLE `resident_disasters` (
  `resident_disaster_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `disaster_id` int(11) NOT NULL,
  `condition_id` int(11) DEFAULT NULL,
  `recorded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resident_disasters`
--

INSERT INTO `resident_disasters` (`resident_disaster_id`, `resident_id`, `disaster_id`, `condition_id`) VALUES
(1, 1, 1, 3),
(2, 2, 2, 1),
(3, 3, 3, 2);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`role_id`, `role_name`) VALUES
(1, 'admin'),
(2, 'staff');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role_id`) VALUES
(1, 'admin', 'admin@gmail.com', '1234', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`barangay_id`),
  ADD UNIQUE KEY `barangay_name` (`barangay_name`);

--
-- Indexes for table `condition_statuses`
--
ALTER TABLE `condition_statuses`
  ADD PRIMARY KEY (`condition_id`),
  ADD UNIQUE KEY `condition_name` (`condition_name`);

--
-- Indexes for table `disaster_statuses`
--
ALTER TABLE `disaster_statuses`
  ADD PRIMARY KEY (`status_id`),
  ADD UNIQUE KEY `status_name` (`status_name`);

--
-- Indexes for table `disasters`
--
ALTER TABLE `disasters`
  ADD PRIMARY KEY (`disaster_id`),
  ADD KEY `disaster_type_id` (`disaster_type_id`),
  ADD KEY `status_id` (`status_id`);

--
-- Indexes for table `disaster_impact`
--
ALTER TABLE `disaster_impact`
  ADD PRIMARY KEY (`impact_id`),
  ADD KEY `disaster_id` (`disaster_id`),
  ADD KEY `barangay_id` (`barangay_id`);

--
-- Indexes for table `disaster_types`
--
ALTER TABLE `disaster_types`
  ADD PRIMARY KEY (`disaster_type_id`);

--
-- Indexes for table `distributions`
--
ALTER TABLE `distributions`
  ADD PRIMARY KEY (`distribution_id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `fk_distribution_package` (`package_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `relief_packages`
--
ALTER TABLE `relief_packages`
  ADD PRIMARY KEY (`package_id`);

--
-- Indexes for table `resident_disasters`
--
ALTER TABLE `resident_disasters`
  ADD PRIMARY KEY (`resident_disaster_id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `disaster_id` (`disaster_id`),
  ADD KEY `condition_id` (`condition_id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`resident_id`),
  ADD KEY `barangay_id` (`barangay_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `barangay_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `condition_statuses`
--
ALTER TABLE `condition_statuses`
  MODIFY `condition_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `disaster_statuses`
--
ALTER TABLE `disaster_statuses`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `disasters`
--
ALTER TABLE `disasters`
  MODIFY `disaster_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disaster_impact`
--
ALTER TABLE `disaster_impact`
  MODIFY `impact_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disaster_types`
--
ALTER TABLE `disaster_types`
  MODIFY `disaster_type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `distributions`
--
ALTER TABLE `distributions`
  MODIFY `distribution_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `relief_packages`
--
ALTER TABLE `relief_packages`
  MODIFY `package_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resident_disasters`
--
ALTER TABLE `resident_disasters`
  MODIFY `resident_disaster_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `resident_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `disasters`
--
ALTER TABLE `disasters`
  ADD CONSTRAINT `disasters_ibfk_1` FOREIGN KEY (`disaster_type_id`) REFERENCES `disaster_types` (`disaster_type_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `disasters_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `disaster_statuses` (`status_id`) ON DELETE RESTRICT;

--
-- Constraints for table `disaster_impact`
--
ALTER TABLE `disaster_impact`
  ADD CONSTRAINT `disaster_impact_ibfk_1` FOREIGN KEY (`disaster_id`) REFERENCES `disasters` (`disaster_id`),
  ADD CONSTRAINT `disaster_impact_ibfk_2` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`barangay_id`);

--
-- Constraints for table `distributions`
--
ALTER TABLE `distributions`
  ADD CONSTRAINT `distributions_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_distribution_package` FOREIGN KEY (`package_id`) REFERENCES `relief_packages` (`package_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `distributions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `resident_disasters`
--
ALTER TABLE `resident_disasters`
  ADD CONSTRAINT `resident_disasters_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`resident_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resident_disasters_ibfk_2` FOREIGN KEY (`disaster_id`) REFERENCES `disasters` (`disaster_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resident_disasters_ibfk_3` FOREIGN KEY (`condition_id`) REFERENCES `condition_statuses` (`condition_id`) ON DELETE SET NULL;

--
-- Constraints for table `residents`
--
ALTER TABLE `residents`
  ADD CONSTRAINT `residents_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`barangay_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`role_id`) ON DELETE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
