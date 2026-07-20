# AI Chatbot Enhancement Implementation Plan

## Overview
Implement 16 features across 4 phases to bring the AI chatbot to modern standards.

---

## Phase 1: Critical Bug Fixes

### 1.1 File Attachment System

**Backend:**
- Create `app/Http/Controllers/Api/ChatAttachmentController.php`
  - `store()` — Upload file, extract text (PDF/DOCX/TXT/MD), return metadata
  - Validation: max 10MB, allowed types: pdf,docx,txt,md,csv,png,jpg,jpeg
  - Storage: `storage/app/private/chat-attachments/{user_id}/{ulid}.{ext}`
  - Use existing `TextExtractionService` for text extraction
  - Return: `{ id, name, type, size, text_content }`

- Update `ChatController::sendMessage()`
  - Accept `attachments` array (JSON) in request
  - Store attachment metadata in `conversation_messages.attachments` column
  - Include extracted text in AI prompt context

**Frontend:**
- Update `ChatInput.vue`
  - On file select: POST to `/api/v1/chat/attachments` with FormData
  - Store returned metadata in `attachedFiles` ref (array)
  - Show file previews with remove button
  - On send: include `attachments` array in message payload

- Update `ChatMessage.vue`
  - Render attachment badges for messages with files

**Database:** `conversation_messages.attachments` column already exists (JSON) — no migration needed

**Routes:** `POST /api/v1/chat/attachments`

---

### 1.2 Like/Dislike Feedback Persistence

**Database:**
- Migration: `create_chat_message_feedback_table`
  ```
  chat_message_feedback:
    id, ulid (char 26, unique), user_id (FK), conversation_id (FK),
    message_id (FK), rating (tinyint: 1=like, -1=dislike),
    comment (text, nullable), created_at, updated_at
    UNIQUE [user_id, message_id]
  ```

**Backend:**
- Create `app/Models/ChatMessageFeedback.php`
- Create `app/Http/Controllers/Api/ChatFeedbackController.php`
  - `store()` — Upsert feedback (one per user per message)

**Frontend:**
- Update `ChatMessage.vue` — wire like/dislike to API

**Routes:** `POST /api/v1/chat/feedback`

---

### 1.3 Message Pagination & Virtualization

**Backend:**
- Update `ChatController::show()` — accept `before` cursor, return 20 messages per page + `has_more` flag

**Frontend:**
- Update `useChat.ts` — add `loadOlderMessages()`, track `hasMoreMessages`
- Update `ChatMessages.vue` — "Load more" button, install `vue-virtual-scroller`

---

## Phase 2: Core Features

### 2.1 Conversation Branching
- **DB:** Add `parent_conversation_id`, `branch_point_message_id` to `conversations`
- **Backend:** `ChatController@branch()` — fork conversation at message point
- **Frontend:** "Branch from here" button on each message
- **Route:** `POST /api/v1/chat/{ulid}/branch`

### 2.2 Message Editing
- **Backend:** `ChatController@editMessage()` — update content, delete subsequent messages, regenerate
- **Frontend:** "Edit" button on user messages, inline textarea
- **Route:** `PUT /api/v1/chat/{ulid}/message/{messageId}`

### 2.3 Conversation Export
- **Backend:** `ChatController@export()` — formats: markdown, json, pdf
- **Frontend:** Export dropdown in conversation menu
- **Route:** `GET /api/v1/chat/{ulid}/export?format=md|json|pdf`

### 2.4 Custom Instructions
- **DB:** Add `chat_custom_instructions` (text, nullable) to `users`
- **Backend:** Prepend to system prompt in `sendMessage()`
- **Frontend:** Settings modal in `ChatSidebar.vue`
- **Route:** `PUT /api/v1/chat/settings`

### 2.5 RAG Integration into Chat
- **Backend:** Accept `use_knowledge_base` flag, query RAG context, inject into prompt
- **Frontend:** "Use knowledge base" toggle in `ChatInput.vue`

---

## Phase 3: Advanced Features

### 3.1 Voice Input/Output
- **Input:** Web Speech API (`SpeechRecognition`), mic button in `ChatInput.vue`
- **Output:** Web Speech API (`SpeechSynthesis`), speaker button in `ChatMessage.vue`

### 3.2 Image Analysis (Vision Models)
- **Backend:** Handle images in `ChatAttachmentController`, detect vision models, include in payload
- **Frontend:** Image previews in `ChatInput.vue`, render in `ChatMessage.vue`

### 3.3 Conversation Sharing
- **DB:** Add `share_token` (char 26, nullable, unique), `is_shared` (boolean) to `conversations`
- **Backend:** `share()`, `unshare()`, public `showShared()`
- **Frontend:** Share button, link modal, copy to clipboard
- **Routes:** `POST/DELETE /api/v1/chat/{ulid}/share`, `GET /shared/chat/{token}`

### 3.4 Pinned Conversations
- **DB:** `is_pinned` already exists
- **Backend:** `ChatController@togglePin()`
- **Frontend:** Pin icon in `ChatSidebar.vue`, pinned at top
- **Route:** `POST /api/v1/chat/{ulid}/toggle-pin`

### 3.5 Conversation Tags
- **DB:** `conversation_tags` + `conversation_tag_pivot` tables
- **Backend:** CRUD tags, tag/untag conversations, filter by tag
- **Frontend:** Tag management in `ChatSidebar.vue`
- **Routes:** `GET/POST/DELETE /api/v1/chat/tags`, `POST/DELETE /api/v1/chat/{ulid}/tags/{tag}`

---

## Phase 4: Nice to Have

### 4.1 Code Execution Sandbox
- `CodeExecutionService` using Docker, "Run" button on code blocks
- Security: 10s timeout, memory limits, no network, language whitelist

### 4.2 Live Web Browsing
- `WebBrowserService` using headless browser, detect URLs, fetch content

### 4.3 Cross-Conversation Memory
- `user_memories` table, `MemoryService`, inject into system prompt

### 4.4 Plugins/Tools System
- `ToolInterface`, built-in tools (WebSearch, Calculator, DateTime)
- AI calls tools during conversation

---

## New Files
- `app/Http/Controllers/Api/ChatAttachmentController.php`
- `app/Http/Controllers/Api/ChatFeedbackController.php`
- `app/Models/ChatMessageFeedback.php`
- `app/Models/ConversationTag.php`
- ~8 migration files
- Phase 4 services as needed

## Modified Files
- `app/Http/Controllers/Api/ChatController.php`
- `resources/js/Composables/useChat.ts`
- `resources/js/Components/Chat/ChatInput.vue`
- `resources/js/Components/Chat/ChatMessage.vue`
- `resources/js/Components/Chat/ChatMessages.vue`
- `resources/js/Components/Chat/ChatSidebar.vue`
- `routes/api.php`

## Dependencies
- `vue-virtual-scroller` — message virtualization

## Verification
- Unit tests for all new controllers
- Feature tests for API endpoints
- Manual testing for frontend interactions
- Security review for file uploads and code execution
