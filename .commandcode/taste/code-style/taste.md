# code-style
- Use theme primary color variables (e.g., `text-primary-500`, `bg-primary-500`) instead of hardcoded green color shades or hex values. Confidence: 0.75
- In raw CSS within `.vue` files, reference the project's primary color as `var(--color-primary-500)` (the actual CSS custom property from `app.css`/Tailwind theme), not `var(--primary)` which doesn't exist. Confidence: 0.70
- Prefer Tailwind utility classes (`text-primary-500`, `bg-primary-500`) over CSS custom properties (`var(--color-primary-500)`) for theme colors — the Tailwind classes are more reliable and consistently resolve in this project's setup. Confidence: 0.70
- When inline `style` with CSS custom properties (e.g., `var(--color-primary-500)`) is overridden by external CSS specificity, add `!important` to force the inline style to take precedence. Confidence: 0.65
- Prefer simple, proven solutions (existing tools, libraries, shell commands) over custom-built implementations that are prone to bugs. Confidence: 0.80
- When implementing a module or feature, ensure all parts are complete — don't omit sub-features like admin article editors (Tiptap), required UI components, or supporting functionality that the prompt/context clearly implies. Confidence: 0.70
