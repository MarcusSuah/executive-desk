-- Executive Desk: MySQL bootstrap script.
-- Run once as root (or another admin account):
--   mysql -u root -p < database/setup/create-databases.sql
-- Then put the password you chose below into DB_PASSWORD in .env.

CREATE DATABASE IF NOT EXISTS executive_desk
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS executive_desk_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CHANGE THIS PASSWORD before running.
CREATE USER IF NOT EXISTS 'executive_desk'@'localhost' IDENTIFIED BY 'change-me-strong-password';

-- Least privilege: only what Laravel migrations and the application need.
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
    ON executive_desk.* TO 'executive_desk'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
    ON executive_desk_test.* TO 'executive_desk'@'localhost';

FLUSH PRIVILEGES;
