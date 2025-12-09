# Nginx Configuration for Finalyze

This document contains all the nginx configurations that have been tested and work for the Finalyze application on DigitalOcean droplet.

## Server Configuration

Add these settings inside the `server` block of your nginx site configuration:

**File location:** `/etc/nginx/sites-available/finalyze.live`

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name finalyze.live www.finalyze.live;
    
    # Redirect HTTP to HTTPS (if using SSL)
    # return 301 https://$server_name$request_uri;
    
    root /home/forge/finalyze.live/public;
    index index.php index.html;

    # =====================================================
    # BUFFER SIZES - Fix for "upstream sent too big header"
    # =====================================================
    # Required for Inertia.js which sends large response headers
    # containing page data. Without these, you'll get 502 errors
    # on data-heavy pages like /projects/*
    
    fastcgi_buffers 16 16k;
    fastcgi_buffer_size 32k;
    proxy_buffer_size 128k;
    proxy_buffers 4 256k;
    proxy_busy_buffers_size 256k;

    # =====================================================
    # TIMEOUTS - Fix for streaming/AI generation cut-offs
    # =====================================================
    # Required for long-running requests like AI content generation
    # which uses Server-Sent Events (SSE) streaming.
    # Without these, connections will be terminated mid-generation.
    
    proxy_read_timeout 600s;
    proxy_connect_timeout 60s;
    proxy_send_timeout 600s;
    fastcgi_read_timeout 600s;
    fastcgi_send_timeout 600s;
    keepalive_timeout 600s;
    send_timeout 600s;
    
    # Disable proxy buffering for SSE (Server-Sent Events)
    proxy_buffering off;

    # =====================================================
    # STANDARD LARAVEL CONFIGURATION
    # =====================================================
    
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # PHP-FPM specific buffer settings (repeat for fastcgi)
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_read_timeout 600s;
        fastcgi_send_timeout 600s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Quick Copy - Essential Settings Only

If you just need to add the fixes to an existing config:

```nginx
# Add inside server { } block:

# Buffer sizes for large Inertia.js headers
fastcgi_buffers 16 16k;
fastcgi_buffer_size 32k;
proxy_buffer_size 128k;
proxy_buffers 4 256k;
proxy_busy_buffers_size 256k;

# Timeouts for AI streaming (10 minutes)
proxy_read_timeout 600s;
proxy_connect_timeout 60s;
proxy_send_timeout 600s;
fastcgi_read_timeout 600s;
fastcgi_send_timeout 600s;
keepalive_timeout 600s;
send_timeout 600s;
proxy_buffering off;
```

## PHP-FPM Configuration

Also update PHP-FPM to match nginx timeouts:

**File:** `/etc/php/8.3/fpm/pool.d/www.conf`

```ini
; Add or update these settings:
request_terminate_timeout = 600s
pm = dynamic
pm.max_children = 3
pm.start_servers = 1
pm.min_spare_servers = 1
pm.max_spare_servers = 2
pm.process_idle_timeout = 10s
```

**File:** `/etc/php/8.3/fpm/php.ini`

```ini
max_execution_time = 600
memory_limit = 256M
```

## Commands to Apply Changes

```bash
# Test nginx configuration
sudo nginx -t

# Restart nginx
sudo systemctl restart nginx

# Restart PHP-FPM (if PHP settings were changed)
sudo systemctl restart php8.3-fpm
```

## Issues These Settings Fix

| Issue | Symptom | Setting That Fixes It |
|-------|---------|----------------------|
| 502 Bad Gateway on page refresh | "upstream sent too big header" in nginx logs | `fastcgi_buffers`, `fastcgi_buffer_size`, `proxy_buffer_size` |
| AI generation connection error | Stream cuts off mid-generation | `proxy_read_timeout`, `fastcgi_read_timeout` set to 600s |
| PHP timeout errors | "execution timed out" in FPM logs | `request_terminate_timeout` in PHP-FPM |

## Notes

- These settings are optimized for a low-resource server (1GB RAM, 1 vCPU)
- Timeout is set to 600 seconds (10 minutes) to accommodate slow AI generation
- If running on larger servers, you can increase `pm.max_children` accordingly
- Consider upgrading to at least 2GB RAM for better stability
