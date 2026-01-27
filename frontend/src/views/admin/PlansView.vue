<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '../../services/api'

const plans = ref([])
const isLoading = ref(true)
const editingPlan = ref(null)
const editForm = ref({})

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
    <h1 class="text-2xl font-bold text-gatales-text mb-6">Planes</h1>

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

          <button @click="startEdit(plan)" class="btn-secondary w-full text-sm">
            Editar
          </button>
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
  </div>
</template>
