<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Exceptions;

use RuntimeException;

/**
 * The addon's single failure type for anything that goes wrong while producing
 * an image: a rejected input, an unusable file, a provider error, a poll that
 * timed out.
 *
 * `RunImageJob` catches exactly this class to mark the job failed and issue the
 * refund, so anything that should be a *clean, user-visible, refundable* failure
 * must be thrown as this — an unexpected \Throwable escapes to `failed()` and is
 * retried first.
 */
class ImageOperationException extends RuntimeException
{
}
