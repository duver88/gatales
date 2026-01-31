<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '../../services/api'

const stats = ref(null)
const tokenChart = ref([])
const recentUsers = ref([])
const openaiStats = ref(null)
const isLoading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    const [dashboardRes, openaiRes] = await Promise.all([
      adminApi.getDashboard(),
      adminApi.getOpenAIStats()
    ])
    stats.value = dashboardRes.data.stats
    tokenChart.value = dashboardRes.data.token_usage_chart
    recentUsers.value = dashboardRes.data.recent_users
    openaiStats.value = openaiRes.data.openai_usage
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
  const days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']
  return days[date.getDay()]
}
</script>

<template>
  <div class="p-4 sm:p-6 lg:p-8">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-text-primary">Dashboard</h1>
      <p class="text-sm text-text-secondary mt-1">Bienvenido al panel de administración de El Cursales</p>
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
      <!-- Stats Cards - TailAdmin Style -->
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

      <!-- Main Grid -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        <!-- Token Usage Chart - Takes 2 columns -->
        <div class="xl:col-span-2 bg-bg-card border border-border rounded-xl p-5">
          <div class="flex items-center justify-between mb-6">
            <div>
              <h2 class="text-lg font-semibold text-text-primary">Consumo de Tokens</h2>
              <p class="text-sm text-text-secondary">Últimos 7 días</p>
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

      <!-- OpenAI Stats -->
      <div v-if="openaiStats" class="bg-bg-card border border-border rounded-xl p-5 mb-6">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-success" viewBox="0 0 24 24" fill="currentColor">
              <path d="M22.2819 9.8211a5.9847 5.9847 0 0 0-.5157-4.9108 6.0462 6.0462 0 0 0-6.5098-2.9A6.0651 6.0651 0 0 0 4.9807 4.1818a5.9847 5.9847 0 0 0-3.9977 2.9 6.0462 6.0462 0 0 0 .7427 7.0966 5.98 5.98 0 0 0 .511 4.9107 6.051 6.051 0 0 0 6.5146 2.9001A5.9847 5.9847 0 0 0 13.2599 24a6.0557 6.0557 0 0 0 5.7718-4.2058 5.9894 5.9894 0 0 0 3.9977-2.9001 6.0557 6.0557 0 0 0-.7475-7.0729zm-9.022 12.6081a4.4755 4.4755 0 0 1-2.8764-1.0408l.1419-.0804 4.7783-2.7582a.7948.7948 0 0 0 .3927-.6813v-6.7369l2.02 1.1686a.071.071 0 0 1 .038.052v5.5826a4.504 4.504 0 0 1-4.4945 4.4944zm-9.6607-4.1254a4.4708 4.4708 0 0 1-.5346-3.0137l.142.0852 4.783 2.7582a.7712.7712 0 0 0 .7806 0l5.8428-3.3685v2.3324a.0804.0804 0 0 1-.0332.0615L9.74 19.9502a4.4992 4.4992 0 0 1-6.1408-1.6464zM2.3408 7.8956a4.485 4.485 0 0 1 2.3655-1.9728V11.6a.7664.7664 0 0 0 .3879.6765l5.8144 3.3543-2.0201 1.1685a.0757.0757 0 0 1-.071 0l-4.8303-2.7865A4.504 4.504 0 0 1 2.3408 7.8956zm16.5963 3.8558L13.1038 8.364 15.1192 7.2a.0757.0757 0 0 1 .071 0l4.8303 2.7913a4.4944 4.4944 0 0 1-.6765 8.1042v-5.6772a.79.79 0 0 0-.407-.667zm2.0107-3.0231l-.142-.0852-4.7735-2.7818a.7759.7759 0 0 0-.7854 0L9.409 9.2297V6.8974a.0662.0662 0 0 1 .0284-.0615l4.8303-2.7866a4.4992 4.4992 0 0 1 6.6802 4.66zM8.3065 12.863l-2.02-1.1638a.0804.0804 0 0 1-.038-.0567V6.0742a4.4992 4.4992 0 0 1 7.3757-3.4537l-.142.0805L8.704 5.459a.7948.7948 0 0 0-.3927.6813zm1.0976-2.3654l2.602-1.4998 2.6069 1.4998v2.9994l-2.5974 1.4997-2.6067-1.4997Z"/>
            </svg>
          </div>
          <div>
            <h2 class="text-lg font-semibold text-text-primary">Uso de OpenAI</h2>
            <p class="text-sm text-text-secondary">Modelo: {{ openaiStats.pricing_info.model }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Today -->
          <div class="bg-bg-secondary rounded-xl p-4 border border-border">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-text-secondary">Hoy</span>
              <span class="text-xs px-2 py-1 bg-brand/10 text-brand rounded-full">En vivo</span>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-text-muted">Input</span>
                <span class="text-text-primary font-medium">{{ formatNumber(openaiStats.today.tokens_input) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-text-muted">Output</span>
                <span class="text-text-primary font-medium">{{ formatNumber(openaiStats.today.tokens_output) }}</span>
              </div>
              <div class="pt-2 border-t border-border flex justify-between">
                <span class="text-sm text-text-secondary">Costo</span>
                <span class="text-lg font-bold text-success">{{ formatCurrency(openaiStats.today.estimated_cost) }}</span>
              </div>
            </div>
          </div>

          <!-- This Month -->
          <div class="bg-bg-secondary rounded-xl p-4 border border-border">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-text-secondary">Este Mes</span>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-text-muted">Input</span>
                <span class="text-text-primary font-medium">{{ formatNumber(openaiStats.month.tokens_input) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-text-muted">Output</span>
                <span class="text-text-primary font-medium">{{ formatNumber(openaiStats.month.tokens_output) }}</span>
              </div>
              <div class="pt-2 border-t border-border flex justify-between">
                <span class="text-sm text-text-secondary">Costo</span>
                <span class="text-lg font-bold text-success">{{ formatCurrency(openaiStats.month.estimated_cost) }}</span>
              </div>
            </div>
          </div>

          <!-- All Time -->
          <div class="bg-bg-secondary rounded-xl p-4 border border-border">
            <div class="flex items-center justify-between mb-3">
              <span class="text-sm font-medium text-text-secondary">Total Histórico</span>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-text-muted">Input</span>
                <span class="text-text-primary font-medium">{{ formatNumber(openaiStats.all_time.tokens_input) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-text-muted">Output</span>
                <span class="text-text-primary font-medium">{{ formatNumber(openaiStats.all_time.tokens_output) }}</span>
              </div>
              <div class="pt-2 border-t border-border flex justify-between">
                <span class="text-sm text-text-secondary">Costo</span>
                <span class="text-lg font-bold text-success">{{ formatCurrency(openaiStats.all_time.estimated_cost) }}</span>
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
              <p class="text-sm text-text-secondary">Últimos usuarios registrados</p>
            </div>
            <router-link to="/admin/users" class="text-sm text-brand hover:text-brand-hover font-medium">
              Ver todos →
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
