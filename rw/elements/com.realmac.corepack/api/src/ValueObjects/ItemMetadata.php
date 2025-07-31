<?php

namespace App\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Value object representing CMS item metadata
 */
readonly class ItemMetadata
{
    public function __construct(
        public string $slug,
        public string $title,
        public DateTimeImmutable $date,
        public ?string $author = null,
        public bool $featured = false,
        public string $status = 'published',
        public array $tags = [],
        public array $categories = [],
        public ?string $image = null,
        public ?string $excerpt = null,
        public array $custom = []
    ) {}

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        // Validate required fields
        if (empty($data['slug'])) {
            throw new InvalidArgumentException('Slug is required');
        }

        if (empty($data['title'])) {
            throw new InvalidArgumentException('Title is required');
        }

        // Parse date
        $date = $data['date'] ?? 'now';
        if (is_string($date)) {
            try {
                $dateObject = new DateTimeImmutable($date);
            } catch (\Exception $e) {
                throw new InvalidArgumentException("Invalid date format: {$date}");
            }
        } elseif ($date instanceof DateTimeImmutable) {
            $dateObject = $date;
        } else {
            throw new InvalidArgumentException('Date must be a string or DateTimeImmutable');
        }

        // Extract custom fields (everything not in standard fields)
        $standardFields = [
            'slug',
            'title',
            'date',
            'author',
            'featured',
            'status',
            'tags',
            'categories',
            'image',
            'excerpt'
        ];
        $custom = array_diff_key($data, array_flip($standardFields));

        // Handle image data - convert array to string if needed
        $imageData = $data['image'] ?? null;
        $imageString = null;

        if (is_array($imageData)) {
            // Extract the image URL from array format
            $imageString = $imageData['src'] ?? $imageData['url'] ?? null;
            // Store the full image array in custom fields for later access
            if ($imageData) {
                $custom['image_data'] = $imageData;
            }
        } elseif (is_string($imageData)) {
            $imageString = $imageData;
        }

        return new self(
            slug: $data['slug'],
            title: $data['title'],
            date: $dateObject,
            author: $data['author'] ?? null,
            featured: (bool) ($data['featured'] ?? false),
            status: $data['status'] ?? 'published',
            tags: (array) ($data['tags'] ?? []),
            categories: (array) ($data['categories'] ?? []),
            image: $imageString,
            excerpt: $data['excerpt'] ?? null,
            custom: $custom
        );
    }

    /**
     * Get custom field value
     */
    public function getCustom(string $key, mixed $default = null): mixed
    {
        return $this->custom[$key] ?? $default;
    }

    /**
     * Check if has custom field
     */
    public function hasCustom(string $key): bool
    {
        return array_key_exists($key, $this->custom);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'date' => $this->date->format('c'),
            'author' => $this->author,
            'featured' => $this->featured,
            'status' => $this->status,
            'tags' => $this->tags,
            'categories' => $this->categories,
            'image' => $this->image,
            'excerpt' => $this->excerpt,
            'custom' => $this->custom,
        ];
    }

    /**
     * Format date
     */
    public function formatDate(string $format = 'F j, Y'): string
    {
        return $this->date->format($format);
    }

    /**
     * Check if published
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
