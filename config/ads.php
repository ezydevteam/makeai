<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ad Zones
    |--------------------------------------------------------------------------
    |
    | The key is stored on ads.zone; the label is what admins pick from. Labels
    | describe WHERE the slot renders, so the admin list is self-explanatory.
    |
    | Keys are contracts — an existing ad row keeps whatever key it was saved
    | with, and AdRequest validates against these keys. Rename labels freely;
    | changing a key orphans existing ads.
    |
    */

    'zones' => [
        'header_banner' => 'Header banner — above the site header (728x90)',
        'footer_banner' => 'Footer banner — above the site footer (728x90)',
        'sidebar_top' => 'Sidebar top (300x250)',
        'sidebar_bottom' => 'Sidebar bottom (300x250)',
        'between_posts' => 'Blog — between post cards',
        'blog_after_content' => 'Blog — after post content',
        'between_ai_tools' => 'Tools directory — between tool cards',
        'tool_page_top' => 'Tool page — above the tool',
        'tool_page_bottom' => 'Tool page — before the tabs',
        'chat_banner' => 'Chat banner',
        'dashboard_top' => 'Dashboard top',
        'custom_zone_1' => 'Custom zone 1',
        'custom_zone_2' => 'Custom zone 2',
    ],
];
