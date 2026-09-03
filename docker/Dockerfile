# Local web image matches production’s PHP 5.4 line (prod is 5.4.16).
# Official php:5.4.45-apache still exists on Hub but uses Docker schema 1,
# which modern Docker (28+) will not pull. cyberpoison/php:5.4-apache is that
# same 5.4.45 Apache image re-pushed as OCI (see docker-library/php#1546).
FROM cyberpoison/php:5.4-apache

# PDO MySQL for includes/dbconnect-template.php.
#
# rewrite and headers back the root .htaccess: rewrite blocks dot-paths, headers
# sets CSP/nosniff and strips X-Powered-By. Neither is enabled by default in
# the PHP Apache image. Without headers, the <IfModule mod_headers.c> block is
# skipped silently — the site still serves, so a local test would wrongly
# suggest the header rules do nothing.
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

# DocumentRoot is /var/www/html (project root is bind-mounted there by compose).
# Apache error/access logs go to stderr/stdout so `docker compose logs` works.
