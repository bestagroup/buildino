-- MySQL/MariaDB equivalent of:
-- 2026_11_02_000001_add_workspace_structure_context
-- Select the application's database in phpMyAdmin before importing this file.
-- Export a database backup first. No USE, DROP, DELETE or data conversion occurs.
-- Run the whole file; sequential re-imports are safe.

SET @buildino_patch_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'complexes' AND column_name = 'workspace_type'),
    'DO 0',
    'ALTER TABLE `complexes` ADD COLUMN `workspace_type` VARCHAR(30) NULL'
);
PREPARE buildino_patch_statement FROM @buildino_patch_sql;
EXECUTE buildino_patch_statement;
DEALLOCATE PREPARE buildino_patch_statement;

SET @buildino_patch_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'buildings' AND column_name = 'structure_type'),
    'DO 0',
    'ALTER TABLE `buildings` ADD COLUMN `structure_type` VARCHAR(30) NULL'
);
PREPARE buildino_patch_statement FROM @buildino_patch_sql;
EXECUTE buildino_patch_statement;
DEALLOCATE PREPARE buildino_patch_statement;

SET @buildino_patch_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'buildings' AND column_name = 'setup_completed_at'),
    'DO 0',
    'ALTER TABLE `buildings` ADD COLUMN `setup_completed_at` TIMESTAMP NULL'
);
PREPARE buildino_patch_statement FROM @buildino_patch_sql;
EXECUTE buildino_patch_statement;
DEALLOCATE PREPARE buildino_patch_statement;

CREATE TABLE IF NOT EXISTS `building_complex_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `building_id` BIGINT UNSIGNED NOT NULL,
    `complex_id` BIGINT UNSIGNED NOT NULL,
    `requested_by` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `reviewed_by` BIGINT UNSIGNED NULL,
    `reviewed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `building_complex_requests_complex_id_status_index` (`complex_id`, `status`),
    CONSTRAINT `building_complex_requests_building_id_foreign`
        FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `building_complex_requests_complex_id_foreign`
        FOREIGN KEY (`complex_id`) REFERENCES `complexes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `building_complex_requests_requested_by_foreign`
        FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
    CONSTRAINT `building_complex_requests_reviewed_by_foreign`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Do not mark an existing, incomplete schema as migrated.
SET @buildino_patch_ready = (
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE()
        AND table_name = 'complexes' AND column_name = 'workspace_type') = 1
    AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE()
        AND table_name = 'buildings' AND column_name IN ('structure_type', 'setup_completed_at')) = 2
    AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE()
        AND table_name = 'building_complex_requests'
        AND column_name IN ('id', 'building_id', 'complex_id', 'requested_by', 'status', 'reviewed_by', 'reviewed_at', 'created_at', 'updated_at')) = 9
    AND (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE()
        AND table_name = 'building_complex_requests' AND constraint_type = 'FOREIGN KEY') = 4
    AND (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE()
        AND table_name = 'building_complex_requests'
        AND index_name = 'building_complex_requests_complex_id_status_index') = 2
);
SET @buildino_patch_batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_02_000001_add_workspace_structure_context', @buildino_patch_batch
FROM DUAL
WHERE @buildino_patch_ready = 1 AND NOT EXISTS (
    SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_11_02_000001_add_workspace_structure_context'
);

SELECT DATABASE() AS `database_name`,
    IF(@buildino_patch_ready = 1, 'READY', 'INCOMPLETE') AS `onboarding_schema`,
    (SELECT COUNT(*) FROM `migrations`
        WHERE `migration` = '2026_11_02_000001_add_workspace_structure_context') AS `migration_records`;
