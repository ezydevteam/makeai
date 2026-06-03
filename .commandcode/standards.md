# MakeAI — Always-Loaded Standards

> This file is read on every session. Follow these rules for ALL generated code.

## Stack & Architecture

- **PHP 8.3+ · Laravel 13.x** — latest APIs, conventions, patterns
- **Laravel AI SDK (`laravel/ai` v0.7.2)** — official Laravel package for all AI operations
- **AI Engine:** `AiService` → `ProviderRegistry` → `LaravelAiDriver` → SDK providers
- **Target:** Envato CodeCanyon premium SaaS script for sale

## AI Engine — File Map

```
app/Services/AI/Contracts/AiDriverInterface.php   ← Canonical driver contract
app/Services/AI/Drivers/LaravelAiDriver.php        ← Wraps all SDK providers
app/Services/AI/ProviderRegistry.php                ← Round-robin API key load balancing
app/Services/AI/AiService.php                       ← 15 methods: complete, stream, chat, embed, image, audio, RAG, agents
app/Services/AI/TokenGuard.php                      ← Credit enforcement (before/after every request)
app/Services/AI/PromptBuilder.php                   ← Template → prompt assembly
app/Services/AI/Rag/                                ← Full RAG pipeline (extract, chunk, vector store, search)
app/Services/AI/Agents/AgentService.php              ← Agent execution with tool calling
```

## Code Rules

1. **ALWAYS** go through `AiService` for AI — never call raw HTTP or SDK providers directly
2. **ALWAYS** use `settings()` helper for all configuration — never `config()` or `.env` for user-configurable values
3. **ALWAYS** use `translate()` for all user-facing strings
4. **ALWAYS** use ULIDs for public-facing user IDs — never expose auto-increment `id`
5. **ALWAYS** encrypt API keys: `settings_set($key, $value, 'encrypted')`
6. **ALWAYS** use separate `auth('admin')->user()` guard for admin context
7. **ALWAYS** queue AI, email, and media jobs — never block HTTP response
8. **ALWAYS** use `TokenGuard` before and after every AI request
9. **ALWAYS** log every AI request to `ai_usage_logs` — even failed ones
10. **ALWAYS** gate Pro/subscription features behind `isProAvailable()`
11. **ALWAYS** use CSS logical properties for RTL compatibility
12. **ALWAYS** use `paginate()` for list queries — never `get()`/`all()` on large tables

## Never Use (Deleted)

- `ProviderInterface.php`
- `OpenAiProvider.php`
- `AnthropicProvider.php`
- `GoogleProvider.php`
- `DeepSeekProvider.php`
- `XaiProvider.php`
- `OpenRouterProvider.php`

## Provider Names (SDK-native)

openai, anthropic, gemini, deepseek, xai, openrouter, groq, mistral, ollama, azure, bedrock, cohere, eleven, jina, voyageai

## Envato Marketplace Standards

- White-label: never hardcode "MakeAI" → use `settings('app_name')`
- All config via admin panel — no hardcoded values
- No debug/console.log in production code
- Demo mode middleware support (blocks destructive writes, allows AI gen)
- Clean, professional, well-structured code
- All templates use same `ToolPage.vue` + `DynamicForm.vue` architecture
