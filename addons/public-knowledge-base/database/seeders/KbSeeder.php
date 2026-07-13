<?php

namespace Addons\PublicKnowledgeBase\Database\Seeders;

use Addons\PublicKnowledgeBase\Models\KbArticle;
use Addons\PublicKnowledgeBase\Models\KbCategory;
use App\Models\Admin;
use Illuminate\Database\Seeder;

class KbSeeder extends Seeder
{
    public function run(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('kb_categories')) {
            return;
        }

        if (KbCategory::count() > 0) {
            return;
        }

        $adminId = Admin::query()->first()?->id;

        $categories = [
            ['slug' => 'getting-started', 'name' => 'Getting Started', 'icon' => 'ti ti-rocket', 'sort_order' => 1, 'is_active' => true],
            ['slug' => 'account-billing', 'name' => 'Account & Billing', 'icon' => 'ti ti-credit-card', 'sort_order' => 2, 'is_active' => true],
            ['slug' => 'troubleshooting', 'name' => 'Troubleshooting', 'icon' => 'ti ti-tool', 'sort_order' => 3, 'is_active' => true],
        ];

        $articles = [
            'getting-started' => [
                [
                    'title' => 'How to create your first AI document',
                    'excerpt' => 'Learn how to generate your first piece of AI content in under 2 minutes.',
                    'body' => '<h2>Getting Started</h2><p>Welcome to ' . settings('app_name') . '! This guide will walk you through generating your first AI-powered document.</p><h3>Step 1: Choose a tool</h3><p>Navigate to the AI Tools section from the main dashboard. Browse the available tools and select one that matches your needs — Blog Writer, Social Media Post, or Document Generator.</p><h3>Step 2: Fill in the form</h3><p>Enter your topic, tone preferences, target audience, and any specific guidelines. The more detail you provide, the better the output will be.</p><h3>Step 3: Generate & Refine</h3><p>Click the Generate button and your content will appear within seconds. You can then refine it using the built-in editor or ask the AI to regenerate with different parameters.</p>',
                    'status' => 'published',
                    'published_at' => now(),
                ],
                [
                    'title' => 'Understanding credits and usage limits',
                    'excerpt' => 'A clear breakdown of how AI generation credits work across different plans.',
                    'body' => '<h2>How Credits Work</h2><p>Every AI generation on ' . settings('app_name') . ' consumes credits based on the model used, token count, and output length.</p><h3>Free Plan</h3><p>Free users get 1,000 credits per month, enough for approximately 10-20 generations depending on length.</p><h3>Pro Plan</h3><p>Pro subscribers receive 10,000 credits monthly, with unused credits rolling over up to 2x your monthly limit.</p><h3>Checking Your Usage</h3><p>Visit your Dashboard → Usage to see real-time credit consumption, broken down by tool and date.</p>',
                    'status' => 'published',
                    'published_at' => now(),
                ],
            ],
            'account-billing' => [
                [
                    'title' => 'How to upgrade your subscription',
                    'excerpt' => 'Step-by-step guide to upgrading from Free to Pro plan.',
                    'body' => '<h2>Upgrading Your Plan</h2><p>Ready for more AI power? Upgrading is quick and secure.</p><h3>Step 1: Go to Pricing</h3><p>Navigate to the Pricing page from the top menu to compare available plans.</p><h3>Step 2: Select Plan</h3><p>Choose between our Pro monthly or annual plans. Annual plans save you 20%.</p><h3>Step 3: Payment</h3><p>We accept credit cards, PayPal, and bank transfers. Your payment is processed securely via Stripe.</p><h3>Step 4: Confirmation</h3><p>You will receive an email confirmation and your upgraded features activate immediately.</p>',
                    'status' => 'published',
                    'published_at' => now(),
                ],
                [
                    'title' => 'Managing your billing and invoices',
                    'excerpt' => 'How to view, download, and update your billing information.',
                    'body' => '<h2>Billing Management</h2><p>All your billing information is accessible from your Account → Billing page.</p><h3>Viewing Invoices</h3><p>Every payment generates an invoice automatically. You can view and download PDFs for all past payments.</p><h3>Updating Payment Method</h3><p>Add or change your credit card at any time. Your new payment method will be used for the next billing cycle.</p><h3>Cancelling</h3><p>You can cancel your subscription anytime from the Billing page. Your access continues until the end of your current billing period.</p>',
                    'status' => 'published',
                    'published_at' => now(),
                ],
            ],
            'troubleshooting' => [
                [
                    'title' => 'AI generation failed — what to do',
                    'excerpt' => 'Common causes and fixes when your AI generation does not complete.',
                    'body' => '<h2>Why Generations Fail</h2><p>AI generations can fail for a few common reasons. Here is how to diagnose and fix each one.</p><h3>Insufficient Credits</h3><p>Check your credit balance on the Dashboard. If you are out of credits, consider upgrading your plan or waiting for your monthly reset.</p><h3>Network Timeout</h3><p>Large generations may time out. Try reducing the output length or splitting your request into smaller parts.</p><h3>Content Filter</h3><p>Our AI safety filters may block certain content. Adjust your input to be more specific or less sensitive.</p><h3>Still Stuck?</h3><p>Contact support via the Help widget or email us at support@' . parse_url(url('/'), PHP_URL_HOST) . '. We respond within 4 hours.</p>',
                    'status' => 'published',
                    'published_at' => now(),
                ],
                [
                    'title' => 'Clearing your browser cache and cookies',
                    'excerpt' => 'Simple steps to resolve UI issues by clearing browser data.',
                    'body' => '<h2>Browser Cache Issues</h2><p>Sometimes old cached files can cause display issues or login problems. Here is how to clear them.</p><h3>Chrome / Edge</h3><p>Press Ctrl+Shift+Delete → select "Cached images and files" and "Cookies" → choose "All time" → Clear data.</p><h3>Firefox</h3><p>Press Ctrl+Shift+Delete → select "Everything" for time range → check "Cache" and "Cookies" → Clear Now.</p><h3>Safari</h3><p>Safari → Preferences → Privacy → Manage Website Data → Remove All.</p><p>After clearing, restart your browser and log back in. Most UI glitches resolve after this step.</p>',
                    'status' => 'published',
                    'published_at' => now(),
                ],
            ],
        ];

        foreach ($categories as $cat) {
            $category = KbCategory::create($cat);

            if (isset($articles[$cat['slug']])) {
                foreach ($articles[$cat['slug']] as $art) {
                    $article = KbArticle::create([
                        'kb_category_id' => $category->id,
                        'title' => $art['title'],
                        'excerpt' => $art['excerpt'],
                        'body' => $art['body'],
                        'status' => $art['status'],
                        'published_at' => $art['published_at'],
                        'created_by' => $adminId,
                    ]);

                    \Addons\PublicKnowledgeBase\Jobs\IngestKbArticle::dispatch($article->id)
                        ->onQueue('embeddings');
                }
            }
        }
    }
}
