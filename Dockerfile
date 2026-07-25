# Base image
FROM php:8.6-rc-apache

# Update apt and install dependencies in one layer
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       ca-certificates \
       curl \
       git \
       libonig-dev \
       libzip-dev \
       unzip \
       zip \
    # Enable Apache modules
    && a2enmod rewrite \
    # Install PHP extensions
    && docker-php-ext-install -j$(nproc) \
       mbstring \
       mysqli \
       sockets \
       shmop \
       zip \
    # Clean up
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
    # Install Mailpit (sendmail-compatible shim for email testing, replaces mhsendmail)
    && ARCH="$(dpkg --print-architecture)" \
    && curl --proto '=https' --tlsv1.2 -sSL "https://github.com/axllent/mailpit/releases/download/v1.30.5/mailpit-linux-${ARCH}.tar.gz" -o mailpit.tar.gz \
    && tar -C /usr/local/bin -xzf mailpit.tar.gz mailpit \
    && rm mailpit.tar.gz \
    && chmod +x /usr/local/bin/mailpit \
    # Keep the old mhsendmail path working as a compatibility shim (remove after config migration)
    && printf '#!/bin/sh\nexec /usr/local/bin/mailpit sendmail "$@"\n' > /usr/local/bin/mhsendmail \
    && chmod +x /usr/local/bin/mhsendmail

# Fail fast if required extensions are missing
RUN php -m | grep -qi mbstring \
    && php -m | grep -qi mysqli \
    && php -m | grep -qi sockets \
    && php -m | grep -qi shmop \
    && php -m | grep -qi zip

# Configure PHP base settings (can be overridden by mounted config)
RUN php -i | grep "Configuration File" || true \
    && php -i | grep "Scan this dir for additional" || true \
    && mkdir -p /usr/local/etc/php/conf.d/

# Copy PHP configuration
COPY docker/php/90-custom.ini /usr/local/etc/php/conf.d/
RUN ls -la /usr/local/etc/php/conf.d/ \
    && cat /usr/local/etc/php/conf.d/90-custom.ini
WORKDIR /var/www/html

# Copy application code (at the end to leverage caching)
COPY --chown=www-data:www-data ./Src /var/www/html/

# Set proper permissions
RUN find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

# Expose port (documentation only)
EXPOSE 80

# Set healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/ || exit 1
