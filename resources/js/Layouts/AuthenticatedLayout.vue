<script setup>
import { computed, ref, watch } from 'vue'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import { Link, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const page = usePage()

const user = computed(() => page?.props?.auth?.user ?? {})

const userInitials = computed(() => {
  const name = (user.value?.name ?? '').trim()
  if (!name) return '?'
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('')
})

const roleLabel = computed(() => {
  const role = user.value?.role ?? ''
  if (!role) return 'Pengguna'
  return role
    .split(/[\s_-]+/)
    .map((chunk) => chunk.charAt(0).toUpperCase() + chunk.slice(1))
    .join(' ')
})

const currentUrl = computed(() => page.url)

const isRouteActive = (...names) => {
  currentUrl.value
  return names.some((name) => route().current(name))
}

const masterDataActive = computed(() =>
  isRouteActive(
    'admin.campus.*',
    'admin.buildings.*',
    'admin.rooms.*',
  )
)

const showMasterData = ref(masterDataActive.value)

watch(masterDataActive, (value) => {
  showMasterData.value = value
})

const navLinkClasses = (isActive) =>
  [
    'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150',
    isActive
      ? 'bg-blue-50 text-blue-700 border border-blue-100 shadow-sm'
      : 'text-gray-700 hover:bg-gray-100',
  ].join(' ')

const subLinkClasses = (isActive) =>
  [
    'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm transition-colors duration-150',
    isActive ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100',
  ].join(' ')

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
    timer: 4000,
    showConfirmButton: false,
    timerProgressBar: true,
    target: getSwalTarget(),
  })
  if (page?.props?.flash) page.props.flash.success = null
}

// tampilkan flash sekali ketika nilai berubah (dan saat load pertama)
watch(
  () => page.props.flash?.error,
  (msg, prev) => {
    if (msg && msg !== prev) {
      showError(msg)
    }
  },
  { immediate: true },
)

watch(
  () => page.props.flash?.success,
  (msg, prev) => {
    if (msg && msg !== prev) {
      showSuccess(msg)
    }
  },
  { immediate: true },
)
</script>

<template>
  <div class="flex min-h-screen bg-gray-100">
    <!-- Sidebar -->
    <div class="relative w-64 flex-shrink-0">
      <aside class="fixed inset-y-0 left-0 z-30 flex w-64 flex-col overflow-y-auto border-r border-gray-200 bg-white p-4">
        <h2 class="text-sm font-semibold text-gray-600 mb-4">Menu</h2>

        <nav class="space-y-2">
          <div>
            <Link :href="route('dashboard')" :class="[navLinkClasses(isRouteActive('dashboard'))]">
              <span class="text-lg">📊</span>
              <span>Dashboard</span>
            </Link>
          </div>

          <div v-if="$page.props.auth.user.role === 'admin'">
            <button
              type="button"
              @click="showMasterData = !showMasterData"
              :class="[navLinkClasses(masterDataActive), 'w-full justify-between']"
            >
              <span class="flex items-center gap-2">
                <span class="text-lg">📂</span>
                <span>Master Data</span>
              </span>
              <svg
                class="w-4 h-4 transform transition-transform"
                :class="{ 'rotate-180': showMasterData }"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div v-if="showMasterData" class="ml-4 mt-2 space-y-1">
              <Link :href="route('admin.campus.index')" :class="subLinkClasses(isRouteActive('admin.campus.*'))">
                🏫 Master Campus
              </Link>
              <Link :href="route('admin.buildings.index')" :class="subLinkClasses(isRouteActive('admin.buildings.*'))">
                🏢 Master Building
              </Link>
              <Link :href="route('admin.rooms.index')" :class="subLinkClasses(isRouteActive('admin.rooms.*'))">
                🚪 Master Rooms
              </Link>
            </div>
          </div>

          <div>
            <Link
              :href="route('bookings.index')"
              :class="[navLinkClasses(isRouteActive('bookings.index', 'bookings.create'))]"
            >
              <span class="text-lg">📝</span>
              <span>Request Booking</span>
            </Link>
          </div>

          <div v-if="$page.props.auth.user.role === 'admin'">
            <Link :href="route('admin.bookings.index')" :class="[navLinkClasses(isRouteActive('admin.bookings.*'))]">
              <span class="text-lg">🛡️</span>
              <span>Approval Booking</span>
            </Link>
          </div>

          <div v-if="$page.props.auth.user.role === 'admin'">
            <Link :href="route('history.index')" :class="[navLinkClasses(isRouteActive('history.*'))]">
              <span class="text-lg">🕑</span>
              <span>History</span>
            </Link>
          </div>

          <div v-if="$page.props.auth.user.role === 'admin'">
            <Link
              :href="route('admin.users.index')"
              :class="[navLinkClasses(isRouteActive('admin.users.index', 'admin.users.edit'))]"
            >
              <span class="text-lg">👥</span>
              <span>Kelola User</span>
            </Link>
          </div>

        </nav>
      </aside>
    </div>

    <div class="flex min-h-screen flex-1 flex-col">
      <!-- Navbar Atas -->
      <nav class="sticky top-0 z-20 border-b border-gray-200 bg-white shadow-sm">
        <div class="flex h-20 items-center justify-between px-4 sm:px-6 lg:px-10">
          <div class="flex flex-1 items-center justify-start">
            <Link
              :href="route('dashboard')"
              class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-gray-300 hover:bg-gray-50"
            >
              <div class="relative flex h-12 w-12 items-center justify-center rounded-2xl border border-gray-200 bg-gradient-to-br from-blue-50 via-white to-blue-100 p-1 shadow-inner">
                <div class="flex h-full w-full items-center justify-center rounded-xl bg-white shadow-sm">
                  <ApplicationLogo class="!h-10 !w-10 object-contain" />
                </div>
                <span class="pointer-events-none absolute inset-0 rounded-2xl ring-1 ring-inset ring-gray-100/80"></span>
              </div>
              <div class="flex flex-col leading-tight text-left">
                <span class="text-xs font-semibold uppercase tracking-[0.35em] text-blue-500">Esa Unggul</span>
                <span class="text-base font-semibold text-gray-900">Sistem Booking Ruangan</span>
                <span class="text-xs text-gray-500">Universitas Esa Unggul</span>
              </div>
            </Link>
          </div>

          <div class="flex items-center gap-4">
            <div class="hidden flex-col text-right sm:flex">
              <span class="text-sm font-semibold text-gray-900">{{ user.name }}</span>
              <span class="text-xs text-gray-500">{{ roleLabel }}</span>
            </div>
            <Dropdown align="right" width="56">
              <template #trigger>
                <button
                  type="button"
                  :aria-label="'Buka menu akun ' + (user.name || '')"
                  class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200"
                >
                  <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 text-white text-sm font-semibold shadow-inner">
                    {{ userInitials }}
                  </span>
                  <span class="text-sm font-semibold text-gray-900 sm:hidden">{{ user.name }}</span>
                  <svg class="h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
              </template>
              <template #content>
                <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
              </template>
            </Dropdown>
          </div>
        </div>
      </nav>

      <!-- Konten Utama -->
      <main class="flex-1 p-6 space-y-6">
        <header v-if="$slots.header">
          <slot name="header" />
        </header>

        <section>
          <slot />
        </section>
      </main>
    </div>
  </div>
</template>
