<script setup lang="ts">
import logo from '../assets/images/makeai.png'

const year = new Date().getFullYear()
</script>

<template>
    <div class="min-h-screen flex flex-col bg-gradient-to-b from-[#edf4ff] via-slate-50 to-slate-50">
        <div class="flex-1 flex items-center justify-center p-6">
            <div class="w-full max-w-2xl">
                <!-- Header / greeting -->
                <div class="text-center mb-6">
                    <img :src="logo" alt="MakeAI" title="MakeAI" class="mx-auto h-12 w-auto" />
                    <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900">
                        Thanks for purchasing <span class="bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500 bg-clip-text !text-transparent">MakeAI</span>
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">Let's get your installation set up.</p>
                </div>

                <!-- Card -->
                <div class="rounded-2xl bg-white p-6 shadow-xl ring-1 ring-slate-100 sm:p-8">
                    <slot />
                </div>

                <!-- Footer menu -->
                <div class="mt-6 flex items-center justify-center gap-6 text-sm">
                    <a
                        href="https://makeai-docs.ezydev.net/"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 font-medium text-[#1757bc] transition-colors hover:text-[#1F75FE]"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        Documentation
                    </a>
                    <span class="h-4 w-px bg-slate-200" />
                    <a
                        href="https://makeai.ezydev.net"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 font-medium text-[#1757bc] transition-colors hover:text-[#1F75FE]"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M4 14v-3a8 8 0 1 1 16 0v3" /><path d="M18 19c0 1.657 -2.686 3 -6 3" /><path d="M4 14a2 2 0 0 1 2 -2h1a2 2 0 0 1 2 2v3a2 2 0 0 1 -2 2h-1a2 2 0 0 1 -2 -2v-3" /><path d="M15 14a2 2 0 0 1 2 -2h1a2 2 0 0 1 2 2v3a2 2 0 0 1 -2 2h-1a2 2 0 0 1 -2 -2v-3" />
                        </svg>
                        Get Support
                    </a>
                </div>

                <p class="mt-4 text-center text-xs text-slate-400">
                    &copy; {{ year }} MakeAI - Developed by <a
                        href="https://makeai.ezydev.net"
                        class="hover:text-[#1F75FE] hover:underline"
                        target="_blank"
                        rel="noopener">EzyDev</a>. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</template>
