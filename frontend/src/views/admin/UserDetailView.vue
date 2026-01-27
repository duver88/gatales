<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi } from '../../services/api'

const route = useRoute()
const router = useRouter()

const user = ref(null)
const recentMessages = ref([])
const tokenStats = ref([])
const plans = ref([])
const isLoading = ref(true)
const error = ref(null)
const showAddTokensModal = ref(false)
const showChangePlanModal = ref(false)
const tokensToAdd = ref(10000)
const selectedPlanId = ref(null)

const userId = computed(() => route.params.id)

async function fetchUser() {
  isLoading.value = true
  try {
    const [userResponse, plansResponse] = await Promise.all([
      adminApi.getUser(userId.value),
      adminApi.getPlans(),
    ])
    user.value = userResponse.data.user
    recentMessages.value = userResponse.data.recent_messages
    tokenStats.value = userResponse.data.token_stats
    plans.value = plansResponse.data.plans
    selectedPlanId.value = user.value.subscription?.plan_id
  } catch (e) {
    error.value = 'Error al cargar el usuario'
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchUser)

async function activateUser() {
  try {
    await adminApi.activateUser(userId.value)
    user.value.status = 'active'
  } catch (e) {
    alert('Error al activar el usuario')
  }
}

async function deactivateUser() {
  if (!confirm('¿Estás seguro de desactivar este usuario?')) return
  try {
    await adminApi.deactivateUser(userId.value)
    user.value.status = 'inactive'
  } catch (e) {
    alert('Error al desactivar el usuario')
  }
}

async function addTokens() {
  try {
    const response = await adminApi.addTokens(userId.value, tokensToAdd.value)
    user.value.tokens_balance = response.data.tokens_balance
    showAddTokensModal.value = false
    tokensToAdd.value = 10000
  } catch (e) {
    alert('Error al agregar tokens')
  }
}

async function changePlan() {
  try {
    await adminApi.changePlan(userId.value, {
      plan_id: selectedPlanId.value,
      reset_tokens: true,
    })
    await fetchUser()
    showChangePlanModal.value = false
  } catch (e) {
    alert('Error al cambiar el plan')
  }
}

function formatNumber(num) {
  return num?.toLocaleString('es-ES') || '0'
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('es-ES', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
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
</script>

<template>
  <div class="p-6">
    <!-- Back button -->
    <button
      @click="router.push('/admin/users')"
      class="flex items-center gap-2 text-gatales-text-secondary hover:text-gatales-text mb-4"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      Volver a usuarios
    </button>

    <!-- Loading -->
    <div v-if="isLoading" class="text-gatales-text-secondary">Cargando...</div>

    <!-- Error -->
    <div v-else-if="error" class="text-red-400">{{ error }}</div>

    <template v-else-if="user">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gatales-text">{{ user.name }}</h1>
          <p class="text-gatales-text-secondary">{{ user.email }}</p>
        </div>
        <div class="flex gap-2">
          <button
            v-if="user.status !== 'active'"
            @click="activateUser"
            class="btn-primary text-sm"
          >
            Activar
          </button>
          <button
            v-if="user.status === 'active'"
            @click="deactivateUser"
            class="btn-secondary text-sm"
          >
            Desactivar
          </button>
          <button @click="showAddTokensModal = true" class="btn-secondary text-sm">
            Agregar tokens
          </button>
          <button @click="showChangePlanModal = true" class="btn-secondary text-sm">
            Cambiar plan
          </button>
        </div>
      </div>

      <!-- User Info -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="card">
          <p class="text-sm text-gatales-text-secondary">Estado</p>
          <span :class="['inline-block mt-1 px-2 py-1 rounded text-sm', getStatusClass(user.status)]">
            {{ user.status }}
          </span>
        </div>
        <div class="card">
          <p class="text-sm text-gatales-text-secondary">Tokens Disponibles</p>
          <p class="text-xl font-bold text-gatales-text mt-1">{{ formatNumber(user.tokens_balance) }}</p>
        </div>
        <div class="card">
          <p class="text-sm text-gatales-text-secondary">Tokens Usados Este Mes</p>
          <p class="text-xl font-bold text-gatales-text mt-1">{{ formatNumber(user.tokens_used_month) }}</p>
        </div>
      </div>

      <!-- Subscription Info -->
      <div class="card mb-8" v-if="user.subscription">
        <h2 class="text-lg font-semibold text-gatales-text mb-4">Suscripción Activa</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <p class="text-sm text-gatales-text-secondary">Plan</p>
            <p class="text-gatales-text font-medium">{{ user.subscription.plan }}</p>
          </div>
          <div>
            <p class="text-sm text-gatales-text-secondary">Estado</p>
            <p class="text-gatales-text font-medium">{{ user.subscription.status }}</p>
          </div>
          <div>
            <p class="text-sm text-gatales-text-secondary">Inicio</p>
            <p class="text-gatales-text font-medium">{{ formatDate(user.subscription.starts_at) }}</p>
          </div>
          <div>
            <p class="text-sm text-gatales-text-secondary">Vence</p>
            <p class="text-gatales-text font-medium">{{ formatDate(user.subscription.ends_at) }}</p>
          </div>
        </div>
      </div>

      <!-- Recent Messages -->
      <div class="card mb-8">
        <h2 class="text-lg font-semibold text-gatales-text mb-4">Mensajes Recientes</h2>
        <div v-if="recentMessages.length === 0" class="text-gatales-text-secondary text-sm">
          No hay mensajes
        </div>
        <div v-else class="space-y-3 max-h-96 overflow-y-auto">
          <div
            v-for="msg in recentMessages"
            :key="msg.id"
            :class="[
              'p-3 rounded-lg',
              msg.role === 'user' ? 'bg-gatales-input' : 'bg-gatales-accent/10'
            ]"
          >
            <div class="flex justify-between items-start mb-1">
              <span class="text-xs font-medium" :class="msg.role === 'user' ? 'text-gatales-text' : 'text-gatales-accent'">
                {{ msg.role === 'user' ? 'Usuario' : 'Asistente' }}
              </span>
              <span class="text-xs text-gatales-text-secondary">
                {{ msg.tokens_used }} tokens
              </span>
            </div>
            <p class="text-sm text-gatales-text line-clamp-3">{{ msg.content }}</p>
          </div>
        </div>
      </div>
    </template>

    <!-- Add Tokens Modal -->
    <div v-if="showAddTokensModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="card w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gatales-text mb-4">Agregar Tokens</h3>
        <input
          v-model.number="tokensToAdd"
          type="number"
          min="1"
          class="input-field mb-4"
          placeholder="Cantidad de tokens"
        />
        <div class="flex gap-2 justify-end">
          <button @click="showAddTokensModal = false" class="btn-secondary">
            Cancelar
          </button>
          <button @click="addTokens" class="btn-primary">
            Agregar
          </button>
        </div>
      </div>
    </div>

    <!-- Change Plan Modal -->
    <div v-if="showChangePlanModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="card w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gatales-text mb-4">Cambiar Plan</h3>
        <select v-model="selectedPlanId" class="input-field mb-4">
          <option v-for="plan in plans" :key="plan.id" :value="plan.id">
            {{ plan.name }} - {{ formatNumber(plan.tokens_monthly) }} tokens/mes
          </option>
        </select>
        <p class="text-sm text-gatales-text-secondary mb-4">
          Los tokens del usuario se renovarán al cambiar de plan.
        </p>
        <div class="flex gap-2 justify-end">
          <button @click="showChangePlanModal = false" class="btn-secondary">
            Cancelar
          </button>
          <button @click="changePlan" class="btn-primary">
            Cambiar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
