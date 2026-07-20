<?php

namespace Database\Seeders;

use App\Models\AiTool;
use App\Models\Category;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Database\Seeder;

class RagToolSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::updateOrCreate(
            ['slug' => 'document-ai', 'type' => 'ai_tool'],
            [
                'name' => '📚 Document AI',
                'description' => 'Chat with PDFs, documents, spreadsheets, websites, and YouTube videos. Summarize, compare, and write from your knowledge base.',
                'icon' => 'ti ti-file-search',
                'color' => '#0891b2',
                'is_active' => true,
                'is_system' => true,
                'requires_pro' => false,
                'sort_order' => 20,
            ]
        );

        $tools = $this->toolDefinitions();
        $sortOrder = 1;

        foreach ($tools as $tool) {
            AiTool::updateOrCreate(
                ['slug' => $tool['slug']],
                $this->payload($tool, $category, $sortOrder++)
            );
        }

        $category->updateToolsCount();
        ToolCatalogCacheService::invalidateAll();
    }

    private function toolDefinitions(): array
    {
        return [
            [
                'name' => 'Chat with PDF',
                'slug' => 'chat-pdf',
                'description' => 'Upload any PDF and chat with it. Get instant answers with page citations.',
                'icon' => 'ti-file-type-pdf',
                'fields' => [
                    'source_type' => 'file',
                    'accept' => ['pdf'],
                    'max_file_mb' => null,
                    'multi_file' => false,
                ],
            ],
            [
                'name' => 'Chat with Document',
                'slug' => 'chat-document',
                'description' => 'Upload Word, text, or Markdown files and interact with their content through natural conversation.',
                'icon' => 'ti-file-text',
                'fields' => [
                    'source_type' => 'file',
                    'accept' => ['docx', 'txt', 'md'],
                    'max_file_mb' => null,
                    'multi_file' => false,
                ],
            ],
            [
                'name' => 'Chat with Spreadsheet',
                'slug' => 'chat-spreadsheet',
                'description' => 'Upload CSV or Excel files and ask questions about your data.',
                'icon' => 'ti-table',
                'fields' => [
                    'source_type' => 'file',
                    'accept' => ['csv', 'xlsx'],
                    'max_file_mb' => null,
                    'multi_file' => false,
                ],
            ],
            [
                'name' => 'Chat with Website',
                'slug' => 'chat-website',
                'description' => 'Enter any URL and chat with the content of that web page.',
                'icon' => 'ti-world',
                'fields' => [
                    'source_type' => 'url',
                ],
            ],
            [
                'name' => 'Chat with YouTube',
                'slug' => 'chat-youtube',
                'description' => 'Paste a YouTube link and chat with the video transcript. Get timestamp-linked answers.',
                'icon' => 'ti-brand-youtube',
                'fields' => [
                    'source_type' => 'youtube',
                ],
            ],
            [
                'name' => 'Long Document Summarizer',
                'slug' => 'long-doc-summarizer',
                'description' => 'Summarize documents too large for standard context windows using map-reduce chunking.',
                'icon' => 'ti-file-description',
                'fields' => [
                    'source_type' => 'file',
                    'accept' => ['pdf', 'docx', 'txt', 'md'],
                    'mode' => 'summarize',
                    'multi_file' => false,
                ],
            ],
            [
                'name' => 'Multi-Document Compare',
                'slug' => 'doc-compare',
                'description' => 'Upload 2-3 documents and get an AI-powered side-by-side comparison with citations.',
                'icon' => 'ti-file-diff',
                'fields' => [
                    'source_type' => 'file',
                    'accept' => ['pdf', 'docx', 'txt', 'md'],
                    'multi_file' => true,
                    'min_files' => 2,
                    'max_files' => 3,
                ],
            ],
            [
                'name' => 'Write from Knowledge Base',
                'slug' => 'kb-writer',
                'description' => 'Generate articles, reports, and content grounded ONLY in your existing knowledge base collection.',
                'icon' => 'ti-books',
                'fields' => [
                    'source_type' => 'collection',
                    'mode' => 'generate',
                ],
            ],
        ];
    }

    private function payload(array $tool, Category $category, int $sortOrder): array
    {
        $name = $tool['name'];
        $description = $tool['description'];

        return [
            'name' => $name,
            'slug' => $tool['slug'],
            'type' => 'rag',
            'description' => $description,
            'category_id' => $category->id,
            'icon' => $tool['icon'],
            'color' => $category->color,
            'fields' => $tool['fields'],
            'output_type' => 'markdown',
            'access_level' => 'inherit',
            'is_active' => true,
            'is_featured' => true,
            'supports_brand_voice' => false,
            'avg_output_tokens' => 800,
            'sort_order' => $sortOrder,
            'show_improve' => false,
            'about_content' => "{$name} lets you interact with documents and external sources through natural conversation powered by AI retrieval.",
            'how_it_works' => [
                ['step' => 1, 'title' => 'Upload or link', 'description' => 'Upload a file, paste a URL, or select a knowledge base collection.'],
                ['step' => 2, 'title' => 'Wait for processing', 'description' => 'The source is extracted, chunked, and embedded for semantic search.'],
                ['step' => 3, 'title' => 'Chat with your source', 'description' => 'Ask questions and get cited answers grounded in your document.'],
            ],
            'meta_title' => "{$name} — AI Document Intelligence",
            'meta_description' => $description,
        ];
    }
}
