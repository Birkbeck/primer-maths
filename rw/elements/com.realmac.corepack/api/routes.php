<?php

/**
 * API Routes Configuration
 * SecurityMiddleware is applied globally to ALL routes
 */

use App\Controllers\ApiController;
use App\Controllers\CMSController;
use App\Controllers\EmailFormController;
use App\Controllers\WebhookController;
use App\Middleware\SecurityMiddleware;
use App\Middleware\SpamProtectionMiddleware;
use App\Middleware\SpamProtectionMiddlewareFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

return function ($app, $controller, $cmsController, $emailFormController, $webhookController, $spamProtectionMiddlewareFactory = null) {

    // ========================================
    // GLOBAL SECURITY MIDDLEWARE
    // ========================================
    // Apply SecurityMiddleware to ALL routes - blocks external requests
    $app->add(new SecurityMiddleware());

    // ========================================
    // ALL ROUTES (All protected by SecurityMiddleware)
    // ========================================

    // Root endpoint
    $app->any('/', function (Request $request, Response $response) use ($controller) {
        return $controller->root($request, $response);
    });

    // API status endpoint
    $app->get('/status', function (Request $request, Response $response) use ($controller) {
        return $controller->status($request, $response);
    });

    // Admin API endpoint
    $app->get('/content-path', function (Request $request, Response $response) use ($controller) {
        return $controller->contentPath($request, $response);
    });

    // FormController removed - functionality split between /email and /webhook endpoints

    // Email form routes (all protected)
    $app->group('/email', function (RouteCollectorProxy $group) use ($emailFormController, $spamProtectionMiddlewareFactory) {
        // Apply spam protection middleware to the submit route
        if ($spamProtectionMiddlewareFactory) {
            $group->post('', [$emailFormController, 'submit'])
                ->add($spamProtectionMiddlewareFactory->forEmailForms());
        } else {
            $group->post('', [$emailFormController, 'submit']);
        }

        $group->get('/status', [$emailFormController, 'status']);
        $group->get('', [$emailFormController, 'info']);
    });

    // Webhook form routes (all protected)
    $app->group('/webhook', function (RouteCollectorProxy $group) use ($webhookController, $spamProtectionMiddlewareFactory) {
        // Apply spam protection middleware to the submit route
        if ($spamProtectionMiddlewareFactory) {
            $group->post('', [$webhookController, 'submit'])
                ->add($spamProtectionMiddlewareFactory->forWebhookForms());
        } else {
            $group->post('', [$webhookController, 'submit']);
        }

        $group->get('/status', [$webhookController, 'status']);
        $group->post('/test', [$webhookController, 'test']);
        $group->get('', [$webhookController, 'info']);
    });

    // CMS API routes
    $app->group('/cms', function (RouteCollectorProxy $group) use ($cmsController) {
        // Collection endpoints
        $group->get('/collections', [$cmsController, 'getCollections']);
        $group->get('/collections/{collection}', [$cmsController, 'getCollectionItems']);
        $group->get('/collections/{collection}/items/{slug}', [$cmsController, 'getItem']);
        $group->get('/collections/{collection}/items/{slug}/related', [$cmsController, 'getRelatedItems']);

        // RSS and Sitemap endpoints
        $group->get('/collections/{collection}/rss', [$cmsController, 'getRssFeed']);
        $group->get('/collections/{collection}/sitemap', [$cmsController, 'getSitemap']);

        // Generic RSS and Sitemap endpoints (content_path via query param)
        $group->get('/rss', [$cmsController, 'getGenericRssFeed']);
        $group->get('/sitemap', [$cmsController, 'getGenericSitemap']);

        // Search endpoints
        $group->get('/search', [$cmsController, 'search']);
        $group->get('/search/suggestions', [$cmsController, 'getSearchSuggestions']);
        $group->get('/search/analytics', [$cmsController, 'getSearchAnalytics']);

        // Individual item endpoints
        $group->get('/items/path/{file_path:.+}', [$cmsController, 'getIndividualItem']);
        $group->get('/items/slug/{slug}', [$cmsController, 'getIndividualItemBySlug']);

        // Tag endpoints
        $group->get('/tags', [$cmsController, 'getTags']);
        $group->get('/tags/stats', [$cmsController, 'getTagStats']);
        $group->get('/tags/search', [$cmsController, 'searchTags']);
        $group->get('/tags/{tag}/items', [$cmsController, 'getItemsByTag']);

        // Cache management
        $group->get('/cache', [$cmsController, 'manageCache']);
    });

    // ========================================
    // CATCH-ALL ROUTE
    // ========================================

    // Catch-all route to handle dynamic path matching
    $app->any('{path:.+}', function (Request $request, Response $response, array $args) use ($controller) {
        return $controller->root($request, $response);
    });
};
