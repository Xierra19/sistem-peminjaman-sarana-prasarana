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
        <h1 class="text-2xl font-semibold text-slate-900">Dashboard Admin</h1>
<p class="text-sm text-slate-500">
  {{ dynamicSubtitle }}
</p>
      </div>

      <div class="grid gap-6" :class="visibleModuleCount === 1 ? 'grid-cols-1 max-w-2xl mx-auto' : 'lg:grid-cols-2'">
        <section
          v-if="canManageRoomModule"
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Modul Ruangan</h2>
              <p class="text-sm text-slate-500">
                {{ canManageHistory ? 'Approval, report, dan histori peminjaman ruangan.' : 'Approval dan report peminjaman ruangan.' }}
              </p>
            </div>
            <Link
              :href="route('admin.bookings.index')"
              class="rounded-xl border border-blue-200 px-4 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-50"
            >
              Buka Modul
            </Link>
          </div>

          <div class="mt-5 grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-4">
              <div class="text-xs uppercase tracking-wide text-slate-400">Total</div>
              <div class="mt-1 text-2xl font-semibold text-slate-900">{{ roomSummary?.total ?? 0 }}</div>
            </div>
            <div class="rounded-xl bg-amber-50 p-4">
              <div class="text-xs uppercase tracking-wide text-amber-500">Menunggu</div>
              <div class="mt-1 text-2xl font-semibold text-amber-700">{{ roomSummary?.waiting ?? 0 }}</div>
            </div>
            <div class="rounded-xl bg-emerald-50 p-4">
              <div class="text-xs uppercase tracking-wide text-emerald-500">Disetujui</div>
              <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ roomSummary?.approved ?? 0 }}</div>
            </div>
            <div class="rounded-xl bg-rose-50 p-4">
              <div class="text-xs uppercase tracking-wide text-rose-500">Ditolak/Batal</div>
              <div class="mt-1 text-2xl font-semibold text-rose-700">
                {{ (roomSummary?.rejected ?? 0) + (roomSummary?.cancelled ?? 0) }}
              </div>
            </div>
          </div>
        </section>

        <section
          v-if="canManageItemModule"
          class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
        >
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Modul Barang</h2>
              <p class="text-sm text-slate-500">Approval, report, dan pemantauan peminjaman barang.</p>
            </div>
            <Link
              :href="route('admin.item-borrowings.index')"
              class="rounded-xl border border-blue-200 px-4 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-50"
            >
              Buka Modul
            </Link>
          </div>

          <div class="mt-5 grid gap-4 sm:grid-cols-5">
            <div class="rounded-xl bg-slate-50 p-4">
              <div class="text-xs uppercase tracking-wide text-slate-400">Total</div>
              <div class="mt-1 text-2xl font-semibold text-slate-900">{{ itemSummary?.total ?? 0 }}</div>
            </div>
            <div class="rounded-xl bg-amber-50 p-4">
              <div class="text-xs uppercase tracking-wide text-amber-500">Menunggu</div>
              <div class="mt-1 text-2xl font-semibold text-amber-700">{{ itemSummary?.waiting ?? 0 }}</div>
            </div>
            <div class="rounded-xl bg-emerald-50 p-4">
              <div class="text-xs uppercase tracking-wide text-emerald-500">Disetujui</div>
              <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ itemSummary?.approved ?? 0 }}</div>
            </div>
            <div class="rounded-xl bg-blue-50 p-4">
              <div class="text-xs uppercase tracking-wide text-blue-500">Kembali</div>
              <div class="mt-1 text-2xl font-semibold text-blue-700">{{ itemSummary?.returned ?? 0 }}</div>
            </div>
            <div class="rounded-xl bg-rose-50 p-4">
              <div class="text-xs uppercase tracking-wide text-rose-500">Ditolak/Batal</div>
              <div class="mt-1 text-2xl font-semibold text-rose-700">
                {{ (itemSummary?.rejected ?? 0) + (itemSummary?.cancelled ?? 0) }}
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
