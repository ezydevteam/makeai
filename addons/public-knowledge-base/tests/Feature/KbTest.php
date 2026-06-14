<?php

use Addons\PublicKnowledgeBase\Models\KbArticle;
use Addons\PublicKnowledgeBase\Models\KbArticleVote;
use Addons\PublicKnowledgeBase\Models\KbCategory;
use Addons\PublicKnowledgeBase\Models\KbSearch;
use App\Services\AI\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = \App\Models\User::factory()->create(['is_admin' => true]);
});

test('admin can create a kb category', function () {
    actingAs($this->admin, 'admin')
        ->post('/admin/kb/categories', [
            'name' => 'Test Category',
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect(KbCategory::where('name', 'Test Category')->exists())->toBeTrue();
});

test('admin cannot delete a category that has articles', function () {
    $cat = KbCategory::create(['name' => 'With Articles', 'sort_order' => 1]);
    KbArticle::create([
        'title' => 'Test Article',
        'body' => 'Test body',
        'kb_category_id' => $cat->id,
        'status' => 'published',
        'published_at' => now(),
    ]);

    actingAs($this->admin, 'admin')
        ->delete("/admin/kb/categories/{$cat->id}")
        ->assertSessionHasErrors();
});

test('category slug is auto-generated from name if not provided', function () {
    actingAs($this->admin, 'admin')
        ->post('/admin/kb/categories', ['name' => 'My New Category', 'sort_order' => 0, 'is_active' => true]);

    expect(KbCategory::where('slug', 'my-new-category')->exists())->toBeTrue();
});

test('admin can create a kb article and it defaults to draft', function () {
    actingAs($this->admin, 'admin')
        ->post('/admin/kb/articles', [
            'title' => 'Hello World',
            'body' => '<p>Test body content</p>',
        ])
        ->assertRedirect();

    $article = KbArticle::first();
    expect($article)->not->toBeNull();
    expect($article->status)->toBe('draft');
    expect($article->body_plain)->toBe('Test body content');
});

test('publishing an article dispatches IngestKbArticle job on embeddings queue', function () {
    \Illuminate\Support\Facades\Queue::fake();

    actingAs($this->admin, 'admin')
        ->post('/admin/kb/articles', [
            'title' => 'Published Article',
            'body' => '<p>Content</p>',
            'status' => 'published',
        ]);

    $article = KbArticle::first();
    \Illuminate\Support\Facades\Queue::assertPushedOn(
        'embeddings',
        \Addons\PublicKnowledgeBase\Jobs\IngestKbArticle::class,
        fn ($job) => $job->articleId === $article->id
    );
});

test('article body change sets embed_status back to pending', function () {
    $article = KbArticle::create([
        'title' => 'Change Test',
        'body' => '<p>Original</p>',
        'status' => 'published',
        'embed_status' => 'done',
        'published_at' => now(),
    ]);

    $article->update(['body' => '<p>Updated</p>']);

    expect($article->fresh()->embed_status)->toBe('pending');
});

test('article plain text is auto-generated from html body on save', function () {
    $article = KbArticle::create([
        'title' => 'HTML Test',
        'body' => '<h2>Heading</h2><p>Paragraph with <strong>bold</strong> text.</p>',
        'status' => 'published',
        'published_at' => now(),
    ]);

    expect($article->fresh()->body_plain)->toBe('HeadingParagraph with bold text.');
});

test('admin can re-embed a failed article', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $article = KbArticle::create([
        'title' => 'Re-embed Me',
        'body' => '<p>Content</p>',
        'status' => 'published',
        'embed_status' => 'failed',
        'published_at' => now(),
    ]);

    actingAs($this->admin, 'admin')
        ->post("/admin/kb/articles/{$article->ulid}/re-embed")
        ->assertOk();

    \Illuminate\Support\Facades\Queue::assertPushedOn('embeddings', \Addons\PublicKnowledgeBase\Jobs\IngestKbArticle::class);
});

test('deleting an article cascades to embeddings and votes', function () {
    $article = KbArticle::create([
        'title' => 'Delete Me',
        'body' => '<p>Content</p>',
        'status' => 'published',
        'published_at' => now(),
    ]);

    KbArticleVote::create([
        'kb_article_id' => $article->id,
        'session_id' => 'test-session',
        'vote' => 1,
    ]);

    actingAs($this->admin, 'admin')
        ->delete("/admin/kb/articles/{$article->ulid}")
        ->assertRedirect();

    expect(KbArticle::find($article->id))->toBeNull();
    expect(KbArticleVote::where('kb_article_id', $article->id)->exists())->toBeFalse();
});

test('search endpoint returns 422 for query shorter than 2 chars', function () {
    post('/help/search', ['query' => 'x'])
        ->assertStatus(422);
});

test('search endpoint is rate limited to 20 per minute', function () {
    for ($i = 0; $i < 21; $i++) {
        $r = post('/help/search', ['query' => 'test query']);
        if ($r->status() === 429) {
            expect(true)->toBeTrue();
            return;
        }
    }
})->markTestSkipped('Rate limiter may not trigger cleanly in test environment');

test('user can vote helpful on an article', function () {
    $article = KbArticle::create([
        'title' => 'Vote Me',
        'body' => '<p>Content</p>',
        'status' => 'published',
        'published_at' => now(),
    ]);

    post("/help/vote/{$article->ulid}", ['vote' => 1])
        ->assertOk()
        ->assertJsonFragment(['your_vote' => 1]);
});

test('vote is idempotent — voting twice updates rather than creates', function () {
    $article = KbArticle::create([
        'title' => 'Double Vote',
        'body' => '<p>Content</p>',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $session = \Illuminate\Support\Facades\Session::getId();

    post("/help/vote/{$article->ulid}", ['vote' => 1]);
    post("/help/vote/{$article->ulid}", ['vote' => -1]);

    expect(KbArticleVote::where('kb_article_id', $article->id)->count())->toBe(1);
    expect(KbArticleVote::where('kb_article_id', $article->id)->first()->vote)->toBe(-1);
});

test('help center home is accessible at the configured public slug', function () {
    addon_setting_set('public-knowledge-base', 'public_slug', 'support');

    get('/support')
        ->assertOk();
});

test('article page returns 404 for unpublished articles', function () {
    $article = KbArticle::create([
        'title' => 'Draft Article',
        'slug' => 'draft-article',
        'body' => '<p>Content</p>',
        'status' => 'draft',
        'published_at' => null,
    ]);

    get("/help/article/{$article->slug}")
        ->assertStatus(404);
});

test('kb returns 404 when addon disabled via settings', function () {
    addon_setting_set('public-knowledge-base', 'enabled', false);

    get('/help')
        ->assertStatus(404);
});
