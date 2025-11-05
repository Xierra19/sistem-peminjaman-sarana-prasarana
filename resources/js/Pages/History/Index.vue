<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Dropdown from '@/Components/Dropdown.vue'
import { usePagination } from '@/Composables/usePagination'
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  histories: {
    type: Array,
    default: () => [],
  },
})

const searchQuery = ref('')
const startDate = ref('')
const endDate = ref('')

const filteredHistories = computed(() => {
  const list = props.histories ?? []
  const query = searchQuery.value.trim().toLowerCase()
  const start = startDate.value ? new Date(startDate.value) : null
  const end = endDate.value ? new Date(endDate.value) : null

  return list.filter((history) => {
    const matchesSearch =
      !query ||
      history.user?.name?.toLowerCase().includes(query) ||
      history.user?.email?.toLowerCase().includes(query) ||
      history.booking?.room?.name?.toLowerCase().includes(query) ||
      history.booking?.room?.building?.name?.toLowerCase().includes(query) ||
      history.action?.toLowerCase().includes(query) ||
      history.description?.toLowerCase().includes(query)

    if (!matchesSearch) {
      return false
    }

    const createdAt = history.created_at ? new Date(history.created_at) : null
    const startOk = !start || (createdAt && createdAt >= start)
    const endOk = !end || (createdAt && createdAt <= end)

    return startOk && endOk
  })
})

const {
  paginatedItems: paginatedHistories,
  rowsPerPage,
  currentPage,
  pageMeta,
  pages,
  changePage,
} = usePagination(filteredHistories)

const perPageOptions = [5, 10, 25, 50]

const formatDateTime = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const exportExcel = () => {
  window.open(route('history.export.excel'), '_blank')
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Riwayat Aktivitas" />

    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Riwayat Aktivitas</h1>
        <p class="text-sm text-gray-500">Pantau semua perubahan booking ruangan yang terekam di sistem.</p>
      </div>

      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-gray-100 px-5 py-4">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-1 flex-col gap-3 md:flex-row md:items-center md:gap-4">
              <div class="relative w-full md:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M21 21l-4.35-4.35M9.5 17a7.5 7.5 0 1 1 7.5-7.5A7.5 7.5 0 0 1 9.5 17Z"
                    />
                  </svg>
                </span>
                <input
                  id="history-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Cari pengguna, ruangan, atau aksi…"
                  class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-10 pr-3 text-sm text-gray-700 placeholder-gray-400 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
              </div>
              <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
                <div class="flex items-center gap-2">
                  <label class="font-medium text-gray-600" for="history-start">Dari</label>
                  <input
                    id="history-start"
                    v-model="startDate"
                    type="date"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                </div>
                <div class="flex items-center gap-2">
                  <label class="font-medium text-gray-600" for="history-end">Sampai</label>
                  <input
                    id="history-end"
                    v-model="endDate"
                    type="date"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  />
                </div>
              </div>
            </div>
            <Dropdown align="right" width="48">
              <template #trigger>
                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100"
                >
                  <span>Export</span>
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path
                      d="M5.25 7.5 10 12.5l4.75-5"
                      stroke="currentColor"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1.5"
                    />
                  </svg>
                </button>
              </template>
              <template #content>
                <div class="flex flex-col gap-1 p-2 text-sm text-gray-700">
                  <button
                    type="button"
                    class="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-blue-50"
                    @click="exportExcel"
                  >
                    <span>Export Excel</span>
                  </button>
                </div>
              </template>
            </Dropdown>
          </div>
          <div class="flex items-center justify-end gap-3 text-sm text-gray-600">
            <label class="font-medium text-gray-700" for="history-rows">Baris per halaman</label>
            <div class="relative">
              <select
                id="history-rows"
                v-model.number="rowsPerPage"
                class="w-24 appearance-none rounded border border-gray-300 bg-white px-3 py-1.5 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              >
                <option v-for="option in perPageOptions" :key="`rows-${option}`" :value="option">
                  {{ option }}
                </option>
              </select>
              <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                  <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.939l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08Z"
                    clip-rule="evenodd"
                  />
                </svg>
              </span>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
              <tr>
                <th class="px-5 py-3 text-left">ID</th>
                <th class="px-5 py-3 text-left">Pengguna</th>
                <th class="px-5 py-3 text-left">Ruangan</th>
                <th class="px-5 py-3 text-left">Aksi</th>
                <th class="px-5 py-3 text-left">Waktu</th>
                <th class="px-5 py-3 text-left">Deskripsi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700">
              <tr v-for="history in paginatedHistories" :key="history.id" class="hover:bg-gray-50">
                <td class="px-5 py-4 text-sm text-gray-500">#{{ history.id }}</td>
                <td class="px-5 py-4">
                  <div class="font-medium text-gray-900">{{ history.user?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500">{{ history.user?.email ?? '-' }}</div>
                </td>
                <td class="px-5 py-4">
                  <div class="font-medium text-gray-800">{{ history.booking?.room?.name ?? '-' }}</div>
                  <div class="text-xs text-gray-500">
                    {{ history.booking?.room?.building?.name ?? '-' }} -
                    {{ history.booking?.room?.building?.campus?.name ?? '-' }}
                  </div>
                </td>
                <td class="px-5 py-4">
                  <span
                    class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-600"
                  >
                    {{ history.action ?? '-' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-sm text-gray-600">
                  <div class="font-medium text-gray-800">{{ formatDateTime(history.created_at) }}</div>
                </td>
                <td class="px-5 py-4 text-sm text-gray-600">
                  <span>{{ history.description ?? '-' }}</span>
                </td>
              </tr>
              <tr v-if="!paginatedHistories.length">
                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">
                  Belum ada riwayat yang sesuai dengan filter.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between"
        >
          <div>
            <span v-if="pageMeta.of">Menampilkan {{ pageMeta.from }}-{{ pageMeta.to }} dari {{ pageMeta.of }} data</span>
            <span v-else>Menampilkan 0 data</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(1)"
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              Awal
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage - 1)"
              :disabled="currentPage === 1 || !pageMeta.of"
            >
              Sebelumnya
            </button>
            <template v-if="pageMeta.of">
              <button
                v-for="page in pages"
                :key="`history-page-${page}`"
                type="button"
                class="rounded border px-3 py-1 text-sm transition"
                :class="
                  currentPage === page
                    ? 'border-blue-500 bg-blue-500 text-white'
                    : 'border-gray-300 text-gray-600 hover:border-blue-400 hover:text-blue-600'
                "
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(currentPage + 1)"
              :disabled="currentPage === pages.length || !pageMeta.of"
            >
              Berikutnya
            </button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-600 transition hover:border-blue-400 hover:text-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
              @click="changePage(pages.length)"
              :disabled="currentPage === pages.length || !pageMeta.of"
            >
              Akhir
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
