<script setup>
import { ref, watch } from 'vue'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import { Link, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const showMasterData = ref(false)
const page = usePage()

// selalu render di lapisan teratas
const swal = Swal.mixin({
  heightAuto: false,
  allowOutsideClick: true,
  zIndex: 2147483647,
})

// jika ada <dialog open>, render di dalamnya (top layer). Kalau tidak ada, ke body biasa
const getSwalTarget = () => document.querySelector('dialog[open]') || document.body

function showError(message) {
  const text = (message ?? '').toString().trim()
  if (!text) return
  swal.fire({
    icon: 'error',
    title: 'Problem',
    text,
    target: getSwalTarget(),
    confirmButtonText: 'OK',
  })
  // habiskan flash di client
  if (page?.props?.flash) page.props.flash.error = null
}

function showSuccess(message) {
  const text = (message ?? '').toString().trim()
  if (!text) return
  swal.fire({
    icon: 'success',
    title: 'Berhasil',
    text,
    timer: 1800,
    showConfirmButton: false,
    target: getSwalTarget(),
  })
  if (page?.props?.flash) page.props.flash.success = null
}

// tampilkan flash sekali ketika nilai berubah (dan saat load pertama)
watch(() => page.props.flash?.error,   (msg, prev) => { if (msg && msg !== prev) showError(msg) },   { immediate: true })
watch(() => page.props.flash?.success, (msg, prev) => { if (msg && msg !== prev) showSuccess(msg) }, { immediate: true })
</script>

<template>
  <div class="min-h-screen bg-gray-100 flex flex-col">
    <!-- Navbar Atas -->
    <nav class="bg-white border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <!-- Logo -->
          <div class="shrink-0 flex items-center">
            <Link :href="route('dashboard')">
              <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
            </Link>
          </div>

          <!-- Profil + Logout -->
          <div class="flex items-center">
            <Dropdown align="right" width="48">
              <template #trigger>
                <span class="inline-flex rounded-md">
                  <button
                    type="button"
                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition"
                  >
                    {{ $page.props.auth.user.name }}
                    <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </span>
              </template>
              <template #content>
                <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
              </template>
            </Dropdown>
          </div>
        </div>
      </div>
    </nav>

    <!-- Body: Sidebar + Konten -->
    <div class="flex flex-1">
      <!-- Sidebar -->
      <aside class="w-64 bg-white border-r border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-600 mb-4">Menu</h2>

        <div class="mb-2">
          <Link :href="route('dashboard')" class="block px-3 py-2 rounded hover:bg-gray-100 text-gray-700">
            📊 Dashboard
          </Link>
        </div>

        <div class="mb-2">
          <Link :href="route('bookings.index')" class="block px-3 py-2 rounded hover:bg-gray-100 text-gray-700">
            📝 Request Booking
          </Link>
        </div>


        <div class="mb-2">
          <Link :href="route('history.index')" class="block px-3 py-2 rounded hover:bg-gray-100 text-gray-700">
            🕑 History
          </Link>
        </div>

        <div v-if="$page.props.auth.user.role === 'admin'" class="mb-2">
          <Link :href="route('admin.bookings.index')" class="block px-3 py-2 rounded hover:bg-gray-100 text-gray-700">
            🛡️ Approval Booking
          </Link>
        </div>

        <div v-if="$page.props.auth.user.role === 'admin'" class="mb-2">
          <button
            @click="showMasterData = !showMasterData"
            class="flex items-center justify-between w-full px-3 py-2 rounded hover:bg-gray-100 text-gray-700"
          >
            <span>📂 Master Data</span>
            <svg class="w-4 h-4 transform transition-transform" :class="{ 'rotate-180': showMasterData }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <div v-if="showMasterData" class="ml-4 mt-2 space-y-1">
            <Link :href="route('admin.campus.index')" class="block px-3 py-1 text-gray-600 hover:bg-gray-100 rounded">
              🏫 Master Campus
            </Link>
            <Link :href="route('admin.buildings.index')" class="block px-3 py-1 text-gray-600 hover:bg-gray-100 rounded">
              🏢 Master Building
            </Link>
            <Link :href="route('admin.rooms.index')" class="block px-3 py-1 text-gray-600 hover:bg-gray-100 rounded">
              🚪 Master Rooms
            </Link>
          </div>
        </div>
      </aside>

      <!-- Konten Utama -->
      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
