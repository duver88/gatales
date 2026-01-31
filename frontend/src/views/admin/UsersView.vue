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

// Create user modal
const showCreateModal = ref(false)
const isCreating = ref(false)
const plans = ref([])
const createForm = ref({
  name: '',
  email: '',
  password: '',
  plan_id: '',
  tokens_balance: '',
  status: 'active',
})
const createError = ref('')

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

async function fetchPlans() {
  try {
    const response = await adminApi.getPlans()
    plans.value = response.data.plans
  } catch (e) {
    console.error('Error fetching plans:', e)
  }
}

onMounted(() => {
  fetchUsers()
  fetchPlans()
})

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

function openCreateModal() {
  createForm.value = {
    name: '',
    email: '',
    password: '',
    plan_id: '',
    tokens_balance: '',
    status: 'active',
  }
  createError.value = ''
  showCreateModal.value = true
}

function closeCreateModal() {
  showCreateModal.value = false
}

async function createUser() {
  if (!createForm.value.name.trim()) {
    createError.value = 'El nombre es requerido'
    return
  }
  if (!createForm.value.email.trim()) {
    createError.value = 'El email es requerido'
    return
  }
  if (!createForm.value.password || createForm.value.password.length < 6) {
    createError.value = 'La contraseña debe tener al menos 6 caracteres'
    return
  }

  isCreating.value = true
  createError.value = ''

  try {
    const data = {
      name: createForm.value.name,
      email: createForm.value.email,
      password: createForm.value.password,
      status: createForm.value.status,
    }

    if (createForm.value.plan_id) {
      data.plan_id = createForm.value.plan_id
    }

    if (createForm.value.tokens_balance !== '') {
      data.tokens_balance = parseInt(createForm.value.tokens_balance)
    }

    await adminApi.createUser(data)
    await fetchUsers()
    closeCreateModal()
  } catch (e) {
    createError.value = e.response?.data?.message || 'Error al crear el usuario'
  } finally {
    isCreating.value = false
  }
}
</script>

<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gatales-text">Usuarios</h1>
      <button @click="openCreateModal" class="btn-primary flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Usuario
      </button>
    </div>

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

    <!-- Create User Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="card max-w-md w-full max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-gatales-text mb-4">Nuevo Usuario</h3>

        <div v-if="createError" class="bg-red-500/20 text-red-400 p-3 rounded mb-4 text-sm">
          {{ createError }}
        </div>

        <div class="space-y-3 mb-4">
          <div>
            <label class="block text-sm text-gatales-text-secondary mb-1">Nombre *</label>
            <input v-model="createForm.name" type="text" class="input-field text-sm" placeholder="Juan Perez" />
          </div>
          <div>
            <label class="block text-sm text-gatales-text-secondary mb-1">Email *</label>
            <input v-model="createForm.email" type="email" class="input-field text-sm" placeholder="juan@ejemplo.com" />
          </div>
          <div>
            <label class="block text-sm text-gatales-text-secondary mb-1">Contraseña *</label>
            <input v-model="createForm.password" type="password" class="input-field text-sm" placeholder="Minimo 6 caracteres" />
          </div>
          <div>
            <label class="block text-sm text-gatales-text-secondary mb-1">Plan</label>
            <select v-model="createForm.plan_id" class="input-field text-sm">
              <option value="">Sin plan</option>
              <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                {{ plan.name }} ({{ plan.tokens_monthly.toLocaleString() }} tokens)
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gatales-text-secondary mb-1">Tokens iniciales</label>
            <input
              v-model="createForm.tokens_balance"
              type="number"
              class="input-field text-sm"
              placeholder="Dejar vacio para usar tokens del plan"
            />
            <p class="text-xs text-gatales-text-secondary mt-1">Si se deja vacio, se usaran los tokens del plan seleccionado</p>
          </div>
          <div>
            <label class="block text-sm text-gatales-text-secondary mb-1">Estado</label>
            <select v-model="createForm.status" class="input-field text-sm">
              <option value="active">Activo</option>
              <option value="pending">Pendiente</option>
              <option value="inactive">Inactivo</option>
            </select>
          </div>
        </div>

        <div class="flex gap-2">
          <button @click="closeCreateModal" :disabled="isCreating" class="btn-secondary flex-1 text-sm">
            Cancelar
          </button>
          <button @click="createUser" :disabled="isCreating" class="btn-primary flex-1 text-sm flex items-center justify-center gap-2">
            <svg v-if="isCreating" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isCreating ? 'Creando...' : 'Crear Usuario' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
