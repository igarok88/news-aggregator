# Use the official Playwright image (Ubuntu Jammy + Python + Browsers)
FROM mcr.microsoft.com/playwright/python:v1.44.0-jammy

# Settings for non-interactive installation (prevents hanging)
ENV DEBIAN_FRONTEND=noninteractive

# Working directory
WORKDIR /app

# Step 1: Install PHP and system utilities
RUN apt-get update && apt-get install -y --no-install-recommends \
    php \
    php-cli \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Step 2: Install Composer (official method)
# Copy the binary from the official Composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Step 3: Python dependencies
COPY requirements.txt .
# --break-system-packages is required for Ubuntu 24.04/Jammy with newer pip versions
RUN pip install --no-cache-dir -r requirements.txt --break-system-packages

# Step 4: PHP dependencies (Composer)
# First, copy only the dependency definition files (for Docker layer caching)
COPY composer.json composer.lock* ./

# Install PHP dependencies
# The check ensures the build doesn't fail if composer.json is missing
RUN if [ -f composer.json ]; then \
        composer install --no-dev --optimize-autoloader; \
    fi

# Step 5: Copy the rest of the application code
COPY . .

# Expose port
EXPOSE 8000

# Start the built-in server
# -t . specifies the current directory as the server root
CMD ["php", "-S", "0.0.0.0:8000", "-t", "."]