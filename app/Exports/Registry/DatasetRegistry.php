<?php

namespace App\Exports\Registry;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Central catalogue of exportable datasets.
 *
 * The Export Center drives its type picker, export, and estimate endpoints
 * entirely off this registry, so a dataset's availability gate (see
 * Dataset::isAvailable) is enforced in exactly one place.
 */
class DatasetRegistry
{
    /**
     * All registered dataset classes. Order defines picker order.
     *
     * @var array<int,class-string<Dataset>>
     */
    private array $datasets = [
        // Core (both licenses — data exists regardless of billing)
        Datasets\UsersDataset::class,
        Datasets\AiUsageDataset::class,
        Datasets\GenerationHistoryDataset::class,
        Datasets\CreditLedgerDataset::class,
        Datasets\AiToolsCatalogDataset::class,
        Datasets\NewsletterSubscribersDataset::class,
        Datasets\SupportTicketsDataset::class,
        Datasets\ContactMessagesDataset::class,
        Datasets\LoginHistoryDataset::class,
        // Extended-gated (data only exists with billing / affiliate on)
        Datasets\RevenueDataset::class,
        Datasets\SubscriptionsDataset::class,
        Datasets\RefundsDataset::class,
        Datasets\CouponRedemptionsDataset::class,
        Datasets\AffiliatesDataset::class,
        Datasets\AffiliateReferralsDataset::class,
        Datasets\AffiliatePayoutsDataset::class,
    ];

    /** @var array<string,Dataset>|null */
    private ?array $resolved = null;

    /**
     * All datasets keyed by key(), regardless of availability.
     *
     * @return array<string,Dataset>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $resolved = [];
        foreach ($this->datasets as $class) {
            /** @var Dataset $instance */
            $instance = app($class);
            $resolved[$instance->key()] = $instance;
        }

        return $this->resolved = $resolved;
    }

    /**
     * Only datasets available under the current license/settings.
     *
     * @return array<string,Dataset>
     */
    public function available(): array
    {
        return array_filter($this->all(), fn (Dataset $d) => $d->isAvailable());
    }

    /** @return string[] */
    public function availableKeys(): array
    {
        return array_keys($this->available());
    }

    public function find(string $key): ?Dataset
    {
        return $this->available()[$key] ?? null;
    }

    /**
     * Resolve an available dataset or abort — defence-in-depth for the export
     * and estimate endpoints beyond request validation.
     */
    public function resolve(string $key): Dataset
    {
        $dataset = $this->find($key);

        if ($dataset === null) {
            throw new NotFoundHttpException('Unknown or unavailable export dataset.');
        }

        return $dataset;
    }
}
