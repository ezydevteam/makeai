import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

interface LocaleProp {
    code: string
}

interface CurrencyProp {
    code: string
    symbol: string
    position: 'before' | 'before_with_space' | 'after' | 'after_with_space'
    decimals: number
}

export function useNumberFormat() {
    const page = usePage()
    const locale = computed(() => (page.props.locale as LocaleProp)?.code ?? 'en')
    const currency = computed(() => (page.props.currency as CurrencyProp) ?? {
        code: 'USD',
        symbol: '$',
        position: 'before',
        decimals: 2,
    })

    const formatNumber = (value: number, decimals = 0): string => new Intl.NumberFormat(locale.value, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value)

    const formatCurrencyWithPosition = (formatted: string, symbol: string, position: CurrencyProp['position']): string => {
        if (position === 'before_with_space') {
            return `${symbol} ${formatted}`
        }

        if (position === 'after') {
            return `${formatted}${symbol}`
        }

        if (position === 'after_with_space') {
            return `${formatted} ${symbol}`
        }

        return `${symbol}${formatted}`
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
            const parts = new Intl.NumberFormat(locale.value, {
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

    const formatCompact = (value: number): string => new Intl.NumberFormat(locale.value, {
        notation: 'compact',
        compactDisplay: 'short',
    }).format(value)

    const formatCredits = (value: number): string => formatNumber(value, 2)

    return { formatNumber, formatCurrency, formatCompact, formatCredits }
}
