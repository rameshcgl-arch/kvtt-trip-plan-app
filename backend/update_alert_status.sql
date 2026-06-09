ALTER TABLE users
ADD COLUMN last_alert_sent DATETIME NULL,
ADD COLUMN last_alert_status ENUM('None', 'Sent', 'Received') DEFAULT 'None';
