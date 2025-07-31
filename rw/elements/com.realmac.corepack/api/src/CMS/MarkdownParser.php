<?php

namespace App\CMS;

use Symfony\Component\Yaml\Yaml;
use League\CommonMark\CommonMarkConverter;
use App\ValueObjects\ItemMetadata;
use App\Services\TagService;
use App\Services\AuthorService;

/**
 * Service for parsing markdown files with YAML frontmatter
 */
class MarkdownParser
{
    private CommonMarkConverter $converter;
    private array $options;
    private ?TagService $tagService = null;
    private ?AuthorService $authorService = null;

    public function __construct(array $options = [])
    {
        $this->options = $options;
        $this->converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
            'allow_unsafe_images' => false,
            'html_input' => 'strip',
        ]);
    }

    /**
     * Parse a markdown file and return an Item instance
     */
    public function parse(string $filepath): ?Item
    {
        if (!file_exists($filepath)) {
            return null;
        }

        $content = file_get_contents($filepath);

        // Parse frontmatter and content
        if (!preg_match('/^---(.*?)---(.*)$/s', $content, $matches)) {
            return null;
        }

        $yaml = trim($matches[1]);
        $body = trim($matches[2]);

        try {
            $meta = Yaml::parse($yaml) ?: [];
        } catch (\Exception $e) {
            return null;
        }

        // Process filename for slug and date extraction
        $filename = basename($filepath, '.md');
        $slug = $filename;
        $date = null;

        // Extract date from filename if present (e.g., 2023-01-01-my-post.md)
        if (preg_match('/^(\d{4}-\d{2}-\d{2})-(.+)$/', $filename, $matches)) {
            $date = $matches[1];
            $slug = $matches[2];
        }

        // Process tags with TagService if available
        $tags = $this->normalizeArray($meta['tags'] ?? []);
        $enrichedTags = $this->enrichTags($tags, $filepath);

        // Process authors with AuthorService if available
        $authors = $this->normalizeArray($meta['authors'] ?? (isset($meta['author']) ? [$meta['author']] : []));
        $enrichedAuthors = $this->enrichAuthors($authors, $filepath);

        // Build metadata with defaults
        $metadata = ItemMetadata::fromArray([
            'slug' => $slug,
            'title' => $meta['title'] ?? ucfirst(str_replace('-', ' ', $slug)),
            'date' => $this->normalizeDate($meta['date'] ?? $date ?? date('Y-m-d H:i:s', filectime($filepath))),
            'author' => $meta['author'] ?? null,
            'featured' => (bool)($meta['featured'] ?? false),
            'status' => $meta['status'] ?? 'published',
            'tags' => $enrichedTags,
            'categories' => $this->normalizeArray($meta['categories'] ?? []),
            'image' => $meta['image'] ?? null,
            'excerpt' => $meta['excerpt'] ?? $this->generateExcerpt($body),
            'filepath' => $filepath, // Add filepath to custom fields
        ] + $this->extractCustomFields($meta));

        // Convert markdown to HTML
        $htmlBody = $this->converter->convert($body)->getContent();

        // Apply resource path prefixing if configured
        $htmlBody = $this->applyResourcePrefixing($htmlBody, $meta);

        // Create item
        $item = new Item($metadata, $body, $htmlBody);

        // Attach enriched authors if available
        if (!empty($enrichedAuthors)) {
            $item->attach('enriched_authors', $enrichedAuthors);
        }

        return $item;
    }

    /**
     * Normalize array fields (tags, categories, etc.)
     */
    private function normalizeArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            return array_map('trim', explode(',', $value));
        }
        return [];
    }

    /**
     * Normalize date field to ensure it's a string
     */
    private function normalizeDate($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value)) {
            // Handle YAML parsing of dates like 2024-01-12 as integers
            $dateStr = (string)$value;

            // Check if it's a Unix timestamp (10 digits)
            if (strlen($dateStr) === 10 && preg_match('/^\d{10}$/', $dateStr)) {
                return date('Y-m-d H:i:s', $value);
            }

            // Check if it's YYYYMMDD format (8 digits)
            if (strlen($dateStr) === 8 && preg_match('/^\d{8}$/', $dateStr)) {
                // Convert YYYYMMDD to YYYY-MM-DD
                return substr($dateStr, 0, 4) . '-' . substr($dateStr, 4, 2) . '-' . substr($dateStr, 6, 2);
            }

            return $dateStr;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        return (string)$value;
    }

    /**
     * Generate excerpt from body content
     */
    private function generateExcerpt(string $body, int $words = 30): string
    {
        // Strip markdown and get plain text
        $text = strip_tags($this->converter->convert($body)->getContent());

        // Split into words and take the first N words
        $words_array = explode(' ', $text);
        if (count($words_array) <= $words) {
            return $text;
        }

        return implode(' ', array_slice($words_array, 0, $words)) . '...';
    }

    /**
     * Extract custom fields (fields not handled by standard metadata)
     */
    private function extractCustomFields(array $meta): array
    {
        $standardFields = [
            'title',
            'date',
            'date_published',
            'date_modified',
            'author',
            'authors',
            'featured',
            'status',
            'tags',
            'categories',
            'image',
            'excerpt'
        ];

        $custom = [];
        foreach ($meta as $key => $value) {
            if (!in_array($key, $standardFields)) {
                $custom[$key] = $value;
            }
        }

        return $custom;
    }

    /**
     * Enrich tags with metadata from Tags folder if available
     */
    private function enrichTags(array $tags, string $filepath): array
    {
        if (empty($tags)) {
            return [];
        }

        // Initialize TagService if not already done
        if ($this->tagService === null) {
            $basePath = $this->getBasePath($filepath);
            $this->tagService = new TagService($basePath);
        }

        // Check if we have a Tags folder
        if (!$this->tagService->hasRelationshipFolder($filepath)) {
            // No Tags folder, return simple string tags
            return $tags;
        }

        // Extract URL options from parser options
        $tagOptions = [];
        if (isset($this->options['tag_page_path'])) {
            $tagOptions['tag_page_path'] = $this->options['tag_page_path'];
            $tagOptions['pretty_urls'] = $this->options['pretty_urls'] ?? true;
        }

        // Resolve tags with metadata
        $enrichedTags = $this->tagService->resolveTags($tags, $filepath, $tagOptions);

        // Convert Tag objects to arrays for ItemMetadata
        return array_map(function ($tag) {
            if (is_object($tag) && method_exists($tag, 'toArray')) {
                return $tag->toArray();
            }
            return $tag;
        }, $enrichedTags);
    }

    /**
     * Enrich authors with metadata from Authors folder if available
     */
    private function enrichAuthors(array $authors, string $filepath): array
    {
        if (empty($authors)) {
            return [];
        }

        // Initialize AuthorService if not already done
        if ($this->authorService === null) {
            $basePath = $this->getBasePath($filepath);
            $this->authorService = new AuthorService($basePath);
        }

        // Check if we have an Authors folder
        if (!$this->authorService->hasRelationshipFolder($filepath)) {
            // No Authors folder, return simple string authors
            return $authors;
        }

        // Extract URL options from parser options
        $authorOptions = [];
        if (isset($this->options['author_page_path'])) {
            $authorOptions['author_page_path'] = $this->options['author_page_path'];
            $authorOptions['pretty_urls'] = $this->options['pretty_urls'] ?? true;
        }

        // Resolve authors with metadata
        $enrichedAuthors = $this->authorService->resolveAuthors($authors, $filepath, $authorOptions);

        // Convert Author objects to arrays for ItemMetadata
        return array_map(function ($author) {
            if (is_object($author) && method_exists($author, 'toArray')) {
                return $author->toArray();
            }
            return $author;
        }, $enrichedAuthors);
    }

    /**
     * Get the base path for content from a file path
     */
    private function getBasePath(string $filepath): string
    {
        // Get the directory containing the file
        $dir = dirname($filepath);

        // Go up one level to get the parent directory (content root)
        return dirname($dir);
    }

    /**
     * Apply resource path prefixing if configured
     */
    private function applyResourcePrefixing(string $htmlBody, array $meta): string
    {
        $resources = $this->options['resources'] ?? [];
        $sharedResourcesPath = $resources['path'] ?? '';
        $prefixFields = $resources['fields'] ?? [];

        if (empty($sharedResourcesPath) || empty($prefixFields)) {
            return $htmlBody;
        }

        foreach ($prefixFields as $field) {
            if (isset($meta[$field]) && is_string($meta[$field])) {
                $prefixedPath = rtrim($sharedResourcesPath, '/') . '/' . ltrim($meta[$field], '/');
                $htmlBody = str_replace($meta[$field], $prefixedPath, $htmlBody);
            }
        }

        return $htmlBody;
    }
}
