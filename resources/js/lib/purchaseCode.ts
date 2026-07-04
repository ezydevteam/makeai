import { usePage } from '@inertiajs/vue3'

/**
 * Frontend purchase-code input masking, driven by the backend single source of
 * truth (App\Support\PurchaseCode -> shared `purchaseCodeFormat` Inertia prop).
 *
 * Do NOT hardcode the format here — it is derived from the prop so the mask always
 * matches server-side validation. Edit the format in app/Support/PurchaseCode.php.
 */

interface MaskMode {
    allowed: string
    maxLength: number
    case: 'upper' | 'lower'
}

interface PurchaseCodeFormat {
    testMode: boolean
    uuid: MaskMode
    test: MaskMode
}

// Fallback mirrors PurchaseCode::frontendConfig() defaults, used only if the prop
// is somehow unavailable (e.g. a page that doesn't share it).
const FALLBACK: PurchaseCodeFormat = {
    testMode: false,
    uuid: { allowed: 'a-f0-9-', maxLength: 36, case: 'lower' },
    test: { allowed: 'A-Za-z0-9-', maxLength: 50, case: 'upper' },
}

function getFormat(): PurchaseCodeFormat {
    const fmt = (usePage().props as Record<string, unknown>).purchaseCodeFormat
    return (fmt as PurchaseCodeFormat) ?? FALLBACK
}

/**
 * Clean/format a purchase-code value as the user types.
 *
 * @param value        the raw input value
 * @param allowTestMode when true (license/install screens) the relaxed TEST-...
 *                      format is used while test mode is active. Addon activation
 *                      passes false — addon codes are always strict UUIDs.
 */
export function applyPurchaseCodeMask(value: string, allowTestMode = false): string {
    const fmt = getFormat()
    const mode: MaskMode = allowTestMode && fmt.testMode ? fmt.test : fmt.uuid

    const cleaned = value
        .replace(new RegExp(`[^${mode.allowed}]`, 'gi'), '')
        .slice(0, mode.maxLength)

    return mode.case === 'upper' ? cleaned.toUpperCase() : cleaned.toLowerCase()
}
