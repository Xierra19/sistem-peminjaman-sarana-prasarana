<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive } from 'vue';

const props = defineProps({
    email: {
        type: String,
        default: '',
    },
    expiresAt: {
        type: String,
        default: null,
    },
    initialCooldown: {
        type: Number,
        default: 0,
    },
    resendCooldownSeconds: {
        type: Number,
        default: 900,
    },
    otpLifetimeSeconds: {
        type: Number,
        default: 600,
    },
});

const state = reactive({
    email: props.email || new URLSearchParams(window.location.search).get('email') || '',
    code: '',
    captchaToken: '',
    newPassword: '',
    confirmPassword: '',
    message: '',
    error: '',
    success: false,
    magicToken: new URLSearchParams(window.location.search).get('token') || '',
    otpSecondsRemaining: deriveOtpSeconds(props.expiresAt) ?? props.otpLifetimeSeconds,
    cooldownRemaining: props.initialCooldown,
    loadingVerify: false,
    loadingResend: false,
});

let tickerId;

function deriveOtpSeconds(expiresAt) {
    if (!expiresAt) {
        return props.otpLifetimeSeconds;
    }

    const expires = new Date(expiresAt).getTime();
    const diff = Math.floor((expires - Date.now()) / 1000);

    return diff > 0 ? diff : 0;
}

const otpCountdown = computed(() => formatTime(state.otpSecondsRemaining));
const cooldownCountdown = computed(() => formatTime(state.cooldownRemaining));

function formatTime(value) {
    const safe = Math.max(0, value);
    const minutes = String(Math.floor(safe / 60)).padStart(2, '0');
    const seconds = String(safe % 60).padStart(2, '0');

    return `${minutes}:${seconds}`;
}

onMounted(() => {
    tickerId = window.setInterval(() => {
        if (state.otpSecondsRemaining > 0) {
            state.otpSecondsRemaining -= 1;
        }
        if (state.cooldownRemaining > 0) {
            state.cooldownRemaining -= 1;
        }
    }, 1000);
});

onUnmounted(() => {
    if (tickerId) {
        window.clearInterval(tickerId);
    }
});

async function resetPassword() {
    if (!state.email) {
        state.error = 'Email wajib diisi.';
        return;
    }

    if (!state.magicToken && state.code.length !== 6) {
        state.error = 'Lengkapi kode 6 digit atau gunakan tautan ajaib.';
        return;
    }

    if (state.newPassword.length < 8) {
        state.error = 'Kata sandi minimal 8 karakter.';
        return;
    }

    if (state.newPassword !== state.confirmPassword) {
        state.error = 'Konfirmasi kata sandi tidak sama.';
        return;
    }

    state.loadingVerify = true;
    state.error = '';
    state.message = '';

    try {
        const payload = {
            email: state.email,
            new_password: state.newPassword,
        };

        let response;

        if (state.magicToken) {
            response = await axios.post(route('api.auth.password.reset-link'), {
                ...payload,
                token: state.magicToken,
            });
        } else {
            response = await axios.post(route('api.auth.password.reset-code'), {
                ...payload,
                code: state.code,
            });
        }

        if (response.data?.success) {
            state.success = true;
            state.message = 'Kata sandi berhasil diperbarui. Silakan masuk dengan kredensial baru.';
        }
    } catch (error) {
        state.error = error.response?.data?.message ?? 'Kode tidak valid atau sudah kedaluwarsa.';
    } finally {
        state.loadingVerify = false;
    }
}

async function resend() {
    if (!state.email) {
        state.error = 'Email wajib diisi.';
        return;
    }

    if (!state.captchaToken) {
        state.error = 'Captcha belum tervalidasi.';
        return;
    }

    state.loadingResend = true;
    state.error = '';
    state.message = '';

    try {
        const response = await axios.post(route('api.auth.password.forgot'), {
            email: state.email,
            captcha_token: state.captchaToken,
        });

        state.message = response.data?.message ?? 'Jika data valid, kami telah mengirim instruksi verifikasi.';
        state.cooldownRemaining = props.resendCooldownSeconds;
        state.otpSecondsRemaining = props.otpLifetimeSeconds;
        state.magicToken = '';
    } catch (error) {
        state.error = error.response?.data?.message ?? 'Terlalu banyak percobaan. Coba lagi nanti.';
    } finally {
        state.loadingResend = false;
    }
}
</script>

<template>
    <GuestLayout>
        <Head title="Reset melalui OTP" />

        <div class="mb-6">
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.25em] text-indigo-600">
                Reset OTP
            </div>
            <h1 class="mt-3 text-2xl font-semibold text-gray-900">
                Reset kata sandi dengan OTP
            </h1>
            <p class="mt-2 text-sm text-gray-600">
                Masukkan kode OTP 6 digit yang kami kirim dan tentukan kata sandi baru dalam {{ otpCountdown }}.
            </p>
        </div>

        <div v-if="state.message" class="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700">
            {{ state.message }}
        </div>
        <div v-if="state.error" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {{ state.error }}
        </div>

        <form class="space-y-4" @submit.prevent="resetPassword">
            <div>
                <InputLabel for="email" value="Email kampus" />
                <TextInput
                    id="email"
                    v-model="state.email"
                    type="email"
                    class="mt-1 block h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                    required
                    autocomplete="username"
                />
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <InputLabel for="code" value="Kode OTP" />
                    <span v-if="state.magicToken" class="text-xs text-emerald-600">
                        Tautan verifikasi terdeteksi
                    </span>
                </div>
                <TextInput
                    id="code"
                    v-model="state.code"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    class="mt-1 block h-14 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-center text-2xl tracking-[0.55em] text-slate-900 shadow-sm focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                    :required="!state.magicToken"
                    :disabled="!!state.magicToken"
                />
                <InputError :message="!state.magicToken && state.code.length && state.code.length !== 6 ? 'Kode harus 6 digit.' : ''" />
            </div>

            <div>
                <InputLabel for="new_password" value="Kata sandi baru" />
                <TextInput
                    id="new_password"
                    v-model="state.newPassword"
                    type="password"
                    class="mt-1 block h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                    required
                    autocomplete="new-password"
                />
            </div>

            <div>
                <InputLabel for="confirm_password" value="Konfirmasi kata sandi" />
                <TextInput
                    id="confirm_password"
                    v-model="state.confirmPassword"
                    type="password"
                    class="mt-1 block h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                    required
                />
            </div>

            <div>
                <InputLabel for="captcha" value="Captcha token" />
                <TextInput
                    id="captcha"
                    v-model="state.captchaToken"
                    type="text"
                    class="mt-1 block h-12 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                    placeholder="Diisi otomatis oleh widget CAPTCHA"
                />
                <p class="mt-1 text-xs text-gray-500">
                    Permintaan kirim ulang memerlukan token CAPTCHA yang valid.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <PrimaryButton :disabled="state.loadingVerify" class="!flex !h-12 !w-full !justify-center !rounded-2xl !bg-indigo-600 !px-4 !text-sm !font-semibold !normal-case !tracking-normal sm:!w-auto">
                    <span v-if="state.loadingVerify">Memproses...</span>
                    <span v-else>Reset kata sandi</span>
                </PrimaryButton>

                <SecondaryButton
                    type="button"
                    class="!flex !h-12 !w-full !justify-center !rounded-2xl !border-slate-200 !px-4 !text-sm !font-semibold !normal-case !tracking-normal sm:!w-auto"
                    :disabled="state.loadingResend || state.cooldownRemaining > 0"
                    @click="resend"
                >
                    <span v-if="state.cooldownRemaining > 0">
                        Kirim ulang dalam {{ cooldownCountdown }}
                    </span>
                    <span v-else>
                        Kirim ulang instruksi
                    </span>
                </SecondaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
