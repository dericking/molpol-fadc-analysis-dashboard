FROM php:8.1-apache

# PDO MySQL for includes/dbconnect-template.php.
#
# rewrite and headers back the root .htaccess: rewrite blocks dot-paths, headers
# sets CSP/nosniff and strips X-Powered-By. Neither is enabled by default in
# php:8.1-apache. Without headers, the <IfModule mod_headers.c> block is skipped
# silently — the site still serves, so a local test would wrongly suggest the
# header rules do nothing.
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

# DocumentRoot is /var/www/html (project root is bind-mounted there by compose).
# Apache error/access logs go to stderr/stdout so `docker compose logs` works.
