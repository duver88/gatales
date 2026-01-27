<script setup>
import { ref, onMounted } from 'vue'
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

function formatNumber(num) {
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M'
  if (num >= 1000) return (num / 1000).toFixed(1) + 'K'
  return num.toLocaleString('es-ES')
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('es-ES', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
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

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
  }).format(amount)
}
</script>

<template>
  <div class="p-4 sm:p-6">
    <h1 class="text-xl sm:text-2xl font-bold text-gatales-text mb-4 sm:mb-6">Dashboard</h1>

    <!-- Loading -->
    <div v-if="isLoading" class="text-gatales-text-secondary">Cargando...</div>

    <!-- Error -->
    <div v-else-if="error" class="text-red-400">{{ error }}</div>

    <template v-else>
      <!-- Stats Grid -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
        <div class="card !p-3 sm:!p-4">
          <p class="text-xs sm:text-sm text-gatales-text-secondary mb-1">Total Usuarios</p>
          <p class="text-xl sm:text-2xl font-bold text-gatales-text">{{ formatNumber(stats.total_users) }}</p>
        </div>
        <div class="card !p-3 sm:!p-4">
          <p class="text-xs sm:text-sm text-gatales-text-secondary mb-1">Activos Hoy</p>
          <p class="text-xl sm:text-2xl font-bold text-gatales-accent">{{ formatNumber(stats.active_users_today) }}</p>
        </div>
        <div class="card !p-3 sm:!p-4">
          <p class="text-xs sm:text-sm text-gatales-text-secondary mb-1">Tokens Hoy</p>
          <p class="text-xl sm:text-2xl font-bold text-gatales-text">{{ formatNumber(stats.tokens_consumed_today) }}</p>
        </div>
        <div class="card !p-3 sm:!p-4">
          <p class="text-xs sm:text-sm text-gatales-text-secondary mb-1">Tokens Este Mes</p>
          <p class="text-xl sm:text-2xl font-bold text-gatales-text">{{ formatNumber(stats.tokens_consumed_month) }}</p>
        </div>
      </div>

      <!-- Users by Status -->
      <div class="card !p-4 sm:!p-6 mb-6 sm:mb-8">
        <h2 class="text-base sm:text-lg font-semibold text-gatales-text mb-3 sm:mb-4">Usuarios por Estado</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
          <div v-for="(count, status) in stats.users_by_status" :key="status">
            <p class="text-xs sm:text-sm text-gatales-text-secondary capitalize">{{ status }}</p>
            <p class="text-lg sm:text-xl font-bold text-gatales-text">{{ count }}</p>
          </div>
        </div>
      </div>

      <!-- OpenAI Usage Stats -->
      <div v-if="openaiStats" class="card !p-4 sm:!p-6 mb-6 sm:mb-8">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-500" viewBox="0 0 24 24" fill="currentColor">
            <path d="M22.2819 9.8211a5.9847 5.9847 0 0 0-.5157-4.9108 6.0462 6.0462 0 0 0-6.5098-2.9A6.0651 6.0651 0 0 0 4.9807 4.1818a5.9847 5.9847 0 0 0-3.9977 2.9 6.0462 6.0462 0 0 0 .7427 7.0966 5.98 5.98 0 0 0 .511 4.9107 6.051 6.051 0 0 0 6.5146 2.9001A5.9847 5.9847 0 0 0 13.2599 24a6.0557 6.0557 0 0 0 5.7718-4.2058 5.9894 5.9894 0 0 0 3.9977-2.9001 6.0557 6.0557 0 0 0-.7475-7.0729zm-9.022 12.6081a4.4755 4.4755 0 0 1-2.8764-1.0408l.1419-.0804 4.7783-2.7582a.7948.7948 0 0 0 .3927-.6813v-6.7369l2.02 1.1686a.071.071 0 0 1 .038.052v5.5826a4.504 4.504 0 0 1-4.4945 4.4944zm-9.6607-4.1254a4.4708 4.4708 0 0 1-.5346-3.0137l.142.0852 4.783 2.7582a.7712.7712 0 0 0 .7806 0l5.8428-3.3685v2.3324a.0804.0804 0 0 1-.0332.0615L9.74 19.9502a4.4992 4.4992 0 0 1-6.1408-1.6464zM2.3408 7.8956a4.485 4.485 0 0 1 2.3655-1.9728V11.6a.7664.7664 0 0 0 .3879.6765l5.8144 3.3543-2.0201 1.1685a.0757.0757 0 0 1-.071 0l-4.8303-2.7865A4.504 4.504 0 0 1 2.3408 7.8956zm16.5963 3.8558L13.1038 8.364 15.1192 7.2a.0757.0757 0 0 1 .071 0l4.8303 2.7913a4.4944 4.4944 0 0 1-.6765 8.1042v-5.6772a.79.79 0 0 0-.407-.667zm2.0107-3.0231l-.142-.0852-4.7735-2.7818a.7759.7759 0 0 0-.7854 0L9.409 9.2297V6.8974a.0662.0662 0 0 1 .0284-.0615l4.8303-2.7866a4.4992 4.4992 0 0 1 6.6802 4.66zM8.3065 12.863l-2.02-1.1638a.0804.0804 0 0 1-.038-.0567V6.0742a4.4992 4.4992 0 0 1 7.3757-3.4537l-.142.0805L8.704 5.459a.7948.7948 0 0 0-.3927.6813zm1.0976-2.3654l2.602-1.4998 2.6069 1.4998v2.9994l-2.5974 1.4997-2.6067-1.4997Z"/>
          </svg>
          <h2 class="text-base sm:text-lg font-semibold text-gatales-text">Uso de OpenAI</h2>
        </div>

        <!-- Usage Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <!-- Today -->
          <div class="bg-gatales-input rounded-lg p-4">
            <p class="text-xs text-gatales-text-secondary mb-2">Hoy</p>
            <div class="space-y-1">
              <div class="flex justify-between">
                <span class="text-xs text-gatales-text-secondary">Input:</span>
                <span class="text-sm text-gatales-text">{{ formatNumber(openaiStats.today.tokens_input) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-xs text-gatales-text-secondary">Output:</span>
                <span class="text-sm text-gatales-text">{{ formatNumber(openaiStats.today.tokens_output) }}</span>
              </div>
              <div class="flex justify-between border-t border-gatales-border pt-1 mt-1">
                <span class="text-xs text-gatales-text-secondary">Total:</span>
                <span class="text-sm font-semibold text-gatales-text">{{ formatNumber(openaiStats.today.total) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-xs text-gatales-text-secondary">Costo est.:</span>
                <span class="text-sm font-semibold text-green-400">{{ formatCurrency(openaiStats.today.estimated_cost) }}</span>
              </div>
            </div>
          </div>

          <!-- This Month -->
          <div class="bg-gatales-input rounded-lg p-4">
            <p class="text-xs text-gatales-text-secondary mb-2">Este Mes</p>
            <div class="space-y-1">
              <div class="flex justify-between">
                <span class="text-xs text-gatales-text-secondary">Input:</span>
                <span class="text-sm text-gatales-text">{{ formatNumber(openaiStats.month.tokens_input) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-xs text-gatales-text-secondary">Output:</span>
                <span class="text-sm text-gatales-text">{{ formatNumber(openaiStats.month.tokens_output) }}</span>
              </div>
              <div class="flex justify-between border-t border-gatales-border pt-1 mt-1">
                <span class="text-xs text-gatales-text-secondary">Total:</span>
                <span class="text-sm font-semibold text-gatales-text">{{ formatNumber(openaiStats.month.total) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-xs text-gatales-text-secondary">Costo est.:</span>
                <span class="text-sm font-semibold text-green-400">{{ formatCurrency(openaiStats.month.estimated_cost) }}</span>
              </div>
            </div>
          </div>

          <!-- All Time -->
          <div class="bg-gatales-input rounded-lg p-4">
            <p class="text-xs text-gatales-text-secondary mb-2">Total Historico</p>
            <div class="space-y-1">
              <div class="flex justify-between">
                <span class="text-xs text-gatales-text-secondary">Input:</span>
                <span class="text-sm text-gatales-text">{{ formatNumber(openaiStats.all_time.tokens_input) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-xs text-gatales-text-secondary">Output:</span>
                <span class="text-sm text-gatales-text">{{ formatNumber(openaiStats.all_time.tokens_output) }}</span>
              </div>
              <div class="flex justify-between border-t border-gatales-border pt-1 mt-1">
                <span class="text-xs text-gatales-text-secondary">Total:</span>
                <span class="text-sm font-semibold text-gatales-text">{{ formatNumber(openaiStats.all_time.total) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-xs text-gatales-text-secondary">Costo est.:</span>
                <span class="text-sm font-semibold text-green-400">{{ formatCurrency(openaiStats.all_time.estimated_cost) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Pricing Info -->
        <div class="text-xs text-gatales-text-secondary bg-gatales-bg rounded-lg p-3">
          <p class="mb-1">
            <span class="font-medium text-gatales-text">Modelo:</span> {{ openaiStats.pricing_info.model }}
          </p>
          <p>
            <span class="font-medium text-gatales-text">Precios:</span>
            ${{ openaiStats.pricing_info.input_per_1m }}/1M tokens input |
            ${{ openaiStats.pricing_info.output_per_1m }}/1M tokens output
          </p>
        </div>
      </div>

      <!-- Token Usage Chart -->
      <div class="card !p-4 sm:!p-6 mb-6 sm:mb-8">
        <h2 class="text-base sm:text-lg font-semibold text-gatales-text mb-3 sm:mb-4">Consumo de Tokens (Ultimos 7 dias)</h2>
        <div class="space-y-2">
          <div
            v-for="day in tokenChart"
            :key="day.date"
            class="flex items-center gap-2 sm:gap-4"
          >
            <span class="text-xs sm:text-sm text-gatales-text-secondary w-16 sm:w-24 shrink-0">
              {{ new Date(day.date).toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric' }) }}
            </span>
            <div class="flex-1 h-5 sm:h-6 bg-gatales-input rounded overflow-hidden">
              <div
                class="h-full bg-gatales-accent"
                :style="{ width: Math.min((day.total / Math.max(...tokenChart.map(d => d.total))) * 100, 100) + '%' }"
              />
            </div>
            <span class="text-xs sm:text-sm text-gatales-text w-14 sm:w-20 text-right shrink-0">{{ formatNumber(day.total) }}</span>
          </div>
        </div>
      </div>

      <!-- Recent Users -->
      <div class="card !p-4 sm:!p-6">
        <h2 class="text-base sm:text-lg font-semibold text-gatales-text mb-3 sm:mb-4">Usuarios Recientes</h2>
        <div class="overflow-x-auto -mx-4 sm:mx-0">
          <table class="w-full min-w-[500px]">
            <thead>
              <tr class="text-left text-xs sm:text-sm text-gatales-text-secondary border-b border-gatales-border">
                <th class="pb-3 pr-3 sm:pr-4 pl-4 sm:pl-0">Usuario</th>
                <th class="pb-3 pr-3 sm:pr-4">Plan</th>
                <th class="pb-3 pr-3 sm:pr-4">Estado</th>
                <th class="pb-3 pr-3 sm:pr-4">Tokens</th>
                <th class="pb-3 pr-4 sm:pr-0">Fecha</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="user in recentUsers"
                :key="user.id"
                class="border-b border-gatales-border/50"
              >
                <td class="py-2.5 sm:py-3 pr-3 sm:pr-4 pl-4 sm:pl-0">
                  <p class="text-xs sm:text-sm font-medium text-gatales-text">{{ user.name }}</p>
                  <p class="text-[10px] sm:text-xs text-gatales-text-secondary">{{ user.email }}</p>
                </td>
                <td class="py-2.5 sm:py-3 pr-3 sm:pr-4 text-xs sm:text-sm text-gatales-text">{{ user.plan }}</td>
                <td class="py-2.5 sm:py-3 pr-3 sm:pr-4">
                  <span :class="['px-1.5 sm:px-2 py-0.5 sm:py-1 rounded text-[10px] sm:text-xs', getStatusClass(user.status)]">
                    {{ user.status }}
                  </span>
                </td>
                <td class="py-2.5 sm:py-3 pr-3 sm:pr-4 text-xs sm:text-sm text-gatales-text">{{ formatNumber(user.tokens_balance) }}</td>
                <td class="py-2.5 sm:py-3 pr-4 sm:pr-0 text-xs sm:text-sm text-gatales-text-secondary whitespace-nowrap">{{ formatDate(user.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
