<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services;

use Addons\AiImagePro\Models\AipJob;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Response;

/**
 * Flat-rate credit charging for `provider`-tier operations (upscale, bg_remove,
 * inpaint, …). Generation is billed separately through TokenGuard's media path;
 * this service never touches that.
 *
 * The whole point of this class is that it is correct under BOTH license modes
 * (see the addon-credit-mode rules): it charges through deduct_credits() and
 * refunds through User::refundCredits(), never a raw wallet increment/decrement,
 * and its balance pre-check is guarded so a quota-mode user with a zero wallet is
 * not wrongly blocked.
 */
class CreditService
{
    /**
     * Pre-flight affordability check. In quota mode the wallet is meaningless
     * (usage meters against a resetting allowance that deduct_credits() enforces
     * later), so we only block on wallet balance in metered mode.
     *
     * @throws HttpResponseException 402 when a metered-mode user can't afford it.
     */
    public function assertCanAffordFlat(?User $user, int $credits): void
    {
        if ($credits <= 0 || ! $user) {
            return;
        }

        if (! credit_quota_mode() && $user->credits < $credits) {
            throw new HttpResponseException(
                Response::json([
                    'success' => false,
                    'message' => translate('Insufficient credits.'),
                ], 402)
            );
        }
    }

    /**
     * Charge the flat cost up front and record it on the job so the refund path
     * knows exactly what to return. No-op when nothing is owed or there is no
     * user (guest operations the admin priced at 0).
     */
    public function chargeFlat(?User $user, AipJob $job, int $credits): void
    {
        $job->billing_mode = OperationRegistry::BILLING_FLAT;

        if ($credits <= 0 || ! $user) {
            $job->credits_charged = 0;
            $job->save();

            return;
        }

        deduct_credits($user->id, (float) $credits, 'AI Image Pro: ' . $job->operation);

        $job->credits_charged = $credits;
        $job->save();
    }

    /**
     * Return the credits charged for a job. Idempotent: the job's `refunded`
     * flag guarantees a retried/failed job can't refund twice. Mode-aware —
     * User::refundCredits() winds back the metered allowance in quota mode and
     * re-credits the wallet in metered mode, matching however chargeFlat() took
     * it. A raw increment here would hand a quota-mode user free wallet balance.
     */
    public function refund(AipJob $job): void
    {
        if ($job->refunded || $job->credits_charged <= 0 || ! $job->user_id) {
            return;
        }

        $user = $job->user;
        if (! $user) {
            return;
        }

        $user->refundCredits(
            (float) $job->credits_charged,
            'AI Image Pro refund: ' . $job->operation,
            ['aip_job' => $job->ulid],
        );

        $job->forceFill(['refunded' => true])->save();
    }
}
