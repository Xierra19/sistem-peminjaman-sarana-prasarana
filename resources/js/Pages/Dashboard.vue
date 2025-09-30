<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    bookings: Array,
});

const isUser = computed(() => page.props.auth.user.role === 'user');
const isAdmin = computed(() => page.props.auth.user.role === 'admin');
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div v-if="isUser" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="flex justify-between items-center mb-6">
                            <h1 class="text-2xl font-bold">Daftar Booking Saya</h1>
                            <Link
                                :href="route('bookings.create')"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                            >
                                + Create Booking
                            </Link>
                        </div>

                        <div v-if="bookings.length > 0">
                            <table class="w-full border-collapse border border-gray-300">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="border p-2">ID</th>
                                        <th class="border p-2">User</th>
                                        <th class="border p-2">Room</th>
                                        <th class="border p-2">Title</th>
                                        <th class="border p-2">Start</th>
                                        <th class="border p-2">End</th>
                                        <th class="border p-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="booking in bookings" :key="booking.id">
                                        <td class="border p-2">{{ booking.id }}</td>
                                        <td class="border p-2">{{ booking.user.name }}</td>
                                        <td class="border p-2">{{ booking.room.name }}</td>
                                        <td class="border p-2">{{ booking.title }}</td>
                                        <td class="border p-2">{{ booking.start_time }}</td>
                                        <td class="border p-2">{{ booking.end_time }}</td>
                                        <td class="border p-2">
                                            <span
                                                :class="{
                                                    'text-yellow-600 font-bold': booking.status === 'pending',
                                                    'text-green-600 font-bold': booking.status === 'approved',
                                                    'text-red-600 font-bold': booking.status === 'rejected',
                                                }"
                                            >
                                                {{ booking.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center text-gray-500 mt-4">
                            Belum ada pemesanan ruangan.
                        </div>
                    </div>
                </div>

                <div v-if="isAdmin" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h1 class="text-2xl font-bold">Selamat Datang, Admin!</h1>
                        <p class="mt-2">Silakan gunakan navigasi di atas untuk mengelola aplikasi.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>