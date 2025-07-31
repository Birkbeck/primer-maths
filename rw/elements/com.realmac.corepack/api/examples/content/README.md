# Example CMS Content

This directory contains example content for the CMS API system, demonstrating the structure and format expected by the content management functionality.

## Directory Structure

```
content/
├── .cache/          # Cache files (automatically generated)
├── blog/            # Blog post collection
├── pages/           # Static pages collection
└── README.md        # This file
```

## Content Format

All content files use Markdown format with YAML front matter. This is a standard format that combines metadata (front matter) with content (markdown).

### Example Structure

```markdown
---
title: "Page Title"
date: "2024-01-15"
status: "published"
featured: true
tags: ["tag1", "tag2"]
categories: ["category1"]
author: "Author Name"
---

# Main Content

This is the content of the page written in Markdown format.

-   You can use lists
-   **Bold text**
-   _Italic text_
-   [Links](https://example.com)

## Subheadings

And more content...
```

## Collections

### Blog Collection (`blog/`)

Contains blog post files with naming convention: `YYYY-MM-DD-post-slug.md`

**Example files:**

-   `2024-01-20-getting-started-with-apis.md`
-   `2024-01-15-welcome-to-our-blog.md`

**Required front matter:**

-   `title`: Post title
-   `date`: Publication date (YYYY-MM-DD)
-   `status`: published/draft
-   `author`: Author name

**Optional front matter:**

-   `featured`: true/false
-   `tags`: Array of tags
-   `categories`: Array of categories
-   `summary`: Brief description

### Pages Collection (`pages/`)

Contains static page files with descriptive names: `page-name.md`

**Example files:**

-   `about.md`
-   `contact.md`

**Required front matter:**

-   `title`: Page title
-   `status`: published/draft

## Usage in Configuration

Reference this content directory in your configuration:

```php
'cms_base_path' => __DIR__ . '/examples/content',
'cache_path' => __DIR__ . '/examples/content/.cache',
```

## API Endpoints

Once configured, the content is available through these API endpoints:

### Collections

-   `GET /cms/collections` - List all collections
-   `GET /cms/collections/blog` - List blog posts
-   `GET /cms/collections/pages` - List pages

### Individual Items

-   `GET /cms/collections/blog/items/welcome-to-our-blog` - Get specific blog post
-   `GET /cms/collections/pages/items/about` - Get specific page

### Search

-   `GET /cms/search?q=api` - Search across all content
-   `GET /cms/search?q=tutorial&collection=blog` - Search within blog

### Feeds

-   `GET /cms/collections/blog/rss` - RSS feed for blog
-   `GET /cms/collections/blog/sitemap` - Sitemap for blog

## Customization

To create your own content:

1. **Copy this directory** to your desired location
2. **Update the configuration** to point to your content directory
3. **Add your own content files** following the format examples
4. **Update front matter** as needed for your use case

### Adding New Collections

Create new directories alongside `blog/` and `pages/`:

```
content/
├── blog/
├── pages/
├── products/        # New collection
├── testimonials/    # New collection
└── portfolio/       # New collection
```

Each collection directory should contain `.md` files with appropriate front matter.

### Front Matter Options

**Common fields:**

-   `title`: Content title (required)
-   `date`: Publication date
-   `status`: published/draft/private
-   `author`: Author information
-   `tags`: Array of tags for categorization
-   `categories`: Array of categories
-   `featured`: Boolean for featured content
-   `summary`: Brief description/excerpt
-   `image`: Featured image path
-   `slug`: Custom URL slug (auto-generated if not provided)

**Custom fields:**
You can add any custom fields you need in the front matter. They will be available in the API responses.

## Cache Directory

The `.cache/` directory contains automatically generated cache files to improve performance. These files are created and managed by the CMS system and should not be edited manually.

**Cache features:**

-   Automatic cache invalidation when content changes
-   Configurable cache TTL (time-to-live)
-   Collection-level and item-level caching

## Testing

Test the content system with:

```bash
# Test CMS endpoints
curl http://localhost:8080/cms/collections
curl http://localhost:8080/cms/collections/blog
curl http://localhost:8080/cms/search?q=api
```

## Production Use

For production use:

1. **Security**: Ensure content directory is not web-accessible
2. **Permissions**: Set appropriate file permissions for the cache directory
3. **Backup**: Regular backups of your content files
4. **Performance**: Configure appropriate cache TTL values
5. **Monitoring**: Monitor cache hit rates and content access patterns

This example content provides a solid foundation for building content-driven applications with the CMS API.
