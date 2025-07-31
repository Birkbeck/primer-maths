# Form Configuration Examples

This directory contains example form configurations that demonstrate various use cases and features of the Form API system.

## Directory Structure

```
examples/
├── forms/
│   ├── contact.php      # Comprehensive contact form example
│   ├── newsletter.php   # Simple newsletter signup
│   └── feedback.php     # Customer feedback form
├── content/             # Example CMS content
│   ├── .cache/          # Cache files (auto-generated)
│   ├── blog/            # Blog post examples
│   ├── pages/           # Static page examples
│   └── README.md        # Content documentation
├── config/              # Configuration examples
│   ├── config.php       # Production environment config
│   └── config.json      # Staging environment config
└── README.md           # This file
```

## Usage

### 1. Copy Example Files

Copy any example file to your desired location:

```bash
cp examples/forms/contact.php /path/to/your/config/contact.php
```

### 2. Customize Configuration

Edit the copied file to match your requirements:

-   Update email addresses
-   Modify validation rules
-   Configure webhook URLs and authentication
-   Customize response messages

### 3. Reference in Form Submissions

Use the `config_path` parameter to reference your configuration:

```javascript
fetch("/forms", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
        config_path: "/path/to/your/config/contact.php",
        form_id: "contact",
        name: "John Doe",
        email: "john@example.com",
        // ... other form fields
    }),
});
```

### 4. Use CMS Content (Optional)

Copy the content directory for CMS functionality:

```bash
cp -r examples/content /path/to/your/content
```

Update your configuration:

```php
'cms_base_path' => '/path/to/your/content',
'cache_path' => '/path/to/your/content/.cache',
```

### 5. Use Configuration Examples (Optional)

Copy configuration templates for different environments:

```bash
# For production deployment
cp examples/config/config.php /etc/webapp/config/config.php

# For staging deployment
cp examples/config/config.json /var/www/staging/config/config.json
```

## Example Configurations

### Contact Form (`contact.php`)

**Features:**

-   Comprehensive validation with custom messages
-   Email notifications with templates
-   Webhook integration with authentication headers
-   Security features (rate limiting, honeypot)
-   Auto-reply functionality

**Fields:** name, email, company, phone, subject, message

### Newsletter Signup (`newsletter.php`)

**Features:**

-   Minimal validation (email-focused)
-   Webhook integration for email marketing services
-   Simple success/error messaging

**Fields:** email, name (optional)

### Feedback Form (`feedback.php`)

**Features:**

-   Rating validation (1-5 scale)
-   Support team notifications
-   Optional contact information

**Fields:** rating, feedback, email (optional), name (optional)

### CMS Content (`content/`)

**Features:**

-   Example markdown content with YAML front matter
-   Blog posts and static pages
-   Demonstrates content collections structure
-   Cache directory for performance

**Collections:** blog, pages

### Configuration (`config/`)

**Features:**

-   Production and staging environment examples
-   PHP and JSON configuration formats
-   Complete email and form configuration
-   Webhook integration examples

**Files:** config.php (production), config.json (staging)

## Configuration Options

### Validation Rules

```php
'validation' => [
    'rules' => [
        'field_name' => 'required|email|max:100',
        // Available rules: required, email, string, integer, min, max
    ],
    'messages' => [
        'field_name.required' => 'Custom error message',
    ],
],
```

### Email Settings

```php
'email' => [
    'enabled' => true,
    'to' => 'recipient@example.com',
    'cc' => ['cc@example.com'],
    'bcc' => ['bcc@example.com'],
    'subject' => 'Email Subject',
    'template' => 'template_name',
    'reply_to' => true, // Use sender's email as reply-to
],
```

### Webhook Configuration

```php
'webhook' => [
    'enabled' => true,
    'url' => 'https://api.example.com/endpoint',
    'method' => 'POST',
    'format' => 'json', // or 'form'
    'headers' => [
        'Authorization' => 'Bearer token',
        'Content-Type' => 'application/json',
    ],
    'timeout' => 30,
    'retry_attempts' => 3,
],
```

### Response Messages

```php
'response' => [
    'success' => [
        'message' => 'Success message',
        'redirect' => 'https://example.com/thank-you', // Optional
    ],
    'error' => [
        'message' => 'Error message',
        'redirect' => 'https://example.com/error', // Optional
    ],
],
```

## Testing

The example configurations are used in the test suite:

```bash
# Run tests that use example configurations
php test_dynamic_config.php
php test_config_path.php
```

## Best Practices

1. **Security**: Always validate and sanitize input data
2. **Error Handling**: Provide clear, user-friendly error messages
3. **Rate Limiting**: Enable rate limiting for public forms
4. **Logging**: Configure appropriate logging for production use
5. **Backup**: Keep backups of your configuration files
6. **Testing**: Test configurations thoroughly before deploying

## Environment-Specific Configurations

You can create environment-specific versions:

```
configs/
├── production/
│   └── contact.php
├── staging/
│   └── contact.php
└── development/
    └── contact.php
```

Reference the appropriate file based on your environment in your form submissions.

# Enhanced Tag System

This system provides two ways to handle tags in your CMS:

## 1. Simple String Tags (Default)

If no `tags` folder is found, tags will be treated as simple strings:

```yaml
---
title: "My Article"
tags: ["web development", "javascript", "tutorial"]
---
```

Results in:

```json
{
    "tags": ["web development", "javascript", "tutorial"]
}
```

## 2. Enriched Tags with Metadata

If a `tags` folder exists at the same level as your collection, tags will be enriched with metadata from corresponding markdown files.

### Folder Structure

```
content/
├── articles/           # Your collection
│   ├── my-article.md
│   └── another-post.md
└── tags/              # Tag metadata folder
    ├── web-development.md
    ├── javascript.md
    └── tutorial.md
```

### Tag File Format

Each tag file should follow this structure:

```yaml
---
name: "Web Development"
slug: "web-development"
title: "Web Development"
description: "Everything related to building websites and web applications"
color: "#3498db"
icon: "code"
image:
    src: "/images/tags/web-development.jpg"
    alt: "Web Development illustration"
    title: "Web Development"
date: "2024-01-15"
category: "Technology"
priority: 1
---
# Web Development

Optional markdown content describing the tag...
```

### Article with Tags

```yaml
---
title: "My Article"
tags: ["Web Development", "JavaScript", "Tutorial"]
---
```

Results in enriched tag data:

```json
{
    "tags": [
        {
            "name": "Web Development",
            "slug": "web-development",
            "title": "Web Development",
            "description": "Everything related to building websites...",
            "color": "#3498db",
            "icon": "code",
            "image": {
                "src": "/images/tags/web-development.jpg",
                "alt": "Web Development illustration",
                "title": "Web Development"
            },
            "date": "2024-01-15T00:00:00+00:00",
            "custom": {
                "category": "Technology",
                "priority": 1
            }
        }
    ]
}
```

## API Endpoints

### Get All Tags

```
GET /api/cms/tags?content_path=/content/articles
```

### Get Tag Statistics

```
GET /api/cms/tags/stats?content_path=/content/articles
```

### Search Tags

```
GET /api/cms/tags/search?content_path=/content/articles&q=web
```

### Clear Tag Cache

```
GET /api/cms/cache?action=clear&type=tags&collection=articles
```

## Reusable System

The relationship system is designed to be reusable. You can extend `RelationshipService` to create similar services for:

-   Authors (`authors` folder)
-   Categories (`categories` folder)
-   Series (`series` folder)
-   Any other content relationships

## Cache Management

The system includes automatic caching with invalidation:

-   Tags are cached when first loaded
-   Cache is invalidated when tag files are modified
-   Manual cache clearing available via API
-   Separate cache management for content vs. tags

## Fallback Behavior

-   If a tag is referenced but no corresponding file exists, a basic Tag object is created with defaults
-   The system gracefully handles missing tag folders
-   No breaking changes to existing content
