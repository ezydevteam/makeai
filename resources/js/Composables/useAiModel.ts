import { usePage } from '@inertiajs/vue3'

export function useAiModel() {
    const page = usePage()

    const getModelNames = (): Record<string, string> => {
        return (page.props.ai as any)?.model_names || {}
    }

    const getProviderNames = (): Record<string, string> => {
        return (page.props.ai as any)?.provider_names || {}
    }

    const friendlyModelName = (modelSlug: string, customModelsList: any[] = []): string => {
        if (customModelsList && customModelsList.length) {
            const custom = customModelsList.find((cm) => cm.id === modelSlug)
            if (custom) {
                return custom.name
            }
        }

        const modelNames = getModelNames()
        return modelNames[modelSlug] || modelSlug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
    }

    const modelProviderName = (modelSlug: string): string => {
        const providersConfig = getProviderNames()
        const slug = modelSlug.toLowerCase()

        if (slug.startsWith('gpt') || slug.startsWith('o1') || slug.startsWith('o3') || slug.startsWith('dall')) {
            return providersConfig['openai'] || 'OpenAI'
        }
        if (slug.startsWith('claude')) {
            return providersConfig['anthropic'] || 'Anthropic'
        }
        if (slug.startsWith('gemini')) {
            return providersConfig['google'] || 'Google'
        }
        if (slug.startsWith('deepseek')) {
            return providersConfig['deepseek'] || 'DeepSeek'
        }
        if (slug.startsWith('mistral')) {
            return providersConfig['mistral'] || 'Mistral'
        }
        if (slug.startsWith('perplexity') || slug.startsWith('sonar')) {
            return providersConfig['perplexity'] || 'Perplexity'
        }
        if (slug.startsWith('flux')) {
            return providersConfig['flux'] || 'Flux'
        }
        if (slug.startsWith('ideogram')) {
            return providersConfig['ideogram'] || 'Ideogram'
        }
        if (slug.startsWith('stability')) {
            return providersConfig['stability'] || 'Stability'
        }

        return 'Other'
    }

    return {
        friendlyModelName,
        modelProviderName,
        modelNames: getModelNames,
        providerNames: getProviderNames,
    }
}
