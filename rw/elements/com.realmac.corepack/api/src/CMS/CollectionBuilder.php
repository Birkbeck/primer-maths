<?php

namespace App\CMS;

/**
 * Collection builder for fluent API
 * Provides chainable methods for building collection queries
 */
class CollectionBuilder
{
    private string $collectionPath;
    private array $options;
    private array $filters = [];
    private ?string $orderBy = 'date_published';
    private string $direction = 'desc';
    private ?int $page = null;
    private int $perPage = 10;
    private ?string $pagePath = null;
    private bool $prettyUrls = false;
    private ?string $tagPagePath = null;
    private ?string $authorPagePath = null;

    public function __construct(string $collectionPath, array $options = [])
    {
        $this->collectionPath = $collectionPath;
        $this->options = $options;

        // Extract URL settings from options if provided
        $this->pagePath = $options['page_path'] ?? null;
        $this->prettyUrls = $options['pretty_urls'] ?? false;
        $this->tagPagePath = $options['tag_page_path'] ?? null;
        $this->authorPagePath = $options['author_page_path'] ?? null;
    }

    /**
     * Add filters
     */
    public function filter(array $filters): self
    {
        $this->filters = array_merge($this->filters, $filters);
        return $this;
    }

    /**
     * Filter by status
     */
    public function status(string $status): self
    {
        $this->filters['status'] = $status;
        return $this;
    }

    /**
     * Filter by featured
     */
    public function featured(bool $featured = true): self
    {
        $this->filters['featured'] = $featured;
        return $this;
    }

    /**
     * Filter by tags
     */
    public function tags(string|array $tags): self
    {
        $this->filters['tags'] = is_array($tags) ? implode(',', $tags) : $tags;
        return $this;
    }

    /**
     * Filter by author
     */
    public function author(string|array $author): self
    {
        $this->filters['author'] = is_array($author) ? implode(',', $author) : $author;
        return $this;
    }

    /**
     * Filter by date range
     */
    public function before(string $date): self
    {
        $this->filters['date_before'] = $date;
        return $this;
    }

    public function after(string $date): self
    {
        $this->filters['date_after'] = $date;
        return $this;
    }

    /**
     * Set ordering
     */
    public function orderBy(string $field, string $direction = 'desc'): self
    {
        $this->orderBy = $field;
        $this->direction = $direction;
        return $this;
    }

    /**
     * Order by date published (most common)
     */
    public function latest(): self
    {
        return $this->orderBy('date_published', 'desc');
    }

    public function oldest(): self
    {
        return $this->orderBy('date_published', 'asc');
    }

    /**
     * Set pagination
     */
    public function paginate(int $page, int $perPage = 10): self
    {
        $this->page = $page;
        $this->perPage = $perPage;
        return $this;
    }

    /**
     * Limit results
     */
    public function limit(int $limit): self
    {
        $this->perPage = $limit;
        return $this;
    }

    /**
     * Get all items (no pagination)
     */
    public function all(): array
    {
        $repository = $this->getRepository();
        $collectionName = basename($this->collectionPath);

        $result = $repository->getCollection(
            $collectionName,
            $this->filters,
            $this->orderBy,
            $this->direction,
            null, // Explicitly pass null for no pagination
            10    // perPage doesn't matter when page is null, but required by signature
        );

        return array_map([$this, 'transformItem'], $result['items'] ?? []);
    }

    /**
     * Get items with pagination info
     */
    public function get(): object
    {
        $repository = $this->getRepository();
        $collectionName = basename($this->collectionPath);

        $result = $repository->getCollection(
            $collectionName,
            $this->filters,
            $this->orderBy,
            $this->direction,
            $this->page,
            $this->perPage
        );

        $items = array_map([$this, 'transformItem'], $result['items'] ?? []);

        // Fix pagination property names to match template expectations
        $meta = $result['meta'] ?? [];
        $pagination = [
            'current_page' => $meta['currentPage'] ?? 1,
            'total_pages' => $meta['lastPage'] ?? 1,
            'total_items' => $meta['totalItems'] ?? count($items),
            'items_per_page' => $meta['perPage'] ?? $this->perPage,
            'has_prev' => ($meta['currentPage'] ?? 1) > 1,
            'has_next' => ($meta['currentPage'] ?? 1) < ($meta['lastPage'] ?? 1)
        ];

        return (object) [
            'items' => $items,
            'pagination' => (object) $pagination,
            'each' => function ($callback) use ($items) {
                foreach ($items as $item) {
                    $callback($item);
                }
            }
        ];
    }

    /**
     * Get first item
     */
    public function first(): ?object
    {
        $items = $this->limit(1)->all();
        return $items[0] ?? null;
    }

    /**
     * Get count of items
     */
    public function count(): int
    {
        $repository = $this->getRepository();
        $collectionName = basename($this->collectionPath);

        $result = $repository->getCollection(
            $collectionName,
            $this->filters,
            $this->orderBy,
            $this->direction
        );

        return $result['count'] ?? count($result['items'] ?? []);
    }

    /**
     * Generate RSS feed
     */
    public function rss(?string $title = null, ?string $description = null, ?string $link = null): string
    {
        $collectionName = basename($this->collectionPath);

        $title = $title ?? ucfirst($collectionName) . ' Feed';
        $description = $description ?? 'RSS feed for ' . $collectionName;
        $link = $link ?? '';

        // Get transformed items with proper URLs
        $originalPerPage = $this->perPage;
        $this->perPage = $this->perPage > 0 ? $this->perPage : 20; // Use builder's limit if set
        $items = $this->latest()->all();
        $this->perPage = $originalPerPage; // Restore original value

        // Generate RSS XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '<title>' . htmlspecialchars($title) . '</title>' . "\n";
        $xml .= '<description>' . htmlspecialchars($description) . '</description>' . "\n";
        $xml .= '<link>' . htmlspecialchars($link) . '</link>' . "\n";
        $xml .= '<lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";

        foreach ($items as $item) {
            $xml .= '<item>' . "\n";
            $xml .= '<title>' . htmlspecialchars($item->title ?? '') . '</title>' . "\n";
            $xml .= '<description>' . htmlspecialchars($item->excerpt ?? '') . '</description>' . "\n";
            $xml .= '<pubDate>' . date('r', strtotime($item->date ?? 'now')) . '</pubDate>' . "\n";
            $xml .= '<link>' . htmlspecialchars($item->url ?? '') . '</link>' . "\n";
            $xml .= '<guid>' . htmlspecialchars($item->url ?? '') . '</guid>' . "\n";
            $xml .= '</item>' . "\n";
        }

        $xml .= '</channel>' . "\n";
        $xml .= '</rss>';

        return $xml;
    }

    /**
     * Generate sitemap
     */
    public function sitemap(string $baseUrl = '', string $changefreq = 'weekly', float $priority = 0.5): string
    {
        // Get transformed items with proper URLs
        $originalPerPage = $this->perPage;
        $this->perPage = $this->perPage > 0 ? $this->perPage : 1000; // Use builder's limit if set
        $items = $this->latest()->all();
        $this->perPage = $originalPerPage; // Restore original value

        // Generate sitemap XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($items as $item) {
            // Use the item's URL if available, otherwise construct from baseUrl + slug
            $itemUrl = $item->url ?? (rtrim($baseUrl, '/') . '/' . ($item->slug ?? ''));

            $xml .= '<url>' . "\n";
            $xml .= '<loc>' . htmlspecialchars($itemUrl) . '</loc>' . "\n";
            $xml .= '<lastmod>' . date('Y-m-d', strtotime($item->date_modified ?? $item->date ?? 'now')) . '</lastmod>' . "\n";
            $xml .= '<changefreq>' . $changefreq . '</changefreq>' . "\n";
            $xml .= '<priority>' . $priority . '</priority>' . "\n";
            $xml .= '</url>' . "\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Get or create a repository instance for this collection path
     */
    private function getRepository(): CMSRepository
    {
        // The collection path should be the full path to the collection directory
        // We need to get the parent directory as the content base path
        $contentPath = dirname($this->collectionPath);

        // Merge tag and author page paths into options
        $options = $this->options;
        if ($this->tagPagePath !== null) {
            $options['tag_page_path'] = $this->tagPagePath;
            $options['pretty_urls'] = $this->prettyUrls;
        }
        if ($this->authorPagePath !== null) {
            $options['author_page_path'] = $this->authorPagePath;
            $options['pretty_urls'] = $this->prettyUrls;
        }

        return CMSRepository::make($contentPath, $options);
    }

    /**
     * Transform CMS item to template-friendly object
     */
    private function transformItem($item): object
    {
        if (!$item) {
            return (object) [];
        }

        $slug = method_exists($item, 'slug') ? $item->slug() : '';

        // Get filepath and collection path
        $filepath = method_exists($item, 'file') ? $item->file() : null;
        $collectionPath = $this->collectionPath; // Use the collection path from the builder

        $transformedItem = (object) [
            'slug' => $slug,
            'title' => method_exists($item, 'title') ? $item->title() : '',
            'date' => method_exists($item, 'date') ? $item->date() : '',
            'date_published' => method_exists($item, 'datePublished') ? $item->datePublished() : '',
            'date_modified' => method_exists($item, 'dateModified') ? $item->dateModified() : '',
            'author' => method_exists($item, 'author') ? $item->author() : '',
            'author_name' => method_exists($item, 'authorName') ? $item->authorName() : '',
            'authors' => method_exists($item, 'getEnrichedAuthors') ? $item->getEnrichedAuthors() : [],
            'tag' => method_exists($item, 'tag') ? $item->tag() : '',
            'tag_name' => method_exists($item, 'tagName') ? $item->tagName() : '',
            'tags' => method_exists($item, 'getEnrichedTags') ? $item->getEnrichedTags() : (method_exists($item, 'tags') ? $item->tags() : []),
            'featured' => method_exists($item, 'featured') ? $item->featured() : false,
            'status' => method_exists($item, 'status') ? $item->status() : 'draft',
            'categories' => method_exists($item, 'categories') ? $item->categories() : [],
            'excerpt' => method_exists($item, 'excerpt') ? $item->excerpt() : '',
            'body' => method_exists($item, 'body') ? $item->body() : '',
            'image' => method_exists($item, 'getImage') ? $item->getImage() : null,
            'url' => $this->generateItemUrl($slug),
            'collection_path' => $collectionPath,
            'filepath' => $filepath,
            'meta' => method_exists($item, 'toArray') ? $item->toArray() : [],
        ];

        // Add detailUrl helper function for backward compatibility
        $transformedItem->detailUrl = function (string $detailPageUrl = '', bool $prettyUrls = true) use ($transformedItem) {
            if ($prettyUrls) {
                return rtrim($detailPageUrl, '/') . '/' . $transformedItem->slug;
            } else {
                return $detailPageUrl . '?item=' . $transformedItem->slug;
            }
        };

        return $transformedItem;
    }

    /**
     * Generate URL for an individual item
     */
    private function generateItemUrl(string $slug): string
    {
        if (!$this->pagePath) {
            return ''; // No page path configured
        }

        if ($this->prettyUrls) {
            return rtrim($this->pagePath, '/') . '/' . $slug;
        } else {
            return $this->pagePath . '?item=' . $slug;
        }
    }

    /**
     * Set the page path for URL generation
     */
    public function pagePath(string $pagePath): self
    {
        $this->pagePath = $pagePath;
        return $this;
    }

    /**
     * Set the tag page path for URL generation
     */
    public function tagPagePath(string $tagPagePath): self
    {
        $this->tagPagePath = $tagPagePath;
        return $this;
    }

    /**
     * Set the author page path for URL generation
     */
    public function authorPagePath(string $authorPagePath): self
    {
        $this->authorPagePath = $authorPagePath;
        return $this;
    }

    /**
     * Set whether to use pretty URLs
     */
    public function prettyUrls(bool $prettyUrls = true): self
    {
        $this->prettyUrls = $prettyUrls;
        return $this;
    }
}
