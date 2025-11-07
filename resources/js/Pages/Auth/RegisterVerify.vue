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
        default: 300,
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
    message: '',
    error: '',
    success: false,
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

    const urlToken = new URLSearchParams(window.location.search).get('token');
    if (urlToken && state.email) {
        verifyViaLink(urlToken);
    }
});

onUnmounted(() => {
    if (tickerId) {
        window.clearInterval(tickerId);
    }
});

async function verifyViaLink(token) {
    state.loadingVerify = true;
    state.error = '';

    try {
        const response = await axios.post(route('api.auth.register.verify-link'), {
            email: state.email,
            token,
        });

        if (response.data?.success) {
            state.success = true;
            state.message = 'Verifikasi berhasil. Silakan lanjut masuk.';
        }
    } catch (error) {
        state.error = error.response?.data?.message ?? 'Kode tidak valid atau sudah kedaluwarsa.';
    } finally {
        state.loadingVerify = false;
    }
}

async function submitCode() {
    if (!state.email || state.code.length !== 6) {
        state.error = 'Lengkapi email dan kode 6 digit.';
        return;
    }

    state.loadingVerify = true;
    state.error = '';
    state.message = '';

    try {
        const response = await axios.post(route('api.auth.register.verify-code'), {
            email: state.email,
            code: state.code,
        });

        if (response.data?.success) {
            state.success = true;
            state.message = 'Verifikasi berhasil. Kami akan mengarahkan Anda ke halaman masuk.';
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
        const response = await axios.post(route('api.auth.register.resend'), {
            email: state.email,
            captcha_token: state.captchaToken,
        });

        state.message = response.data?.message ?? 'Jika data valid, kami telah mengirim instruksi verifikasi.';
        state.cooldownRemaining = props.resendCooldownSeconds;
        state.otpSecondsRemaining = props.otpLifetimeSeconds;
    } catch (error) {
        state.error = error.response?.data?.message ?? 'Terlalu banyak percobaan. Coba lagi nanti.';
    } finally {
        state.loadingResend = false;
    }
}
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Pendaftaran" />

        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">
                Verifikasi email Anda
            </h1>
            <p class="mt-2 text-sm text-gray-600">
                Masukkan kode OTP 6 digit atau gunakan tautan ajaib yang kami kirim ke email terdaftar. Kode kedaluwarsa dalam {{ otpCountdown }}.
            </p>
        </div>

        <div v-if="state.message" class="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700">
            {{ state.message }}
        </div>
        <div v-if="state.error" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {{ state.error }}
        </div>

        <form class="space-y-4" @submit.prevent="submitCode">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="state.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autocomplete="username"
                />
            </div>

            <div>
                <InputLabel for="code" value="Kode OTP" />
                <TextInput
                    id="code"
                    v-model="state.code"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    class="mt-1 block w-full tracking-[0.7em] text-center text-2xl"
                    required
                />
                <InputError :message="state.error && state.code.length !== 6 ? 'Kode harus 6 digit.' : ''" />
            </div>

            <div>
                <InputLabel for="captcha" value="Captcha token" />
                <TextInput
                    id="captcha"
                    v-model="state.captchaToken"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="Diisi otomatis oleh widget CAPTCHA"
                />
                <p class="mt-1 text-xs text-gray-500">
                    Sistem akan mengirim ulang OTP hanya jika CAPTCHA tervalidasi.
                </p>
            </div>

            <div class="flex items-center justify-between">
                <PrimaryButton :disabled="state.loadingVerify">
                    <span v-if="state.loadingVerify">Memverifikasi...</span>
                    <span v-else>Verifikasi kode</span>
                </PrimaryButton>

                <SecondaryButton
                    type="button"
                    :disabled="state.loadingResend || state.cooldownRemaining > 0"
                    @click="resend"
                >
                    <span v-if="state.cooldownRemaining > 0">
                        Kirim ulang dalam {{ cooldownCountdown }}
                    </span>
                    <span v-else>
                        Kirim ulang kode
                    </span>
                </SecondaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
