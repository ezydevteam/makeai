import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

interface LocaleProp {
    code: string
    number_format?: {
        decimal: string
        thousands: string
        system: string
    }
}

interface CurrencyProp {
    code: string
    symbol: string
    position: 'before' | 'before_with_space' | 'after' | 'after_with_space'
    decimals: number
}

const digitMaps: Record<string, string[]> = {
    latn: ['0','1','2','3','4','5','6','7','8','9'],
    arab: ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'],
    arabext: ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
    beng: ['০','১','২','৩','৪','৫','৬','৭','৮','৯'],
    deva: ['०','१','२','३','४','५','६','७','८','९'],
}

function convertDigits(value: string, system: string): string {
    const map = digitMaps[system]
    if (!map || system === 'latn') return value
    return value.replace(/\d/g, (d) => map[Number(d)])
}

function formatRaw(value: number, decimals: number, decimalSep: string, thousandsSep: string): string {
    const fixed = Math.abs(value).toFixed(decimals)
    const [intPart, fracPart] = fixed.split('.')
    const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep)
    const sign = value < 0 ? '-' : ''
    return sign + grouped + (fracPart ? decimalSep + fracPart : '')
}

export function useNumberFormat() {
    const page = usePage()
    const localeCode = computed(() => (page.props.locale as LocaleProp)?.code ?? 'en')
    const numberFormat = computed(() => (page.props.locale as LocaleProp)?.number_format ?? {
        decimal: '.',
        thousands: ',',
        system: 'latn',
    })
    const currency = computed(() => (page.props.currency as CurrencyProp) ?? {
        code: 'USD',
        symbol: '$',
        position: 'before',
        decimals: 2,
    })

    const formatNumber = (value: number, decimals = 0): string => {
        const raw = formatRaw(value, decimals, numberFormat.value.decimal, numberFormat.value.thousands)
        return convertDigits(raw, numberFormat.value.system)
    }

    const formatCurrencyWithPosition = (formatted: string, symbol: string, position: CurrencyProp['position']): string => {
        switch (position) {
            case 'before_with_space': return `${symbol} ${formatted}`
            case 'after': return `${formatted}${symbol}`
            case 'after_with_space': return `${formatted} ${symbol}`
            default: return `${symbol}${formatted}`
        }
    }

    const formatCurrency = (value: number, currencyOverride?: string): string => {
        const code = currencyOverride ?? currency.value.code

        if (!currencyOverride || code === currency.value.code) {
            return formatCurrencyWithPosition(
                formatNumber(value, currency.value.decimals),
                currency.value.symbol,
                currency.value.position,
            )
        }

        try {
            const parts = new Intl.NumberFormat(localeCode.value, {
                style: 'currency',
                currency: code,
                minimumFractionDigits: currency.value.decimals,
                maximumFractionDigits: currency.value.decimals,
            }).formatToParts(value)
            const symbol = parts.find((part) => part.type === 'currency')?.value ?? code
            const formatted = parts
                .filter((part) => part.type !== 'currency' && part.type !== 'literal')
                .map((part) => part.value)
                .join('')

            return formatCurrencyWithPosition(formatted, symbol, currency.value.position)
        } catch {
            const formatted = formatNumber(value, currency.value.decimals)
            return formatCurrencyWithPosition(formatted, code, currency.value.position)
        }
    }

    const formatCompact = (value: number): string => {
        const raw = new Intl.NumberFormat(localeCode.value, {
            notation: 'compact',
            compactDisplay: 'short',
        }).format(value)
        return convertDigits(raw, numberFormat.value.system)
    }

    const formatCredits = (value: number): string => formatNumber(value, 2)

    return { formatNumber, formatCurrency, formatCompact, formatCredits }
}
