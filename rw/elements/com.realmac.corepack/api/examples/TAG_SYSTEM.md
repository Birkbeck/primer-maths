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
            "url": "/tags/web-development",
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

### Get All Tags with URLs

```
GET /api/cms/tags?content_path=/content/articles&tag_url_pattern=/tags/{tag-slug}&pretty_urls=true
```

### Get Tag Statistics

```
GET /api/cms/tags/stats?content_path=/content/articles
```

### Search Tags

```
GET /api/cms/tags/search?content_path=/content/articles&q=web&tag_url_pattern=/news/tags/{tag-slug}
```

### Get Items by Tag (for tag pages)

```
GET /api/cms/tags/web-development/items?content_path=/content/articles&tag_url_pattern=/tags/{tag-slug}
```

### Get Collection Items with Tag URLs

```
GET /api/cms/collections/articles?tag_url_pattern=/tags/{tag-slug}&pretty_urls=true
```

### Clear Tag Cache

```
GET /api/cms/cache?action=clear&type=tags&collection=articles
```

## Tag URL Configuration

You can configure individual tag page URLs by providing URL patterns in your API requests. This enables linking to dedicated tag pages.

### URL Pattern Options

-   `tag_url_pattern` - The URL pattern for individual tag pages
-   `pretty_urls` - Whether to use pretty URLs (default: true)

### URL Pattern Placeholders

-   `{tag-slug}` or `{slug}` - Replaced with the tag's slug

### Examples

**Pretty URLs (default):**

```
tag_url_pattern=/tags/{tag-slug}
→ /tags/web-development
```

**Query Parameter URLs:**

```
tag_url_pattern=/tags.php&pretty_urls=false
→ /tags.php?tag=web-development
```

**Nested URLs:**

```
tag_url_pattern=/news/tags/{tag-slug}
→ /news/tags/web-development
```

### Usage in Templates

When you configure tag URLs, each tag object will include a `url` property:

```json
{
    "name": "Web Development",
    "slug": "web-development",
    "url": "/tags/web-development"
    // ... other properties
}
```

This makes it easy to create clickable tag links in your templates:

```html
<a href="{{ tag.url }}">{{ tag.name }}</a>
```

### Fluent API Usage

You can also configure tag URLs directly in the CollectionBuilder fluent API:

```php
// Using the convenience method
$articles = cms()->collection('/content/articles')
    ->tagUrls('/tags')  // Generates /tags/{tag-slug}
    ->latest()
    ->get();

// Using the full pattern method
$articles = cms()->collection('/content/articles')
    ->setTagUrlPattern('/news/tags/{tag-slug}')
    ->prettyUrls(true)
    ->latest()
    ->get();

// Tags in the results will now include URLs
foreach ($articles->items as $article) {
    foreach ($article->tags as $tag) {
        echo '<a href="' . $tag['url'] . '">' . $tag['name'] . '</a>';
    }
}
```

**Method Options:**

-   `tagUrls($basePath)` - Convenience method that creates `$basePath/{tag-slug}` pattern
-   `setTagUrlPattern($pattern)` - Set custom URL pattern with placeholders
-   `prettyUrls($enabled)` - Enable/disable pretty URLs (affects tag URL generation)

## Reusable System

The relationship system is designed to be reusable. You can extend `RelationshipService` to create similar services for:

-   Authors (`authors` folder)
-   Categories (`categories` folder)
-   Series (`series` folder)
-   Any other content relationships

### Creating a Custom Relationship Service

```php
<?php

namespace App\Services;

use App\ValueObjects\Author;

class AuthorService extends RelationshipService
{
    public function __construct(string $basePath)
    {
        parent::__construct($basePath, 'authors');
    }

    protected function createFromArray(array $data): Author
    {
        return Author::fromArray($data);
    }

    protected function createDefault(string $identifier): Author
    {
        return Author::fromString($identifier);
    }
}
```

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

## Implementation Details

### Key Classes

1. **Tag** (`App\ValueObjects\Tag`) - Value object representing a tag with metadata
2. **RelationshipService** (`App\Services\RelationshipService`) - Abstract base class for relationship services
3. **TagService** (`App\Services\TagService`) - Concrete implementation for tag relationships
4. **MarkdownParser** - Enhanced to use TagService for tag enrichment

### How It Works

1. When parsing a markdown file, the `MarkdownParser` checks for a `tags` folder
2. If found, it uses `TagService` to resolve each tag name to a `Tag` object
3. Tag objects are created either from markdown files or as defaults
4. The enriched tags are stored in the item's metadata
5. API endpoints provide access to tag data and statistics

### Performance Considerations

-   Tags are cached after first load
-   File system checks are minimized through caching
-   Batch processing for multiple tags
-   Lazy loading of tag metadata

This system provides a flexible foundation for content relationships while maintaining backward compatibility with existing content.
