<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Mail, RefreshCcw, ShieldCheck } from 'lucide-vue-next';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = reactive({
    email: '',
    captchaToken: '',
    processing: false,
    message: '',
    errors: {},
});

const page = usePage();
const captchaContainer = ref(null);
const captchaConfig = computed(() => page.props.security?.captcha ?? {
    enabled: false,
    site_key: null,
});
const captchaEnabled = computed(() => Boolean(captchaConfig.value.enabled));
const captchaSiteKey = computed(() => captchaConfig.value.site_key);
let turnstileWidgetId = null;

const infoCards = [
    {
        title: 'Kode berlaku 10 menit',
        description: 'Kode OTP kedaluwarsa otomatis dan dapat diminta ulang setelah cooldown.',
        icon: RefreshCcw,
    },
    {
        title: 'Email kampus terverifikasi',
        description: 'Kami mengirim instruksi hanya ke alamat email akun yang terdaftar.',
        icon: ShieldCheck,
    },
];

const steps = [
    'Masukkan email kampus yang terdaftar.',
    'Periksa inbox / folder spam untuk kode OTP dari sistem.',
    'Masukkan kode OTP dan buat kata sandi baru.',
];

onMounted(() => {
    renderCaptcha();
});

onUnmounted(() => {
    if (turnstileWidgetId !== null && window.turnstile) {
        window.turnstile.remove(turnstileWidgetId);
    }
});

function loadTurnstileScript() {
    if (window.turnstile) {
        return Promise.resolve();
    }

    const existingScript = document.querySelector('script[data-turnstile]');
    if (existingScript) {
        return new Promise((resolve) => {
            existingScript.addEventListener('load', resolve, { once: true });
        });
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.defer = true;
        script.dataset.turnstile = 'true';
        script.addEventListener('load', resolve, { once: true });
        script.addEventListener('error', reject, { once: true });
        document.head.appendChild(script);
    });
}

async function renderCaptcha() {
    if (!captchaEnabled.value || !captchaSiteKey.value || !captchaContainer.value) {
        return;
    }

    await nextTick();
    await loadTurnstileScript();

    if (turnstileWidgetId !== null || !window.turnstile || !captchaContainer.value) {
        return;
    }

    turnstileWidgetId = window.turnstile.render(captchaContainer.value, {
        sitekey: captchaSiteKey.value,
        callback: (token) => {
            form.captchaToken = token;
        },
        'expired-callback': () => {
            form.captchaToken = '';
        },
        'error-callback': () => {
            form.captchaToken = '';
        },
    });
}

function resetCaptcha() {
    form.captchaToken = '';

    if (turnstileWidgetId !== null && window.turnstile) {
        window.turnstile.reset(turnstileWidgetId);
    }
}

const submit = async () => {
    form.errors = {};
    form.message = '';

    if (captchaEnabled.value && !form.captchaToken) {
        form.errors.captcha = 'Captcha belum tervalidasi.';
        return;
    }

    form.processing = true;

    try {
        const payload = {
            email: form.email,
        };

        if (captchaEnabled.value) {
            payload.captcha_token = form.captchaToken;
        }

        await axios.post(route('api.auth.password.forgot'), payload);

        router.get(route('password.verify'), {
            email: form.email,
        });
    } catch (error) {
        form.errors = error.response?.data?.errors ?? {};
        form.errors.general = error.response?.data?.message ?? 'Permintaan belum dapat diproses. Coba lagi nanti.';
        resetCaptcha();
    } finally {
        form.processing = false;
    }
};
</script>

<template>
    <Head title="Lupa Password" />

    <div class="relative min-h-screen overflow-hidden bg-slate-950 text-white">
        <div class="absolute inset-0">
            <div class="absolute -left-16 top-16 hidden h-72 w-72 rounded-full bg-indigo-500/25 blur-[160px] md:block"></div>
            <div class="absolute -right-16 bottom-10 h-96 w-96 rounded-full bg-blue-500/30 blur-[180px]"></div>
        </div>

        <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-10">
            <div class="w-full max-w-5xl">
                <div class="grid gap-8 lg:grid-cols-[1.05fr_0.95fr]">
                    <section class="rounded-[32px] border border-white/10 bg-gradient-to-b from-white/15 to-white/5 p-6 shadow-[0_25px_90px_rgba(15,23,42,0.55)] backdrop-blur sm:p-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-indigo-200">Pemulihan akun</p>
                        <h1 class="mt-4 text-4xl font-semibold leading-tight text-white">
                            Reset kata sandi dengan aman
                        </h1>
                        <p class="mt-3 text-base text-indigo-100">
                            Kami akan mengirim kode OTP ke email akun Anda. Gunakan kode tersebut untuk membuat kata sandi baru.
                        </p>

                        <div class="mt-10 space-y-4">
                            <div
                                v-for="card in infoCards"
                                :key="card.title"
                                class="flex gap-4 rounded-2xl border border-white/10 bg-white/5 p-4"
                            >
                                <component
                                    :is="card.icon"
                                    class="h-11 w-11 flex-shrink-0 rounded-2xl bg-white/10 p-2 text-indigo-100"
                                />
                                <div>
                                    <p class="font-medium text-white">{{ card.title }}</p>
                                    <p class="text-sm text-indigo-100">{{ card.description }}</p>
                                </div>
                            </div>
                        </div>

                        <ul class="mt-10 space-y-3 text-sm text-indigo-100">
                            <li
                                v-for="step in steps"
                                :key="step"
                                class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-3"
                            >
                                <span class="mt-1 h-2 w-2 rounded-full bg-sky-300"></span>
                                <span>{{ step }}</span>
                            </li>
                        </ul>
                    </section>

                    <section class="rounded-[32px] bg-white p-6 text-slate-900 shadow-2xl ring-1 ring-slate-100 transition-colors sm:p-8 lg:p-10 dark:bg-slate-900/95 dark:text-slate-100 dark:ring-white/10">
                        <div class="mb-8 space-y-2">
                            <p class="text-sm font-semibold uppercase tracking-[0.35em] text-indigo-500 dark:text-indigo-300">Lupa password</p>
                            <h2 class="text-3xl font-semibold text-slate-900 dark:text-white">Kirim kode reset</h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Masukkan email akun Anda dan kami akan kirim kode OTP untuk verifikasi.
                            </p>
                        </div>

                        <div
                            v-if="props.status || form.message"
                            class="mb-6 rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200"
                        >
                            {{ props.status || form.message }}
                        </div>

                        <div
                            v-if="form.errors.general"
                            class="mb-6 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-sm font-medium text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200"
                        >
                            {{ form.errors.general }}
                        </div>

                        <form class="space-y-5" @submit.prevent="submit">
                            <div>
                                <label for="email" class="text-sm font-medium text-slate-700 dark:text-slate-300">Email kampus</label>
                                <div class="relative mt-2">
                                    <Mail class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 dark:text-slate-500" />
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="username"
                                        placeholder="nama@student.esaunggul.ac.id"
                                        class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/60 pl-12 pr-4 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800 dark:focus:ring-indigo-500/20"
                                        required
                                        autofocus
                                    />
                                </div>
                                <p v-if="form.errors.email" class="mt-2 text-sm text-rose-500">
                                    {{ Array.isArray(form.errors.email) ? form.errors.email[0] : form.errors.email }}
                                </p>
                            </div>

                            <div v-if="captchaEnabled">
                                <label for="captcha" class="text-sm font-medium text-slate-700 dark:text-slate-300">Verifikasi keamanan</label>
                                <div
                                    v-if="captchaSiteKey"
                                    id="captcha"
                                    ref="captchaContainer"
                                    class="mt-2 min-h-[65px]"
                                ></div>
                                <p v-else class="mt-2 text-sm text-rose-500">
                                    CAPTCHA belum dikonfigurasi.
                                </p>
                                <p v-if="form.errors.captcha" class="mt-2 text-sm text-rose-500">
                                    {{ form.errors.captcha }}
                                </p>
                                <p v-if="form.errors.captcha_token" class="mt-2 text-sm text-rose-500">
                                    {{ Array.isArray(form.errors.captcha_token) ? form.errors.captcha_token[0] : form.errors.captcha_token }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing || (captchaEnabled && !captchaSiteKey)"
                                class="flex h-12 w-full items-center justify-center rounded-2xl bg-indigo-600 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition hover:bg-indigo-500 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200 disabled:cursor-not-allowed disabled:opacity-60 dark:shadow-indigo-950/50 dark:focus-visible:ring-indigo-500/30"
                            >
                                <span v-if="form.processing">Mengirim kode...</span>
                                <span v-else>Kirim Kode Reset</span>
                            </button>
                        </form>

                        <p class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            Ingat kata sandi?
                            <Link :href="route('login')" class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-300 dark:hover:text-indigo-200">
                                Kembali ke halaman masuk
                            </Link>
                        </p>

                        <p class="mt-4 text-center text-xs text-slate-400 dark:text-slate-500">
                            <Link href="/" class="font-semibold text-indigo-500 hover:text-indigo-400 dark:text-indigo-300 dark:hover:text-indigo-200">
                                Kembali ke beranda
                            </Link>
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
