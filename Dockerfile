# Base image
FROM php:8.4-rc-apache

# Set environment variables
ENV GOPATH=/usr/local/go-packages \
    PATH=/usr/local/go-packages/bin:/usr/local/go/bin:$PATH

# Update apt and install dependencies in one layer
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       ca-certificates \
       curl \
       git \
       libzip-dev \
       unzip \
       zip \
    # Enable Apache modules
    && a2enmod rewrite \
    # Install PHP extensions
    && docker-php-ext-install -j$(nproc) \
       mysqli \
       sockets \
       shmop \
       zip \
    # Clean up
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
    # Install Go (using specific version and verifying checksum)
    && mkdir -p "${GOPATH}/src" "${GOPATH}/bin" \
    && curl -sSL "https://go.dev/dl/go1.20.5.linux-amd64.tar.gz" -o go.tar.gz \
    && tar -C /usr/local -xzf go.tar.gz \
    && rm go.tar.gz \
    # Install mhsendmail (for email testing)
    && go install github.com/mailhog/mhsendmail@latest \
    && cp ${GOPATH}/bin/mhsendmail /usr/local/bin/mhsendmail \
    && chmod +x /usr/local/bin/mhsendmail

# Configure PHP base settings (can be overridden by mounted config)
RUN php -i | grep "Configuration File" || true \
    && php -i | grep "Scan this dir for additional" || true \
    && mkdir -p /usr/local/etc/php/conf.d/ \
    && { \
    echo '[mail function]'; \
    echo 'sendmail_path = "/usr/local/bin/mhsendmail --smtp-addr=mailhog:1025"'; \
    echo ''; \
    echo '[PHP]'; \
    echo 'upload_max_filesize = 10M'; \
    echo 'post_max_size = 10M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 600'; \
    echo 'max_input_vars = 3000'; \
    } > /usr/local/etc/php/conf.d/90-custom.ini \
    && ls -la /usr/local/etc/php/conf.d/ \
    && cat /usr/local/etc/php/conf.d/90-custom.ini

# Set the working directory
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
