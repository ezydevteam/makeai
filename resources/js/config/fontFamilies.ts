export type FontFamilyOption = {
    label: string
    value: string
    category: string
}

export const FONT_FAMILY_OPTIONS: FontFamilyOption[] = [
    { label: 'Inter', value: 'Inter', category: 'Latin' },
    { label: 'Plus Jakarta Sans', value: 'Plus Jakarta Sans', category: 'Latin' },
    { label: 'Poppins', value: 'Poppins', category: 'Latin' },
    { label: 'DM Sans', value: 'DM Sans', category: 'Latin' },
    { label: 'Nunito', value: 'Nunito', category: 'Latin' },
    { label: 'Montserrat', value: 'Montserrat', category: 'Latin' },
    { label: 'Source Sans 3', value: 'Source Sans 3', category: 'Latin' },
    { label: 'Roboto', value: 'Roboto', category: 'Latin' },
    { label: 'Open Sans', value: 'Open Sans', category: 'Latin' },
    { label: 'Lato', value: 'Lato', category: 'Latin' },
    { label: 'Raleway', value: 'Raleway', category: 'Latin' },
    { label: 'Ubuntu', value: 'Ubuntu', category: 'Latin' },
    { label: 'Merriweather', value: 'Merriweather', category: 'Latin Serif' },
    { label: 'Playfair Display', value: 'Playfair Display', category: 'Latin Serif' },
    { label: 'Libre Baskerville', value: 'Libre Baskerville', category: 'Latin Serif' },
    { label: 'Noto Sans Bengali', value: 'Noto Sans Bengali', category: 'Bangla' },
    { label: 'Hind Siliguri', value: 'Hind Siliguri', category: 'Bangla' },
    { label: 'Noto Sans Devanagari', value: 'Noto Sans Devanagari', category: 'Hindi' },
    { label: 'Hind', value: 'Hind', category: 'Hindi' },
    { label: 'Noto Sans Tamil', value: 'Noto Sans Tamil', category: 'Tamil' },
    { label: 'Catamaran', value: 'Catamaran', category: 'Tamil' },
    { label: 'Noto Sans Telugu', value: 'Noto Sans Telugu', category: 'Telugu' },
    { label: 'Noto Nastaliq Urdu', value: 'Noto Nastaliq Urdu', category: 'Urdu' },
    { label: 'Noto Sans Arabic', value: 'Noto Sans Arabic', category: 'Arabic' },
    { label: 'Cairo', value: 'Cairo', category: 'Arabic' },
    { label: 'Noto Sans Hebrew', value: 'Noto Sans Hebrew', category: 'Hebrew' },
    { label: 'Rubik', value: 'Rubik', category: 'Hebrew' },
    { label: 'Noto Sans SC', value: 'Noto Sans SC', category: 'Chinese Simplified' },
    { label: 'Noto Serif SC', value: 'Noto Serif SC', category: 'Chinese Simplified' },
    { label: 'Noto Sans JP', value: 'Noto Sans JP', category: 'Japanese' },
    { label: 'M PLUS 1p', value: 'M PLUS 1p', category: 'Japanese' },
    { label: 'Noto Sans Thai', value: 'Noto Sans Thai', category: 'Thai' },
    { label: 'Sarabun', value: 'Sarabun', category: 'Thai' },
    { label: 'Noto Sans KR', value: 'Noto Sans KR', category: 'Korean' },
    { label: 'Nanum Gothic', value: 'Nanum Gothic', category: 'Korean' },
    { label: 'Roboto Flex', value: 'Roboto Flex', category: 'Global Sans' },
    { label: 'Noto Sans', value: 'Noto Sans', category: 'Global Sans' },
    { label: 'Noto Serif', value: 'Noto Serif', category: 'Global Serif' },
    { label: 'PT Sans', value: 'PT Sans', category: 'Cyrillic' },
    { label: 'Roboto Slab', value: 'Roboto Slab', category: 'Cyrillic' },
    { label: 'system-ui', value: 'system-ui', category: 'System' },
    { label: 'Arial', value: 'Arial', category: 'System' },
    { label: 'Georgia', value: 'Georgia', category: 'System Serif' },
].map((item) => ({
    ...item,
    value: item.value,
}))

export const FONT_FAMILY_SELECT_OPTIONS = FONT_FAMILY_OPTIONS.map((font) => ({
    value: font.value,
    label: font.label,
}))

export const RICH_EDITOR_FONT_OPTIONS = FONT_FAMILY_OPTIONS.map((font) => ({
    label: font.label,
    value: `"${font.value}", sans-serif`,
}))
