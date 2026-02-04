<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '../../services/api'

const stats = ref(null)
const tokenChart = ref([])
const recentUsers = ref([])
const openaiStats = ref(null)
const providerStats = ref(null)
const isLoading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    const [dashboardRes, openaiRes, providerRes] = await Promise.all([
      adminApi.getDashboard(),
      adminApi.getOpenAIStats(),
      adminApi.getProviderStats()
    ])
    stats.value = dashboardRes.data.stats
    tokenChart.value = dashboardRes.data.token_usage_chart
    recentUsers.value = dashboardRes.data.recent_users
    openaiStats.value = openaiRes.data.openai_usage
    providerStats.value = providerRes.data
  } catch (e) {
    error.value = 'Error al cargar el dashboard'
  } finally {
    isLoading.value = false
  }
})

// Calculate max value for chart
const maxChartValue = computed(() => {
  if (!tokenChart.value.length) return 0
  return Math.max(...tokenChart.value.map(d => d.total))
})

function formatNumber(num) {
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M'
  if (num >= 1000) return (num / 1000).toFixed(1) + 'K'
  return num?.toLocaleString('es-ES') || '0'
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('es-ES', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

function getStatusBadge(status) {
  const badges = {
    active: { class: 'bg-success/15 text-success', label: 'Activo' },
    pending: { class: 'bg-warning/15 text-warning', label: 'Pendiente' },
    inactive: { class: 'bg-error/15 text-error', label: 'Inactivo' },
    suspended: { class: 'bg-text-muted/15 text-text-muted', label: 'Suspendido' },
  }
  return badges[status] || badges.inactive
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
  }).format(amount)
}

function getChartBarHeight(value) {
  if (!maxChartValue.value) return 0
  return Math.max((value / maxChartValue.value) * 100, 5)
}

function getDayName(dateStr) {
  const date = new Date(dateStr)
  const days = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab']
  return days[date.getDay()]
}
</script>

<template>
  <div class="p-4 sm:p-6 lg:p-8">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-text-primary">Dashboard</h1>
      <p class="text-sm text-text-secondary mt-1">Panel de administracion</p>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center h-64">
      <div class="flex items-center gap-3 text-text-secondary">
        <svg class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>Cargando datos...</span>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg">
      {{ error }}
    </div>

    <template v-else>
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <!-- Total Users -->
        <div class="bg-bg-card border border-border rounded-xl p-5 hover:border-brand/30 transition-colors">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-text-secondary mb-1">Total Usuarios</p>
              <h3 class="text-2xl font-bold text-text-primary">{{ formatNumber(stats.total_users) }}</h3>
            </div>
            <div class="w-12 h-12 bg-brand/10 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
          </div>
        </div>

        <!-- Active Today -->
        <div class="bg-bg-card border border-border rounded-xl p-5 hover:border-brand/30 transition-colors">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-text-secondary mb-1">Activos Hoy</p>
              <h3 class="text-2xl font-bold text-brand">{{ formatNumber(stats.active_users_today) }}</h3>
            </div>
            <div class="w-12 h-12 bg-success/10 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            </div>
          </div>
        </div>

        <!-- Tokens Today -->
        <div class="bg-bg-card border border-border rounded-xl p-5 hover:border-brand/30 transition-colors">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-text-secondary mb-1">Tokens Hoy</p>
              <h3 class="text-2xl font-bold text-text-primary">{{ formatNumber(stats.tokens_consumed_today) }}</h3>
            </div>
            <div class="w-12 h-12 bg-info/10 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
          </div>
        </div>

        <!-- Tokens Month -->
        <div class="bg-bg-card border border-border rounded-xl p-5 hover:border-brand/30 transition-colors">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-text-secondary mb-1">Tokens Este Mes</p>
              <h3 class="text-2xl font-bold text-text-primary">{{ formatNumber(stats.tokens_consumed_month) }}</h3>
            </div>
            <div class="w-12 h-12 bg-warning/10 rounded-xl flex items-center justify-center">
              <svg class="w-6 h-6 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Provider Stats (OpenAI vs DeepSeek) -->
      <div v-if="providerStats" class="bg-bg-card border border-border rounded-xl p-5 mb-6">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 bg-brand/10 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <div>
            <h2 class="text-lg font-semibold text-text-primary">Consumo por Proveedor</h2>
            <p class="text-sm text-text-secondary">OpenAI vs DeepSeek (Usuarios + Admin)</p>
          </div>
        </div>

        <!-- Combined Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <!-- Today -->
          <div class="bg-bg-secondary rounded-xl p-4 border border-border">
            <div class="flex items-center justify-between mb-4">
              <span class="text-sm font-medium text-text-secondary">Hoy</span>
              <span class="text-xs px-2 py-1 bg-brand/10 text-brand rounded-full">En vivo</span>
            </div>
            <div class="space-y-3">
              <!-- OpenAI -->
              <div class="flex items-center justify-between p-2 bg-green-500/10 rounded-lg">
                <div class="flex items-center gap-2">
                  <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                  <span class="text-sm text-text-primary">OpenAI</span>
                </div>
                <div class="text-right">
                  <p class="text-sm font-medium text-text-primary">{{ formatNumber(providerStats.combined.today.openai.tokens) }}</p>
                  <p class="text-xs text-green-400">{{ formatCurrency(providerStats.combined.today.openai.cost) }}</p>
                </div>
              </div>
              <!-- DeepSeek -->
              <div class="flex items-center justify-between p-2 bg-blue-500/10 rounded-lg">
                <div class="flex items-center gap-2">
                  <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                  <span class="text-sm text-text-primary">DeepSeek</span>
                </div>
                <div class="text-right">
                  <p class="text-sm font-medium text-text-primary">{{ formatNumber(providerStats.combined.today.deepseek.tokens) }}</p>
                  <p class="text-xs text-blue-400">{{ formatCurrency(providerStats.combined.today.deepseek.cost) }}</p>
                </div>
              </div>
              <!-- Total -->
              <div class="pt-2 border-t border-border flex justify-between">
                <span class="text-sm text-text-secondary">Total</span>
                <div class="text-right">
                  <p class="font-bold text-text-primary">{{ formatNumber(providerStats.combined.today.total_tokens) }}</p>
                  <p class="text-sm font-medium text-success">{{ formatCurrency(providerStats.combined.today.total_cost) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- This Month -->
          <div class="bg-bg-secondary rounded-xl p-4 border border-border">
            <div class="flex items-center justify-between mb-4">
              <span class="text-sm font-medium text-text-secondary">Este Mes</span>
            </div>
            <div class="space-y-3">
              <!-- OpenAI -->
              <div class="flex items-center justify-between p-2 bg-green-500/10 rounded-lg">
                <div class="flex items-center gap-2">
                  <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                  <span class="text-sm text-text-primary">OpenAI</span>
                </div>
                <div class="text-right">
                  <p class="text-sm font-medium text-text-primary">{{ formatNumber(providerStats.combined.month.openai.tokens) }}</p>
                  <p class="text-xs text-green-400">{{ formatCurrency(providerStats.combined.month.openai.cost) }}</p>
                </div>
              </div>
              <!-- DeepSeek -->
              <div class="flex items-center justify-between p-2 bg-blue-500/10 rounded-lg">
                <div class="flex items-center gap-2">
                  <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                  <span class="text-sm text-text-primary">DeepSeek</span>
                </div>
                <div class="text-right">
                  <p class="text-sm font-medium text-text-primary">{{ formatNumber(providerStats.combined.month.deepseek.tokens) }}</p>
                  <p class="text-xs text-blue-400">{{ formatCurrency(providerStats.combined.month.deepseek.cost) }}</p>
                </div>
              </div>
              <!-- Total -->
              <div class="pt-2 border-t border-border flex justify-between">
                <span class="text-sm text-text-secondary">Total</span>
                <div class="text-right">
                  <p class="font-bold text-text-primary">{{ formatNumber(providerStats.combined.month.total_tokens) }}</p>
                  <p class="text-sm font-medium text-success">{{ formatCurrency(providerStats.combined.month.total_cost) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- All Time -->
          <div class="bg-bg-secondary rounded-xl p-4 border border-border">
            <div class="flex items-center justify-between mb-4">
              <span class="text-sm font-medium text-text-secondary">Total Historico</span>
            </div>
            <div class="space-y-3">
              <!-- OpenAI -->
              <div class="flex items-center justify-between p-2 bg-green-500/10 rounded-lg">
                <div class="flex items-center gap-2">
                  <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                  <span class="text-sm text-text-primary">OpenAI</span>
                </div>
                <div class="text-right">
                  <p class="text-sm font-medium text-text-primary">{{ formatNumber(providerStats.combined.all_time.openai.tokens) }}</p>
                  <p class="text-xs text-green-400">{{ formatCurrency(providerStats.combined.all_time.openai.cost) }}</p>
                </div>
              </div>
              <!-- DeepSeek -->
              <div class="flex items-center justify-between p-2 bg-blue-500/10 rounded-lg">
                <div class="flex items-center gap-2">
                  <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                  <span class="text-sm text-text-primary">DeepSeek</span>
                </div>
                <div class="text-right">
                  <p class="text-sm font-medium text-text-primary">{{ formatNumber(providerStats.combined.all_time.deepseek.tokens) }}</p>
                  <p class="text-xs text-blue-400">{{ formatCurrency(providerStats.combined.all_time.deepseek.cost) }}</p>
                </div>
              </div>
              <!-- Total -->
              <div class="pt-2 border-t border-border flex justify-between">
                <span class="text-sm text-text-secondary">Total</span>
                <div class="text-right">
                  <p class="font-bold text-text-primary">{{ formatNumber(providerStats.combined.all_time.total_tokens) }}</p>
                  <p class="text-sm font-medium text-success">{{ formatCurrency(providerStats.combined.all_time.total_cost) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Admin vs Users Breakdown -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Users Usage -->
          <div class="bg-bg-secondary rounded-xl p-4 border border-border">
            <h3 class="text-sm font-semibold text-text-primary mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              Uso Usuarios (Este Mes)
            </h3>
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-text-muted">OpenAI</span>
                <span class="text-green-400">{{ formatNumber(providerStats.user_usage?.openai?.month?.total || 0) }} ({{ formatCurrency(providerStats.user_usage?.openai?.month?.estimated_cost || 0) }})</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-text-muted">DeepSeek</span>
                <span class="text-blue-400">{{ formatNumber(providerStats.user_usage?.deepseek?.month?.total || 0) }} ({{ formatCurrency(providerStats.user_usage?.deepseek?.month?.estimated_cost || 0) }})</span>
              </div>
            </div>
          </div>

          <!-- Admin Usage -->
          <div class="bg-bg-secondary rounded-xl p-4 border border-border">
            <h3 class="text-sm font-semibold text-text-primary mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
              </svg>
              Uso Admin/Pruebas (Este Mes)
            </h3>
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-text-muted">OpenAI</span>
                <span class="text-green-400">{{ formatNumber(providerStats.admin_usage?.openai?.month?.total || 0) }} ({{ formatCurrency(providerStats.admin_usage?.openai?.month?.estimated_cost || 0) }})</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-text-muted">DeepSeek</span>
                <span class="text-blue-400">{{ formatNumber(providerStats.admin_usage?.deepseek?.month?.total || 0) }} ({{ formatCurrency(providerStats.admin_usage?.deepseek?.month?.estimated_cost || 0) }})</span>
              </div>
              <div class="pt-2 border-t border-border flex justify-between text-sm">
                <span class="text-text-secondary">Total Admin</span>
                <span class="font-medium text-warning">{{ formatNumber(providerStats.admin_usage?.totals?.month?.total || 0) }} ({{ formatCurrency(providerStats.admin_usage?.totals?.month?.estimated_cost || 0) }})</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Users by Usage This Month -->
      <div v-if="providerStats?.users_breakdown?.length" class="bg-bg-card border border-border rounded-xl p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-text-primary">Top Usuarios por Consumo (Este Mes)</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-bg-secondary">
                <th class="px-4 py-2 text-left text-xs font-semibold text-text-secondary uppercase">Usuario</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-text-secondary uppercase">OpenAI</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-text-secondary uppercase">DeepSeek</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-text-secondary uppercase">Total</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-text-secondary uppercase">Costo</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr v-for="user in providerStats.users_breakdown.slice(0, 10)" :key="user.user_id" class="hover:bg-bg-hover">
                <td class="px-4 py-3">
                  <div>
                    <p class="text-sm font-medium text-text-primary">{{ user.name }}</p>
                    <p class="text-xs text-text-muted">{{ user.email }}</p>
                  </div>
                </td>
                <td class="px-4 py-3 text-right">
                  <span class="text-sm text-green-400">{{ formatNumber(user.openai.total) }}</span>
                </td>
                <td class="px-4 py-3 text-right">
                  <span class="text-sm text-blue-400">{{ formatNumber(user.deepseek.total) }}</span>
                </td>
                <td class="px-4 py-3 text-right">
                  <span class="text-sm font-medium text-text-primary">{{ formatNumber(user.total) }}</span>
                </td>
                <td class="px-4 py-3 text-right">
                  <span class="text-sm font-medium text-success">{{ formatCurrency(user.total_cost) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Main Grid -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <!-- Token Usage Chart - Takes 2 columns -->
        <div class="xl:col-span-2 bg-bg-card border border-border rounded-xl p-5">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h2 class="text-lg font-semibold text-text-primary">Consumo de Tokens</h2>
              <p class="text-sm text-text-secondary">Ultimos 7 dias</p>
            </div>
            <div class="flex items-center gap-2 text-sm">
              <span class="w-3 h-3 bg-brand rounded-full"></span>
              <span class="text-text-secondary">Tokens</span>
            </div>
          </div>

          <!-- Bar Chart -->
          <div class="flex items-end justify-between gap-2 h-48">
            <div
              v-for="day in tokenChart"
              :key="day.date"
              class="flex-1 flex flex-col items-center gap-2"
            >
              <span class="text-xs text-text-secondary">{{ formatNumber(day.total) }}</span>
              <div class="w-full bg-bg-input rounded-t-lg relative overflow-hidden" style="height: 140px;">
                <div
                  class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-brand to-brand/60 rounded-t-lg transition-all duration-500"
                  :style="{ height: getChartBarHeight(day.total) + '%' }"
                ></div>
              </div>
              <span class="text-xs text-text-muted">{{ getDayName(day.date) }}</span>
            </div>
          </div>
        </div>

        <!-- Users by Status -->
        <div class="bg-bg-card border border-border rounded-xl p-5">
          <h2 class="text-lg font-semibold text-text-primary mb-4">Estado de Usuarios</h2>

          <div class="space-y-4">
            <div v-for="(count, status) in stats.users_by_status" :key="status" class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div :class="[
                  'w-3 h-3 rounded-full',
                  status === 'active' ? 'bg-success' : '',
                  status === 'pending' ? 'bg-warning' : '',
                  status === 'inactive' ? 'bg-error' : '',
                  status === 'suspended' ? 'bg-text-muted' : ''
                ]"></div>
                <span class="text-sm text-text-secondary capitalize">{{ status }}</span>
              </div>
              <span class="text-lg font-semibold text-text-primary">{{ count }}</span>
            </div>
          </div>

          <!-- Progress bars -->
          <div class="mt-6 space-y-3">
            <div v-for="(count, status) in stats.users_by_status" :key="status + '-bar'">
              <div class="h-2 bg-bg-input rounded-full overflow-hidden">
                <div
                  :class="[
                    'h-full rounded-full transition-all duration-500',
                    status === 'active' ? 'bg-success' : '',
                    status === 'pending' ? 'bg-warning' : '',
                    status === 'inactive' ? 'bg-error' : '',
                    status === 'suspended' ? 'bg-text-muted' : ''
                  ]"
                  :style="{ width: (count / stats.total_users * 100) + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Users Table -->
      <div class="bg-bg-card border border-border rounded-xl overflow-hidden">
        <div class="p-5 border-b border-border">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-text-primary">Usuarios Recientes</h2>
              <p class="text-sm text-text-secondary">Ultimos usuarios registrados</p>
            </div>
            <router-link to="/admin/users" class="text-sm text-brand hover:text-brand-hover font-medium">
              Ver todos
            </router-link>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-bg-secondary">
                <th class="px-5 py-3 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Usuario</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Plan</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Estado</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Tokens</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-text-secondary uppercase tracking-wider">Registro</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr
                v-for="user in recentUsers"
                :key="user.id"
                class="hover:bg-bg-hover transition-colors"
              >
                <td class="px-5 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-brand/20 flex items-center justify-center text-brand font-semibold text-sm">
                      {{ user.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div>
                      <p class="text-sm font-medium text-text-primary">{{ user.name }}</p>
                      <p class="text-xs text-text-muted">{{ user.email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm text-text-primary">{{ user.plan }}</span>
                </td>
                <td class="px-5 py-4">
                  <span :class="['inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium', getStatusBadge(user.status).class]">
                    {{ getStatusBadge(user.status).label }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm font-medium text-text-primary">{{ formatNumber(user.tokens_balance) }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-sm text-text-muted">{{ formatDate(user.created_at) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
