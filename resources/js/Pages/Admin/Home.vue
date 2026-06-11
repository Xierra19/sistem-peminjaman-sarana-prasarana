<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()

const props = defineProps({
  roomSummary: {
    type: Object,
    default: null,
  },
  itemSummary: {
    type: Object,
    default: null,
  },
})

const permissions = computed(() => page.props.auth?.permissions ?? {})
const canManageHistory = computed(() => Boolean(permissions.value?.can_manage_history))
const canManageRoomModule = computed(() => Boolean(permissions.value?.can_manage_room_module))
const canManageItemModule = computed(() => Boolean(permissions.value?.can_manage_item_module))

const visibleModuleCount = computed(() => {
  let count = 0
  if (canManageRoomModule.value) count++
  if (canManageItemModule.value) count++
  return count
})

const dynamicSubtitle = computed(() => {
  if (visibleModuleCount.value === 2) {
    return 'Anda mengelola seluruh modul'
  } else if (canManageRoomModule.value) {
    return 'Anda mengelola Modul Ruangan'
  } else if (canManageItemModule.value) {
    return 'Anda mengelola Modul Barang'
  }
  return ''
})
</script>

<template>
  <Head title="Dashboard Admin" />

  <AuthenticatedLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Dashboard Admin</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          {{ dynamicSubtitle }}
        </p>
      </div>

      <div class="grid gap-4 md:gap-6" :class="visibleModuleCount === 1 ? 'grid-cols-1 max-w-2xl mx-auto' : 'lg:grid-cols-2'">
        <section
          v-if="canManageRoomModule"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 md:p-6"
        >
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Modul Ruangan</h2>
              <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ canManageHistory ? 'Approval, report, dan histori peminjaman ruangan.' : 'Approval dan report peminjaman ruangan.' }}
              </p>
            </div>
            <Link
              :href="route('admin.bookings.index')"
              class="inline-flex w-full items-center justify-center rounded-xl border border-blue-200 px-4 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-50 sm:w-auto"
            >
              Buka Modul
            </Link>
          </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-700">
                <div class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-400">Total</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ roomSummary?.total ?? 0 }}</div>
              </div>
              <div class="rounded-2xl bg-amber-50 p-4 dark:border dark:border-amber-800 dark:bg-amber-900/30">
                <div class="text-xs uppercase tracking-wide text-amber-500 dark:text-amber-300">Menunggu</div>
                <div class="mt-1 text-2xl font-semibold text-amber-700 dark:text-amber-100">{{ roomSummary?.waiting ?? 0 }}</div>
              </div>
              <div class="rounded-2xl bg-emerald-50 p-4 dark:border dark:border-emerald-800 dark:bg-emerald-900/30">
                <div class="text-xs uppercase tracking-wide text-emerald-500 dark:text-emerald-300">Disetujui</div>
                <div class="mt-1 text-2xl font-semibold text-emerald-700 dark:text-emerald-100">{{ roomSummary?.approved ?? 0 }}</div>
              </div>
              <div class="rounded-2xl bg-rose-50 p-4 dark:border dark:border-rose-800 dark:bg-rose-900/30">
                <div class="text-xs uppercase tracking-wide text-rose-500 dark:text-rose-300">Final Lain</div>
                <div class="mt-1 text-2xl font-semibold text-rose-700 dark:text-rose-100">
                  {{ (roomSummary?.rejected ?? 0) + (roomSummary?.cancelled ?? 0) + (roomSummary?.expired ?? 0) }}
                </div>
              </div>
            </div>
        </section>

        <section
          v-if="canManageItemModule"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800 md:p-6"
        >
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Modul Barang</h2>
              <p class="text-sm text-slate-500 dark:text-slate-400">Approval, report, dan pemantauan peminjaman barang.</p>
            </div>
            <Link
              :href="route('admin.item-borrowings.index')"
              class="inline-flex w-full items-center justify-center rounded-xl border border-blue-200 px-4 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-50 sm:w-auto"
            >
              Buka Modul
            </Link>
          </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
              <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-700">
                <div class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-400">Total</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ itemSummary?.total ?? 0 }}</div>
              </div>
              <div class="rounded-2xl bg-amber-50 p-4 dark:border dark:border-amber-800 dark:bg-amber-900/30">
                <div class="text-xs uppercase tracking-wide text-amber-500 dark:text-amber-300">Menunggu</div>
                <div class="mt-1 text-2xl font-semibold text-amber-700 dark:text-amber-100">{{ itemSummary?.waiting ?? 0 }}</div>
              </div>
              <div class="rounded-2xl bg-emerald-50 p-4 dark:border dark:border-emerald-800 dark:bg-emerald-900/30">
                <div class="text-xs uppercase tracking-wide text-emerald-500 dark:text-emerald-300">Disetujui</div>
                <div class="mt-1 text-2xl font-semibold text-emerald-700 dark:text-emerald-100">{{ itemSummary?.approved ?? 0 }}</div>
              </div>
              <div class="rounded-2xl bg-blue-50 p-4 dark:border dark:border-blue-800 dark:bg-blue-900/30">
                <div class="text-xs uppercase tracking-wide text-blue-500 dark:text-blue-300">Selesai</div>
                <div class="mt-1 text-2xl font-semibold text-blue-700 dark:text-blue-100">{{ itemSummary?.completed ?? 0 }}</div>
              </div>
              <div class="rounded-2xl bg-rose-50 p-4 dark:border dark:border-rose-800 dark:bg-rose-900/30">
                <div class="text-xs uppercase tracking-wide text-rose-500 dark:text-rose-300">Ditolak/Batal</div>
                <div class="mt-1 text-2xl font-semibold text-rose-700 dark:text-rose-100">
                  {{ (itemSummary?.rejected ?? 0) + (itemSummary?.cancelled ?? 0) }}
                </div>
              </div>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
