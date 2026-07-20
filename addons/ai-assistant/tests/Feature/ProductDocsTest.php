<?php

namespace Addons\AiAssistant\Tests\Feature;

require_once dirname(__DIR__) . '/AssistantTestCase.php';

use Addons\AiAssistant\Services\SlashCommandRegistry;
use Addons\AiAssistant\Support\ProductDocs;
use Addons\AiAssistant\Tests\AssistantTestCase;
use App\Services\Docs\ProductDocsService;

/**
 * The admin assistant grounds on the MakeAI documentation that ships inside the release.
 *
 * The bug this closes: BOTH surfaces used to auto-ground on the buyer's public Knowledge
 * Base. So an admin asking "how do I add a provider key?" was answered from the help centre
 * the buyer wrote for their own *customers* — and, on the many installs with no KB addon at
 * all, from nothing. The admin assistant had no product knowledge and invented admin screens.
 */
class ProductDocsTest extends AssistantTestCase
{
    /** The corpus ships in the release, so it is present without installing anything. */
    public function test_the_bundled_corpus_is_available_out_of_the_box(): void
    {
        $this->assertTrue(ProductDocs::available());
        $this->assertNotEmpty((new ProductDocsService)->pages());
    }

    // ─── retrieval ────────────────────────────────────────────

    public function test_a_question_retrieves_the_page_that_answers_it(): void
    {
        $docs = new ProductDocsService;

        $hit = $docs->search('how do I add an OpenAI api key');

        $this->assertSame(
            'ai-provider-keys',
            $hit['sources'][0]['slug'] ?? null,
            'the provider-key page should be the top hit for a provider-key question'
        );
        $this->assertStringContainsString('Test Connection', $hit['context']);
    }

    /**
     * Retrieval must be able to say "nothing". A corpus that always returns *something*
     * would inject an irrelevant page into every prompt and invite the model to answer
     * confidently from it — worse than answering ungrounded.
     */
    public function test_an_unrelated_question_retrieves_nothing(): void
    {
        $hit = (new ProductDocsService)->search('what is the capital of Portugal');

        $this->assertSame('', $hit['context']);
        $this->assertSame([], $hit['sources']);
    }

    // ─── license filtering ────────────────────────────────────

    /**
     * Extended-only pages are invisible on a Regular license. Without this the assistant
     * would walk an admin through configuring subscriptions on a license that does not
     * include them: a confident, well-cited, completely unusable answer.
     */
    public function test_extended_only_pages_are_hidden_on_a_regular_license(): void
    {
        $question = 'how do I enable subscriptions and create a paid plan';

        $this->useQuotaMode(); // Regular license
        $regular = (new ProductDocsService)->search($question);

        $this->assertNotContains(
            'subscriptions-and-plans',
            array_column($regular['sources'], 'slug'),
            'a Regular install must never be cited an Extended-only page'
        );

        $this->useMeteredMode(); // Extended license + subscriptions on
        $extended = (new ProductDocsService)->search($question);

        $this->assertContains(
            'subscriptions-and-plans',
            array_column($extended['sources'], 'slug'),
            'the same page must surface once the license permits it'
        );
    }

    /**
     * The license filter runs at query time, never baked into the cached index — otherwise
     * an install upgraded from Regular to Extended would keep hiding pages until the cache
     * happened to expire.
     */
    public function test_upgrading_the_license_surfaces_extended_pages_without_reindexing(): void
    {
        $docs = new ProductDocsService;
        $question = 'how do I enable subscriptions and create a paid plan';

        $this->useQuotaMode();
        $docs->search($question); // warms the index under the Regular license

        $this->useMeteredMode();

        $this->assertContains(
            'subscriptions-and-plans',
            array_column($docs->search($question)['sources'], 'slug'),
            'the Extended page must appear on the same (already-warm) service instance'
        );
    }

    // ─── the admin surface ────────────────────────────────────

    public function test_admin_chat_is_grounded_on_the_product_docs(): void
    {
        addon_setting_set('ai-assistant', 'admin_enabled', true, 'boolean');
        $this->useMeteredMode();
        $this->seedChatModel();
        $this->fakeProvider(['Open Admin → AI → Providers.']);

        $this->actingAsAdmin();

        $response = $this->post(route('addon.ai-assistant.admin.chat'), [
            'message' => 'how do I add an OpenAI api key',
            'session_id' => 'sess-docs-admin',
        ]);

        $sources = collect($this->frames($response))->firstWhere('type', 'sources');

        $this->assertNotNull($sources, 'admin chat should cite the documentation it was grounded on');
        $this->assertContains('ai-provider-keys', array_column($sources['sources'], 'slug'));
    }

    /**
     * Citations must arrive AFTER the answer, not before it.
     *
     * The sources frame used to be emitted immediately after `ready` — so the widget painted
     * "Sources: …" beneath a message that was still showing the thinking dots, offering the
     * user references to an answer that did not exist yet and, on a provider error, never
     * would. A citation is a claim about an answer; there has to be an answer first.
     */
    public function test_citations_are_sent_only_after_the_answer(): void
    {
        addon_setting_set('ai-assistant', 'admin_enabled', true, 'boolean');
        $this->useMeteredMode();
        $this->seedChatModel();
        $this->fakeProvider(['Open Admin → AI → Providers.']);

        $this->actingAsAdmin();

        $response = $this->post(route('addon.ai-assistant.admin.chat'), [
            'message' => 'how do I add an OpenAI api key',
            'session_id' => 'sess-docs-order',
        ]);

        $types = $this->frameTypes($response);

        $firstToken = array_search('token', $types, true);
        $sources = array_search('sources', $types, true);

        $this->assertNotFalse($firstToken, 'the answer should have streamed');
        $this->assertNotFalse($sources, 'the answer should have been cited');

        $this->assertGreaterThan(
            $firstToken,
            $sources,
            'sources must not be emitted before the answer has started streaming'
        );
        $this->assertSame('done', $types[$sources + 1] ?? null, 'sources should be the last frame before done');
    }

    /**
     * The public widget must never be handed the MakeAI admin manual. Its audience is the
     * buyer's customers, and the KB addon is absent here, so it grounds on nothing at all.
     */
    public function test_user_chat_is_never_grounded_on_the_product_docs(): void
    {
        $this->enableFrontend('all');
        $this->useMeteredMode();
        $this->setLimits(guest: 0, member: 0, pro: 0);
        $this->seedChatModel();
        $this->fakeProvider(['An answer for a visitor.']);

        $response = $this->actingAs($this->freeUser())->post(route('addon.ai-assistant.chat'), [
            'message' => 'how do I add an OpenAI api key',
            'session_id' => 'sess-docs-user',
        ]);

        $this->assertNotContains(
            'sources',
            $this->frameTypes($response),
            'the public widget must not be cited MakeAI admin documentation'
        );
    }

    // ─── the /docs command ────────────────────────────────────

    /**
     * /docs is scope-dependent. In the admin panel it is backed by the bundled docs, so it
     * works with no addon installed; on the public surface it is backed by the KB addon,
     * which is absent here — and there it stays hidden rather than being offered and failing.
     */
    public function test_docs_command_is_offered_to_admins_without_the_kb_addon(): void
    {
        $commands = app(SlashCommandRegistry::class);

        $this->assertContains('docs', array_column($commands->list(SlashCommandRegistry::SCOPE_ADMIN), 'name'));
        $this->assertNotContains('docs', array_column($commands->list(SlashCommandRegistry::SCOPE_USER), 'name'));
    }

    public function test_admin_docs_command_grounds_on_the_documentation(): void
    {
        $result = app(SlashCommandRegistry::class)->handle(
            'docs',
            'how do I set up cron',
            SlashCommandRegistry::SCOPE_ADMIN,
            null,
        );

        $this->assertNotNull($result);
        $this->assertFalse($result->isDirectReply(), '/docs must ground and let the model answer, not reply canned');
        $this->assertStringContainsString('MakeAI Documentation', $result->systemAppend);
        $this->assertContains('cron-and-scheduling', array_column($result->sources, 'slug'));
    }

    // ─── citation links ───────────────────────────────────────

    /**
     * A citation links to the docs site ONLY when the page declares its published filename.
     *
     * The docs site is a flat set of *.html files whose names do not track our slugs, so
     * deriving a link from the slug would mint a confident 404 for every page not published
     * there. A page with no `page:` renders as a plain label — the widget already handles a
     * source with no url. No dead citations, ever.
     */
    public function test_only_published_pages_are_linked(): void
    {
        $dir = sys_get_temp_dir() . '/makeai-docs-links-' . uniqid();
        mkdir($dir);

        try {
            file_put_contents($dir . '/published.md', <<<'MD'
                ---
                title: Installing MakeAI
                slug: installing
                page: installation-guide.html
                keywords: [zaphod]
                ---
                ## Running the installer
                Follow the installer to set up zaphod.
                MD);

            file_put_contents($dir . '/unpublished.md', <<<'MD'
                ---
                title: Not On The Site Yet
                slug: unpublished
                keywords: [zaphod]
                ---
                ## Something
                This zaphod page has no published counterpart.
                MD);

            // Asserted through pages() rather than search(): linking is not a ranking
            // concern, and a two-page corpus cannot produce meaningful BM25 scores anyway
            // (a term present in every chunk has an IDF of almost zero, by design).
            $pages = collect((new ProductDocsService($dir))->pages())->keyBy('slug');

            $this->assertSame(
                'https://makeai-docs.ezydev.net/installation-guide.html',
                $pages['installing']['url'],
                'a published page links to its real filename on the docs site'
            );

            $this->assertNull(
                $pages['unpublished']['url'],
                'a page with no published counterpart must not be given a fabricated link'
            );
        } finally {
            array_map('unlink', glob($dir . '/*.md') ?: []);
            rmdir($dir);
        }
    }

    // ─── golden questions (Phase 1 admin-sidebar coverage) ────

    /**
     * One real admin question per page added in the Phase 1 admin-sidebar documentation
     * pass, asserting each is the top hit. A page that cannot be retrieved by the question
     * it exists to answer is not finished, per the docs writing guidelines.
     */
    public function test_golden_questions_retrieve_the_right_regular_license_page(): void
    {
        $docs = new ProductDocsService;

        $cases = [
            'what does the dashboard show me' => 'dashboard-overview',
            'whats the difference between users and admins' => 'managing-users-and-admins',
            'how do I create a custom admin role' => 'roles-and-permissions',
            'how do I build a new AI tool' => 'managing-ai-tools-and-categories',
            'how do I set a daily spending limit for AI' => 'daily-ai-budget',
            'how do I restrict an AI tool to paying customers only' => 'ai-access-control',
            'how do I let customers chat with a PDF document' => 'rag-settings',
            'how do I connect a plagiarism checker' => 'ai-integrations-and-usage-logs',
            'how do I add a custom page like an About page' => 'managing-pages',
            'how do I moderate blog comments' => 'blog-posts-and-comments',
            'how do I add a customer testimonial' => 'testimonials-and-faqs',
            'how do I reply to a tool review' => 'tool-reviews-and-messages',
            'how do I set up support ticket departments' => 'support-ticket-system',
            'how do I upload my logo' => 'branding-your-site',
            'how do I apply a theme preset' => 'branding-your-site',
            'how do I rearrange my homepage sections' => 'customizing-homepage',
            'how do I build a header navigation menu' => 'custom-menus-and-sidebar',
            'how do I install a new addon' => 'addons-install',
            'how do I change my site name and default currency' => 'core-site-settings',
            'how do I set up the cookie consent banner' => 'gdpr-compliance',
            'how do I switch to S3 storage' => 'storage-and-file-uploads',
            'how do I let customers log in with google' => 'oauth-and-extensions',
            'how do I set up real time notifications with reverb' => 'notification-delivery-settings',
            'why is my health check failing' => 'system-health-and-maintenance',
            'how do I verify my purchase code' => 'verifying-your-license',
            'how do I update to the latest version safely' => 'updating-your-site',
            'how do I connect an smtp email service' => 'setting-up-email',
            'how do I edit the welcome email template' => 'email-templates-and-logs',
            'how do I add a new language translation' => 'managing-languages',
            'how do I export my customer list to a spreadsheet' => 'export-center',
            'how do I send a newsletter campaign' => 'newsletter-and-announcements',
            'how do I send a bulk sms to my customers' => 'bulk-sms-campaigns',
            'how do I make ai chatbot my homepage' => 'customizing-homepage',
        ];

        foreach ($cases as $question => $expectedSlug) {
            $hit = $docs->search($question);

            $this->assertSame(
                $expectedSlug,
                $hit['sources'][0]['slug'] ?? null,
                "expected \"{$question}\" to retrieve \"{$expectedSlug}\", got: " . json_encode(array_column($hit['sources'], 'slug'))
            );
        }
    }

    /** Same as above, for pages tagged license: extended — must be searched under metered mode. */
    public function test_golden_questions_retrieve_the_right_extended_license_page(): void
    {
        $this->useMeteredMode();
        $docs = new ProductDocsService;

        $cases = [
            'how do I set the price and credits for a plan' => 'managing-pricing-plans',
            'how do I connect stripe for payments' => 'payment-gateways',
            'how do I create a discount coupon code' => 'coupons-transactions-and-credit-settings',
            'how does the affiliate commission get paid out' => 'affiliate-program',
        ];

        foreach ($cases as $question => $expectedSlug) {
            $hit = $docs->search($question);

            $this->assertSame(
                $expectedSlug,
                $hit['sources'][0]['slug'] ?? null,
                "expected \"{$question}\" to retrieve \"{$expectedSlug}\", got: " . json_encode(array_column($hit['sources'], 'slug'))
            );
        }
    }

    /** One golden question per addon page added in the Phase 2 addon-documentation pass. */
    public function test_golden_questions_retrieve_the_right_addon_page(): void
    {
        $docs = new ProductDocsService;

        $cases = [
            'how do I move the assistant widget to the header' => 'ai-assistant-addon-setup',
            'what does the slash command forward slash docs do' => 'ai-assistant-slash-commands-and-feedback',
            'how do I let guests use the chatbot' => 'ai-chatbot-addon-setup',
            'who pays for chatbot credits guests or logged in users' => 'ai-chatbot-analytics-and-usage',
            'how do I turn on the public help center' => 'knowledge-base-addon-setup',
            'how do I write a new help center article' => 'knowledge-base-articles-and-categories',
            'how do I turn on background removal for images' => 'ai-image-pro-addon-setup',
            'how long are generated images kept before they are deleted' => 'ai-image-pro-limits-and-customization',
            'how many credits does upscaling an image cost' => 'ai-image-pro-studio-and-credits',
            'how do I populate my empty site with fake demo data' => 'faker-ai-addon-setup',
            'how do I undo a fakerai generation' => 'faker-ai-history-and-rollback',
        ];

        foreach ($cases as $question => $expectedSlug) {
            $hit = $docs->search($question);

            $this->assertSame(
                $expectedSlug,
                $hit['sources'][0]['slug'] ?? null,
                "expected \"{$question}\" to retrieve \"{$expectedSlug}\", got: " . json_encode(array_column($hit['sources'], 'slug'))
            );
        }
    }

    // ─── degradation ──────────────────────────────────────────

    /**
     * A stripped or empty docs folder must degrade to ungrounded chat, not a broken admin
     * panel. Same contract the KB gate has.
     */
    public function test_a_missing_corpus_degrades_to_ungrounded_chat(): void
    {
        $empty = sys_get_temp_dir() . '/makeai-docs-empty-' . uniqid();
        mkdir($empty);

        try {
            $this->app->instance(ProductDocsService::class, new ProductDocsService($empty));

            $this->assertFalse(ProductDocs::available());
            $this->assertSame(['context' => '', 'sources' => []], ProductDocs::context('anything'));

            addon_setting_set('ai-assistant', 'admin_enabled', true, 'boolean');
            $this->useMeteredMode();
            $this->seedChatModel();
            $this->fakeProvider(['Still answering.']);

            $this->actingAsAdmin();

            $response = $this->post(route('addon.ai-assistant.admin.chat'), [
                'message' => 'how do I add an OpenAI api key',
                'session_id' => 'sess-docs-empty',
            ]);

            $this->assertStringContainsString('Still answering', $this->streamText($response));
            $this->assertNotContains('sources', $this->frameTypes($response));

            // ...and the command is withdrawn rather than offered and then failing.
            $this->assertNotContains(
                'docs',
                array_column(app(SlashCommandRegistry::class)->list(SlashCommandRegistry::SCOPE_ADMIN), 'name')
            );
        } finally {
            rmdir($empty);
        }
    }
}
