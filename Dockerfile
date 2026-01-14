# Use the official PHP 8.3 image to support typed class constants in deps
FROM php:8.3-fpm

# Optional heavy deps (enable with `--build-arg INSTALL_PANDOC=true`)
ARG INSTALL_PANDOC=false

# Install dependencies for PHP and Node.js
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    unzip \
    curl \
    git \
    libzip-dev \
    libmagickwand-dev \
    libreoffice-writer \
    libreoffice-common \
    libreoffice-core && \
    if [ "$INSTALL_PANDOC" = "true" ]; then apt-get install -y --no-install-recommends pandoc; fi && \
    rm -rf /var/lib/apt/lists/*

# Install Imagick extension
RUN pecl install imagick && docker-php-ext-enable imagick

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip

# Install Node.js
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y --no-install-recommends nodejs && \
    rm -f /etc/apt/sources.list.d/nodesource.list /etc/apt/keyrings/nodesource.gpg /usr/share/keyrings/nodesource.gpg && \
    rm -rf /var/lib/apt/lists/*

# Update package sources and install Chromium (apt-key is deprecated; use keyrings)
RUN apt-get update && apt-get install -y --no-install-recommends \
    apt-transport-https \
    ca-certificates \
    gnupg \
    wget && \
    mkdir -p /etc/apt/keyrings && \
    wget -q -O - https://dl-ssl.google.com/linux/linux_signing_key.pub | gpg --dearmor | tee /etc/apt/keyrings/google-chrome.gpg > /dev/null && \
    echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/google-chrome.gpg] https://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list && \
    apt-get update && \
    apt-get install -y --no-install-recommends google-chrome-stable && \
    rm -rf /var/lib/apt/lists/*

ENV PATH /node_modules/.bin:$PATH

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Add a new non-root user with the same UID and GID as the host user
RUN addgroup --gid 1000 cassiopea && \
    adduser --disabled-password --gecos '' --uid 1000 --gid 1000 cassiopea

# Change ownership of the working directory to cassiopea
RUN chown -R cassiopea:cassiopea /var/www/html

# Set the user to use when running the image
USER cassiopea

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
