<?php

namespace App\CMS;

use App\ValueObjects\ItemMetadata;

/**
 * Refactored CMS Item class with clean separation of concerns
 */
class Item
{
    private array $relations = [];

    public function __construct(
        private ItemMetadata $metadata,
        private string $body,
        private string $htmlBody
    ) {}

    // Metadata accessors
    public function slug(): string
    {
        return $this->metadata->slug;
    }
    public function title(): string
    {
        return $this->metadata->title;
    }
    public function author(): mixed
    {
        // Check if we have enriched authors available
        $enrichedAuthors = $this->getEnrichedAuthors();

        if (!empty($enrichedAuthors)) {
            $firstAuthor = $enrichedAuthors[0];

            // If it's an enriched author (array), return it
            if (is_array($firstAuthor)) {
                return $firstAuthor;
            }

            // If it's a string, return it
            return $firstAuthor;
        }

        // Fallback to original metadata author for backward compatibility
        return $this->metadata->author;
    }

    /**
     * Get author name as string (for backward compatibility)
     */
    public function authorName(): ?string
    {
        $author = $this->author();

        if (is_array($author)) {
            return $author['name'] ?? null;
        }

        return $author;
    }
    public function featured(): bool
    {
        return $this->metadata->featured;
    }
    public function status(): string
    {
        return $this->metadata->status;
    }
    public function tags(): array
    {
        return $this->metadata->tags;
    }

    /**
     * Get the first tag (enriched Tag object if available, otherwise string)
     * For backward compatibility and convenience
     */
    public function tag(): mixed
    {
        $enrichedTags = $this->getEnrichedTags();

        if (!empty($enrichedTags)) {
            return $enrichedTags[0];
        }

        // Fallback to simple string tag
        $tags = $this->tags();
        return !empty($tags) ? $tags[0] : null;
    }

    /**
     * Get tag name as string for backward compatibility
     */
    public function tagName(): ?string
    {
        $tag = $this->tag();

        if (is_array($tag)) {
            return $tag['name'] ?? null;
        }

        return is_string($tag) ? $tag : null;
    }

    /**
     * Get enriched tags (Tag objects if available, otherwise strings)
     */
    public function getEnrichedTags(): array
    {
        $tags = $this->metadata->tags;

        // If tags are already enriched (arrays with metadata), return them as-is
        if (!empty($tags) && is_array($tags[0] ?? null)) {
            return $tags;
        }

        // Otherwise return simple string tags
        return $tags;
    }

    /**
     * Get enriched authors (Author objects if available, otherwise strings)
     */
    public function getEnrichedAuthors(): array
    {
        // Check if we have enriched authors stored in relations
        $enrichedAuthors = $this->relation('enriched_authors');
        if ($enrichedAuthors !== null) {
            return $enrichedAuthors;
        }

        // Fallback to simple author string from metadata (avoid circular dependency)
        $author = $this->metadata->author;
        if ($author) {
            return [$author];
        }

        return [];
    }
    public function categories(): array
    {
        return $this->metadata->categories;
    }
    public function image(): ?string
    {
        return $this->metadata->image;
    }
    public function excerpt(): ?string
    {
        return $this->metadata->excerpt;
    }

    // Content accessors
    public function body(): string
    {
        return $this->htmlBody;
    }
    public function rawBody(): string
    {
        return $this->body;
    }

    // Date methods
    public function date(string $format = 'F j, Y'): string
    {
        return $this->metadata->formatDate($format);
    }

    public function datePublished(string $format = 'F j, Y'): string
    {
        return $this->date($format);
    }

    public function dateModified(string $format = 'F j, Y'): string
    {
        return $this->metadata->formatDate($format);
    }

    // URL method for compatibility
    public function url(): ?string
    {
        return $this->meta('url');
    }

    // Custom metadata
    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->metadata->getCustom($key, $default);
    }

    // File information (for compatibility)
    public function file(): ?string
    {
        return $this->meta('filepath');
    }

    public function fileName(): ?string
    {
        $filepath = $this->file();
        return $filepath ? basename($filepath) : null;
    }

    // Enhanced image handling
    public function getImage(): ?array
    {
        $imageUrl = $this->image();

        if (!$imageUrl) {
            return null;
        }

        // Check if we have enhanced image data stored in custom fields
        $imageData = $this->meta('image_data');

        if (is_array($imageData)) {
            // Use the enhanced image data
            return [
                'src' => $imageUrl,
                'alt' => $imageData['alt'] ?? $this->meta('image_alt') ?? $this->title(),
                'title' => $imageData['title'] ?? $this->meta('image_title') ?? $this->title(),
                'width' => $imageData['width'] ?? null,
                'height' => $imageData['height'] ?? null,
                'type' => $imageData['type'] ?? 'remote',
            ];
        }

        // Fallback to simple image data
        return [
            'src' => $imageUrl,
            'alt' => $this->meta('image_alt') ?? $this->title(),
            'title' => $this->meta('image_title') ?? $this->title(),
        ];
    }

    // Relations
    public function attach(string $key, mixed $value): void
    {
        $this->relations[$key] = $value;
    }

    public function relation(string $key): mixed
    {
        return $this->relations[$key] ?? null;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    // Status checks
    public function isPublished(): bool
    {
        return $this->metadata->isPublished();
    }

    public function isDraft(): bool
    {
        return $this->metadata->isDraft();
    }

    // Metadata access
    public function getMetadata(): ItemMetadata
    {
        return $this->metadata;
    }

    // Serialization
    public function toArray(): array
    {
        return [
            'slug' => $this->slug(),
            'url' => $this->url(),
            'title' => $this->title(),
            'date' => $this->date('c'),
            'author' => $this->author(),
            'author_name' => $this->authorName(),
            'authors' => $this->getEnrichedAuthors(),
            'tag' => $this->tag(),
            'tag_name' => $this->tagName(),
            'tags' => $this->getEnrichedTags(),
            'featured' => $this->featured(),
            'status' => $this->status(),
            'categories' => $this->categories(),
            'image' => $this->getImage(),
            'excerpt' => $this->excerpt(),
            'body' => $this->body(),
            'raw_body' => $this->rawBody(),
            'meta' => $this->metadata->custom,
            'relations' => $this->relations,
        ];
    }

    // JSON serialization
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // String representation
    public function __toString(): string
    {
        return $this->title();
    }

    // Magic method for dynamic property access
    public function __get(string $name): mixed
    {
        // Try metadata first
        if (property_exists($this->metadata, $name)) {
            return $this->metadata->$name;
        }

        // Try relations
        if (isset($this->relations[$name])) {
            return $this->relations[$name];
        }

        // Try custom metadata
        return $this->meta($name);
    }

    // Check if property exists
    public function __isset(string $name): bool
    {
        return property_exists($this->metadata, $name) ||
            isset($this->relations[$name]) ||
            $this->metadata->hasCustom($name);
    }
}
