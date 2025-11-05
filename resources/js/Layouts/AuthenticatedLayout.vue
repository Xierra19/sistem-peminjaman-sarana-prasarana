<script setup>
import { computed, ref, watch } from 'vue'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import { Link, usePage } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const page = usePage()

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
    'admin.semesters.*',
    'admin.semesters.defaults.*',
    'admin.semester.*',
    'admin.offerings.*',
    'admin.courses.import.*',
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
              <!-- ✅ baru: master semester -->
              <Link
                :href="route('admin.semesters.index')"
                :class="subLinkClasses(isRouteActive('admin.semesters.*', 'admin.semesters.defaults.*', 'admin.semester.*', 'admin.offerings.*', 'admin.courses.import.*'))"
              >
                🗓️ Master Semester
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

          <div v-if="$page.props.auth.user.role === 'admin'">
            <Link :href="route('admin.users.onboarding')" :class="[navLinkClasses(isRouteActive('admin.users.onboarding'))]">
              <span class="text-lg">🧑‍🤝‍🧑</span>
              <span>Onboarding User</span>
            </Link>
          </div>
        </nav>
      </aside>
    </div>

    <div class="flex min-h-screen flex-1 flex-col">
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
