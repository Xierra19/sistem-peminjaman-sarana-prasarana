<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DarkModeSwitch from '@/Components/DarkModeSwitch.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col overflow-hidden bg-slate-100 px-4 py-6 text-slate-900 transition-colors sm:px-6 lg:px-8 dark:bg-slate-950 dark:text-slate-100"
    >
        <div class="absolute inset-0">
            <div class="absolute -left-16 top-16 hidden h-72 w-72 rounded-full bg-blue-300/30 blur-[160px] md:block dark:bg-blue-500/20"></div>
            <div class="absolute -right-10 bottom-10 h-80 w-80 rounded-full bg-cyan-300/20 blur-[170px] dark:bg-cyan-400/10"></div>
            <div class="absolute inset-x-0 top-24 mx-auto h-64 w-[28rem] rounded-full bg-slate-300/25 blur-[150px] dark:bg-white/5"></div>
        </div>
        <div class="absolute right-4 top-4 z-20 sm:right-6 sm:top-6">
            <DarkModeSwitch />
        </div>
        <div class="relative z-10 flex min-h-screen flex-col items-center justify-center">
        <div class="mb-6">
            <Link href="/">
                <ApplicationLogo class="h-16 w-16 fill-current text-white/90 sm:h-20 sm:w-20" />
            </Link>
        </div>

        <div
            class="w-full max-w-md overflow-hidden rounded-[28px] border border-white/60 bg-white/90 px-5 py-5 text-slate-900 shadow-[0_24px_90px_rgba(15,23,42,0.12)] ring-1 ring-slate-200/80 backdrop-blur-sm sm:px-6 sm:py-6 dark:border-white/10 dark:bg-slate-900/85 dark:text-slate-100 dark:shadow-[0_24px_90px_rgba(15,23,42,0.55)] dark:ring-white/10"
        >
            <slot />
        </div>
        </div>
    </div>
</template>
