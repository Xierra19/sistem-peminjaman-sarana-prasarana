<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DarkModeSwitch from '@/Components/DarkModeSwitch.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col overflow-hidden bg-slate-950 px-4 py-6 text-slate-100 sm:px-6 lg:px-8"
    >
        <div class="absolute inset-0">
            <div class="absolute -left-16 top-16 hidden h-72 w-72 rounded-full bg-blue-500/20 blur-[160px] md:block"></div>
            <div class="absolute -right-10 bottom-10 h-80 w-80 rounded-full bg-cyan-400/10 blur-[170px]"></div>
            <div class="absolute inset-x-0 top-24 mx-auto h-64 w-[28rem] rounded-full bg-white/5 blur-[150px]"></div>
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
            class="w-full max-w-md overflow-hidden rounded-[28px] border border-white/10 bg-white px-5 py-5 text-slate-900 shadow-[0_24px_90px_rgba(15,23,42,0.55)] ring-1 ring-slate-100 sm:px-6 sm:py-6"
        >
            <slot />
        </div>
        </div>
    </div>
</template>
