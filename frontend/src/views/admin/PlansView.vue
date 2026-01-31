<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '../../services/api'

const plans = ref([])
const isLoading = ref(true)
const editingPlan = ref(null)
const editForm = ref({})
const showCreateModal = ref(false)
const isCreating = ref(false)
const isDeleting = ref(null)
const createForm = ref({
  name: '',
  tokens_monthly: 100000,
  price: 0,
  hotmart_product_id: '',
  is_active: true,
})

async function fetchPlans() {
  isLoading.value = true
  try {
    const response = await adminApi.getPlans()
    plans.value = response.data.plans
  } catch (e) {
    console.error('Error fetching plans:', e)
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchPlans)

function startEdit(plan) {
  editingPlan.value = plan.id
  editForm.value = {
    name: plan.name,
    tokens_monthly: plan.tokens_monthly,
    price: plan.price,
    hotmart_product_id: plan.hotmart_product_id || '',
    is_active: plan.is_active,
  }
}

function cancelEdit() {
  editingPlan.value = null
  editForm.value = {}
}

async function saveEdit(planId) {
  try {
    await adminApi.updatePlan(planId, editForm.value)
    await fetchPlans()
    cancelEdit()
  } catch (e) {
    alert('Error al actualizar el plan')
  }
}

function openCreateModal() {
  createForm.value = {
    name: '',
    tokens_monthly: 100000,
    price: 0,
    hotmart_product_id: '',
    is_active: true,
  }
  showCreateModal.value = true
}

function closeCreateModal() {
  showCreateModal.value = false
}

async function createPlan() {
  if (!createForm.value.name.trim()) {
    alert('El nombre es requerido')
    return
  }

  isCreating.value = true
  try {
    await adminApi.createPlan(createForm.value)
    await fetchPlans()
    closeCreateModal()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al crear el plan')
  } finally {
    isCreating.value = false
  }
}

async function deletePlan(plan) {
  if (plan.active_subscriptions > 0) {
    alert(`No se puede eliminar el plan porque tiene ${plan.active_subscriptions} suscripciones activas`)
    return
  }

  if (plan.slug === 'free') {
    alert('No se puede eliminar el plan gratuito')
    return
  }

  if (!confirm(`¿Estas seguro de eliminar el plan "${plan.name}"?`)) {
    return
  }

  isDeleting.value = plan.id
  try {
    await adminApi.deletePlan(plan.id)
    await fetchPlans()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar el plan')
  } finally {
    isDeleting.value = null
  }
}

function formatNumber(num) {
  return num?.toLocaleString('es-ES') || '0'
}

function formatPrice(price) {
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'USD',
  }).format(price)
}
</script>

<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gatales-text">Planes</h1>
      <button @click="openCreateModal" class="btn-primary flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Plan
      </button>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="text-gatales-text-secondary">Cargando...</div>

    <!-- Plans Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="plan in plans" :key="plan.id" class="card">
        <!-- View Mode -->
        <template v-if="editingPlan !== plan.id">
          <div class="flex justify-between items-start mb-4">
            <div>
              <h3 class="text-lg font-semibold text-gatales-text">{{ plan.name }}</h3>
              <p class="text-sm text-gatales-text-secondary">{{ plan.slug }}</p>
            </div>
            <span
              :class="[
                'px-2 py-1 rounded text-xs',
                plan.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
              ]"
            >
              {{ plan.is_active ? 'Activo' : 'Inactivo' }}
            </span>
          </div>

          <div class="space-y-2 mb-4">
            <div class="flex justify-between">
              <span class="text-gatales-text-secondary">Tokens mensuales</span>
              <span class="text-gatales-text font-medium">{{ formatNumber(plan.tokens_monthly) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gatales-text-secondary">Precio</span>
              <span class="text-gatales-text font-medium">{{ formatPrice(plan.price) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gatales-text-secondary">Suscriptores activos</span>
              <span class="text-gatales-text font-medium">{{ plan.active_subscriptions }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gatales-text-secondary">Hotmart ID</span>
              <span class="text-gatales-text font-medium text-sm">{{ plan.hotmart_product_id || '-' }}</span>
            </div>
          </div>

          <div class="flex gap-2">
            <button @click="startEdit(plan)" class="btn-secondary flex-1 text-sm">
              Editar
            </button>
            <button
              @click="deletePlan(plan)"
              :disabled="isDeleting === plan.id || plan.slug === 'free'"
              :class="[
                'px-3 py-2 rounded text-sm transition-colors',
                plan.slug === 'free'
                  ? 'bg-gray-600/30 text-gray-500 cursor-not-allowed'
                  : 'bg-red-500/20 text-red-400 hover:bg-red-500/30'
              ]"
            >
              <svg v-if="isDeleting === plan.id" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </template>

        <!-- Edit Mode -->
        <template v-else>
          <h3 class="text-lg font-semibold text-gatales-text mb-4">Editar Plan</h3>

          <div class="space-y-3 mb-4">
            <div>
              <label class="block text-sm text-gatales-text-secondary mb-1">Nombre</label>
              <input v-model="editForm.name" type="text" class="input-field text-sm" />
            </div>
            <div>
              <label class="block text-sm text-gatales-text-secondary mb-1">Tokens mensuales</label>
              <input v-model.number="editForm.tokens_monthly" type="number" class="input-field text-sm" />
            </div>
            <div>
              <label class="block text-sm text-gatales-text-secondary mb-1">Precio (USD)</label>
              <input v-model.number="editForm.price" type="number" step="0.01" class="input-field text-sm" />
            </div>
            <div>
              <label class="block text-sm text-gatales-text-secondary mb-1">Hotmart Product ID</label>
              <input v-model="editForm.hotmart_product_id" type="text" class="input-field text-sm" />
            </div>
            <div class="flex items-center gap-2">
              <input v-model="editForm.is_active" type="checkbox" id="is_active" class="w-4 h-4" />
              <label for="is_active" class="text-sm text-gatales-text">Plan activo</label>
            </div>
          </div>

          <div class="flex gap-2">
            <button @click="cancelEdit" class="btn-secondary flex-1 text-sm">
              Cancelar
            </button>
            <button @click="saveEdit(plan.id)" class="btn-primary flex-1 text-sm">
              Guardar
            </button>
          </div>
        </template>
      </div>
    </div>

    <!-- Create Plan Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="card max-w-md w-full">
        <h3 class="text-lg font-semibold text-gatales-text mb-4">Nuevo Plan</h3>

        <div class="space-y-3 mb-4">
          <div>
            <label class="block text-sm text-gatales-text-secondary mb-1">Nombre *</label>
            <input v-model="createForm.name" type="text" class="input-field text-sm" placeholder="Ej: Plan Pro" />
          </div>
          <div>
            <label class="block text-sm text-gatales-text-secondary mb-1">Tokens mensuales</label>
            <input v-model.number="createForm.tokens_monthly" type="number" class="input-field text-sm" />
          </div>
          <div>
            <label class="block text-sm text-gatales-text-secondary mb-1">Precio (USD)</label>
            <input v-model.number="createForm.price" type="number" step="0.01" class="input-field text-sm" />
          </div>
          <div>
            <label class="block text-sm text-gatales-text-secondary mb-1">Hotmart Product ID</label>
            <input v-model="createForm.hotmart_product_id" type="text" class="input-field text-sm" placeholder="Opcional" />
          </div>
          <div class="flex items-center gap-2">
            <input v-model="createForm.is_active" type="checkbox" id="create_is_active" class="w-4 h-4" />
            <label for="create_is_active" class="text-sm text-gatales-text">Plan activo</label>
          </div>
        </div>

        <div class="flex gap-2">
          <button @click="closeCreateModal" :disabled="isCreating" class="btn-secondary flex-1 text-sm">
            Cancelar
          </button>
          <button @click="createPlan" :disabled="isCreating" class="btn-primary flex-1 text-sm flex items-center justify-center gap-2">
            <svg v-if="isCreating" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isCreating ? 'Creando...' : 'Crear Plan' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
