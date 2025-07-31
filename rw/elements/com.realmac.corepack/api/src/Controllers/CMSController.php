<?php

namespace App\Controllers;

use App\CMS\CMSRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * CMS Controller - Clean, secure, and consistent
 * Extends BaseController for security validation and common helpers
 */
class CMSController extends BaseController
{
    // ========================================
    // PUBLIC API ENDPOINTS
    // ========================================

    /**
     * Get collections list
     */
    public function getCollections(Request $request, Response $response): Response
    {
        return $this->handleRequest($request, $response, function ($contentPath) {
            $cms = CMSRepository::make($contentPath, $this->config['cms_options'] ?? []);
            $collections = $cms->getCollections();

            return [
                'success' => true,
                'data' => $collections,
                'count' => count($collections),
                'timestamp' => date('c')
            ];
        });
    }

    /**
     * Get collection items with filtering and pagination
     */
    public function getCollectionItems(Request $request, Response $response, array $args): Response
    {
        $collectionPath = $args['collection'] ?? '';
        if (empty($collectionPath)) {
            return $this->errorResponse($response, 'Collection path is required', 400);
        }

        return $this->handleRequest($request, $response, function ($contentPath) use ($request, $collectionPath) {
            $queryParams = $request->getQueryParams();

            // Build CMS options including tag and author URL configuration
            $cmsOptions = $this->config['cms_options'] ?? [];
            if (isset($queryParams['tag_page_path'])) {
                $cmsOptions['tag_page_path'] = $queryParams['tag_page_path'];
                $cmsOptions['pretty_urls'] = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);
            }
            if (isset($queryParams['author_page_path'])) {
                $cmsOptions['author_page_path'] = $queryParams['author_page_path'];
                $cmsOptions['pretty_urls'] = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);
            }

            $cms = CMSRepository::make($contentPath, $cmsOptions);

            // Build filters and pagination
            $filters = $this->buildFilters($queryParams);
            $orderBy = $queryParams['orderBy'] ?? null;
            $direction = $queryParams['direction'] ?? 'desc';
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : null;
            $perPage = (int)($queryParams['per_page'] ?? 10);

            $result = $cms->getCollection($collectionPath, $filters, $orderBy, $direction, $page, $perPage);

            $data = [
                'success' => true,
                'collection' => $collectionPath,
                'data' => array_map([$this, 'serializeItem'], $result['items']),
                'timestamp' => date('c')
            ];

            // Add pagination metadata
            if ($page !== null) {
                $data['meta'] = $result['meta'];
            } else {
                $data['count'] = $result['count'];
            }

            return $data;
        }, $collectionPath);
    }

    /**
     * Get a specific item by slug
     */
    public function getItem(Request $request, Response $response, array $args): Response
    {
        $collectionPath = $args['collection'] ?? '';
        $slug = $args['slug'] ?? '';

        if (empty($collectionPath) || empty($slug)) {
            return $this->errorResponse($response, 'Collection path and slug are required', 400);
        }

        return $this->handleRequest($request, $response, function ($contentPath) use ($request, $collectionPath, $slug) {
            $queryParams = $request->getQueryParams();

            // Build CMS options including tag and author URL configuration
            $cmsOptions = $this->config['cms_options'] ?? [];
            if (isset($queryParams['tag_page_path'])) {
                $cmsOptions['tag_page_path'] = $queryParams['tag_page_path'];
                $cmsOptions['pretty_urls'] = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);
            }
            if (isset($queryParams['author_page_path'])) {
                $cmsOptions['author_page_path'] = $queryParams['author_page_path'];
                $cmsOptions['pretty_urls'] = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);
            }

            $cms = CMSRepository::make($contentPath, $cmsOptions);
            $options = $this->buildOptions($queryParams);

            $item = $cms->getItem($collectionPath, $slug, $options);

            if (!$item) {
                throw new \Exception('Item not found', 404);
            }

            return [
                'success' => true,
                'collection' => $collectionPath,
                'data' => $this->serializeItem($item),
                'timestamp' => date('c')
            ];
        }, $collectionPath);
    }

    /**
     * Search across collections or within a specific collection
     */
    public function search(Request $request, Response $response): Response
    {
        // Support both GET and POST requests
        $isPost = $request->getMethod() === 'POST';

        if ($isPost) {
            $requestData = json_decode($request->getBody()->getContents(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->errorResponse($response, 'Invalid JSON in request body', 400);
            }
            $queryParams = $request->getQueryParams();
        } else {
            $requestData = [];
            $queryParams = $request->getQueryParams();
        }

        // Get parameters from appropriate source
        $query = $requestData['q'] ?? $queryParams['q'] ?? '';
        $contentPath = $requestData['content_path'] ?? $queryParams['content_path'] ?? '';
        $collection = $requestData['collection'] ?? $queryParams['collection'] ?? '';
        $itemTemplate = $requestData['item_template'] ?? $queryParams['item_template'] ?? '';

        if (empty($query)) {
            return $this->errorResponse($response, 'Search query (q) is required', 400);
        }

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($request, $response, $requestData, $queryParams, $query, $contentPath, $collection, $itemTemplate) {
            // Build CMS options including tag and author URL configuration
            $cmsOptions = $this->config['cms_options'] ?? [];
            $configSource = !empty($requestData) ? $requestData : $queryParams;

            if (isset($configSource['tag_page_path'])) {
                $cmsOptions['tag_page_path'] = $configSource['tag_page_path'];
                $cmsOptions['pretty_urls'] = in_array($configSource['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);
            }
            if (isset($configSource['author_page_path'])) {
                $cmsOptions['author_page_path'] = $configSource['author_page_path'];
            }

            $cms = CMSRepository::make($resolvedContentPath, $cmsOptions);
            $filters = $this->buildFilters($configSource);
            $page = (int)($configSource['page'] ?? 1);
            $perPage = (int)($configSource['per_page'] ?? 10);

            $searchOptions = ['filters' => $filters];
            if ($page && $perPage) {
                $searchOptions['page'] = $page;
                $searchOptions['per_page'] = $perPage;
            }

            // Use specific collection or search all collections in content_path
            $searchCollection = $collection ?: null; // null means search all collections
            $results = $cms->search($query, $searchCollection, $searchOptions);

            // Handle template rendering if provided
            $responseData = [
                'success' => true,
                'collection' => $searchCollection ?: 'all',
                'data' => array_map([$this, 'serializeItem'], $results),
                'count' => count($results),
                'timestamp' => date('c')
            ];

            if (!empty($itemTemplate)) {
                try {
                    $templateService = new \App\Services\TemplateRenderingService();
                    $renderedResults = $templateService->renderItemsWithTemplate($responseData['data'], $itemTemplate);
                    $responseData['data'] = $renderedResults;
                    $responseData['template_cache_stats'] = $templateService->getCacheStats();
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage(), 400);
                }
            }

            return $responseData;
        }, $contentPath);
    }

    /**
     * Get related items for a specific item
     */
    public function getRelatedItems(Request $request, Response $response, array $args): Response
    {
        $collectionPath = $args['collection'] ?? '';
        $slug = $args['slug'] ?? '';

        if (empty($collectionPath) || empty($slug)) {
            return $this->errorResponse($response, 'Collection path and slug are required', 400);
        }

        return $this->handleRequest($request, $response, function ($contentPath) use ($request, $collectionPath, $slug) {
            $cms = CMSRepository::make($contentPath, $this->config['cms_options'] ?? []);
            $queryParams = $request->getQueryParams();
            $criteria = isset($queryParams['by']) ? explode(',', $queryParams['by']) : ['tags'];
            $limit = (int)($queryParams['limit'] ?? 5);

            $relatedItems = $cms->getRelatedItems($collectionPath, $slug, $criteria, $limit);

            return [
                'success' => true,
                'collection' => $collectionPath,
                'data' => array_map([$this, 'serializeItem'], $relatedItems),
                'count' => count($relatedItems),
                'timestamp' => date('c')
            ];
        }, $collectionPath);
    }

    /**
     * Get search suggestions for autocomplete
     */
    public function getSearchSuggestions(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $partialQuery = $queryParams['q'] ?? '';
        $contentPath = $queryParams['content_path'] ?? '';
        $collection = $queryParams['collection'] ?? ''; // Optional collection filter
        $limit = (int)($queryParams['limit'] ?? 5);

        if (empty($partialQuery)) {
            return $this->errorResponse($response, 'Search query (q) is required', 400);
        }

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($partialQuery, $contentPath, $collection, $limit) {
            $cms = CMSRepository::make($resolvedContentPath, $this->config['cms_options'] ?? []);

            // Use specific collection or search all collections in content_path
            $searchCollection = $collection ?: null; // null means search all collections
            $suggestions = $cms->getSearchSuggestions($partialQuery, $searchCollection, $limit);

            return [
                'success' => true,
                'content_path' => $contentPath,
                'query' => $partialQuery,
                'collection' => $searchCollection ?: 'all',
                'suggestions' => $suggestions,
                'count' => count($suggestions),
                'timestamp' => date('c')
            ];
        }, $contentPath);
    }

    /**
     * Get search analytics
     */
    public function getSearchAnalytics(Request $request, Response $response): Response
    {
        return $this->handleRequest($request, $response, function ($contentPath) {
            $cms = CMSRepository::make($contentPath, $this->config['cms_options'] ?? []);
            $analytics = $cms->getSearchAnalytics();

            return [
                'success' => true,
                'analytics' => $analytics,
                'timestamp' => date('c')
            ];
        });
    }

    /**
     * Generate RSS feed for a collection
     */
    public function getRssFeed(Request $request, Response $response, array $args): Response
    {
        $collectionPath = $args['collection'] ?? '';
        if (empty($collectionPath)) {
            return $this->errorResponse($response, 'Collection path is required', 400);
        }

        $queryParams = $request->getQueryParams();

        // Use content_path parameter if provided, otherwise use collection path
        $contentPathParam = $queryParams['content_path'] ?? $collectionPath;

        return $this->handleRequest($request, $response, function ($contentPath) use ($request, $collectionPath, $contentPathParam) {
            $cms = CMSRepository::make($contentPath, $this->config['cms_options'] ?? []);
            $queryParams = $request->getQueryParams();
            $uri = $request->getUri();

            $title = $queryParams['title'] ?? ucfirst($collectionPath) . ' Feed';
            $description = $queryParams['description'] ?? 'RSS feed for ' . $collectionPath;
            $link = $queryParams['link'] ?? $uri->getScheme() . '://' . $uri->getHost();

            // Support filtering parameters
            $filters = $this->buildFilters($queryParams);
            $limit = (int)($queryParams['limit'] ?? 20);

            // Use the basename of the content path parameter as the collection name
            $collectionName = basename($contentPathParam);
            return $cms->generateRssFeed($collectionName, $title, $description, $link, $filters, $limit);
        }, $contentPathParam, 'application/rss+xml');
    }

    /**
     * Generate sitemap for a collection
     */
    public function getSitemap(Request $request, Response $response, array $args): Response
    {
        $collectionPath = $args['collection'] ?? '';
        if (empty($collectionPath)) {
            return $this->errorResponse($response, 'Collection path is required', 400);
        }

        $queryParams = $request->getQueryParams();

        // Use content_path parameter if provided, otherwise use collection path
        $contentPathParam = $queryParams['content_path'] ?? $collectionPath;

        return $this->handleRequest($request, $response, function ($contentPath) use ($request, $collectionPath, $contentPathParam) {
            $cms = CMSRepository::make($contentPath, $this->config['cms_options'] ?? []);
            $queryParams = $request->getQueryParams();
            $uri = $request->getUri();

            $baseUrl = $queryParams['base_url'] ?? $uri->getScheme() . '://' . $uri->getHost();
            $changefreq = $queryParams['changefreq'] ?? 'weekly';
            $priority = (float)($queryParams['priority'] ?? 0.5);

            // Support filtering parameters
            $filters = $this->buildFilters($queryParams);
            $limit = (int)($queryParams['limit'] ?? 1000); // Higher default for sitemaps

            // Use the basename of the content path parameter as the collection name
            $collectionName = basename($contentPathParam);
            return $cms->generateSitemap($collectionName, $baseUrl, $changefreq, $priority, $filters, $limit);
        }, $contentPathParam, 'application/xml');
    }

    /**
     * Generate RSS feed with content_path parameter
     */
    public function getGenericRssFeed(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $contentPath = $queryParams['content_path'] ?? '';

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($request, $contentPath) {
            // Set the resolved content path in $_SERVER for the CMS system to use
            $_SERVER['CMS_RESOLVED_CONTENT_PATH'] = $resolvedContentPath;

            $queryParams = $request->getQueryParams();
            $uri = $request->getUri();

            $title = $queryParams['title'] ?? ucfirst(basename($contentPath)) . ' Feed';
            $description = $queryParams['description'] ?? 'RSS feed for ' . basename($contentPath);
            $link = $queryParams['link'] ?? $uri->getScheme() . '://' . $uri->getHost();

            // Support filtering parameters
            $filters = $this->buildFilters($queryParams);
            $limit = (int)($queryParams['limit'] ?? 20);

            // Use the new CMS system with automatic path resolution
            $cms = \App\CMS\CMS::make();
            $collection = $cms->collection($contentPath);

            // Configure URL generation
            $pagePath = $queryParams['page_path'] ?? '';
            $prettyUrls = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);

            if ($pagePath) {
                $collection = $collection->pagePath($pagePath)->prettyUrls($prettyUrls);
            }

            // Apply filters
            foreach ($filters as $key => $value) {
                switch ($key) {
                    case 'tags':
                        $collection = $collection->tags($value);
                        break;
                    case 'author':
                        $collection = $collection->author($value);
                        break;
                    case 'featured':
                        $collection = $collection->featured($value === 'true');
                        break;
                    case 'status':
                        $collection = $collection->status($value);
                        break;
                    case 'date_before':
                        $collection = $collection->before($value);
                        break;
                    case 'date_after':
                        $collection = $collection->after($value);
                        break;
                }
            }

            return $collection->limit($limit)->latest()->rss($title, $description, $link);
        }, $contentPath, 'application/rss+xml');
    }

    /**
     * Generate sitemap with content_path parameter
     */
    public function getGenericSitemap(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $contentPath = $queryParams['content_path'] ?? '';

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($request, $contentPath) {
            // Set the resolved content path in $_SERVER for the CMS system to use
            $_SERVER['CMS_RESOLVED_CONTENT_PATH'] = $resolvedContentPath;

            $queryParams = $request->getQueryParams();
            $uri = $request->getUri();

            $baseUrl = $queryParams['base_url'] ?? $uri->getScheme() . '://' . $uri->getHost();
            $changefreq = $queryParams['changefreq'] ?? 'weekly';
            $priority = (float)($queryParams['priority'] ?? 0.5);

            // Support filtering parameters
            $filters = $this->buildFilters($queryParams);
            $limit = (int)($queryParams['limit'] ?? 1000); // Higher default for sitemaps

            // Use the new CMS system with automatic path resolution
            $cms = \App\CMS\CMS::make();
            $collection = $cms->collection($contentPath);

            // Configure URL generation
            $pagePath = $queryParams['page_path'] ?? '';
            $prettyUrls = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);

            if ($pagePath) {
                $collection = $collection->pagePath($pagePath)->prettyUrls($prettyUrls);
            }

            // Apply filters
            foreach ($filters as $key => $value) {
                switch ($key) {
                    case 'tags':
                        $collection = $collection->tags($value);
                        break;
                    case 'author':
                        $collection = $collection->author($value);
                        break;
                    case 'featured':
                        $collection = $collection->featured($value === 'true');
                        break;
                    case 'status':
                        $collection = $collection->status($value);
                        break;
                    case 'date_before':
                        $collection = $collection->before($value);
                        break;
                    case 'date_after':
                        $collection = $collection->after($value);
                        break;
                }
            }

            return $collection->limit($limit)->latest()->sitemap($baseUrl, $changefreq, $priority);
        }, $contentPath, 'application/xml');
    }

    /**
     * Get all tags for a content path
     */
    public function getTags(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $contentPath = $queryParams['content_path'] ?? '';

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($contentPath, $queryParams) {
            $tagService = new \App\Services\TagService($resolvedContentPath);

            // Build options for URL generation
            $options = [];
            if (isset($queryParams['tag_page_path'])) {
                $options['tag_page_path'] = $queryParams['tag_page_path'];
                $options['pretty_urls'] = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);
            }

            $tags = $tagService->getAllTags($contentPath, $options);

            return [
                'success' => true,
                'content_path' => $contentPath,
                'data' => array_map(fn($tag) => $tag->toArray(), $tags),
                'count' => count($tags),
                'timestamp' => date('c')
            ];
        }, $contentPath);
    }

    /**
     * Get tag statistics
     */
    public function getTagStats(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $contentPath = $queryParams['content_path'] ?? '';

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($contentPath) {
            $tagService = new \App\Services\TagService($resolvedContentPath);
            $stats = $tagService->getTagStats($contentPath);

            return [
                'success' => true,
                'content_path' => $contentPath,
                'stats' => $stats,
                'timestamp' => date('c')
            ];
        }, $contentPath);
    }

    /**
     * Search tags
     */
    public function searchTags(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $contentPath = $queryParams['content_path'] ?? '';
        $query = $queryParams['q'] ?? '';

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        if (empty($query)) {
            return $this->errorResponse($response, 'Search query (q) is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($contentPath, $query, $queryParams) {
            $tagService = new \App\Services\TagService($resolvedContentPath);

            // Build options for URL generation
            $options = [];
            if (isset($queryParams['tag_page_path'])) {
                $options['tag_page_path'] = $queryParams['tag_page_path'];
                $options['pretty_urls'] = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);
            }

            $tags = $tagService->searchTags($contentPath, $query, $options);

            return [
                'success' => true,
                'content_path' => $contentPath,
                'query' => $query,
                'data' => array_map(fn($tag) => $tag->toArray(), $tags),
                'count' => count($tags),
                'timestamp' => date('c')
            ];
        }, $contentPath);
    }

    /**
     * Get items by tag (for tag pages)
     */
    public function getItemsByTag(Request $request, Response $response, array $args): Response
    {
        $queryParams = $request->getQueryParams();
        $contentPath = $queryParams['content_path'] ?? '';
        $tag = $args['tag'] ?? '';

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        if (empty($tag)) {
            return $this->errorResponse($response, 'Tag parameter is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($request, $contentPath, $tag) {
            $queryParams = $request->getQueryParams();

            // Build CMS options including tag URL configuration
            $cmsOptions = $this->config['cms_options'] ?? [];
            if (isset($queryParams['tag_page_path'])) {
                $cmsOptions['tag_page_path'] = $queryParams['tag_page_path'];
                $cmsOptions['pretty_urls'] = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);
            }

            $cms = CMSRepository::make($resolvedContentPath, $cmsOptions);

            // Build filters for tag
            $filters = $this->buildFilters($queryParams);
            $filters['tags'] = $tag; // Add tag filter

            $orderBy = $queryParams['orderBy'] ?? 'date';
            $direction = $queryParams['direction'] ?? 'desc';
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : null;
            $perPage = (int)($queryParams['per_page'] ?? 10);

            // Get collection name from content_path
            $collectionName = basename($contentPath);
            $result = $cms->getCollection($collectionName, $filters, $orderBy, $direction, $page, $perPage);

            $data = [
                'success' => true,
                'content_path' => $contentPath,
                'tag' => $tag,
                'data' => array_map([$this, 'serializeItem'], $result['items']),
                'timestamp' => date('c')
            ];

            // Add pagination metadata
            if ($page !== null) {
                $data['meta'] = $result['meta'];
            } else {
                $data['count'] = $result['count'];
            }

            return $data;
        }, $contentPath);
    }

    // ========================================
    // AUTHOR ENDPOINTS
    // ========================================

    /**
     * Get all authors
     */
    public function getAuthors(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $contentPath = $queryParams['content_path'] ?? '';

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($contentPath, $queryParams) {
            $authorService = new \App\Services\AuthorService($resolvedContentPath);

            // Build options for URL generation
            $options = [];
            if (isset($queryParams['author_page_path'])) {
                $options['author_page_path'] = $queryParams['author_page_path'];
                $options['pretty_urls'] = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);
            }

            $authors = $authorService->getAllAuthors($contentPath, $options);

            return [
                'success' => true,
                'content_path' => $contentPath,
                'data' => array_map(fn($author) => $author->toArray(), $authors),
                'count' => count($authors),
                'timestamp' => date('c')
            ];
        }, $contentPath);
    }

    /**
     * Get author statistics
     */
    public function getAuthorStats(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $contentPath = $queryParams['content_path'] ?? '';

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($contentPath) {
            $authorService = new \App\Services\AuthorService($resolvedContentPath);
            $stats = $authorService->getAuthorStats($contentPath);

            return [
                'success' => true,
                'content_path' => $contentPath,
                'stats' => $stats,
                'timestamp' => date('c')
            ];
        }, $contentPath);
    }

    /**
     * Search authors
     */
    public function searchAuthors(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $contentPath = $queryParams['content_path'] ?? '';
        $query = $queryParams['q'] ?? '';

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        if (empty($query)) {
            return $this->errorResponse($response, 'Search query (q) is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($contentPath, $query, $queryParams) {
            $authorService = new \App\Services\AuthorService($resolvedContentPath);

            // Build options for URL generation
            $options = [];
            if (isset($queryParams['author_page_path'])) {
                $options['author_page_path'] = $queryParams['author_page_path'];
                $options['pretty_urls'] = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);
            }

            $authors = $authorService->searchAuthors($contentPath, $query, $options);

            return [
                'success' => true,
                'content_path' => $contentPath,
                'query' => $query,
                'data' => array_map(fn($author) => $author->toArray(), $authors),
                'count' => count($authors),
                'timestamp' => date('c')
            ];
        }, $contentPath);
    }

    /**
     * Get items by author (for author pages)
     */
    public function getItemsByAuthor(Request $request, Response $response, array $args): Response
    {
        $queryParams = $request->getQueryParams();
        $contentPath = $queryParams['content_path'] ?? '';
        $author = $args['author'] ?? '';

        if (empty($contentPath)) {
            return $this->errorResponse($response, 'content_path parameter is required', 400);
        }

        if (empty($author)) {
            return $this->errorResponse($response, 'Author parameter is required', 400);
        }

        return $this->handleRequest($request, $response, function ($resolvedContentPath) use ($request, $contentPath, $author) {
            $queryParams = $request->getQueryParams();

            // Build CMS options including author URL configuration
            $cmsOptions = $this->config['cms_options'] ?? [];
            if (isset($queryParams['author_page_path'])) {
                $cmsOptions['author_page_path'] = $queryParams['author_page_path'];
                $cmsOptions['pretty_urls'] = in_array($queryParams['pretty_urls'] ?? 'true', ['true', '1', 1, true], true);
            }

            $cms = CMSRepository::make($resolvedContentPath, $cmsOptions);

            // Build filters for author
            $filters = $this->buildFilters($queryParams);
            $filters['author'] = $author; // Add author filter

            $orderBy = $queryParams['orderBy'] ?? 'date';
            $direction = $queryParams['direction'] ?? 'desc';
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : null;
            $perPage = (int)($queryParams['per_page'] ?? 10);

            // Get collection name from content_path
            $collectionName = basename($contentPath);
            $result = $cms->getCollection($collectionName, $filters, $orderBy, $direction, $page, $perPage);

            $data = [
                'success' => true,
                'content_path' => $contentPath,
                'author' => $author,
                'data' => array_map([$this, 'serializeItem'], $result['items']),
                'timestamp' => date('c')
            ];

            // Add pagination metadata
            if ($page !== null) {
                $data['meta'] = $result['meta'];
            } else {
                $data['count'] = $result['count'];
            }

            return $data;
        }, $contentPath);
    }

    /**
     * Cache management endpoint
     */
    public function manageCache(Request $request, Response $response): Response
    {
        return $this->handleRequest($request, $response, function ($contentPath) use ($request) {
            $cms = CMSRepository::make($contentPath, $this->config['cms_options'] ?? []);
            $queryParams = $request->getQueryParams();
            $action = $queryParams['action'] ?? 'stats';

            switch ($action) {
                case 'stats':
                    return [
                        'success' => true,
                        'action' => 'stats',
                        'cache_stats' => $cms->getCacheStats(),
                        'timestamp' => date('c')
                    ];

                case 'clear':
                    $collection = $queryParams['collection'] ?? null;
                    $cacheType = $queryParams['type'] ?? 'all'; // 'all', 'content', 'tags', 'authors'

                    $messages = [];

                    if ($cacheType === 'all' || $cacheType === 'content') {
                        if ($collection) {
                            $cms->clearCollectionCache($collection);
                            $messages[] = "Content cache cleared for collection: $collection";
                        } else {
                            $cms->clearCache();
                            $messages[] = "All content caches cleared";
                        }
                    }

                    if ($cacheType === 'all' || $cacheType === 'tags') {
                        $tagService = new \App\Services\TagService($contentPath);
                        $tagService->clearCache($collection ? $contentPath . '/' . $collection : null);
                        $messages[] = $collection ? "Tag cache cleared for collection: $collection" : "All tag caches cleared";
                    }

                    if ($cacheType === 'all' || $cacheType === 'authors') {
                        $authorService = new \App\Services\AuthorService($contentPath);
                        $authorService->clearCache($collection ? $contentPath . '/' . $collection : null);
                        $messages[] = $collection ? "Author cache cleared for collection: $collection" : "All author caches cleared";
                    }

                    return [
                        'success' => true,
                        'action' => 'clear',
                        'type' => $cacheType,
                        'messages' => $messages,
                        'timestamp' => date('c')
                    ];

                default:
                    throw new \Exception("Unknown action: $action. Use 'stats' or 'clear'", 400);
            }
        });
    }

    // ========================================
    // LEGACY ENDPOINTS (Individual Items)
    // ========================================

    /**
     * Get an individual item by file path from anywhere in the CMS
     * @deprecated Use collection-based endpoints instead
     */
    public function getIndividualItem(Request $request, Response $response, array $args): Response
    {
        $filePath = $args['file_path'] ?? '';
        if (empty($filePath)) {
            return $this->errorResponse($response, 'File path is required', 400);
        }

        return $this->handleRequest($request, $response, function ($contentPath) use ($request, $filePath) {
            $cms = CMSRepository::make($contentPath, $this->config['cms_options'] ?? []);
            $queryParams = $request->getQueryParams();
            $options = $this->buildOptions($queryParams);

            $item = $cms->getIndividualItem($filePath, $options);

            if (!$item) {
                throw new \Exception('Item not found', 404);
            }

            return [
                'success' => true,
                'file_path' => $filePath,
                'data' => $this->serializeItem($item),
                'timestamp' => date('c')
            ];
        });
    }

    /**
     * Get an individual item by slug from anywhere in the CMS
     * @deprecated Use collection-based endpoints instead
     */
    public function getIndividualItemBySlug(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        if (empty($slug)) {
            return $this->errorResponse($response, 'Slug is required', 400);
        }

        return $this->handleRequest($request, $response, function ($contentPath) use ($request, $slug) {
            $cms = CMSRepository::make($contentPath, $this->config['cms_options'] ?? []);
            $queryParams = $request->getQueryParams();
            $options = $this->buildOptions($queryParams);

            $item = $cms->getIndividualItemBySlug($slug, $options);

            if (!$item) {
                throw new \Exception('Item not found', 404);
            }

            return [
                'success' => true,
                'slug' => $slug,
                'data' => $this->serializeItem($item),
                'timestamp' => date('c')
            ];
        });
    }

    // ========================================
    // PRIVATE HELPER METHODS
    // ========================================

    /**
     * Serialize an item for JSON output
     */
    private function serializeItem($item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if (is_object($item)) {
            if (method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            if (method_exists($item, 'jsonSerialize')) {
                return $item->jsonSerialize();
            }
            return json_decode(json_encode($item), true);
        }

        return ['data' => $item];
    }
}
