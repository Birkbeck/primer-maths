# Configuration Examples

This directory contains example configuration files demonstrating different environments and formats for the API system.

## Files Overview

### `config.php` - Production Environment

-   **Environment**: Production
-   **Format**: PHP array (return statement)
-   **Features**: Complete production configuration with SMTP, forms, webhooks
-   **Use Case**: Production deployment template

### `config.json` - Staging Environment

-   **Environment**: Staging
-   **Format**: JSON
-   **Features**: Simplified staging configuration
-   **Use Case**: Staging/testing environment template

## Configuration Structure

Both files demonstrate the standard configuration structure:

```php
[
    'APP_ENV' => 'environment_name',
    'APP_DEBUG' => true/false,
    'email' => [
        'driver' => 'smtp',
        'from_email' => 'sender@example.com',
        'from_name' => 'Sender Name',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'username',
        'smtp_password' => 'password',
        'smtp_encryption' => 'tls'
    ],
    'forms' => [
        'form_name' => [
            'validation' => [...],
            'email' => [...],
            'webhook' => [...],
            'response' => [...]
        ]
    ]
]
```

## Usage Patterns

### 1. Development

The DynamicConfigLoader automatically discovers these files during development:

```php
// These configs are found in search paths automatically
$loader = new DynamicConfigLoader($fallbackConfig);
$config = $loader->loadConfig(); // Uses examples/config/config.php
```

### 2. Deployment

Copy these files to system directories for production use:

```bash
# Production deployment
sudo cp examples/config/config.php /etc/webapp/config/config.php
sudo chown www-data:www-data /etc/webapp/config/config.php
sudo chmod 640 /etc/webapp/config/config.php

# Staging deployment
sudo cp examples/config/config.json /var/www/staging/config/config.json
sudo chown www-data:www-data /var/www/staging/config/config.json
sudo chmod 640 /var/www/staging/config/config.json

# Docker deployment
cp examples/config/config.php /app/config/config.php
```

### 3. Environment-Specific Loading

The system supports environment-specific configuration loading:

```php
// Load production environment config
$prodLoader = DynamicConfigLoader::createForEnvironment('production');
$prodConfig = $prodLoader->loadConfig();

// Load staging environment config
$stagingLoader = DynamicConfigLoader::createForEnvironment('staging');
$stagingConfig = $stagingLoader->loadConfig();
```

## Search Path Priority

The DynamicConfigLoader searches for configuration files in this order:

1. **Custom paths** (explicitly added)
2. **Environment variable** (`CONFIG_PATH`)
3. **System directories**:
    - `/etc/webapp/config/`
    - `/var/www/config/`
    - `/app/config/`
    - `/home/config/`
4. **Relative paths**:
    - `examples/config/` ← **These files**
    - `../config/`
    - `../../config/`

## File Format Support

### PHP Format (`config.php`)

```php
<?php
return [
    'APP_ENV' => 'production',
    'email' => [
        'driver' => 'smtp',
        // ... configuration
    ]
];
```

**Advantages:**

-   Supports PHP expressions and constants
-   Better for complex configurations
-   Can include comments
-   Native PHP array format

### JSON Format (`config.json`)

```json
{
    "APP_ENV": "staging",
    "email": {
        "driver": "smtp"
    }
}
```

**Advantages:**

-   Language-agnostic format
-   Easy to parse and validate
-   Good for simple configurations
-   Integrates well with deployment tools

## Configuration Validation

Both configurations are validated for:

-   **Required sections**: `email`, `forms`
-   **Email settings**: Valid SMTP configuration when driver is 'smtp'
-   **Form settings**: Proper validation rules, email recipients, webhook URLs
-   **Format compliance**: Valid PHP array or JSON structure

## Security Considerations

### Production Deployment

1. **File permissions**: Set restrictive permissions (640 or 600)
2. **Ownership**: Ensure web server user owns the files
3. **Location**: Store outside web-accessible directories
4. **Sensitive data**: Use environment variables for passwords/keys

### Example Security Setup

```bash
# Set secure permissions
sudo chmod 640 /etc/webapp/config/config.php
sudo chown www-data:www-data /etc/webapp/config/config.php

# Verify permissions
ls -la /etc/webapp/config/config.php
# Should show: -rw-r----- 1 www-data www-data
```

## Customization

### Adding New Environments

Create additional config files for custom environments:

```bash
# Create development config
cp examples/config/config.php examples/config/config-dev.php

# Create testing config
cp examples/config/config.json examples/config/config-test.json
```

### Adding New Configuration Sections

Extend the configuration structure:

```php
return [
    // Standard sections
    'APP_ENV' => 'production',
    'email' => [...],
    'forms' => [...],

    // Custom sections
    'database' => [
        'host' => 'localhost',
        'name' => 'app_db'
    ],
    'cache' => [
        'driver' => 'redis',
        'host' => 'redis-server'
    ],
    'logging' => [
        'level' => 'info',
        'file' => '/var/log/app.log'
    ]
];
```

## Testing

Test configuration loading:

```bash
# Test configuration discovery
php test_dynamic_config.php

# Test form configuration
php test_dynamic_forms.php

# Test with specific environment
CONFIG_PATH=/path/to/config php test_dynamic_config.php
```

## Integration with Forms

These configurations work seamlessly with the form system:

```javascript
// Form submission uses the loaded configuration
fetch("/forms", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
        config_path: "/etc/webapp/config/config.php", // Points to deployed config
        form_data: {
            name: "John Doe",
            email: "john@example.com",
            message: "Hello world",
        },
    }),
});
```

## Troubleshooting

### Configuration Not Found

-   Check file permissions and ownership
-   Verify search paths with `DynamicConfigLoader::getSearchPaths()`
-   Ensure CONFIG_PATH environment variable is set correctly

### Invalid Configuration

-   Validate PHP syntax: `php -l config.php`
-   Validate JSON format: `python -m json.tool config.json`
-   Check required sections are present

### Permission Errors

-   Ensure web server can read configuration files
-   Check directory permissions (755) and file permissions (640)
-   Verify correct ownership (www-data or appropriate user)

These configuration examples provide a solid foundation for deploying the API system across different environments while maintaining security and flexibility.
