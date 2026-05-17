<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'content' => '<h2>1. Information We Collect</h2><p>We collect information you provide directly to us, such as when you create or modify your account, request support, or otherwise communicate with us.</p><h2>2. How We Use Your Information</h2><p>We may use the information we collect to provide, maintain, and improve our services, and to develop new tools and features.</p><h2>3. Data Security</h2><p>We use reasonable measures to help protect information about you from loss, theft, misuse, and unauthorized access.</p>',
                'excerpt' => 'Our commitment to protecting your personal data and privacy.',
                'template' => 'default',
                'status' => 'published',
                'is_system' => true,
            ],
            [
                'title' => 'Terms of Service',
                'slug' => 'terms-of-service',
                'content' => '<h2>1. Acceptance of Terms</h2><p>By accessing or using our services, you agree to be bound by these terms.</p><h2>2. Use of Services</h2><p>You may use our services only as permitted by law and these terms.</p><h2>3. Limitation of Liability</h2><p>To the maximum extent permitted by law, MakeAI shall not be liable for any indirect, incidental, special, or consequential damages.</p>',
                'excerpt' => 'The legal agreement between you and MakeAI for using our services.',
                'template' => 'default',
                'status' => 'published',
                'is_system' => true,
            ],
            [
                'title' => 'Contact Us',
                'slug' => 'contact',
                'content' => '<h2>We\'re Here to Help</h2><p>Have a question about our features, pricing, or anything else? Our team is ready to answer all your questions.</p>',
                'excerpt' => 'Get in touch with the MakeAI support team.',
                'template' => 'default',
                'status' => 'published',
                'is_system' => true,
            ],
            [
                'title' => 'About Us',
                'slug' => 'about',
                'content' => '<h2>Empowering Creativity with AI</h2><p>MakeAI is at the forefront of the generative AI revolution. We provide tools that help creators, developers, and businesses unlock their full potential using the power of artificial intelligence.</p><h2>Our Mission</h2><p>To democratize access to advanced AI technology and enable everyone to create high-quality content effortlessly.</p>',
                'excerpt' => 'Discover the story behind MakeAI and our mission to empower creators.',
                'template' => 'default',
                'status' => 'published',
                'is_system' => true,
            ],
            [
                'title' => 'Frequently Asked Questions',
                'slug' => 'faq',
                'content' => '<h3>What is MakeAI?</h3><p>MakeAI is an all-in-one platform for generative AI, offering tools for text generation, image creation, and more.</p><h3>Is there a free trial?</h3><p>Yes, we offer a free plan with limited credits to help you get started.</p><h3>How do I cancel my subscription?</h3><p>You can cancel your subscription at any time from your account settings.</p>',
                'excerpt' => 'Find answers to the most common questions about MakeAI.',
                'template' => 'default',
                'status' => 'published',
                'is_system' => true,
            ],
            [
                'title' => 'Cookie Policy',
                'slug' => 'cookie-policy',
                'content' => '<h2>What are Cookies?</h2><p>Cookies are small text files that are stored on your device when you visit a website.</p><h2>How We Use Cookies</h2><p>We use cookies to understand how you use our site and to improve your experience.</p>',
                'excerpt' => 'How we use cookies to improve your experience on our platform.',
                'template' => 'default',
                'status' => 'published',
                'is_system' => true,
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'show_title' => true,
                    'show_breadcrumbs' => true,
                    'show_featured_image' => false,
                    'show_sidebar' => false,
                    'sidebar_position' => 'left',
                    'container_width' => 'default',
                    'published_at' => now(),
                ])
            );
        }
    }
}
