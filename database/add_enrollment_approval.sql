-- Add approval status to enrollments table for admin control
ALTER TABLE `enrollments` ADD COLUMN `approval_status` enum('pending','approved','rejected') DEFAULT 'pending' AFTER `status`;

-- Add approved_by and approved_at columns for tracking (nullable for auto-approvals)
ALTER TABLE `enrollments` ADD COLUMN `approved_by` int(11) unsigned NULL AFTER `approval_status`;
ALTER TABLE `enrollments` ADD COLUMN `approved_at` datetime NULL AFTER `approved_by`;
ALTER TABLE `enrollments` ADD COLUMN `rejection_reason` text NULL AFTER `approved_at`;

-- Add foreign key for approved_by (allows NULL)
ALTER TABLE `enrollments` ADD CONSTRAINT `fk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
