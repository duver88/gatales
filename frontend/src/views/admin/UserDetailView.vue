<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi } from '../../services/api'

const route = useRoute()
const router = useRouter()

const user = ref(null)
const conversations = ref([])
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
    conversations.value = userResponse.data.conversations || []
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

      <!-- Token Usage History -->
      <div class="card mb-8" v-if="tokenStats">
        <h2 class="text-lg font-semibold text-gatales-text mb-4">Consumo de Tokens por Proveedor</h2>

        <!-- Provider Totals - All Time -->
        <div class="mb-6">
          <h3 class="text-sm font-medium text-gatales-text-secondary mb-3">Totales Históricos</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- OpenAI -->
            <div class="bg-gradient-to-br from-green-500/10 to-green-600/5 border border-green-500/20 rounded-lg p-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-green-400 font-semibold">OpenAI</span>
              </div>
              <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <p class="text-gatales-text-secondary text-xs">Tokens</p>
                  <p class="text-gatales-text font-medium">{{ formatNumber(tokenStats.all_time_totals?.openai?.total || 0) }}</p>
                </div>
                <div>
                  <p class="text-gatales-text-secondary text-xs">Costo USD</p>
                  <p class="text-green-400 font-bold">${{ (tokenStats.all_time_totals?.openai?.cost || 0).toFixed(4) }}</p>
                </div>
              </div>
            </div>
            <!-- DeepSeek -->
            <div class="bg-gradient-to-br from-blue-500/10 to-blue-600/5 border border-blue-500/20 rounded-lg p-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-blue-400 font-semibold">DeepSeek</span>
              </div>
              <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <p class="text-gatales-text-secondary text-xs">Tokens</p>
                  <p class="text-gatales-text font-medium">{{ formatNumber(tokenStats.all_time_totals?.deepseek?.total || 0) }}</p>
                </div>
                <div>
                  <p class="text-gatales-text-secondary text-xs">Costo USD</p>
                  <p class="text-blue-400 font-bold">${{ (tokenStats.all_time_totals?.deepseek?.cost || 0).toFixed(4) }}</p>
                </div>
              </div>
            </div>
            <!-- Total -->
            <div class="bg-gatales-input rounded-lg p-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-gatales-text font-semibold">Total</span>
              </div>
              <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <p class="text-gatales-text-secondary text-xs">Tokens</p>
                  <p class="text-gatales-text font-medium">{{ formatNumber(tokenStats.all_time_totals?.total || 0) }}</p>
                </div>
                <div>
                  <p class="text-gatales-text-secondary text-xs">Costo USD</p>
                  <p class="text-amber-400 font-bold">${{ (tokenStats.all_time_totals?.total_cost || 0).toFixed(4) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Provider Totals - Last 30 Days -->
        <div class="mb-6">
          <h3 class="text-sm font-medium text-gatales-text-secondary mb-3">Últimos 30 Días</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- OpenAI -->
            <div class="bg-gradient-to-br from-green-500/10 to-green-600/5 border border-green-500/20 rounded-lg p-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-green-400 font-semibold">OpenAI</span>
              </div>
              <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <p class="text-gatales-text-secondary text-xs">Tokens</p>
                  <p class="text-gatales-text font-medium">{{ formatNumber(tokenStats.period_totals?.openai?.total || 0) }}</p>
                </div>
                <div>
                  <p class="text-gatales-text-secondary text-xs">Costo USD</p>
                  <p class="text-green-400 font-bold">${{ (tokenStats.period_totals?.openai?.cost || 0).toFixed(4) }}</p>
                </div>
              </div>
            </div>
            <!-- DeepSeek -->
            <div class="bg-gradient-to-br from-blue-500/10 to-blue-600/5 border border-blue-500/20 rounded-lg p-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-blue-400 font-semibold">DeepSeek</span>
              </div>
              <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <p class="text-gatales-text-secondary text-xs">Tokens</p>
                  <p class="text-gatales-text font-medium">{{ formatNumber(tokenStats.period_totals?.deepseek?.total || 0) }}</p>
                </div>
                <div>
                  <p class="text-gatales-text-secondary text-xs">Costo USD</p>
                  <p class="text-blue-400 font-bold">${{ (tokenStats.period_totals?.deepseek?.cost || 0).toFixed(4) }}</p>
                </div>
              </div>
            </div>
            <!-- Total -->
            <div class="bg-gatales-input rounded-lg p-4">
              <div class="flex items-center gap-2 mb-2">
                <span class="text-gatales-text font-semibold">Total</span>
              </div>
              <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <p class="text-gatales-text-secondary text-xs">Tokens</p>
                  <p class="text-gatales-text font-medium">{{ formatNumber(tokenStats.period_totals?.total || 0) }}</p>
                </div>
                <div>
                  <p class="text-gatales-text-secondary text-xs">Costo USD</p>
                  <p class="text-amber-400 font-bold">${{ (tokenStats.period_totals?.total_cost || 0).toFixed(4) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Daily Breakdown Table -->
        <div v-if="tokenStats.daily?.length > 0">
          <h3 class="text-sm font-medium text-gatales-text-secondary mb-3">Desglose Diario</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gatales-border text-left">
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Fecha</th>
                  <th class="py-2 px-3 text-green-400 font-medium text-right">OpenAI</th>
                  <th class="py-2 px-3 text-green-400 font-medium text-right">Costo</th>
                  <th class="py-2 px-3 text-blue-400 font-medium text-right">DeepSeek</th>
                  <th class="py-2 px-3 text-blue-400 font-medium text-right">Costo</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium text-right">Total</th>
                  <th class="py-2 px-3 text-amber-400 font-medium text-right">Costo Total</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="day in [...tokenStats.daily].reverse()"
                  :key="day.date"
                  class="border-b border-gatales-border/50 hover:bg-gatales-input/50"
                >
                  <td class="py-2 px-3 text-gatales-text">{{ day.date }}</td>
                  <td class="py-2 px-3 text-gatales-text text-right">{{ formatNumber(day.openai?.total || 0) }}</td>
                  <td class="py-2 px-3 text-green-400 text-right">${{ (day.openai?.cost || 0).toFixed(4) }}</td>
                  <td class="py-2 px-3 text-gatales-text text-right">{{ formatNumber(day.deepseek?.total || 0) }}</td>
                  <td class="py-2 px-3 text-blue-400 text-right">${{ (day.deepseek?.cost || 0).toFixed(4) }}</td>
                  <td class="py-2 px-3 text-gatales-text font-medium text-right">{{ formatNumber(day.total || 0) }}</td>
                  <td class="py-2 px-3 text-amber-400 font-bold text-right">${{ (day.total_cost || 0).toFixed(4) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="text-gatales-text-secondary text-sm">
          No hay datos de consumo
        </div>
      </div>

      <!-- Conversations -->
      <div class="card mb-8">
        <h2 class="text-lg font-semibold text-gatales-text mb-4">Conversaciones ({{ conversations.length }})</h2>
        <div v-if="conversations.length === 0" class="text-gatales-text-secondary text-sm">
          No hay conversaciones
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gatales-border text-left">
                <th class="py-2 px-3 text-gatales-text-secondary font-medium">Título</th>
                <th class="py-2 px-3 text-gatales-text-secondary font-medium text-center">Mensajes</th>
                <th class="py-2 px-3 text-gatales-text-secondary font-medium text-right">Tokens Input</th>
                <th class="py-2 px-3 text-gatales-text-secondary font-medium text-right">Tokens Output</th>
                <th class="py-2 px-3 text-gatales-text-secondary font-medium text-right">Total Tokens</th>
                <th class="py-2 px-3 text-gatales-text-secondary font-medium text-right">Costo USD</th>
                <th class="py-2 px-3 text-gatales-text-secondary font-medium text-right">Último mensaje</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="conv in conversations"
                :key="conv.id"
                class="border-b border-gatales-border/50 hover:bg-gatales-input/50"
              >
                <td class="py-2 px-3 text-gatales-text max-w-xs truncate" :title="conv.title">
                  {{ conv.title }}
                </td>
                <td class="py-2 px-3 text-gatales-text text-center">{{ conv.messages_count }}</td>
                <td class="py-2 px-3 text-gatales-text text-right">{{ formatNumber(conv.tokens_input) }}</td>
                <td class="py-2 px-3 text-gatales-text text-right">{{ formatNumber(conv.tokens_output) }}</td>
                <td class="py-2 px-3 text-gatales-text font-medium text-right">{{ formatNumber(conv.total_tokens) }}</td>
                <td class="py-2 px-3 text-green-400 text-right">${{ (conv.estimated_cost || 0).toFixed(4) }}</td>
                <td class="py-2 px-3 text-gatales-text-secondary text-right text-xs">
                  {{ conv.last_message_at ? formatDate(conv.last_message_at) : '-' }}
                </td>
              </tr>
            </tbody>
          </table>
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
