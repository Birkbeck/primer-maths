<?php

namespace App\CMS;

/**
 * CMS API for internal server-side usage
 * Provides a clean, chainable interface for CMS operations
 */
class CMS
{
    private static array $instances = [];
    private array $options;

    private function __construct(array $options = [])
    {
        $this->options = array_merge([
            'cache_ttl' => 3600,
            'parser' => [
                'markdown' => [
                    'allow_unsafe_links' => false,
                    'allow_unsafe_images' => false,
                    'html_input' => 'strip',
                ]
            ]
        ], $options);
    }

    /**
     * Create or get a cached CMS instance
     */
    public static function make(array $options = []): self
    {
        $key = md5(serialize($options));

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($options);
        }

        return self::$instances[$key];
    }

    /**
     * Get a collection builder for a relative collection path
     * The path will be automatically resolved based on the HTTP referer
     */
    public function collection(string $relativePath): CollectionBuilder
    {
        $fullPath = $this->resolveCollectionPath($relativePath);
        return new CollectionBuilder($fullPath, $this->options);
    }

    /**
     * Get a single item by path - either a specific file or a collection with URL slug
     */
    public function item(string $path, ?string $slug = null, array $options = []): ?object
    {
        // Resolve the path first
        $fullPath = $this->resolveCollectionPath($path);

        // Check if path points to a specific file
        if (pathinfo($fullPath, PATHINFO_EXTENSION)) {
            // Single file mode - use file() method
            return $this->file($fullPath, $options);
        }

        // Collection mode - get slug from URL or use provided slug
        $actualSlug = $slug ?? $this->getSlugFromUrl();

        if (!$actualSlug) {
            return null; // No slug available
        }

        $repository = $this->getRepository($fullPath);
        $collectionName = basename($fullPath);
        $item = $repository->getItem($collectionName, $actualSlug, $options);
        return $item ? $this->transformItem($item, $options, $path) : null;
    }

    /**
     * Search across collections or within a specific collection path
     */
    public function search(string $query, ?string $collectionPath = null, array $options = []): array
    {
        if ($collectionPath) {
            $repository = $this->getRepository($collectionPath);
            $collectionName = basename($collectionPath);
            $results = $repository->search($query, $collectionName, $options);
        } else {
            // For global search, we'd need a base path - this might need to be rethought
            throw new \InvalidArgumentException('Global search requires a collection path');
        }
        return array_map(fn($item) => $this->transformItem($item, $options, $collectionPath), $results);
    }

    /**
     * Get individual item by full file path
     */
    public function file(string $filePath, array $options = []): ?object
    {
        // For single files, create repository with the file's directory as content base
        $contentPath = dirname($filePath);
        $repository = CMSRepository::make($contentPath, $this->options);
        $item = $repository->getIndividualItem(basename($filePath), $options);
        return $item ? $this->transformItem($item, $options, $filePath) : null;
    }

    /**
     * Get all available collections in a directory
     */
    public function collections(string $basePath): array
    {
        $repository = $this->getRepository($basePath);
        return $repository->getCollections();
    }

    /**
     * Get related items for a specific item
     */
    public function getRelatedItems(string $collectionPath, string $slug, array $criteria = ['tags'], int $limit = 5): array
    {
        $repository = $this->getRepository($collectionPath);
        $items = $repository->getRelatedItems($collectionPath, $slug, $criteria, $limit);

        // Transform items to template-friendly objects
        return array_map(fn($item) => $this->transformItem($item, [], $collectionPath), $items);
    }

    /**
     * Get or create a repository instance for a specific path
     */
    private function getRepository(string $path): CMSRepository
    {
        // For collection paths, we need the parent directory as content base
        $contentPath = dirname($path);
        return CMSRepository::make($contentPath, $this->options);
    }

    /**
     * Transform CMS item to template-friendly object
     */
    public function transformItem($item, array $options = [], ?string $originalCollectionPath = null): object
    {
        if (!$item) {
            return (object) [];
        }

        $slug = method_exists($item, 'slug') ? $item->slug() : '';
        $pagePath = $options['page_path'] ?? null;
        $prettyUrls = $options['pretty_urls'] ?? false;

        // Get image data and ensure remote images are properly handled
        $imageData = null;
        if (method_exists($item, 'getImage')) {
            $imageData = $item->getImage();
        } elseif (method_exists($item, 'image')) {
            $imageUrl = $item->image();
            if ($imageUrl) {
                $imageData = [
                    'src' => $imageUrl,
                    'alt' => method_exists($item, 'title') ? $item->title() : '',
                    'title' => method_exists($item, 'title') ? $item->title() : '',
                ];
            }
        }

        // Ensure remote images are marked as such to prevent URL prepending
        if ($imageData && is_array($imageData) && isset($imageData['src'])) {
            // Check if it's a remote URL (starts with http/https)
            if (preg_match('/^https?:\/\//', $imageData['src'])) {
                $imageData['type'] = 'remote';
                $imageData['is_remote'] = true;
                // For remote images, store the URL in a field that won't be processed as a resource
                $imageData['remote_url'] = $imageData['src'];
                $imageData['absolute_url'] = $imageData['src'];
                // Also add a flag to indicate this should not be processed as a relative resource
                $imageData['skip_resource_processing'] = true;
                // Add protocol and domain info to help with URL processing
                $parsedUrl = parse_url($imageData['src']);
                $imageData['protocol'] = $parsedUrl['scheme'] ?? 'https';
                $imageData['domain'] = $parsedUrl['host'] ?? '';
                $imageData['path'] = $parsedUrl['path'] ?? '';
            } else {
                $imageData['type'] = 'local';
                $imageData['is_remote'] = false;
            }
        }

        // Use original collection path if provided, otherwise extract from filepath
        $collectionPath = $originalCollectionPath;
        $filepath = method_exists($item, 'file') ? $item->file() : null;
        if (!$collectionPath && $filepath) {
            $pathParts = explode('/', dirname($filepath));
            $collectionPath = end($pathParts);
        }

        $transformedItem = (object) [
            'slug' => $slug,
            'title' => method_exists($item, 'title') ? $item->title() : '',
            'date' => method_exists($item, 'date') ? $item->date('c') : '',
            'date_published' => method_exists($item, 'datePublished') ? $item->datePublished('c') : '',
            'date_modified' => method_exists($item, 'dateModified') ? $item->dateModified('c') : '',
            'author' => method_exists($item, 'author') ? $item->author() : '',
            'featured' => method_exists($item, 'featured') ? $item->featured() : false,
            'status' => method_exists($item, 'status') ? $item->status() : 'draft',
            'tags' => method_exists($item, 'tags') ? $item->tags() : [],
            'categories' => method_exists($item, 'categories') ? $item->categories() : [],
            'excerpt' => method_exists($item, 'excerpt') ? $item->excerpt() : '',
            'body' => method_exists($item, 'body') ? $item->body() : '',
            'image' => $imageData,
            'url' => $this->generateItemUrl($slug, $pagePath, $prettyUrls),
            'collection_path' => $collectionPath,
            'filepath' => $filepath,
            'meta' => method_exists($item, 'toArray') ? $item->toArray() : [],
        ];

        // Add detailUrl helper function for backward compatibility
        $transformedItem->detailUrl = function (string $detailPageUrl = '', bool $prettyUrls = false) use ($transformedItem) {
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
    private function generateItemUrl(string $slug, ?string $pagePath, bool $prettyUrls = false): string
    {
        if (!$pagePath) {
            return ''; // No page path configured
        }

        if ($prettyUrls) {
            return rtrim($pagePath, '/') . '/' . $slug;
        } else {
            return $pagePath . '?item=' . $slug;
        }
    }

    /**
     * Get slug from URL - either from query parameter or path
     */
    private function getSlugFromUrl(): ?string
    {
        // Try to get from query parameter first
        if (isset($_GET['item'])) {
            return $_GET['item'];
        }

        // Try to get from path (for pretty URLs)
        $pathInfo = $_SERVER['PATH_INFO'] ?? '';
        if ($pathInfo) {
            return trim(basename($pathInfo), '/');
        }

        // Try to get from REQUEST_URI as fallback
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if ($requestUri) {
            $path = parse_url($requestUri, PHP_URL_PATH);
            $pathParts = array_filter(explode('/', $path));
            return end($pathParts) ?: null;
        }

        return null;
    }

    /**
     * Clear all cached instances (useful for testing)
     */
    public static function clearInstances(): void
    {
        self::$instances = [];
    }

    /**
     * Resolve a relative collection path to an absolute filesystem path
     * Uses the current HTTP request context to determine the base path
     */
    private function resolveCollectionPath(string $relativePath): string
    {
        // Check if we have a pre-resolved content path (set by API controllers)
        if (isset($_SERVER['CMS_RESOLVED_CONTENT_PATH'])) {
            // For API calls, the path has already been resolved
            return $_SERVER['CMS_RESOLVED_CONTENT_PATH'];
        }

        // Create a mock PSR-7 request from global variables for path resolution
        $request = $this->createRequestFromGlobals();

        // Use PathResolverService to resolve the path
        $pathResolver = new \App\Services\PathResolverService();
        $resolvedPath = $pathResolver->resolveContentPath($relativePath, $request);

        if (!$resolvedPath) {
            // Fallback: try to resolve from current working directory
            $cwd = getcwd();
            $resolvedPath = $cwd . '/' . ltrim($relativePath, '/');
        }

        return $resolvedPath;
    }

    /**
     * Create a basic PSR-7 request from PHP globals
     */
    private function createRequestFromGlobals(): \Psr\Http\Message\ServerRequestInterface
    {
        // Create a simple request object with the referer header
        $headers = [];
        if (isset($_SERVER['HTTP_REFERER'])) {
            $headers['referer'] = $_SERVER['HTTP_REFERER'];
        }

        // Create a basic request implementation
        return new class($headers) implements \Psr\Http\Message\ServerRequestInterface {
            private array $headers;

            public function __construct(array $headers)
            {
                $this->headers = $headers;
            }

            public function getHeaderLine(string $name): string
            {
                $name = strtolower($name);
                return $this->headers[$name] ?? '';
            }

            // Minimal implementation - only what we need
            public function getProtocolVersion(): string
            {
                return '1.1';
            }
            public function withProtocolVersion(string $version): \Psr\Http\Message\MessageInterface
            {
                return $this;
            }
            public function getHeaders(): array
            {
                return $this->headers;
            }
            public function hasHeader(string $name): bool
            {
                return isset($this->headers[strtolower($name)]);
            }
            public function getHeader(string $name): array
            {
                return [$this->getHeaderLine($name)];
            }
            public function withHeader(string $name, $value): \Psr\Http\Message\MessageInterface
            {
                return $this;
            }
            public function withAddedHeader(string $name, $value): \Psr\Http\Message\MessageInterface
            {
                return $this;
            }
            public function withoutHeader(string $name): \Psr\Http\Message\MessageInterface
            {
                return $this;
            }
            public function getBody(): \Psr\Http\Message\StreamInterface
            {
                throw new \Exception('Not implemented');
            }
            public function withBody(\Psr\Http\Message\StreamInterface $body): \Psr\Http\Message\MessageInterface
            {
                return $this;
            }
            public function getRequestTarget(): string
            {
                return '/';
            }
            public function withRequestTarget(string $requestTarget): \Psr\Http\Message\RequestInterface
            {
                return $this;
            }
            public function getMethod(): string
            {
                return 'GET';
            }
            public function withMethod(string $method): \Psr\Http\Message\RequestInterface
            {
                return $this;
            }
            public function getUri(): \Psr\Http\Message\UriInterface
            {
                throw new \Exception('Not implemented');
            }
            public function withUri(\Psr\Http\Message\UriInterface $uri, bool $preserveHost = false): \Psr\Http\Message\RequestInterface
            {
                return $this;
            }
            public function getServerParams(): array
            {
                return $_SERVER;
            }
            public function getCookieParams(): array
            {
                return [];
            }
            public function withCookieParams(array $cookies): \Psr\Http\Message\ServerRequestInterface
            {
                return $this;
            }
            public function getQueryParams(): array
            {
                return [];
            }
            public function withQueryParams(array $query): \Psr\Http\Message\ServerRequestInterface
            {
                return $this;
            }
            public function getUploadedFiles(): array
            {
                return [];
            }
            public function withUploadedFiles(array $uploadedFiles): \Psr\Http\Message\ServerRequestInterface
            {
                return $this;
            }
            public function getParsedBody()
            {
                return null;
            }
            public function withParsedBody($data): \Psr\Http\Message\ServerRequestInterface
            {
                return $this;
            }
            public function getAttributes(): array
            {
                return [];
            }
            public function getAttribute(string $name, $default = null)
            {
                return $default;
            }
            public function withAttribute(string $name, $value): \Psr\Http\Message\ServerRequestInterface
            {
                return $this;
            }
            public function withoutAttribute(string $name): \Psr\Http\Message\ServerRequestInterface
            {
                return $this;
            }
        };
    }

    /**
     * Detect if the current request is using pretty URLs
     * This method analyzes the current URL structure to determine if pretty URLs are enabled
     */
    public static function detectPrettyUrls(): bool
    {
        // Check if we're accessing via a pretty URL (no ?item= parameter in the URL)
        $currentUrlHasQuery = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
        if (!$currentUrlHasQuery || !str_contains($currentUrlHasQuery, 'item=')) {
            // No query parameters or no item parameter - likely a pretty URL
            return true;
        }

        // Also check if we got the slug from PATH_INFO (pretty URL indicator)
        if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
            return true;
        }

        return false;
    }

    /**
     * Clean the page path for pretty URLs to avoid double slugs
     * This removes the slug from the end of the URL if it matches the provided slug
     */
    public static function cleanPagePathForPrettyUrls(string $currentUrl, ?string $slug): string
    {
        if (!$slug) {
            return $currentUrl;
        }

        // Remove the slug from the end of the URL path
        $path = parse_url($currentUrl, PHP_URL_PATH);
        $pathParts = array_filter(explode('/', $path));

        // Remove the last part (the slug) if it matches our slug
        if (end($pathParts) === $slug) {
            array_pop($pathParts);
        }

        // Reconstruct the URL without the slug
        $newPath = '/' . implode('/', $pathParts);
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $newPath;
    }
}
