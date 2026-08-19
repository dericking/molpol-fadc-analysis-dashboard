-- The MariaDB image grants ALL PRIVILEGES to MARIADB_USER by default.
-- Pare that down to SELECT only, so the test container matches how
-- dbconnect-template.php is meant to be used against it.
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'readonly_user'@'%';
GRANT SELECT ON app_db.* TO 'readonly_user'@'%';
FLUSH PRIVILEGES;
