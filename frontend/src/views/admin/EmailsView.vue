<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '../../services/api'

const stats = ref(null)
const logs = ref([])
const bouncedEmails = ref([])
const isLoading = ref(true)
const isLoadingLogs = ref(false)
const error = ref(null)
const activeTab = ref('overview') // overview, logs, bounced

// Filters for logs
const filters = ref({
  status: '',
  email: '',
  only_issues: false,
})
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
})

async function fetchStats() {
  try {
    const response = await adminApi.getEmailStats()
    stats.value = response.data
  } catch (e) {
    console.error('Error fetching email stats:', e)
    error.value = 'Error al cargar estadísticas'
  }
}

async function fetchLogs(page = 1) {
  isLoadingLogs.value = true
  try {
    const params = {
      page,
      per_page: 20,
      ...filters.value,
    }
    // Remove empty params
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === false) delete params[key]
    })

    const response = await adminApi.getEmailLogs(params)
    logs.value = response.data.logs
    pagination.value = response.data.pagination
  } catch (e) {
    console.error('Error fetching logs:', e)
  } finally {
    isLoadingLogs.value = false
  }
}

async function fetchBouncedEmails() {
  try {
    const response = await adminApi.getBouncedEmails()
    bouncedEmails.value = response.data.bounced_emails
  } catch (e) {
    console.error('Error fetching bounced emails:', e)
  }
}

async function resendEmail(id) {
  if (!confirm('¿Reenviar este correo?')) return
  try {
    await adminApi.resendEmail(id)
    alert('Correo marcado para reenvío')
    fetchLogs(pagination.value.current_page)
  } catch (e) {
    alert('Error al reenviar correo')
  }
}

function changeTab(tab) {
  activeTab.value = tab
  if (tab === 'logs' && logs.value.length === 0) {
    fetchLogs()
  } else if (tab === 'bounced' && bouncedEmails.value.length === 0) {
    fetchBouncedEmails()
  }
}

function applyFilters() {
  fetchLogs(1)
}

function clearFilters() {
  filters.value = { status: '', email: '', only_issues: false }
  fetchLogs(1)
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

function formatNumber(num) {
  return num?.toLocaleString('es-ES') || '0'
}

function getStatusColor(status) {
  const colors = {
    pending: 'bg-yellow-500/20 text-yellow-400',
    sent: 'bg-blue-500/20 text-blue-400',
    delivered: 'bg-green-500/20 text-green-400',
    bounced: 'bg-red-500/20 text-red-400',
    failed: 'bg-red-500/20 text-red-400',
    complained: 'bg-orange-500/20 text-orange-400',
  }
  return colors[status] || 'bg-gray-500/20 text-gray-400'
}

const statusLabels = {
  pending: 'Pendiente',
  sent: 'Enviado',
  delivered: 'Entregado',
  bounced: 'Rebotado',
  failed: 'Fallido',
  complained: 'Queja',
}

onMounted(async () => {
  isLoading.value = true
  await fetchStats()
  isLoading.value = false
})
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold text-gatales-text mb-6">Monitoreo de Correos</h1>

    <!-- Loading -->
    <div v-if="isLoading" class="text-gatales-text-secondary">Cargando...</div>

    <!-- Error -->
    <div v-else-if="error" class="text-red-400">{{ error }}</div>

    <template v-else>
      <!-- Tabs -->
      <div class="flex gap-2 mb-6 border-b border-gatales-border">
        <button
          @click="changeTab('overview')"
          :class="[
            'px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px',
            activeTab === 'overview'
              ? 'text-gatales-accent border-gatales-accent'
              : 'text-gatales-text-secondary border-transparent hover:text-gatales-text'
          ]"
        >
          Resumen
        </button>
        <button
          @click="changeTab('logs')"
          :class="[
            'px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px',
            activeTab === 'logs'
              ? 'text-gatales-accent border-gatales-accent'
              : 'text-gatales-text-secondary border-transparent hover:text-gatales-text'
          ]"
        >
          Historial
        </button>
        <button
          @click="changeTab('bounced')"
          :class="[
            'px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px flex items-center gap-2',
            activeTab === 'bounced'
              ? 'text-gatales-accent border-gatales-accent'
              : 'text-gatales-text-secondary border-transparent hover:text-gatales-text'
          ]"
        >
          Rebotados
          <span v-if="stats?.recent_bounces > 0" class="px-1.5 py-0.5 text-xs bg-red-500/20 text-red-400 rounded-full">
            {{ stats.recent_bounces }}
          </span>
        </button>
      </div>

      <!-- Overview Tab -->
      <div v-if="activeTab === 'overview'" class="space-y-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="card">
            <p class="text-sm text-gatales-text-secondary">Total (30 días)</p>
            <p class="text-2xl font-bold text-gatales-text">{{ formatNumber(stats?.stats?.total || 0) }}</p>
          </div>
          <div class="card">
            <p class="text-sm text-gatales-text-secondary">Entregados</p>
            <p class="text-2xl font-bold text-green-400">{{ stats?.stats?.delivery_rate || 0 }}%</p>
          </div>
          <div class="card">
            <p class="text-sm text-gatales-text-secondary">Tasa de Rebote</p>
            <p class="text-2xl font-bold text-red-400">{{ stats?.stats?.bounce_rate || 0 }}%</p>
          </div>
          <div class="card">
            <p class="text-sm text-gatales-text-secondary">Rebotes (7 días)</p>
            <p class="text-2xl font-bold text-red-400">{{ formatNumber(stats?.recent_bounces || 0) }}</p>
          </div>
        </div>

        <!-- Status Breakdown -->
        <div class="card">
          <h2 class="text-lg font-semibold text-gatales-text mb-4">Desglose por Estado</h2>
          <div class="grid grid-cols-3 md:grid-cols-6 gap-4">
            <div v-for="(count, status) in stats?.stats?.by_status" :key="status" class="text-center">
              <p :class="['text-2xl font-bold', getStatusColor(status).split(' ')[1]]">{{ formatNumber(count) }}</p>
              <p class="text-xs text-gatales-text-secondary capitalize">{{ statusLabels[status] || status }}</p>
            </div>
          </div>
        </div>

        <!-- Daily Chart (simple table) -->
        <div class="card" v-if="stats?.daily?.length > 0">
          <h2 class="text-lg font-semibold text-gatales-text mb-4">Últimos 7 Días</h2>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gatales-border text-left">
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Fecha</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium text-right">Total</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium text-right">Entregados</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium text-right">Rebotados</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium text-right">Fallidos</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="day in stats.daily"
                  :key="day.date"
                  class="border-b border-gatales-border/50 hover:bg-gatales-input/50"
                >
                  <td class="py-2 px-3 text-gatales-text">{{ day.date }}</td>
                  <td class="py-2 px-3 text-gatales-text text-right">{{ day.total || 0 }}</td>
                  <td class="py-2 px-3 text-green-400 text-right">{{ day.delivered || 0 }}</td>
                  <td class="py-2 px-3 text-red-400 text-right">{{ day.bounced || 0 }}</td>
                  <td class="py-2 px-3 text-red-400 text-right">{{ day.failed || 0 }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Logs Tab -->
      <div v-if="activeTab === 'logs'" class="space-y-4">
        <!-- Filters -->
        <div class="card">
          <div class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[200px]">
              <label class="block text-sm text-gatales-text-secondary mb-1">Correo</label>
              <input
                v-model="filters.email"
                type="text"
                placeholder="Buscar por email..."
                class="input-field"
              />
            </div>
            <div class="w-40">
              <label class="block text-sm text-gatales-text-secondary mb-1">Estado</label>
              <select v-model="filters.status" class="input-field">
                <option value="">Todos</option>
                <option value="pending">Pendiente</option>
                <option value="sent">Enviado</option>
                <option value="delivered">Entregado</option>
                <option value="bounced">Rebotado</option>
                <option value="failed">Fallido</option>
              </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-gatales-text-secondary">
              <input type="checkbox" v-model="filters.only_issues" class="rounded" />
              Solo problemas
            </label>
            <div class="flex gap-2">
              <button @click="applyFilters" class="btn-primary text-sm">Filtrar</button>
              <button @click="clearFilters" class="btn-secondary text-sm">Limpiar</button>
            </div>
          </div>
        </div>

        <!-- Logs Table -->
        <div class="card">
          <div v-if="isLoadingLogs" class="text-gatales-text-secondary text-center py-8">
            Cargando...
          </div>
          <div v-else-if="logs.length === 0" class="text-gatales-text-secondary text-center py-8">
            No hay registros
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gatales-border text-left">
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Fecha</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Destinatario</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Asunto</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Tipo</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Estado</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Error</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium"></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="log in logs"
                  :key="log.id"
                  class="border-b border-gatales-border/50 hover:bg-gatales-input/50"
                >
                  <td class="py-2 px-3 text-gatales-text text-xs whitespace-nowrap">
                    {{ formatDate(log.created_at) }}
                  </td>
                  <td class="py-2 px-3 text-gatales-text">
                    <div>{{ log.to_email }}</div>
                    <div v-if="log.to_name" class="text-xs text-gatales-text-secondary">{{ log.to_name }}</div>
                  </td>
                  <td class="py-2 px-3 text-gatales-text max-w-xs truncate" :title="log.subject">
                    {{ log.subject }}
                  </td>
                  <td class="py-2 px-3 text-gatales-text-secondary text-xs capitalize">
                    {{ log.type }}
                  </td>
                  <td class="py-2 px-3">
                    <span :class="['px-2 py-1 rounded text-xs', getStatusColor(log.status)]">
                      {{ statusLabels[log.status] || log.status }}
                    </span>
                  </td>
                  <td class="py-2 px-3 text-red-400 text-xs max-w-xs truncate" :title="log.error_message">
                    {{ log.error_message || '-' }}
                  </td>
                  <td class="py-2 px-3">
                    <button
                      v-if="['bounced', 'failed'].includes(log.status)"
                      @click="resendEmail(log.id)"
                      class="text-xs text-gatales-accent hover:underline"
                    >
                      Reenviar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="pagination.last_page > 1" class="flex justify-center gap-2 mt-4">
            <button
              @click="fetchLogs(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="btn-secondary text-sm disabled:opacity-50"
            >
              Anterior
            </button>
            <span class="px-4 py-2 text-sm text-gatales-text-secondary">
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </span>
            <button
              @click="fetchLogs(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.last_page"
              class="btn-secondary text-sm disabled:opacity-50"
            >
              Siguiente
            </button>
          </div>
        </div>
      </div>

      <!-- Bounced Tab -->
      <div v-if="activeTab === 'bounced'" class="space-y-4">
        <div class="card">
          <h2 class="text-lg font-semibold text-gatales-text mb-4">Correos con Problemas de Entrega</h2>
          <p class="text-sm text-gatales-text-secondary mb-4">
            Lista de direcciones de correo que han rebotado o fallado. Considera eliminar estas direcciones de tus listas.
          </p>

          <div v-if="bouncedEmails.length === 0" class="text-gatales-text-secondary text-center py-8">
            No hay correos rebotados
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gatales-border text-left">
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Correo</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Nombre</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium text-center">Rebotes</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Tipo</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Último rebote</th>
                  <th class="py-2 px-3 text-gatales-text-secondary font-medium">Error</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="email in bouncedEmails"
                  :key="email.to_email"
                  class="border-b border-gatales-border/50 hover:bg-gatales-input/50"
                >
                  <td class="py-2 px-3 text-gatales-text font-medium">{{ email.to_email }}</td>
                  <td class="py-2 px-3 text-gatales-text-secondary">{{ email.to_name || '-' }}</td>
                  <td class="py-2 px-3 text-red-400 text-center font-bold">{{ email.bounce_count }}</td>
                  <td class="py-2 px-3">
                    <span :class="[
                      'px-2 py-1 rounded text-xs',
                      email.bounce_type === 'hard' ? 'bg-red-500/20 text-red-400' : 'bg-yellow-500/20 text-yellow-400'
                    ]">
                      {{ email.bounce_type || 'unknown' }}
                    </span>
                  </td>
                  <td class="py-2 px-3 text-gatales-text-secondary text-xs">
                    {{ formatDate(email.last_bounce) }}
                  </td>
                  <td class="py-2 px-3 text-red-400 text-xs max-w-xs truncate" :title="email.error_message">
                    {{ email.error_message || '-' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
