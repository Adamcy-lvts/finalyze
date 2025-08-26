# Docker Database Configuration Management

## Problem
Laravel Boost commands that require database operations fail when run outside Docker containers because they cannot connect to the MySQL service running inside Docker.

## Solution
When Laravel Boost (or any other tool) needs to perform database operations outside Docker:

### Step 1: Switch to Local Database Configuration
Update `.env` file to connect to Docker's exposed MySQL port:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3311
DB_DATABASE=finalyze
DB_USERNAME=finalyze_user
DB_PASSWORD=finalyze_password
```

### Step 2: Perform the Required Operations
Run the Laravel Boost command or any database operation outside Docker:
```bash
php artisan boost:install
# or any other Laravel Boost command that needs database access
```

### Step 3: Switch Back to Docker Configuration
Immediately after completing the operations, restore Docker-compatible settings:
```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=finalyze
DB_USERNAME=finalyze_user
DB_PASSWORD=finalyze_password
```

## Automation Instructions for AI
When a user requests Laravel Boost operations that may require database access:

1. **Before the operation**: Automatically switch `.env` to use `DB_HOST=127.0.0.1` and `DB_PORT=3311`
2. **Perform the operation**: Execute the requested Laravel Boost command
3. **After the operation**: Immediately switch `.env` back to `DB_HOST=mysql` and `DB_PORT=3306`
4. **Verify**: Confirm Docker containers can still connect to the database

## Docker Compose Environment Override
The `docker-compose.yml` already includes environment overrides for containers:
```yaml
app:
  environment:
    DB_HOST: mysql
    DB_PORT: 3306
queue:
  environment:
    DB_HOST: mysql
    DB_PORT: 3306
```

This ensures Docker containers always use the internal network regardless of `.env` settings.

## Commands That May Require This Workflow
- `php artisan boost:install`
- `php artisan boost:*` (any Laravel Boost command with database operations)
- Any Artisan command run outside Docker that accesses the database
- Composer scripts that include database operations

## Important Notes
- Always switch back to Docker configuration after operations
- Test Docker container database connectivity after switching back
- This workflow is only needed for commands run outside Docker containers
- Commands run inside Docker (via `docker-compose exec app`) don't need this switching