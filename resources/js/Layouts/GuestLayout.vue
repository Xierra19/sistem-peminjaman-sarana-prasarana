<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import DarkModeSwitch from '@/Components/DarkModeSwitch.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0 dark:bg-slate-950"
    >
        <div class="absolute right-4 top-4">
            <DarkModeSwitch />
        </div>
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-gray-500 dark:text-slate-300" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg dark:bg-slate-800 dark:text-slate-200"
        >
            <slot />
        </div>
    </div>
</template>
