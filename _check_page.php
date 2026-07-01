<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$page = \App\Models\Page::where('slug', 'privacy-policy')->first();
if ($page) {
    echo json_encode([
        'container_width' => $page->container_width,
        'show_featured_image' => $page->show_featured_image,
        'featured_image' => $page->featured_image,
        'show_title' => $page->show_title,
        'title' => $page->title,
    ], JSON_PRETTY_PRINT);
} else {
    echo "Page not found";
}
