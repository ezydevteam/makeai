<?php

namespace App\DataTransferObjects;

readonly class LicenseResult
{
    /**
     * @param  bool    $valid         Whether the license was successfully verified
     * @param  int     $type          License type: 1 = Regular, 2 = Extended
     * @param  string  $buyer         Envato buyer username
     * @param  string  $purchaseDate  ISO date of purchase
     * @param  string  $license       Raw license type string from Envato ("Regular License" / "Extended License")
     * @param  string  $error         Error message if verification failed
     * @param  string  $errorCode     Machine-readable error code (invalid_code, item_mismatch, etc.)
     */
    public function __construct(
        public bool $valid,
        public int $type = 1,
        public string $buyer = '',
        public string $purchaseDate = '',
        public string $license = '',
        public string $error = '',
        public string $errorCode = '',
    ) {}

    public static function success(array $data): self
    {
        return new self(
            valid: true,
            type: $data['type'],
            buyer: $data['buyer'],
            purchaseDate: $data['purchase_date'],
            license: $data['license'],
        );
    }

    public static function failure(string $error, string $errorCode = ''): self
    {
        return new self(
            valid: false,
            error: $error,
            errorCode: $errorCode,
        );
    }
}
