<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  message: {
    type: String,
    default: 'Anda tidak memiliki akses ke halaman atau modul yang diminta.',
  },
})

const page = usePage()

const user = computed(() => page.props.auth?.user ?? null)
const permissions = computed(() => page.props.auth?.permissions ?? {})
const hasAuthenticatedUser = computed(() => Boolean(user.value))
const canManageRoomModule = computed(() => Boolean(permissions.value?.can_manage_room_module))
const canManageItemModule = computed(() => Boolean(permissions.value?.can_manage_item_module))
const canManageUsers = computed(() => Boolean(permissions.value?.can_manage_users))
const wrapperComponent = computed(() => (hasAuthenticatedUser.value ? AuthenticatedLayout : 'div'))

const availableLinks = computed(() => {
  const links = []

  if (hasAuthenticatedUser.value) {
    links.push(
      { label: 'Dashboard', href: route('dashboard') },
      { label: 'Peminjaman Ruangan', href: route('bookings.index') },
      { label: 'Peminjaman Barang', href: route('item-borrowings.index') },
    )
  } else {
    links.push(
      { label: 'Beranda', href: '/' },
      { label: 'Login', href: route('login') },
    )
  }

  if (canManageRoomModule.value) {
    links.push(
      { label: 'Approval Ruangan', href: route('admin.bookings.index') },
      { label: 'Report Ruangan', href: route('admin.reports.index') },
    )
  }

  if (canManageItemModule.value) {
    links.push(
      { label: 'Approval Barang', href: route('admin.item-borrowings.index') },
      { label: 'Report Barang', href: route('admin.item-borrowing-reports.index') },
    )
  }

  if (canManageUsers.value) {
    links.push({ label: 'Kelola User', href: route('admin.users.index') })
  }

  return links
})
</script>

<template>
  <Head title="403 Forbidden" />

  <component :is="wrapperComponent">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
      <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-slate-50 px-6 py-5">
          <div class="text-sm font-semibold uppercase tracking-[0.3em] text-slate-400">403 Forbidden</div>
          <h1 class="mt-2 text-2xl font-semibold text-slate-900">Akses Ditolak</h1>
          <p class="mt-2 text-sm leading-6 text-slate-600">
            {{ message }}
          </p>
        </div>

        <div class="space-y-6 px-6 py-6">
          <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
            Menu yang tidak sesuai hak akses memang disembunyikan dari navigasi. Jika halaman ini muncul, berarti URL tersebut
            tidak termasuk modul yang boleh diakses oleh akun Anda.
          </div>

          <div>
            <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Halaman Yang Bisa Diakses</h2>
            <div class="mt-4 flex flex-wrap gap-3">
              <Link
                v-for="link in availableLinks"
                :key="link.href"
                :href="link.href"
                class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
              >
                {{ link.label }}
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </component>
</template>
