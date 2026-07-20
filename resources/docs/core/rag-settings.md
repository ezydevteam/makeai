---
title: RAG Settings — Chat With Documents, Websites, and YouTube
slug: rag-settings
page: rag-settings.html
section: AI
license: regular
keywords: [rag, retrieval augmented generation, chat with document, chat with website, chat with youtube, document ai, grounding, chunking, embeddings]
---

**Admin → AI Management → RAG Settings** configures the "Document AI" tools — the features that let a customer upload a file, point at a website, or link a YouTube video and then chat with an AI that answers only from that content instead of guessing.

## What RAG actually does here

Retrieval-Augmented Generation ("RAG") means the AI's answer is grounded in real chunks of the customer's own content rather than its general knowledge. If the answer genuinely isn't in the material provided, the AI is instructed to say so honestly instead of making something up — this is controlled by the **Grounding System Prompt** setting, which must include a `{context}` placeholder for the retrieved chunks.

## Ingestion, retrieval, and cost settings

**Ingestion & Chunking** controls how uploaded content is split up for processing: Max File Size, Max PDF Pages, Chunk Size, Chunk Overlap, and Chunking Mode (fixed-size or semantic/topic-based). **Retrieval & Search** controls how the AI finds relevant chunks when answering: Top-K Chunks (how many chunks it pulls in per answer), Search Mode (vector-only or hybrid), and the Embedding Model. **Credits & Retention** controls what this costs your customers and how long unsaved sessions are kept: Chunks Per Credit, Ephemeral Retention (days), and Ingestion Credits Per MB / Per URL / Per Video.

## Website and YouTube-specific settings

For "Chat with Website," the **Web Scraper** section sets Max Page Size, Request Timeout, and the User Agent string sent when fetching pages. For "Chat with YouTube," a **YouTube Whisper Fallback** toggle transcribes the audio (charging credits per minute) whenever a video has no captions available, alongside Transcript API Endpoint and Key settings.

## Why a Document AI answer is empty or wrong

- The uploaded file or page exceeded **Max File Size** / **Max PDF Pages** / **Max Page Size** and was only partially processed.
- **Top-K Chunks** is set too low to retrieve the specific detail being asked about — raising it lets the AI see more of the source material per answer, at a higher credit cost.
- For YouTube content with no captions, **YouTube Whisper Fallback** is turned off, so no transcript was ever generated.
