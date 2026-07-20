<script setup lang="ts">
/**
 * Decorative backdrop for the Home tab.
 *
 * Lives in the shell (not in HomePanel) so it can sit BEHIND the header as well as the
 * greeting — on Home the header is transparent and the artwork runs to the very top of
 * the window, as it does in the reference design.
 *
 * Drawn with currentColor tinted from --ai-accent, so it takes on whatever accent colour
 * the admin has set rather than shipping a fixed-brand asset. Purely ornamental, hence
 * aria-hidden and pointer-events-none.
 */
</script>

<template>
    <!--
        Layering: the window isolates its own stacking context, this sits at z-0 (above the
        window's background), and the header/body sit at z-10 above it. Deliberately NOT a
        negative z-index — the window is `relative` without a z-index, so it forms no
        stacking context of its own and a negative value escapes behind its white
        background, making the artwork invisible.
    -->
    <div
        class="pointer-events-none absolute inset-x-0 top-0 z-0 h-[230px] overflow-hidden text-[var(--ai-accent,#1F75FE)]"
        aria-hidden="true"
    >
        <svg class="h-full w-full" viewBox="0 0 420 230" fill="none" preserveAspectRatio="xMidYMid slice">
            <defs>
                <linearGradient id="ai-hero-fade" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="currentColor" stop-opacity="0.18" />
                    <stop offset="100%" stop-color="currentColor" stop-opacity="0" />
                </linearGradient>
            </defs>

            <rect width="420" height="230" fill="url(#ai-hero-fade)" />

            <!-- Soft orbs -->
            <circle cx="58" cy="30" r="48" fill="currentColor" opacity="0.10" />
            <circle cx="372" cy="18" r="32" fill="currentColor" opacity="0.12" />
            <circle cx="336" cy="132" r="14" fill="currentColor" opacity="0.09" />
            <circle cx="26" cy="146" r="9" fill="currentColor" opacity="0.10" />

            <!-- Sweeping arcs -->
            <path
                d="M-20 168 Q 120 96 210 148 T 440 108"
                stroke="currentColor"
                stroke-opacity="0.14"
                stroke-width="1.5"
                fill="none"
            />
            <path
                d="M-20 196 Q 140 132 240 176 T 440 142"
                stroke="currentColor"
                stroke-opacity="0.08"
                stroke-width="1.5"
                fill="none"
            />
        </svg>
    </div>
</template>
