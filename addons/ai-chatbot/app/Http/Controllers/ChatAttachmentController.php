<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AI\Rag\TextExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatAttachmentController extends Controller
{
    private const ALLOWED_TYPES = 'pdf,docx,txt,md,csv,png,jpg,jpeg';

    public function store(Request $request, TextExtractionService $extractor): JsonResponse
    {
        $maxSizeMb = (int) $this->getPlanSetting('max_file_size_mb', 10);
        if ($maxSizeMb <= 0) {
            $maxSizeMb = 10;
        }
        $maxSizeKb = $maxSizeMb * 1024;

        $request->validate([
            'file' => 'required|file|mimes:' . self::ALLOWED_TYPES . '|max:' . $maxSizeKb,
        ]);

        $file = $request->file('file');
        $user = Auth::user();
        $ownerDirectory = $user ? (string) $user->id : 'guest_' . session()->getId();
        $ulid = (string) Str::ulid();
        $ext = $file->getClientOriginalExtension();
        $path = $file->storeAs(
            "chat-attachments/{$ownerDirectory}",
            "{$ulid}.{$ext}",
            'local'
        );

        $fullPath = Storage::disk('local')->path($path);

        $textContent = '';
        try {
            if ($extractor->supports($fullPath)) {
                $textContent = $extractor->extract($fullPath);
                if (mb_strlen($textContent) > 15000) {
                    $textContent = mb_substr($textContent, 0, 15000) . "\n\n[Content truncated...]";
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Chat attachment text extraction failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ulid,
                'name' => $file->getClientOriginalName(),
                'type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'extension' => $ext,
                'storage_path' => $path,
                'text_content' => $textContent,
            ],
        ]);
    }

    private function getPlanSetting(string $key, $default = null)
    {
        $user = Auth::user();

        if ($user) {
            $plan = $user->plan;
            if ($plan && !$plan->is_free) {
                $planKey = "plan_{$plan->slug}_{$key}";
                $val = addon_setting('ai-chatbot', $planKey);
                if ($val !== null && $val !== '') {
                    if ($val === 'true' || $val === '1') return true;
                    if ($val === 'false' || $val === '0') return false;
                    return $val;
                }
            } else {
                $freeKey = "free_{$key}";
                $val = addon_setting('ai-chatbot', $freeKey);
                if ($val !== null && $val !== '') {
                    if ($val === 'true' || $val === '1') return true;
                    if ($val === 'false' || $val === '0') return false;
                    return $val;
                }
            }
        } else {
            $guestKey = "guest_{$key}";
            $val = addon_setting('ai-chatbot', $guestKey);
            if ($val !== null && $val !== '') {
                if ($val === 'true' || $val === '1') return true;
                if ($val === 'false' || $val === '0') return false;
                return $val;
            }
        }

        return $default;
    }

    public function preview(string $id): \Symfony\Component\HttpFoundation\Response
    {
        // Find the attachment by ID in the user's recent messages
        $user = Auth::user();
        $query = $user 
            ? $user->conversations() 
            : \Addons\AiChatbot\Models\Conversation::whereNull('user_id')->where('session_id', session()->getId());

        $message = $query
            ->with(['messages' => function ($query) use ($id) {
                $query->whereJsonContains('attachments->id', $id);
            }])
            ->get()
            ->pluck('messages')
            ->flatten()
            ->first();

        if (!$message || !$message->attachments) {
            abort(404);
        }

        $attachment = collect($message->attachments)->firstWhere('id', $id);
        if (!$attachment) {
            abort(404);
        }

        $path = $attachment['storage_path'] ?? null;

        // Defense-in-depth: only ever serve files from the caller's own upload
        // directory, regardless of what path is stored on the message.
        $ownerDirectory = $user ? (string) $user->id : 'guest_' . session()->getId();
        $ownerPrefix = "chat-attachments/{$ownerDirectory}/";
        if (!$path || str_contains($path, '..') || !str_starts_with($path, $ownerPrefix)) {
            abort(404);
        }

        if (!Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $fullPath = Storage::disk('local')->path($path);
        $mimeType = $attachment['type'] ?? 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
