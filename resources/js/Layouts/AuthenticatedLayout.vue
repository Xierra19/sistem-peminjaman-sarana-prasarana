<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import { Link, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const page = usePage()

const user = computed(() => page?.props?.auth?.user ?? {})
const isAdmin = computed(() => (user.value?.role ?? '').toLowerCase() === 'admin')

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
    'admin.items.*',
  )
)

const showMasterData = ref(masterDataActive.value)
const isMobileMenuOpen = ref(false)

watch(masterDataActive, (value) => {
  showMasterData.value = value
})

const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value
}

const closeMobileMenu = () => {
  isMobileMenuOpen.value = false
}

watch(currentUrl, () => {
  closeMobileMenu()
})

const handleEscape = (event) => {
  if (event.key === 'Escape') {
    closeMobileMenu()
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscape)
})

const navLinkClasses = (isActive) =>
  [
    'flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition-colors duration-150',
    isActive
      ? 'border border-blue-100 bg-blue-50 text-blue-700 shadow-sm'
      : 'text-slate-600 hover:bg-slate-50',
  ].join(' ')

const subLinkClasses = (isActive) =>
  [
    'flex w-full items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition-colors duration-150',
    isActive ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:bg-slate-50',
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
  <div class="relative min-h-screen bg-slate-100 lg:flex">
    <transition name="fade">
      <div
        v-if="isMobileMenuOpen"
        class="fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-sm lg:hidden"
        @click="closeMobileMenu"
      />
    </transition>

    <aside
      id="sidebar-navigation"
      class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-100 bg-white px-4 pb-6 pt-5 shadow-xl transition-transform duration-300 ease-in-out lg:translate-x-0 lg:shadow-none"
      :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
      aria-label="Navigasi utama"
    >
      <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
        <Link
          :href="route('dashboard')"
          class="flex items-center gap-3 rounded-2xl border border-slate-100 px-3 py-2 text-left transition hover:border-blue-200 hover:bg-blue-50/40"
          @click="closeMobileMenu"
        >
          <div
            class="relative flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-100 bg-gradient-to-br from-blue-50 via-white to-blue-100 p-1 shadow-inner"
          >
            <div class="flex h-full w-full items-center justify-center rounded-xl bg-white shadow-sm">
              <ApplicationLogo class="!h-8 !w-8 object-contain" />
            </div>
          </div>
          <div class="flex flex-col leading-tight">
            <span class="text-[10px] font-semibold uppercase tracking-[0.45em] text-blue-500">Esa Unggul</span>
            <span class="text-sm font-semibold text-slate-900">Sistem Booking</span>
            <span class="text-xs text-slate-500">Universitas Esa Unggul</span>
          </div>
        </Link>
        <button
          type="button"
          class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 lg:hidden"
          @click="closeMobileMenu"
          aria-label="Tutup menu navigasi"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="flex-1 overflow-y-auto">
        <h2 class="mt-4 text-xs font-semibold uppercase tracking-[0.45em] text-slate-400">Menu</h2>
        <nav class="mt-3 space-y-2">
          <div>
            <Link :href="route('dashboard')" :class="navLinkClasses(isRouteActive('dashboard'))">
              <span class="text-lg">📊</span>
              <span>Dashboard</span>
            </Link>
          </div>

          <div v-if="isAdmin">
            <button
              type="button"
              @click="showMasterData = !showMasterData"
              :class="[navLinkClasses(masterDataActive), 'justify-between']"
            >
              <span class="flex items-center gap-2">
                <span class="text-lg">📂</span>
                <span>Master Data</span>
              </span>
              <svg
                class="h-4 w-4 transform transition-transform"
                :class="{ 'rotate-180': showMasterData }"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div v-if="showMasterData" class="ml-6 mt-2 space-y-1 border-l border-dashed border-slate-100 pl-4">
              <Link :href="route('admin.campus.index')" :class="subLinkClasses(isRouteActive('admin.campus.*'))">
                🏫 Master Campus
              </Link>
              <Link :href="route('admin.buildings.index')" :class="subLinkClasses(isRouteActive('admin.buildings.*'))">
                🏢 Master Building
              </Link>
              <Link :href="route('admin.rooms.index')" :class="subLinkClasses(isRouteActive('admin.rooms.*'))">
                🚪 Master Rooms
              </Link>
              <Link :href="route('admin.items.index')" :class="subLinkClasses(isRouteActive('admin.items.*'))">
                📦 Master Barang
              </Link>
            </div>
          </div>

          <div>
            <Link
              :href="route('bookings.index')"
              :class="navLinkClasses(isRouteActive('bookings.index', 'bookings.create'))"
            >
              <span class="text-lg">📝</span>
              <span>Request Booking</span>
            </Link>
          </div>

          <div v-if="isAdmin">
            <Link :href="route('admin.bookings.index')" :class="navLinkClasses(isRouteActive('admin.bookings.*'))">
              <span class="text-lg">🛡️</span>
              <span>Approval Booking</span>
            </Link>
          </div>

          <div v-if="isAdmin">
            <Link :href="route('history.index')" :class="navLinkClasses(isRouteActive('history.*'))">
              <span class="text-lg">🕑</span>
              <span>History</span>
            </Link>
          </div>

          <div v-if="isAdmin">
            <Link :href="route('admin.reports.index')" :class="navLinkClasses(isRouteActive('admin.reports.*'))">
              <span class="text-lg">📑</span>
              <span>Report Booking</span>
            </Link>
          </div>

          <div v-if="isAdmin">
            <Link
              :href="route('admin.users.index')"
              :class="navLinkClasses(isRouteActive('admin.users.index', 'admin.users.edit'))"
            >
              <span class="text-lg">👥</span>
              <span>Kelola User</span>
            </Link>
          </div>
        </nav>
      </div>
    </aside>

    <div class="flex min-h-screen flex-1 flex-col lg:ml-64">
      <nav class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="page-shell flex h-16 items-center justify-between">
          <div class="flex flex-1 items-center gap-3">
            <button
              type="button"
              class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 lg:hidden"
              @click="toggleMobileMenu"
              :aria-expanded="isMobileMenuOpen"
              aria-controls="sidebar-navigation"
              aria-label="Buka menu navigasi"
            >
              <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
              </svg>
            </button>
            <Link
              :href="route('dashboard')"
              class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white/90 px-3 py-2 text-left shadow-sm transition hover:border-blue-200 hover:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 lg:hidden"
            >
              <div
                class="relative flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-100 bg-gradient-to-br from-blue-50 via-white to-blue-100 p-1 shadow-inner"
              >
                <div class="flex h-full w-full items-center justify-center rounded-xl bg-white shadow-sm">
                  <ApplicationLogo class="!h-7 !w-7 object-contain" />
                </div>
              </div>
              <div class="hidden flex-col leading-tight sm:flex">
                <span class="text-[10px] font-semibold uppercase tracking-[0.45em] text-blue-500">Esa Unggul</span>
                <span class="text-sm font-semibold text-slate-900">Sistem Booking Ruangan</span>
                <span class="text-xs text-slate-500">Universitas Esa Unggul</span>
              </div>
            </Link>
          </div>

          <div class="flex items-center gap-4">
            <div class="hidden flex-col text-right sm:flex">
              <span class="text-sm font-semibold text-slate-900">{{ user.name }}</span>
              <span class="text-xs text-slate-500">{{ roleLabel }}</span>
            </div>
            <Dropdown align="right" width="56">
              <template #trigger>
                <button
                  type="button"
                  :aria-label="'Buka menu akun ' + (user.name || '')"
                  class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <span
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 text-sm font-semibold text-white shadow-inner"
                  >
                    {{ userInitials }}
                  </span>
                  <span class="text-sm font-semibold text-slate-900 sm:hidden">{{ user.name }}</span>
                  <svg class="h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

      <main class="flex-1 bg-slate-100/60 py-6">
        <div class="page-shell space-y-6">
          <header v-if="$slots.header">
            <slot name="header" />
          </header>

          <section>
            <slot />
          </section>
        </div>
      </main>
    </div>
  </div>
</template>
