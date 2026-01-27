<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '../../services/api'

const router = useRouter()

const users = ref([])
const pagination = ref({})
const isLoading = ref(true)
const search = ref('')
const statusFilter = ref('')
const currentPage = ref(1)

async function fetchUsers() {
  isLoading.value = true
  try {
    const response = await adminApi.getUsers({
      search: search.value || undefined,
      status: statusFilter.value || undefined,
      page: currentPage.value,
      per_page: 15,
    })
    users.value = response.data.users
    pagination.value = response.data.pagination
  } catch (e) {
    console.error('Error fetching users:', e)
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchUsers)

watch([search, statusFilter], () => {
  currentPage.value = 1
  fetchUsers()
})

watch(currentPage, fetchUsers)

function formatNumber(num) {
  return num.toLocaleString('es-ES')
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('es-ES')
}

function getStatusClass(status) {
  const classes = {
    active: 'bg-green-500/20 text-green-400',
    pending: 'bg-yellow-500/20 text-yellow-400',
    inactive: 'bg-red-500/20 text-red-400',
    suspended: 'bg-gray-500/20 text-gray-400',
  }
  return classes[status] || classes.inactive
}

function viewUser(id) {
  router.push(`/admin/users/${id}`)
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold text-gatales-text mb-6">Usuarios</h1>

    <!-- Filters -->
    <div class="flex flex-col md:flex-row gap-4 mb-6">
      <input
        v-model="search"
        type="text"
        placeholder="Buscar por email o nombre..."
        class="input-field md:w-80"
      />
      <select v-model="statusFilter" class="input-field md:w-40">
        <option value="">Todos los estados</option>
        <option value="active">Activo</option>
        <option value="pending">Pendiente</option>
        <option value="inactive">Inactivo</option>
        <option value="suspended">Suspendido</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="text-gatales-text-secondary">Cargando...</div>

    <!-- Users Table -->
    <div v-else class="card overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="text-left text-sm text-gatales-text-secondary border-b border-gatales-border">
            <th class="pb-3 pr-4">Usuario</th>
            <th class="pb-3 pr-4">Plan</th>
            <th class="pb-3 pr-4">Estado</th>
            <th class="pb-3 pr-4">Tokens</th>
            <th class="pb-3 pr-4">Registro</th>
            <th class="pb-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in users"
            :key="user.id"
            class="border-b border-gatales-border/50 hover:bg-gatales-input/50 cursor-pointer"
            @click="viewUser(user.id)"
          >
            <td class="py-3 pr-4">
              <p class="text-sm font-medium text-gatales-text">{{ user.name }}</p>
              <p class="text-xs text-gatales-text-secondary">{{ user.email }}</p>
            </td>
            <td class="py-3 pr-4 text-sm text-gatales-text">
              {{ user.active_subscription?.plan?.name || 'Sin plan' }}
            </td>
            <td class="py-3 pr-4">
              <span :class="['px-2 py-1 rounded text-xs', getStatusClass(user.status)]">
                {{ user.status }}
              </span>
            </td>
            <td class="py-3 pr-4 text-sm text-gatales-text">
              {{ formatNumber(user.tokens_balance) }}
            </td>
            <td class="py-3 pr-4 text-sm text-gatales-text-secondary">
              {{ formatDate(user.created_at) }}
            </td>
            <td class="py-3">
              <button
                @click.stop="viewUser(user.id)"
                class="text-gatales-accent hover:underline text-sm"
              >
                Ver detalle
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Empty state -->
      <div v-if="users.length === 0" class="text-center py-8 text-gatales-text-secondary">
        No se encontraron usuarios
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="flex justify-center gap-2 mt-6">
      <button
        @click="currentPage--"
        :disabled="currentPage === 1"
        class="btn-secondary px-3 py-1 text-sm"
      >
        Anterior
      </button>
      <span class="text-gatales-text-secondary text-sm self-center">
        Página {{ currentPage }} de {{ pagination.last_page }}
      </span>
      <button
        @click="currentPage++"
        :disabled="currentPage === pagination.last_page"
        class="btn-secondary px-3 py-1 text-sm"
      >
        Siguiente
      </button>
    </div>
  </div>
</template>
