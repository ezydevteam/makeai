<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services\Providers;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Services\OperationRegistry;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Stability AI (v2beta "Stable Image").
 *
 * Ported from the ai-image-editor client with one deliberate change: nothing here
 * touches the filesystem. Every method takes raw image bytes and returns raw image
 * bytes — where those bytes end up (which disk, which path, whose library, what
 * retention window) is `AssetService`'s decision, not the provider client's.
 */
class StabilityClient
{
    private const BASE_URL = 'https://api.stability.ai/v2beta';

    /** Async endpoints (replace-background) hand back an id; this caps the wait. */
    private const POLL_TIMEOUT_SECONDS = 300;

    private const POLL_INTERVAL_SECONDS = 3;

    /**
     * Text-to-image, and image-to-image when `image` bytes are supplied.
     *
     * Stability returns exactly one image per call, so a batch is N calls — the
     * caller keeps calling until it has the images it asked for.
     *
     * @param  array<string, mixed>  $options  model, negative_prompt, aspect_ratio, seed,
     *                                         output_format, image (bytes), strength
     * @return array<int, string>  raw image bytes, one entry per image
     */
    public function generate(string $prompt, array $options = []): array
    {
        $model = (string) ($options['model'] ?? 'core');
        $reference = $options['image'] ?? null;

        $fields = array_filter([
            'prompt' => $prompt,
            'negative_prompt' => $options['negative_prompt'] ?? null,
            'aspect_ratio' => $reference ? null : ($options['aspect_ratio'] ?? null),
            'seed' => isset($options['seed']) ? (string) $options['seed'] : null,
            'output_format' => (string) ($options['output_format'] ?? 'png'),
            'style_preset' => $options['style_preset'] ?? null,
            // image-to-image needs an explicit mode + how far to travel from the input.
            'mode' => $reference ? 'image-to-image' : null,
            'strength' => $reference ? (string) ($options['strength'] ?? 0.6) : null,
        ], fn ($value) => $value !== null && $value !== '');

        $request = $this->request();

        $request = is_string($reference) && $reference !== ''
            ? $request->attach('image', $reference, 'input.png')
            : $request->attach('none', '');

        return [$this->body(
            $request->post(self::BASE_URL . "/stable-image/generate/{$model}", $fields),
            'generation',
        )];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function inpaint(string $image, string $mask, string $prompt, array $options = []): string
    {
        $fields = array_filter([
            'prompt' => $prompt,
            'negative_prompt' => $options['negative_prompt'] ?? null,
            'grow_mask' => isset($options['grow_mask']) ? (string) $options['grow_mask'] : null,
            'seed' => isset($options['seed']) ? (string) $options['seed'] : null,
            'output_format' => (string) ($options['output_format'] ?? 'png'),
        ], fn ($value) => $value !== null && $value !== '');

        return $this->body(
            $this->request()
                ->attach('image', $image, 'input.png')
                ->attach('mask', $mask, 'mask.png')
                ->post(self::BASE_URL . '/stable-image/edit/inpaint', $fields),
            'inpaint',
        );
    }

    /**
     * Erase what the mask covers and rebuild the background behind it. This is the
     * dedicated endpoint — a prompt-less inpaint would hallucinate a replacement.
     */
    public function objectRemove(string $image, string $mask, array $options = []): string
    {
        $fields = array_filter([
            'grow_mask' => isset($options['grow_mask']) ? (string) $options['grow_mask'] : null,
            'output_format' => (string) ($options['output_format'] ?? 'png'),
        ], fn ($value) => $value !== null && $value !== '');

        return $this->body(
            $this->request()
                ->attach('image', $image, 'input.png')
                ->attach('mask', $mask, 'mask.png')
                ->post(self::BASE_URL . '/stable-image/edit/erase', $fields),
            'object removal',
        );
    }

    /**
     * @param  array{left?: int, right?: int, up?: int, down?: int}  $expand  pixels per side
     * @param  array<string, mixed>  $options
     */
    public function outpaint(string $image, array $expand, string $prompt = '', array $options = []): string
    {
        $sides = array_filter([
            'left' => (int) ($expand['left'] ?? 0),
            'right' => (int) ($expand['right'] ?? 0),
            'up' => (int) ($expand['up'] ?? 0),
            'down' => (int) ($expand['down'] ?? 0),
        ], fn (int $pixels) => $pixels > 0);

        if ($sides === []) {
            throw new ImageOperationException(translate('Choose at least one direction to expand.'));
        }

        $fields = array_map(fn (int $pixels) => (string) $pixels, $sides);
        $fields['output_format'] = (string) ($options['output_format'] ?? 'png');

        if ($prompt !== '') {
            $fields['prompt'] = $prompt;
        }

        if (isset($options['creativity'])) {
            $fields['creativity'] = (string) $options['creativity'];
        }

        return $this->body(
            $this->request()
                ->attach('image', $image, 'input.png')
                ->post(self::BASE_URL . '/stable-image/edit/outpaint', $fields),
            'outpaint',
        );
    }

    /**
     * @param  array<string, mixed>  $options  fidelity, prompt, negative_prompt
     */
    public function styleTransfer(string $image, string $style, array $options = []): string
    {
        $fields = array_filter([
            'prompt' => $options['prompt'] ?? '',
            'negative_prompt' => $options['negative_prompt'] ?? null,
            'fidelity' => (string) ($options['fidelity'] ?? 0.5),
            'output_format' => (string) ($options['output_format'] ?? 'png'),
        ], fn ($value) => $value !== null && $value !== '');

        return $this->body(
            $this->request()
                ->attach('image', $image, 'input.png')
                ->attach('style_image', $style, 'style.png')
                ->post(self::BASE_URL . '/stable-image/control/style', $fields),
            'style transfer',
        );
    }

    /**
     * Fast upscale — synchronous, 4× fixed. The conservative/creative upscalers are
     * async and cost several times more; the flat credit price the admin sets for
     * `upscale` is priced for this one.
     */
    public function upscale(string $image, array $options = []): string
    {
        return $this->body(
            $this->request()
                ->attach('image', $image, 'input.png')
                ->post(self::BASE_URL . '/stable-image/upscale/fast', [
                    'output_format' => (string) ($options['output_format'] ?? 'png'),
                ]),
            'upscale',
        );
    }

    /**
     * Replace the background behind the subject. Stability runs this one async: the
     * POST returns a job id and the result is fetched from /results/{id}.
     *
     * @param  array<string, mixed>  $options
     */
    public function replaceBackground(string $image, string $prompt, array $options = []): string
    {
        $fields = array_filter([
            'background_prompt' => $prompt,
            'negative_prompt' => $options['negative_prompt'] ?? null,
            'preserve_original_subject' => isset($options['preserve_subject'])
                ? (string) $options['preserve_subject']
                : null,
            'light_source_direction' => $options['light_direction'] ?? null,
            'output_format' => (string) ($options['output_format'] ?? 'png'),
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->request()
            ->withHeaders(['Accept' => 'application/json'])
            ->attach('subject_image', $image, 'input.png')
            ->post(self::BASE_URL . '/stable-image/edit/replace-background-and-relight', $fields);

        if (! $response->successful()) {
            throw new ImageOperationException($this->errorMessage($response->body(), 'background replace'));
        }

        $id = (string) $response->json('id', '');

        if ($id === '') {
            throw new ImageOperationException(translate('Stability accepted the request but returned no job id.'));
        }

        return $this->pollResult($id, (int) ($options['poll_timeout'] ?? self::POLL_TIMEOUT_SECONDS));
    }

    /**
     * Wait for an async Stability job. 202 = still running; anything else is final.
     * The wait is capped — a provider that never finishes must fail the job (and
     * refund the user), not hold a queue worker until its 600s timeout.
     *
     * @throws ImageOperationException
     */
    private function pollResult(string $id, int $timeoutSeconds): string
    {
        $deadline = time() + max(self::POLL_INTERVAL_SECONDS, $timeoutSeconds);

        while (time() < $deadline) {
            sleep(self::POLL_INTERVAL_SECONDS);

            $response = Http::timeout(30)
                ->withToken($this->apiKey())
                ->withHeaders(['Accept' => 'image/*'])
                ->get(self::BASE_URL . "/results/{$id}");

            if ($response->status() === 202) {
                continue;
            }

            if (! $response->successful()) {
                throw new ImageOperationException($this->errorMessage($response->body(), 'background replace'));
            }

            return $response->body();
        }

        throw new ImageOperationException(
            translate('The image provider took too long to respond. Please try again.')
        );
    }

    /**
     * Every Stability endpoint here is multipart and returns the raw image when we
     * ask for `image/*` — asking for JSON would base64 the bytes for no reason.
     */
    private function request(): PendingRequest
    {
        return Http::timeout(120)
            ->withToken($this->apiKey())
            ->withHeaders(['Accept' => 'image/*']);
    }

    private function body(\Illuminate\Http\Client\Response $response, string $operation): string
    {
        if (! $response->successful()) {
            throw new ImageOperationException($this->errorMessage($response->body(), $operation));
        }

        $body = $response->body();

        if ($body === '') {
            throw new ImageOperationException(translate('The image provider returned an empty response.'));
        }

        return $body;
    }

    /**
     * Stability reports failures as JSON even when we asked for an image. Surface the
     * provider's own message when there is one — "content moderation" and "invalid
     * mask" are things the user can act on.
     */
    private function errorMessage(string $body, string $operation): string
    {
        $decoded = json_decode($body, true);

        $message = is_array($decoded)
            ? (is_array($decoded['errors'] ?? null) ? implode(' ', $decoded['errors']) : ($decoded['message'] ?? ''))
            : '';

        return "Stability {$operation} failed" . ($message !== '' ? ": {$message}" : '.');
    }

    private function apiKey(): string
    {
        $key = (string) addon_setting(OperationRegistry::SLUG, 'stability_api_key', '');

        if (trim($key) === '') {
            throw new ImageOperationException(translate('The Stability AI API key is not configured.'));
        }

        return $key;
    }
}
