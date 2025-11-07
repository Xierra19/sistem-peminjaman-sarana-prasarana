<script setup>
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { Mail, Lock, Eye, EyeOff } from 'lucide-vue-next'; // Ganti ini sesuai dengan library ikon yang Anda gunakan

const showPassword = ref(false);

defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    email: '',
    password: '',
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 space-y-1 text-center">
                <div class="mx-auto mb-4 w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center">
                    <div class="w-8 h-8 bg-white rounded-sm flex items-center justify-center">
                        <div class="w-4 h-4 bg-blue-600 rounded-sm"></div>
                    </div>
                </div>
                <h1 class="text-2xl font-bold">Room Reservation</h1>
                <p class="text-sm text-gray-500">
                    Universitas Esa Unggul Bekasi
                </p>
            </div>
            <div class="p-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="space-y-2">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <div class="relative">
                            <Mail class="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                            <input
                                id="email"
                                type="email"
                                placeholder="Enter your email"
                                class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                v-model="form.email"
                                required
                            />
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <div class="relative">
                            <Lock class="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                            <input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Enter your password"
                                class="pl-10 pr-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                v-model="form.password"
                                required
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-3 h-4 w-4 text-gray-400 hover:text-gray-600"
                            >
                                <EyeOff v-if="showPassword" size="16" />
                                <Eye v-else size="16" />
                            </button>
                        </div>
                    </div>

                    <div v-if="canResetPassword" class="flex items-center justify-between">
                        <Link
                            :href="route('password.request')"
                            class="text-sm text-blue-600 hover:text-blue-800 hover:underline"
                        >
                            Forgot your password?
                        </Link>
                    </div>

                    <button type="submit" class="w-full py-2 px-4 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Sign In
                    </button>
                </form>

                <div class="mt-6 text-center text-sm text-gray-500">
                    <p>Need help? Contact your system administrator</p>
                </div>
            </div>
        </div>
    </div>
</template>
