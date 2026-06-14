<?php

namespace Addons\AiRepurposer\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranscriptService
{
    public function getYoutubeTranscript(string $url): array
    {
        $videoId = $this->extractVideoId($url);

        if (! $videoId) {
            throw new \RuntimeException(__('Invalid YouTube URL.'));
        }

        $transcript = $this->fetchYoutubeCaptions($videoId);

        if (empty(trim($transcript))) {
            throw new \RuntimeException(__('No transcript available for this video. The video may not have captions enabled.'));
        }

        $metadata = $this->fetchVideoMetadata($url, $videoId);

        return [
            'transcript' => $transcript,
            'title'      => $metadata['title'] ?? null,
            'duration'   => $metadata['duration'] ?? 0,
            'chapters'   => $metadata['chapters'] ?? [],
        ];
    }

    public function transcribeFile(string $storagePath, ?string $provider = null): array
    {
        $provider ??= addon_setting('ai-repurposer', 'transcription_provider', 'whisper');
        $maxMb = (int) addon_setting('ai-repurposer', 'max_file_size_mb', 100);

        $absolutePath = storage_path('app/' . $storagePath);

        if (! file_exists($absolutePath)) {
            throw new \RuntimeException(__('Uploaded file not found.'));
        }

        $sizeMb = filesize($absolutePath) / 1048576;
        if ($sizeMb > $maxMb) {
            throw new \RuntimeException(__('File exceeds the maximum size of :max MB.', ['max' => $maxMb]));
        }

        if ($provider === 'assemblyai') {
            return $this->transcribeWithAssemblyAI($absolutePath);
        }

        return $this->transcribeWithWhisper($absolutePath);
    }

    public function extractVideoId(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([^&\s]+)/',
            '/youtu\.be\/([^?\s]+)/',
            '/youtube\.com\/shorts\/([^?\s]+)/',
            '/youtube\.com\/embed\/([^?\s]+)/',
            '/youtube\.com\/v\/([^?\s]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    protected function fetchYoutubeCaptions(string $videoId): string
    {
        try {
            if (class_exists('YoutubeTranscript\YoutubeTranscript')) {
                $lines = \YoutubeTranscript\YoutubeTranscript::getTranscript($videoId);
                return collect($lines)->pluck('text')->map(fn ($t) => strip_tags($t))->implode(' ');
            }
        } catch (\Throwable $e) {
            Log::warning("youtube-transcript package failed for {$videoId}: " . $e->getMessage());
        }

        $html = Http::timeout(15)
            ->withUserAgent('Mozilla/5.0 (compatible; MakeAI/1.0)')
            ->get("https://www.youtube.com/watch?v={$videoId}")
            ->body();

        if (preg_match('/"captions":\{"playerCaptionsTracklistRenderer":\{"captionTracks":(\[\{"baseUrl":"([^"]+)"/', $html, $m)) {
            $captions = Http::timeout(10)->get($m[2])->body();
            $xml = simplexml_load_string($captions);
            if ($xml) {
                $texts = [];
                foreach ($xml->text as $t) {
                    $texts[] = strip_tags((string) $t);
                }
                return preg_replace('/\s+/', ' ', implode(' ', $texts));
            }
        }

        throw new \RuntimeException(__('Could not retrieve transcript for this video.'));
    }

    protected function fetchVideoMetadata(string $url, string $videoId): array
    {
        $result = ['title' => null, 'duration' => 0, 'chapters' => []];

        try {
            $oembed = Http::timeout(10)
                ->get('https://www.youtube.com/oembed', [
                    'url'    => "https://www.youtube.com/watch?v={$videoId}",
                    'format' => 'json',
                ]);

            if ($oembed->successful()) {
                $result['title'] = $oembed->json('title');
            }
        } catch (\Throwable $e) {
            Log::warning("YouTube oEmbed fetch failed for {$videoId}: " . $e->getMessage());
        }

        $apiKey = settings('youtube_api_key');
        if ($apiKey) {
            try {
                $api = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/videos', [
                    'id'    => $videoId,
                    'part'  => 'contentDetails,snippet',
                    'key'   => $apiKey,
                ]);

                if ($api->successful()) {
                    $item = $api->json('items.0') ?? [];
                    $result['title'] = $result['title'] ?? ($item['snippet']['title'] ?? null);
                    $durationStr = $item['contentDetails']['duration'] ?? '';
                    $result['duration'] = $this->parseIsoDuration($durationStr);

                    $description = $item['snippet']['description'] ?? '';
                    $result['chapters'] = $this->extractChaptersFromDescription($description);
                }
            } catch (\Throwable $e) {
                Log::warning("YouTube Data API fetch failed for {$videoId}: " . $e->getMessage());
            }
        }

        return $result;
    }

    protected function transcribeWithWhisper(string $absolutePath): array
    {
        $apiKey = settings('openai_api_key');

        if (! $apiKey) {
            throw new \RuntimeException(__('OpenAI API key is not configured.'));
        }

        $response = Http::timeout(120)
            ->withToken($apiKey)
            ->attach('file', fopen($absolutePath, 'r'), basename($absolutePath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model'           => 'whisper-1',
                'response_format' => 'json',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(__('Whisper transcription failed: :error', ['error' => $response->json('error.message') ?? $response->body()]));
        }

        $data = $response->json();

        return [
            'transcript' => $data['text'] ?? '',
            'duration'   => $data['duration'] ?? 0,
        ];
    }

    protected function transcribeWithAssemblyAI(string $absolutePath): array
    {
        $apiKey = settings('assemblyai_api_key');

        if (! $apiKey) {
            throw new \RuntimeException(__('AssemblyAI API key is not configured.'));
        }

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->post('https://api.assemblyai.com/v2/upload', [
                'multipart' => [
                    ['name' => 'file', 'contents' => fopen($absolutePath, 'r'), 'filename' => basename($absolutePath)],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(__('AssemblyAI upload failed.'));
        }

        $uploadUrl = $response->json('upload_url');

        $transcriptResponse = Http::timeout(30)
            ->withToken($apiKey)
            ->post('https://api.assemblyai.com/v2/transcript', [
                'audio_url' => $uploadUrl,
            ]);

        $transcriptId = $transcriptResponse->json('id');

        $attempts = 0;
        while ($attempts < 60) {
            $poll = Http::timeout(15)
                ->withToken($apiKey)
                ->get("https://api.assemblyai.com/v2/transcript/{$transcriptId}");

            $status = $poll->json('status');

            if ($status === 'completed') {
                return [
                    'transcript' => $poll->json('text') ?? '',
                    'duration'   => $poll->json('audio_duration') ?? 0,
                ];
            }

            if ($status === 'error') {
                throw new \RuntimeException(__('AssemblyAI transcription failed: :error', ['error' => $poll->json('error')]));
            }

            $attempts++;
            sleep(3);
        }

        throw new \RuntimeException(__('AssemblyAI transcription timed out.'));
    }

    protected function extractChaptersFromDescription(string $description): array
    {
        $chapters = [];
        $lines = explode("\n", $description);
        $insideChapters = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(\d{1,2}:\d{2}(?::\d{2})?)\s+(.+)$/', $line, $matches)) {
                $insideChapters = true;
                $chapters[] = [
                    'title'         => trim($matches[2]),
                    'start_seconds' => $this->timeToSeconds($matches[1]),
                ];
            } elseif ($insideChapters && $line === '') {
                break;
            }
        }

        return $chapters;
    }

    protected function parseIsoDuration(string $duration): int
    {
        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $m)) {
            return ((int) ($m[1] ?? 0) * 3600)
                 + ((int) ($m[2] ?? 0) * 60)
                 +  (int) ($m[3] ?? 0);
        }

        return 0;
    }

    protected function timeToSeconds(string $time): int
    {
        $parts = array_reverse(explode(':', $time));

        return ((int) ($parts[2] ?? 0) * 3600)
             + ((int) ($parts[1] ?? 0) * 60)
             +  (int) ($parts[0] ?? 0);
    }
}
