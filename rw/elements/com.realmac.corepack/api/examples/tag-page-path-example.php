<?php

/**
 * Example: Testing Tag Page Path functionality
 */

// Include the CMS bootstrap
require_once __DIR__ . '/../cms.php';

// Test 1: With tag page path set
echo "=== Test 1: With tag page path '/tags' ===\n";
$articles = cms()->collection('/content/articles')
    ->tagPagePath('/tags')
    ->prettyUrls(true)
    ->limit(3)
    ->get();

foreach ($articles->items as $article) {
    echo "Article: {$article->title}\n";
    foreach ($article->tags as $tag) {
        if (is_array($tag) && isset($tag['url'])) {
            echo "  Tag: {$tag['name']} -> {$tag['url']}\n";
        } else {
            echo "  Tag: {$tag} (no URL)\n";
        }
    }
    echo "\n";
}

// Test 2: With empty tag page path
echo "=== Test 2: With empty tag page path ===\n";
$articles2 = cms()->collection('/content/articles')
    ->tagPagePath('')
    ->prettyUrls(true)
    ->limit(3)
    ->get();

foreach ($articles2->items as $article) {
    echo "Article: {$article->title}\n";
    foreach ($article->tags as $tag) {
        if (is_array($tag) && isset($tag['url'])) {
            echo "  Tag: {$tag['name']} -> {$tag['url']}\n";
        } else {
            echo "  Tag: {$tag} (no URL)\n";
        }
    }
    echo "\n";
}

// Test 3: Non-pretty URLs
echo "=== Test 3: Non-pretty URLs ===\n";
$articles3 = cms()->collection('/content/articles')
    ->tagPagePath('/tag-page.php')
    ->prettyUrls(false)
    ->limit(3)
    ->get();

foreach ($articles3->items as $article) {
    echo "Article: {$article->title}\n";
    foreach ($article->tags as $tag) {
        if (is_array($tag) && isset($tag['url'])) {
            echo "  Tag: {$tag['name']} -> {$tag['url']}\n";
        } else {
            echo "  Tag: {$tag} (no URL)\n";
        }
    }
    echo "\n";
}
