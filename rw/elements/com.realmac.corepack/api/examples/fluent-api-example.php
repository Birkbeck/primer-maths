<?php

/**
 * Example: Using Tag Page Path in CollectionBuilder Fluent API
 */

// Include the CMS bootstrap
require_once __DIR__ . '/../cms.php';

// Example 1: Simple tag page path
$articles = cms()->collection($collectionPath)
    ->tagPagePath('/tags')  // Creates /tags/{tag-slug} URLs
    ->orderBy($orderBy, $orderDirection)
    ->get();

// Example 2: Custom tag page path
$newsArticles = cms()->collection('/content/news')
    ->tagPagePath('/news/categories')  // Creates /news/categories/{tag-slug} URLs
    ->prettyUrls(true)
    ->featured(true)
    ->latest()
    ->limit(10)
    ->all();

// Example 3: Non-pretty URLs
$blogPosts = cms()->collection('/content/blog')
    ->tagPagePath('/tag-page.php')  // Creates /tag-page.php?tag={slug} URLs
    ->prettyUrls(false)
    ->orderBy('date', 'desc')
    ->get();

// Example 4: Chaining with other methods
$featuredArticles = cms()->collection('/content/articles')
    ->tagPagePath('/topics')
    ->featured(true)
    ->status('published')
    ->after('2024-01-01')
    ->orderBy('date', 'desc')
    ->paginate(1, 5);

// Usage in templates - tags now include URLs
foreach ($articles->items as $article) {
    echo "<h2>{$article->title}</h2>";
    echo "<div class='tags'>";

    foreach ($article->tags as $tag) {
        // If tag page path is configured, each tag will have a 'url' property
        if (isset($tag['url'])) {
            echo "<a href='{$tag['url']}' class='tag'>{$tag['name']}</a> ";
        } else {
            echo "<span class='tag'>{$tag['name']}</span> ";
        }
    }

    echo "</div>";
}

// Example 5: RSS feeds with tag URLs
$rssFeed = cms()->collection('/content/articles')
    ->tagPagePath('/tags')
    ->latest()
    ->limit(20)
    ->rss('My Blog', 'Latest articles from my blog', 'https://example.com');

// Example 6: Working with individual tags
$webDevArticles = cms()->collection('/content/articles')
    ->tagPagePath('/categories')
    ->tags('Web Development')
    ->latest()
    ->get();

// Each article's tags will now include URLs like /categories/web-development
foreach ($webDevArticles->items as $article) {
    foreach ($article->tags as $tag) {
        echo "Tag: {$tag['name']} - URL: {$tag['url']}\n";
    }
}
