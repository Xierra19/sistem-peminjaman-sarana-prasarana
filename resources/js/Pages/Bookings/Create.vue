<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

// Definisikan props untuk menerima data dari controller
const props = defineProps({
    rooms: Array,
});

// Definisikan form
const form = useForm({
    room_id: '',
    title: '',
    description: '',
    start_time: '',
    end_time: '',
    attachment: null,
});

const submit = () => {
    form.post(route('bookings.store'));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Create Booking" />

        <div class="max-w-2xl mx-auto p-6 bg-white shadow rounded">
            <h1 class="text-2xl font-bold mb-4">Create Booking</h1>

            <form @submit.prevent="submit">
                <!-- Pilih Room -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Room</label>
                    <select v-model="form.room_id" class="w-full border rounded p-2">
                        <option value="">-- Select Room --</option>
                        <option v-for="room in props.rooms" :key="room.id" :value="room.id">
                            {{ room.name }}
                        </option>
                    </select>
                </div>

                <!-- Title -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Title</label>
                    <input v-model="form.title" type="text" class="w-full border rounded p-2" />
                </div>

                <!-- Description -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea v-model="form.description" class="w-full border rounded p-2"></textarea>
                </div>

                <!-- Start Time -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Start Time</label>
                    <input v-model="form.start_time" type="datetime-local" class="w-full border rounded p-2" />
                </div>

                <!-- End Time -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">End Time</label>
                    <input v-model="form.end_time" type="datetime-local" class="w-full border rounded p-2" />
                </div>

                <!-- Attachment -->
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Attachment</label>
                    <input type="file" @change="e => form.attachment = e.target.files[0]" />
                </div>

                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">
                    Save
                </button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
