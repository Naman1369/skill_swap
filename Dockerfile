# SkillSwap production image: PHP 8.3 + Apache.
# Settings come from environment variables (see docs/DEPLOY.md).
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod headers deflate expires

# Container-friendly defaults. The platform injects PORT at run time (Railway, Render, Fly.io...).
ENV APP_ENV=production \
    TRUST_PROXY=1 \
    PORT=8080

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-skillswap.ini
COPY docker/apache.conf /etc/apache2/conf-available/skillswap.conf

# Listen on $PORT instead of 80 and load our hardening config.
RUN sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && a2enconf skillswap

WORKDIR /var/www/html
COPY . .

# Not needed (and not wanted) at run time.
RUN rm -rf docker router.php docs README.md .htaccess \
 && sed -i 's/\r$//' /var/www/html/database/*.php \
 && chown -R www-data:www-data /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1:".getenv("PORT")."/health.php") ? 0 : 1);'

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
