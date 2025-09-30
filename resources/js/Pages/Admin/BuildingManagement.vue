<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Building2, Plus, Search, Edit, Trash2, Users, Settings, LogOut } from 'lucide-vue-next'; // Asumsi lucide-vue-next sudah terinstal

// Mock data. Nanti kita akan hapus ini dan ambil dari backend
const initialBuildings = [
  {
    id: 1,
    campus_id: 1,
    name: 'Building A - Engineering',
    campus_name: 'Main Campus',
    created_at: '2024-01-15T10:30:00Z',
    updated_at: '2024-01-15T10:30:00Z'
  },
  {
    id: 2,
    campus_id: 1,
    name: 'Building B - Sciences',
    campus_name: 'Main Campus',
    created_at: '2024-01-15T10:35:00Z',
    updated_at: '2024-01-15T10:35:00Z'
  },
  {
    id: 3,
    campus_id: 2,
    name: 'Building C - Business',
    campus_name: 'Bekasi Campus',
    created_at: '2024-01-15T10:40:00Z',
    updated_at: '2024-01-15T10:40:00Z'
  }
];

const campuses = [
  { id: 1, name: 'Main Campus' },
  { id: 2, name: 'Bekasi Campus' },
  { id: 3, name: 'Jakarta Campus' }
];

const buildings = ref(initialBuildings);
const searchTerm = ref('');
const isAddDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingBuilding = ref(null);
const formData = ref({ name: '', campus_id: '' });

const filteredBuildings = computed(() => {
  return buildings.value.filter(building =>
    building.name.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
    building.campus_name.toLowerCase().includes(searchTerm.value.toLowerCase())
  );
});

const handleAddBuilding = () => {
  if (formData.value.name && formData.value.campus_id) {
    const campus = campuses.find(c => c.id === parseInt(formData.value.campus_id));
    const newBuilding = {
      id: Math.max(...buildings.value.map(b => b.id)) + 1,
      campus_id: parseInt(formData.value.campus_id),
      name: formData.value.name,
      campus_name: campus?.name || '',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };
    buildings.value.push(newBuilding);
    formData.value = { name: '', campus_id: '' };
    isAddDialogOpen.value = false;
  }
};

const handleEditBuilding = () => {
  if (editingBuilding.value && formData.value.name && formData.value.campus_id) {
    const campus = campuses.find(c => c.id === parseInt(formData.value.campus_id));
    const updatedBuilding = {
      ...editingBuilding.value,
      name: formData.value.name,
      campus_id: parseInt(formData.value.campus_id),
      campus_name: campus?.name || '',
      updated_at: new Date().toISOString()
    };
    const index = buildings.value.findIndex(b => b.id === editingBuilding.value.id);
    if (index !== -1) {
      buildings.value[index] = updatedBuilding;
    }
    formData.value = { name: '', campus_id: '' };
    isEditDialogOpen.value = false;
    editingBuilding.value = null;
  }
};

const handleDeleteBuilding = (id) => {
  buildings.value = buildings.value.filter(b => b.id !== id);
};

const openEditDialog = (building) => {
  editingBuilding.value = building;
  formData.value = { name: building.name, campus_id: building.campus_id.toString() };
  isEditDialogOpen.value = true;
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};
</script>

<template>
  <Head title="Admin Dashboard" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-gray-50">
      <main class="p-6">
        <div class="max-w-7xl mx-auto">
          <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Building Management</h2>
            <p class="text-gray-600">Manage campus buildings and their information</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
              <div class="flex flex-row items-center justify-between pb-2">
                <h3 class="text-sm font-medium">Total Buildings</h3>
                <Building2 class="h-4 w-4 text-gray-400" />
              </div>
              <div>
                <div class="text-2xl font-bold">{{ buildings.length }}</div>
                <p class="text-xs text-gray-500">Across all campuses</p>
              </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
              <div class="flex flex-row items-center justify-between pb-2">
                <h3 class="text-sm font-medium">Active Campuses</h3>
                <Users class="h-4 w-4 text-gray-400" />
              </div>
              <div>
                <div class="text-2xl font-bold">{{ campuses.length }}</div>
                <p class="text-xs text-gray-500">Available locations</p>
              </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
              <div class="flex flex-row items-center justify-between pb-2">
                <h3 class="text-sm font-medium">Recent Updates</h3>
                <Settings class="h-4 w-4 text-gray-400" />
              </div>
              <div>
                <div class="text-2xl font-bold">3</div>
                <p class="text-xs text-gray-500">This week</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm">
            <div class="p-6">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-lg font-bold">Buildings</h3>
                  <p class="text-sm text-gray-600">View and manage all campus buildings</p>
                </div>
                <button @click="isAddDialogOpen = true" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow h-9 px-4 py-2 bg-blue-600 text-white hover:bg-blue-700">
                  <Plus class="w-4 h-4 mr-2" />
                  Add Building
                </button>
              </div>
              
              <div class="flex items-center gap-2 mt-4">
                <div class="relative flex-1 max-w-sm">
                  <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                  <input
                    type="text"
                    placeholder="Search buildings..."
                    v-model="searchTerm"
                    class="pl-10 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                  />
                </div>
              </div>
            </div>
            
            <div class="p-6">
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Building Name</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Campus</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
                      <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="building in filteredBuildings" :key="building.id">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#{{ building.id }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ building.name }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                          {{ building.campus_name }}
                        </span>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(building.created_at) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(building.updated_at) }}</td>
                      <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end gap-2">
                          <button @click="openEditDialog(building)" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 text-gray-500 hover:bg-gray-100 h-9 w-9">
                            <Edit class="w-4 h-4" />
                          </button>
                          <button @click="handleDeleteBuilding(building.id)" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 text-red-500 hover:bg-red-50 h-9 w-9">
                            <Trash2 class="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <div v-if="isAddDialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
      <div class="relative w-full max-w-md mx-auto rounded-lg bg-white p-6 shadow-lg">
        <h3 class="text-lg font-bold mb-2">Add New Building</h3>
        <p class="text-sm text-gray-500 mb-4">Create a new building entry for the campus</p>
        <div class="space-y-4">
          <div>
            <label for="building-name" class="block text-sm font-medium text-gray-700">Building Name</label>
            <input
              id="building-name"
              type="text"
              placeholder="Enter building name"
              v-model="formData.name"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            />
          </div>
          <div>
            <label for="campus-select" class="block text-sm font-medium text-gray-700">Campus</label>
            <select id="campus-select" v-model="formData.campus_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
              <option value="">Select a campus</option>
              <option v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">
                {{ campus.name }}
              </option>
            </select>
          </div>
          <div class="flex justify-end gap-2">
            <button @click="isAddDialogOpen = false" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-white border border-gray-300 shadow-sm h-9 px-4 py-2 hover:bg-gray-50">
              Cancel
            </button>
            <button @click="handleAddBuilding" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-blue-600 text-white shadow h-9 px-4 py-2 hover:bg-blue-700">
              Add Building
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <div v-if="isEditDialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
      <div class="relative w-full max-w-md mx-auto rounded-lg bg-white p-6 shadow-lg">
        <h3 class="text-lg font-bold mb-2">Edit Building</h3>
        <p class="text-sm text-gray-500 mb-4">Update building information</p>
        <div class="space-y-4">
          <div>
            <label for="edit-building-name" class="block text-sm font-medium text-gray-700">Building Name</label>
            <input
              id="edit-building-name"
              type="text"
              placeholder="Enter building name"
              v-model="formData.name"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            />
          </div>
          <div>
            <label for="edit-campus-select" class="block text-sm font-medium text-gray-700">Campus</label>
            <select id="edit-campus-select" v-model="formData.campus_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
              <option value="">Select a campus</option>
              <option v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">
                {{ campus.name }}
              </option>
            </select>
          </div>
          <div class="flex justify-end gap-2">
            <button @click="isEditDialogOpen = false" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-white border border-gray-300 shadow-sm h-9 px-4 py-2 hover:bg-gray-50">
              Cancel
            </button>
            <button @click="handleEditBuilding" class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-blue-600 text-white shadow h-9 px-4 py-2 hover:bg-blue-700">
              Update Building
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>