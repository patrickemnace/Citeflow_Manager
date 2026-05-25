-- CiteFlow Manager Database Cleanup Script
-- Removes unused/rarely-used columns from tables
-- Run this in phpMyAdmin to optimize database size and performance

-- ============================================
-- STEP 1: Remove unused columns from BUSINESSES table
-- ============================================
-- Remove login_credentials (security risk, not used in UI)
ALTER TABLE businesses DROP COLUMN IF EXISTS login_credentials;

-- Remove payment_methods (rarely referenced)
ALTER TABLE businesses DROP COLUMN IF EXISTS payment_methods;

-- Remove services (redundant with description)
ALTER TABLE businesses DROP COLUMN IF EXISTS services;

-- ============================================
-- STEP 2: Remove unused columns from DIRECTORIES table
-- ============================================
-- Remove average_approval_days (not displayed in UI)
ALTER TABLE directories DROP COLUMN IF EXISTS average_approval_days;

-- ============================================
-- STEP 3: Add missing performance indexes
-- ============================================
-- Speed up business status filtering
ALTER TABLE businesses ADD INDEX IF NOT EXISTS idx_business_status (status);

-- Speed up task nap_status filtering
ALTER TABLE listing_tasks ADD INDEX IF NOT EXISTS idx_task_nap_status (nap_status);

-- Speed up activity logs sorting
ALTER TABLE activity_logs ADD INDEX IF NOT EXISTS idx_activity_created_at (created_at);

-- Speed up user presence lookups
ALTER TABLE user_presence ADD INDEX IF NOT EXISTS idx_user_presence_updated_at (updated_at);

-- ============================================
-- STEP 4: Clean orphaned activity logs (older than 90 days)
-- ============================================
DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- ============================================
-- STEP 5: Clean orphaned notifications (read and older than 30 days)
-- ============================================
DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- ============================================
-- STEP 6: Optimize tables to reclaim space
-- ============================================
OPTIMIZE TABLE users;
OPTIMIZE TABLE clients;
OPTIMIZE TABLE businesses;
OPTIMIZE TABLE directories;
OPTIMIZE TABLE listing_tasks;
OPTIMIZE TABLE submission_proofs;
OPTIMIZE TABLE notifications;
OPTIMIZE TABLE activity_logs;
OPTIMIZE TABLE user_presence;

-- ============================================
-- SUMMARY OF CHANGES:
-- ============================================
-- REMOVED COLUMNS:
--   - businesses.login_credentials (security risk)
--   - businesses.payment_methods (unused)
--   - businesses.services (redundant)
--   - directories.average_approval_days (unused)
--
-- ADDED INDEXES:
--   - idx_business_status (faster filtering)
--   - idx_task_nap_status (faster filtering)
--   - idx_activity_created_at (faster sorting)
--   - idx_user_presence_updated_at (faster updates)
--
-- CLEANED DATA:
--   - Deleted activity logs older than 90 days
--   - Deleted read notifications older than 30 days
--
-- RESULT: Faster queries + smaller database size
