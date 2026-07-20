<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services\Providers;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Services\OperationRegistry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Replicate — hosted diffusion models.
 *
 * Ported from the ai-image-editor client with the addon's conventions applied:
 * nothing touches the filesystem (image inputs arrive as raw bytes, outputs are
 * returned as raw bytes), the API key is read from this addon's settings, and the
 * async poll is bounded by an explicit deadline so a prediction that never
 * finishes fails the job (and refunds the user) instead of holding a queue worker
 * until its own 600s timeout.
 */
class ReplicateClient
{
    private const BASE_URL = 'https://api.replicate.com/v1';

    /** Real-ESRGAN — the same fixed 4× upscaler the editor addon used. */
    private const UPSCALE_VERSION = '42fed1c4974146d4d2414e2be2c5277c7fcf05fcc3a73abf41610695738c1d7b';

    /** Stable-diffusion inpainting model version. */
    private const INPAINT_VERSION = '95b7223104132402a9ae91cc677285bc5eb997834bd2349fa486f53910fd68b3';

    /** rembg — background removal. */
    private const REMBG_VERSION = 'fb8af171cfa1616ddcf1242c093f9c46bcada5ad4cf6f2fbe8b81b330ec5c003';

    /** Caps the async wait; a prediction still running past this fails the job. */
    private const POLL_TIMEOUT_SECONDS = 300;

    private const POLL_INTERVAL_SECONDS = 3;

    /**
     * Text-to-image (and image-to-image when `image` bytes are supplied) for a
     * Replicate-hosted model — Flux, SDXL, etc. The version hash is not hardcoded:
     * it comes from the model row's `meta.replicate_version`, passed in as
     * `options['version']`.
     *
     * @param  array<string, mixed>  $options  version (required), negative_prompt, seed,
     *                                         aspect_ratio, width, height, num_outputs, image (bytes)
     * @return array<int, string>  raw image bytes, one entry per output
     */
    public function generate(string $prompt, array $options = []): array
    {
        $version = trim((string) ($options['version'] ?? ''));

        if ($version === '') {
            throw new ImageOperationException(translate('This Replicate model is missing its version. Set meta.replicate_version on the model.'));
        }

        $input = array_filter([
            'prompt' => $prompt,
            'negative_prompt' => $options['negative_prompt'] ?? null,
            'aspect_ratio' => $options['aspect_ratio'] ?? null,
            'width' => isset($options['width']) ? (int) $options['width'] : null,
            'height' => isset($options['height']) ? (int) $options['height'] : null,
            'num_outputs' => isset($options['num_outputs']) ? max(1, (int) $options['num_outputs']) : null,
            'seed' => isset($options['seed']) ? (int) $options['seed'] : null,
            'output_format' => (string) ($options['output_format'] ?? 'png'),
        ], fn ($value) => $value !== null && $value !== '');

        if (is_string($options['image'] ?? null) && $options['image'] !== '') {
            $input['image'] = $this->toDataUri($options['image']);
        }

        $urls = $this->createAndPoll($version, $input, (int) ($options['poll_timeout'] ?? self::POLL_TIMEOUT_SECONDS));

        return array_map(fn (string $url) => $this->download($url), $urls);
    }

    /**
     * Fixed 4× upscale.
     *
     * @param  array<string, mixed>  $options
     */
    public function upscale(string $image, array $options = []): string
    {
        $input = [
            'image' => $this->toDataUri($image),
            'scale' => (int) ($options['scale'] ?? 4),
            'face_enhance' => (bool) ($options['face_enhance'] ?? false),
        ];

        return $this->first($this->createAndPoll(
            self::UPSCALE_VERSION,
            $input,
            (int) ($options['poll_timeout'] ?? self::POLL_TIMEOUT_SECONDS),
        ));
    }

    /**
     * Stable-diffusion inpaint.
     *
     * @param  array<string, mixed>  $options
     */
    public function sdInpaint(string $image, string $mask, string $prompt, array $options = []): string
    {
        $input = array_filter([
            'image' => $this->toDataUri($image),
            'mask' => $this->toDataUri($mask),
            'prompt' => $prompt,
            'negative_prompt' => $options['negative_prompt'] ?? null,
            'num_outputs' => 1,
        ], fn ($value) => $value !== null && $value !== '');

        return $this->first($this->createAndPoll(
            self::INPAINT_VERSION,
            $input,
            (int) ($options['poll_timeout'] ?? self::POLL_TIMEOUT_SECONDS),
        ));
    }

    /**
     * Background removal via rembg.
     *
     * @param  array<string, mixed>  $options
     */
    public function removeBackground(string $image, array $options = []): string
    {
        $input = ['image' => $this->toDataUri($image)];

        return $this->first($this->createAndPoll(
            self::REMBG_VERSION,
            $input,
            (int) ($options['poll_timeout'] ?? self::POLL_TIMEOUT_SECONDS),
        ));
    }

    /**
     * Submit a prediction and wait for it, bounded by an explicit deadline.
     *
     * @param  array<string, mixed>  $input
     * @return array<int, string>  output image URLs
     *
     * @throws ImageOperationException
     */
    private function createAndPoll(string $version, array $input, int $timeoutSeconds): array
    {
        $response = Http::timeout(60)
            ->withToken($this->apiKey())
            ->post(self::BASE_URL . '/predictions', [
                'version' => $version,
                'input' => $input,
            ]);

        if (! $response->successful()) {
            throw new ImageOperationException($this->errorMessage($response, 'prediction'));
        }

        $id = (string) $response->json('id', '');

        if ($id === '') {
            throw new ImageOperationException(translate('Replicate accepted the request but returned no prediction id.'));
        }

        $deadline = time() + max(self::POLL_INTERVAL_SECONDS, $timeoutSeconds);

        while (time() < $deadline) {
            sleep(self::POLL_INTERVAL_SECONDS);

            $poll = Http::timeout(30)
                ->withToken($this->apiKey())
                ->get(self::BASE_URL . "/predictions/{$id}");

            if (! $poll->successful()) {
                throw new ImageOperationException($this->errorMessage($poll, 'poll'));
            }

            $status = (string) $poll->json('status', '');

            if ($status === 'succeeded') {
                return $this->outputUrls($poll->json('output'));
            }

            if (in_array($status, ['failed', 'canceled'], true)) {
                $error = $poll->json('error');
                throw new ImageOperationException(
                    "Replicate prediction {$status}" . (is_string($error) && $error !== '' ? ": {$error}" : '.')
                );
            }
        }

        throw new ImageOperationException(
            translate('The image provider took too long to respond. Please try again.')
        );
    }

    /**
     * @param  mixed  $output
     * @return array<int, string>
     *
     * @throws ImageOperationException
     */
    private function outputUrls(mixed $output): array
    {
        $urls = is_array($output) ? array_values($output) : [$output];
        $urls = array_values(array_filter($urls, fn ($url) => is_string($url) && $url !== ''));

        if ($urls === []) {
            throw new ImageOperationException(translate('Replicate finished but returned no image.'));
        }

        return $urls;
    }

    /**
     * @param  array<int, string>  $urls
     */
    private function first(array $urls): string
    {
        return $this->download($urls[0]);
    }

    private function download(string $url): string
    {
        $response = Http::timeout(120)->get($url);

        if (! $response->successful() || $response->body() === '') {
            throw new ImageOperationException(translate('Failed to download the Replicate output image.'));
        }

        return $response->body();
    }

    private function toDataUri(string $binary): string
    {
        $info = @getimagesizefromstring($binary);
        $mime = is_array($info) && ! empty($info['mime']) ? (string) $info['mime'] : 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }

    private function errorMessage(Response $response, string $stage): string
    {
        $detail = (string) ($response->json('detail') ?? $response->json('title') ?? '');

        return "Replicate {$stage} failed" . ($detail !== '' ? ": {$detail}" : ' (HTTP ' . $response->status() . ').');
    }

    private function apiKey(): string
    {
        $key = (string) addon_setting(OperationRegistry::SLUG, 'replicate_api_key', '');

        if (trim($key) === '') {
            throw new ImageOperationException(translate('The Replicate API key is not configured.'));
        }

        return $key;
    }
}
