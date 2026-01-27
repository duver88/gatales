<script setup>
import { ref, onMounted, watch } from 'vue'
import { adminApi } from '../../services/api'

const logs = ref([])
const pagination = ref({})
const isLoading = ref(true)
const sourceFilter = ref('')
const processedFilter = ref('')
const currentPage = ref(1)
const selectedLog = ref(null)

async function fetchLogs() {
  isLoading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: 20,
    }
    if (sourceFilter.value) params.source = sourceFilter.value
    if (processedFilter.value !== '') params.processed = processedFilter.value === 'true'

    const response = await adminApi.getWebhookLogs(params)
    logs.value = response.data.logs
    pagination.value = response.data.pagination
  } catch (e) {
    console.error('Error fetching logs:', e)
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchLogs)

watch([sourceFilter, processedFilter], () => {
  currentPage.value = 1
  fetchLogs()
})

watch(currentPage, fetchLogs)

async function viewLog(id) {
  try {
    const response = await adminApi.getWebhookLog(id)
    selectedLog.value = response.data.log
  } catch (e) {
    alert('Error al cargar el detalle')
  }
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleString('es-ES')
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold text-gatales-text mb-6">Webhook Logs</h1>

    <!-- Filters -->
    <div class="flex flex-col md:flex-row gap-4 mb-6">
      <select v-model="sourceFilter" class="input-field md:w-40">
        <option value="">Todas las fuentes</option>
        <option value="n8n">n8n</option>
        <option value="hotmart">Hotmart</option>
      </select>
      <select v-model="processedFilter" class="input-field md:w-40">
        <option value="">Todos</option>
        <option value="true">Procesados</option>
        <option value="false">Con errores</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="text-gatales-text-secondary">Cargando...</div>

    <!-- Logs Table -->
    <div v-else class="card overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="text-left text-sm text-gatales-text-secondary border-b border-gatales-border">
            <th class="pb-3 pr-4">Fecha</th>
            <th class="pb-3 pr-4">Fuente</th>
            <th class="pb-3 pr-4">Evento</th>
            <th class="pb-3 pr-4">Estado</th>
            <th class="pb-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="log in logs"
            :key="log.id"
            class="border-b border-gatales-border/50"
          >
            <td class="py-3 pr-4 text-sm text-gatales-text-secondary">
              {{ formatDate(log.created_at) }}
            </td>
            <td class="py-3 pr-4 text-sm text-gatales-text">
              {{ log.source }}
            </td>
            <td class="py-3 pr-4 text-sm text-gatales-text">
              {{ log.event_type }}
            </td>
            <td class="py-3 pr-4">
              <span
                :class="[
                  'px-2 py-1 rounded text-xs',
                  log.processed ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
                ]"
              >
                {{ log.processed ? 'OK' : 'Error' }}
              </span>
            </td>
            <td class="py-3">
              <button
                @click="viewLog(log.id)"
                class="text-gatales-accent hover:underline text-sm"
              >
                Ver detalle
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Empty state -->
      <div v-if="logs.length === 0" class="text-center py-8 text-gatales-text-secondary">
        No se encontraron logs
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

    <!-- Log Detail Modal -->
    <div v-if="selectedLog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="card w-full max-w-2xl max-h-[80vh] overflow-auto">
        <div class="flex justify-between items-start mb-4">
          <h3 class="text-lg font-semibold text-gatales-text">Detalle del Webhook</h3>
          <button @click="selectedLog = null" class="text-gatales-text-secondary hover:text-gatales-text">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="space-y-3">
          <div class="flex gap-4">
            <div>
              <span class="text-sm text-gatales-text-secondary">Fuente:</span>
              <span class="ml-2 text-gatales-text">{{ selectedLog.source }}</span>
            </div>
            <div>
              <span class="text-sm text-gatales-text-secondary">Evento:</span>
              <span class="ml-2 text-gatales-text">{{ selectedLog.event_type }}</span>
            </div>
          </div>

          <div>
            <span class="text-sm text-gatales-text-secondary">Fecha:</span>
            <span class="ml-2 text-gatales-text">{{ formatDate(selectedLog.created_at) }}</span>
          </div>

          <div>
            <span class="text-sm text-gatales-text-secondary">Estado:</span>
            <span
              :class="[
                'ml-2 px-2 py-1 rounded text-xs',
                selectedLog.processed ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
              ]"
            >
              {{ selectedLog.processed ? 'Procesado correctamente' : 'Error' }}
            </span>
          </div>

          <div v-if="selectedLog.error">
            <p class="text-sm text-gatales-text-secondary mb-1">Error:</p>
            <p class="text-red-400 text-sm bg-red-500/10 p-3 rounded">{{ selectedLog.error }}</p>
          </div>

          <div>
            <p class="text-sm text-gatales-text-secondary mb-1">Payload:</p>
            <pre class="bg-gatales-input p-3 rounded text-sm text-gatales-text overflow-x-auto">{{ JSON.stringify(selectedLog.payload, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
