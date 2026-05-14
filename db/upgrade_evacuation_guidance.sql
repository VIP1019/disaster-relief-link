-- Run once on an existing ReliefLink DB to add evacuation guidance to reports.
USE relieflink;

ALTER TABLE disaster_reports
    ADD COLUMN suggested_evacuation_center_id INT NULL DEFAULT NULL AFTER geographic_sector_label,
    ADD COLUMN evacuation_suggestion_notes TEXT NULL DEFAULT NULL AFTER suggested_evacuation_center_id,
    ADD COLUMN evacuation_suggested_at TIMESTAMP NULL DEFAULT NULL AFTER evacuation_suggestion_notes,
    ADD COLUMN evacuation_confirmed_at TIMESTAMP NULL DEFAULT NULL AFTER evacuation_suggested_at,
    ADD COLUMN evacuation_confirmation_notes TEXT NULL DEFAULT NULL AFTER evacuation_confirmed_at,
    ADD INDEX idx_suggested_evacuation_center (suggested_evacuation_center_id),
    ADD CONSTRAINT fk_dr_suggested_evacuation FOREIGN KEY (suggested_evacuation_center_id) REFERENCES evacuation_centers(id) ON DELETE SET NULL;
