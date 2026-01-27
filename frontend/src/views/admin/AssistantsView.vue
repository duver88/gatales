<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '../../services/api'

const assistants = ref([])
const availableModels = ref({})
const isLoading = ref(true)
const error = ref(null)
const success = ref(null)

// Modal state
const showModal = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const selectedAssistant = ref(null)

// Test state
const isTesting = ref(false)
const testMessage = ref('Hola, como estas?')
const testResponse = ref(null)
const testingAssistantId = ref(null)

// Knowledge Base / Files modal state
const showFilesModal = ref(false)
const filesAssistant = ref(null)
const files = ref([])
const filesStats = ref(null)
const supportedTypes = ref({})
const maxFileSizeMB = ref(512)
const isLoadingFiles = ref(false)
const isUploading = ref(false)
const isTogglingKB = ref(false)
const isDeletingFile = ref(null)

// Form data
const defaultFormData = {
  name: '',
  description: '',
  is_active: true,
  model: 'gpt-4o-mini',
  system_prompt: 'Eres un asistente de IA util y amigable.',
  temperature: 0.7,
  max_tokens: 2000,
  top_p: 1,
  frequency_penalty: 0,
  presence_penalty: 0,
  response_format: 'text',
  stop_sequences: '',
  seed: null,
  n_completions: 1,
  logprobs: false,
  stream: false,
  context_messages: 10,
  filter_unsafe_content: true,
  include_user_id: true,
  assistant_display_name: '',
  welcome_message: 'Hola! Como puedo ayudarte hoy?',
  use_knowledge_base: false,
}
const formData = ref({ ...defaultFormData })

// Track which advanced section is expanded
const showAdvanced = ref(false)

// Computed to check if selected model doesn't support custom temperature (o1 and GPT-5)
const noTemperatureSupport = computed(() => {
  const model = formData.value.model || ''
  return model.startsWith('o1') || model.startsWith('gpt-5')
})

// Computed to check if model uses new API format (GPT-5, o1)
const isNewModel = computed(() => {
  const model = formData.value.model || ''
  return model.startsWith('gpt-5') || model.startsWith('o1')
})

// Computed for formatted total size
const formattedTotalSize = computed(() => {
  if (!filesStats.value) return '0 bytes'
  const bytes = filesStats.value.total_size
  if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB'
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB'
  if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB'
  return bytes + ' bytes'
})

onMounted(async () => {
  await fetchAssistants()
})

async function fetchAssistants() {
  isLoading.value = true
  error.value = null
  try {
    const response = await adminApi.getAssistants()
    assistants.value = response.data.assistants
    availableModels.value = response.data.available_models || {}
  } catch (e) {
    error.value = 'Error al cargar los asistentes'
  } finally {
    isLoading.value = false
  }
}

function openCreateModal() {
  formData.value = { ...defaultFormData }
  isEditing.value = false
  selectedAssistant.value = null
  showModal.value = true
}

function openEditModal(assistant) {
  formData.value = { ...assistant }
  isEditing.value = true
  selectedAssistant.value = assistant
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  selectedAssistant.value = null
}

async function saveAssistant() {
  isSaving.value = true
  error.value = null

  try {
    if (isEditing.value) {
      await adminApi.updateAssistant(selectedAssistant.value.id, formData.value)
      success.value = 'Asistente actualizado correctamente'
    } else {
      await adminApi.createAssistant(formData.value)
      success.value = 'Asistente creado correctamente'
    }
    closeModal()
    await fetchAssistants()
    setTimeout(() => { success.value = null }, 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar'
  } finally {
    isSaving.value = false
  }
}

async function deleteAssistant(assistant) {
  if (!confirm(`¿Eliminar "${assistant.name}"? Los usuarios seran reasignados al asistente por defecto.`)) return

  try {
    await adminApi.deleteAssistant(assistant.id)
    success.value = 'Asistente eliminado correctamente'
    await fetchAssistants()
    setTimeout(() => { success.value = null }, 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al eliminar'
  }
}

async function setDefault(assistant) {
  try {
    await adminApi.setDefaultAssistant(assistant.id)
    success.value = 'Asistente establecido como predeterminado'
    await fetchAssistants()
    setTimeout(() => { success.value = null }, 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Error'
  }
}

async function duplicateAssistant(assistant) {
  try {
    await adminApi.duplicateAssistant(assistant.id)
    success.value = 'Asistente duplicado correctamente'
    await fetchAssistants()
    setTimeout(() => { success.value = null }, 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al duplicar'
  }
}

async function testAssistant(assistant) {
  testingAssistantId.value = assistant.id
  isTesting.value = true
  testResponse.value = null

  try {
    const response = await adminApi.testAssistant(assistant.id, testMessage.value)
    testResponse.value = response.data
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al probar'
  } finally {
    isTesting.value = false
  }
}

// Knowledge Base / Files Functions
async function openFilesModal(assistant) {
  filesAssistant.value = assistant
  showFilesModal.value = true
  await fetchFiles()
}

function closeFilesModal() {
  showFilesModal.value = false
  filesAssistant.value = null
  files.value = []
  filesStats.value = null
}

async function fetchFiles() {
  if (!filesAssistant.value) return
  isLoadingFiles.value = true
  try {
    const response = await adminApi.getAssistantFiles(filesAssistant.value.id)
    files.value = response.data.files
    filesStats.value = response.data.stats
    supportedTypes.value = response.data.supported_types
    maxFileSizeMB.value = response.data.max_file_size_mb
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al cargar archivos'
  } finally {
    isLoadingFiles.value = false
  }
}

async function toggleKnowledgeBase() {
  if (!filesAssistant.value) return
  isTogglingKB.value = true
  error.value = null

  try {
    if (filesAssistant.value.use_knowledge_base) {
      // Disable - confirm first
      if (!confirm('¿Desactivar la base de conocimientos? Se eliminaran todos los archivos subidos.')) {
        isTogglingKB.value = false
        return
      }
      await adminApi.disableKnowledgeBase(filesAssistant.value.id)
      success.value = 'Base de conocimientos desactivada'
    } else {
      await adminApi.enableKnowledgeBase(filesAssistant.value.id)
      success.value = 'Base de conocimientos activada'
    }

    // Refresh assistants and files
    await fetchAssistants()
    // Update local reference
    filesAssistant.value = assistants.value.find(a => a.id === filesAssistant.value.id)
    await fetchFiles()
    setTimeout(() => { success.value = null }, 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al cambiar estado'
  } finally {
    isTogglingKB.value = false
  }
}

async function uploadFile(event) {
  const file = event.target.files?.[0]
  if (!file || !filesAssistant.value) return

  // Validate size
  if (file.size > maxFileSizeMB.value * 1024 * 1024) {
    error.value = `El archivo excede el tamaño máximo de ${maxFileSizeMB.value} MB`
    event.target.value = ''
    return
  }

  isUploading.value = true
  error.value = null

  try {
    await adminApi.uploadAssistantFile(filesAssistant.value.id, file)
    success.value = 'Archivo subido correctamente'
    await fetchFiles()
    setTimeout(() => { success.value = null }, 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al subir archivo'
  } finally {
    isUploading.value = false
    event.target.value = ''
  }
}

async function deleteFile(file) {
  if (!filesAssistant.value) return
  if (!confirm(`¿Eliminar "${file.original_name}"?`)) return

  isDeletingFile.value = file.id
  error.value = null

  try {
    await adminApi.deleteAssistantFile(filesAssistant.value.id, file.id)
    success.value = 'Archivo eliminado correctamente'
    await fetchFiles()
    setTimeout(() => { success.value = null }, 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al eliminar archivo'
  } finally {
    isDeletingFile.value = null
  }
}

function getFileIcon(extension) {
  const icons = {
    pdf: 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z',
    doc: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    docx: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    txt: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    md: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  }
  return icons[extension] || icons.txt
}

function getStatusColor(status) {
  switch (status) {
    case 'ready': return 'bg-green-500'
    case 'processing': return 'bg-yellow-500'
    case 'failed': return 'bg-red-500'
    default: return 'bg-gray-500'
  }
}

function getStatusText(status) {
  switch (status) {
    case 'ready': return 'Listo'
    case 'processing': return 'Procesando'
    case 'failed': return 'Error'
    default: return 'Desconocido'
  }
}
</script>

<template>
  <div class="p-4 sm:p-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
      <h1 class="text-xl sm:text-2xl font-bold text-gatales-text">Asistentes de IA</h1>
      <button @click="openCreateModal" class="btn-primary">
        + Crear Asistente
      </button>
    </div>

    <!-- Messages -->
    <div v-if="error" class="bg-red-500/20 text-red-400 p-4 rounded-lg mb-6">
      {{ error }}
    </div>
    <div v-if="success" class="bg-green-500/20 text-green-400 p-4 rounded-lg mb-6">
      {{ success }}
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="text-gatales-text-secondary">Cargando...</div>

    <!-- Assistants Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="assistant in assistants"
        :key="assistant.id"
        class="card relative"
      >
        <!-- Default badge -->
        <div v-if="assistant.is_default" class="absolute top-2 right-2 px-2 py-0.5 bg-gatales-accent text-white text-xs rounded">
          Por defecto
        </div>

        <!-- Status indicator -->
        <div class="flex items-center gap-2 mb-3">
          <div :class="['w-2 h-2 rounded-full', assistant.is_active ? 'bg-green-500' : 'bg-red-500']"></div>
          <span class="text-xs text-gatales-text-secondary">{{ assistant.is_active ? 'Activo' : 'Inactivo' }}</span>
        </div>

        <h3 class="text-lg font-semibold text-gatales-text mb-1">{{ assistant.name }}</h3>
        <p class="text-sm text-gatales-text-secondary mb-2">{{ assistant.assistant_display_name }}</p>
        <p v-if="assistant.description" class="text-xs text-gatales-text-secondary mb-3 line-clamp-2">
          {{ assistant.description }}
        </p>

        <div class="flex flex-wrap gap-2 text-xs text-gatales-text-secondary mb-4">
          <span class="px-2 py-1 bg-gatales-input rounded">{{ assistant.model }}</span>
          <span class="px-2 py-1 bg-gatales-input rounded">
            Temp: {{ (assistant.model?.startsWith('o1') || assistant.model?.startsWith('gpt-5')) ? 'N/A' : assistant.temperature }}
          </span>
          <span class="px-2 py-1 bg-gatales-input rounded">{{ assistant.users_count || 0 }} usuarios</span>
          <span v-if="assistant.use_knowledge_base" class="px-2 py-1 bg-purple-500/20 text-purple-400 rounded flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            Base de conocimientos
          </span>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-2">
          <button @click="openEditModal(assistant)" class="btn-secondary text-xs px-3 py-1.5">
            Editar
          </button>
          <button @click="openFilesModal(assistant)" class="btn-secondary text-xs px-3 py-1.5 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            Archivos
          </button>
          <button @click="testAssistant(assistant)" :disabled="isTesting && testingAssistantId === assistant.id" class="btn-secondary text-xs px-3 py-1.5">
            {{ isTesting && testingAssistantId === assistant.id ? 'Probando...' : 'Probar' }}
          </button>
          <button @click="duplicateAssistant(assistant)" class="btn-secondary text-xs px-3 py-1.5">
            Duplicar
          </button>
          <button v-if="!assistant.is_default" @click="setDefault(assistant)" class="btn-secondary text-xs px-3 py-1.5">
            Hacer default
          </button>
          <button v-if="!assistant.is_default" @click="deleteAssistant(assistant)" class="text-red-400 text-xs px-3 py-1.5 hover:bg-red-500/20 rounded">
            Eliminar
          </button>
        </div>

        <!-- Test Response -->
        <div v-if="testResponse && testingAssistantId === assistant.id" class="mt-4 p-3 bg-gatales-input rounded-lg">
          <div class="flex justify-between items-start mb-2">
            <span class="text-xs font-medium text-gatales-accent">Respuesta de prueba:</span>
            <span class="text-xs text-gatales-text-secondary">{{ testResponse.tokens_used }} tokens</span>
          </div>
          <p class="text-sm text-gatales-text whitespace-pre-wrap line-clamp-4">{{ testResponse.response }}</p>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="closeModal"></div>
      <div class="relative bg-gatales-sidebar rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gatales-sidebar p-4 border-b border-gatales-border flex justify-between items-center">
          <h2 class="text-lg font-semibold text-gatales-text">
            {{ isEditing ? 'Editar Asistente' : 'Crear Asistente' }}
          </h2>
          <button @click="closeModal" class="text-gatales-text-secondary hover:text-gatales-text">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="saveAssistant" class="p-4 space-y-4">
          <!-- Basic Info -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Nombre (Admin)</label>
              <input v-model="formData.name" type="text" class="input-field" required placeholder="ej: Gatales Premium" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Nombre mostrado al usuario</label>
              <input v-model="formData.assistant_display_name" type="text" class="input-field" required placeholder="ej: Tu Asistente" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Descripcion (opcional)</label>
            <input v-model="formData.description" type="text" class="input-field" placeholder="Descripcion para admin" />
          </div>

          <div class="flex items-center gap-2">
            <input v-model="formData.is_active" type="checkbox" id="is_active" class="accent-gatales-accent" />
            <label for="is_active" class="text-sm text-gatales-text">Activo</label>
          </div>

          <!-- Model & Basic Config -->
          <div class="border-t border-gatales-border pt-4">
            <h3 class="text-sm font-semibold text-gatales-text mb-3">Modelo y Configuracion Basica</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Modelo</label>
                <select v-model="formData.model" class="input-field">
                  <option v-for="(label, value) in availableModels" :key="value" :value="value">
                    {{ label }}
                  </option>
                </select>
                <p class="text-xs text-gatales-text-secondary mt-1">Modelo de OpenAI a utilizar</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Max Tokens</label>
                <input v-model.number="formData.max_tokens" type="number" min="100" max="16000" class="input-field" />
                <p class="text-xs text-gatales-text-secondary mt-1">Maximo de tokens en la respuesta</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">
                  Temperatura ({{ noTemperatureSupport ? 'N/A' : formData.temperature }})
                </label>
                <div class="flex items-center gap-3">
                  <input
                    v-model.number="formData.temperature"
                    type="range"
                    min="0"
                    max="2"
                    step="0.1"
                    :disabled="noTemperatureSupport"
                    :class="['flex-1 accent-gatales-accent', noTemperatureSupport ? 'opacity-50 cursor-not-allowed' : '']"
                  />
                  <span class="text-sm text-gatales-text w-10 text-right">{{ noTemperatureSupport ? '-' : formData.temperature }}</span>
                </div>
                <p v-if="noTemperatureSupport" class="text-xs text-yellow-400 mt-1">
                  Los modelos o1 y GPT-5 no soportan temperatura personalizada (solo valor por defecto=1)
                </p>
                <p v-else class="text-xs text-gatales-text-secondary mt-1">0 = determinista, 2 = muy creativo</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Mensajes de Contexto</label>
                <input v-model.number="formData.context_messages" type="number" min="1" max="50" class="input-field" />
                <p class="text-xs text-gatales-text-secondary mt-1">Cuantos mensajes previos incluir</p>
              </div>
            </div>
          </div>

          <!-- System Prompt -->
          <div class="border-t border-gatales-border pt-4">
            <h3 class="text-sm font-semibold text-gatales-text mb-3">Instrucciones del Sistema</h3>
            <textarea v-model="formData.system_prompt" rows="8" class="input-field font-mono text-sm" required placeholder="Instrucciones que definen el comportamiento del asistente..."></textarea>
            <p class="text-xs text-gatales-text-secondary mt-1">Define la personalidad y comportamiento del asistente</p>
          </div>

          <!-- Welcome Message -->
          <div class="border-t border-gatales-border pt-4">
            <h3 class="text-sm font-semibold text-gatales-text mb-3">Mensaje de Bienvenida</h3>
            <textarea v-model="formData.welcome_message" rows="2" class="input-field" required placeholder="Mensaje que se muestra al iniciar el chat..."></textarea>
            <p class="text-xs text-gatales-text-secondary mt-1">Mensaje inicial que ve el usuario</p>
          </div>

          <!-- Sampling Parameters -->
          <div class="border-t border-gatales-border pt-4">
            <h3 class="text-sm font-semibold text-gatales-text mb-3">Parametros de Muestreo</h3>
            <p class="text-xs text-gatales-text-secondary mb-3">Controlan como el modelo selecciona los tokens</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Top P ({{ formData.top_p }})</label>
                <div class="flex items-center gap-3">
                  <input v-model.number="formData.top_p" type="range" min="0" max="1" step="0.05" class="flex-1 accent-gatales-accent" />
                  <span class="text-sm text-gatales-text w-10 text-right">{{ formData.top_p }}</span>
                </div>
                <p class="text-xs text-gatales-text-secondary mt-1">Nucleus sampling</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Freq. Penalty ({{ formData.frequency_penalty }})</label>
                <div class="flex items-center gap-3">
                  <input v-model.number="formData.frequency_penalty" type="range" min="0" max="2" step="0.1" class="flex-1 accent-gatales-accent" />
                  <span class="text-sm text-gatales-text w-10 text-right">{{ formData.frequency_penalty }}</span>
                </div>
                <p class="text-xs text-gatales-text-secondary mt-1">Penaliza repeticion</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Presence Penalty ({{ formData.presence_penalty }})</label>
                <div class="flex items-center gap-3">
                  <input v-model.number="formData.presence_penalty" type="range" min="0" max="2" step="0.1" class="flex-1 accent-gatales-accent" />
                  <span class="text-sm text-gatales-text w-10 text-right">{{ formData.presence_penalty }}</span>
                </div>
                <p class="text-xs text-gatales-text-secondary mt-1">Fomenta nuevos temas</p>
              </div>
            </div>
          </div>

          <!-- Response Options -->
          <div class="border-t border-gatales-border pt-4">
            <h3 class="text-sm font-semibold text-gatales-text mb-3">Opciones de Respuesta</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Formato de Respuesta</label>
                <select v-model="formData.response_format" class="input-field">
                  <option value="text">Texto</option>
                  <option value="json_object">JSON</option>
                </select>
                <p class="text-xs text-gatales-text-secondary mt-1">Formato de salida</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Stop Sequences</label>
                <input v-model="formData.stop_sequences" type="text" class="input-field" placeholder="Separadas por coma" />
                <p class="text-xs text-gatales-text-secondary mt-1">Secuencias que detienen la generacion</p>
              </div>
            </div>
          </div>

          <!-- Advanced Options (Collapsible) -->
          <div class="border-t border-gatales-border pt-4">
            <button
              type="button"
              @click="showAdvanced = !showAdvanced"
              class="flex items-center gap-2 text-sm font-semibold text-gatales-text mb-3"
            >
              <svg :class="['w-4 h-4 transition-transform', showAdvanced ? 'rotate-90' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
              Configuracion Avanzada
            </button>

            <div v-if="showAdvanced" class="space-y-4 pl-6">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Seed (reproducibilidad)</label>
                  <input v-model.number="formData.seed" type="number" class="input-field" placeholder="Dejar vacio para aleatorio" />
                  <p class="text-xs text-gatales-text-secondary mt-1">Mismo seed = misma respuesta</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gatales-text-secondary mb-1">N Completions</label>
                  <input v-model.number="formData.n_completions" type="number" min="1" max="5" class="input-field" />
                  <p class="text-xs text-gatales-text-secondary mt-1">Numero de respuestas a generar</p>
                </div>
              </div>

              <div class="flex flex-wrap gap-6">
                <div class="flex items-center gap-2">
                  <input v-model="formData.stream" type="checkbox" id="stream" class="accent-gatales-accent" />
                  <label for="stream" class="text-sm text-gatales-text">Streaming</label>
                </div>
                <div class="flex items-center gap-2">
                  <input v-model="formData.logprobs" type="checkbox" id="logprobs" class="accent-gatales-accent" />
                  <label for="logprobs" class="text-sm text-gatales-text">Log Probabilities</label>
                </div>
                <div class="flex items-center gap-2">
                  <input v-model="formData.include_user_id" type="checkbox" id="include_user_id" class="accent-gatales-accent" />
                  <label for="include_user_id" class="text-sm text-gatales-text">Incluir User ID</label>
                </div>
              </div>
            </div>
          </div>

          <!-- Safety -->
          <div class="border-t border-gatales-border pt-4">
            <h3 class="text-sm font-semibold text-gatales-text mb-3">Seguridad</h3>
            <div class="flex items-center gap-2">
              <input v-model="formData.filter_unsafe_content" type="checkbox" id="filter_unsafe" class="accent-gatales-accent" />
              <label for="filter_unsafe" class="text-sm text-gatales-text">Filtrar contenido inseguro</label>
            </div>
          </div>

          <!-- Submit -->
          <div class="flex gap-2 pt-4 border-t border-gatales-border">
            <button type="button" @click="closeModal" class="btn-secondary flex-1">Cancelar</button>
            <button type="submit" :disabled="isSaving" class="btn-primary flex-1">
              {{ isSaving ? 'Guardando...' : (isEditing ? 'Guardar Cambios' : 'Crear Asistente') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Files / Knowledge Base Modal -->
    <div v-if="showFilesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="closeFilesModal"></div>
      <div class="relative bg-gatales-sidebar rounded-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gatales-sidebar p-4 border-b border-gatales-border">
          <div class="flex justify-between items-center">
            <div>
              <h2 class="text-lg font-semibold text-gatales-text">Base de Conocimientos</h2>
              <p class="text-sm text-gatales-text-secondary">{{ filesAssistant?.name }}</p>
            </div>
            <button @click="closeFilesModal" class="text-gatales-text-secondary hover:text-gatales-text">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <div class="p-4 space-y-4">
          <!-- Enable/Disable Toggle -->
          <div class="flex items-center justify-between p-4 bg-gatales-input rounded-lg">
            <div>
              <h3 class="text-sm font-medium text-gatales-text">Habilitar Base de Conocimientos</h3>
              <p class="text-xs text-gatales-text-secondary mt-1">
                Permite subir archivos (PDF, DOCX, TXT, etc.) que el asistente usara para responder preguntas
              </p>
            </div>
            <button
              @click="toggleKnowledgeBase"
              :disabled="isTogglingKB"
              :class="[
                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                filesAssistant?.use_knowledge_base ? 'bg-gatales-accent' : 'bg-gray-600'
              ]"
            >
              <span
                :class="[
                  'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                  filesAssistant?.use_knowledge_base ? 'translate-x-5' : 'translate-x-0'
                ]"
              ></span>
            </button>
          </div>

          <!-- Content when KB is enabled -->
          <template v-if="filesAssistant?.use_knowledge_base">
            <!-- Stats -->
            <div v-if="filesStats" class="grid grid-cols-4 gap-3">
              <div class="p-3 bg-gatales-input rounded-lg text-center">
                <p class="text-lg font-bold text-gatales-text">{{ filesStats.total }}</p>
                <p class="text-xs text-gatales-text-secondary">Total</p>
              </div>
              <div class="p-3 bg-gatales-input rounded-lg text-center">
                <p class="text-lg font-bold text-green-400">{{ filesStats.ready }}</p>
                <p class="text-xs text-gatales-text-secondary">Listos</p>
              </div>
              <div class="p-3 bg-gatales-input rounded-lg text-center">
                <p class="text-lg font-bold text-yellow-400">{{ filesStats.processing }}</p>
                <p class="text-xs text-gatales-text-secondary">Procesando</p>
              </div>
              <div class="p-3 bg-gatales-input rounded-lg text-center">
                <p class="text-sm font-bold text-gatales-text">{{ formattedTotalSize }}</p>
                <p class="text-xs text-gatales-text-secondary">Tamaño</p>
              </div>
            </div>

            <!-- Upload Area -->
            <div class="border-2 border-dashed border-gatales-border rounded-lg p-6 text-center">
              <input
                type="file"
                id="file-upload"
                class="hidden"
                @change="uploadFile"
                :disabled="isUploading"
                accept=".pdf,.doc,.docx,.txt,.md,.html,.xls,.xlsx,.ppt,.pptx,.csv,.json"
              />
              <label
                for="file-upload"
                :class="[
                  'cursor-pointer flex flex-col items-center gap-2',
                  isUploading ? 'opacity-50 pointer-events-none' : ''
                ]"
              >
                <svg class="w-10 h-10 text-gatales-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <span class="text-sm text-gatales-text">
                  {{ isUploading ? 'Subiendo...' : 'Haz clic para subir un archivo' }}
                </span>
                <span class="text-xs text-gatales-text-secondary">
                  PDF, DOCX, TXT, MD, HTML, XLS, XLSX, PPT, PPTX, CSV, JSON (max {{ maxFileSizeMB }}MB)
                </span>
              </label>
            </div>

            <!-- Files List -->
            <div v-if="isLoadingFiles" class="text-center py-4 text-gatales-text-secondary">
              Cargando archivos...
            </div>
            <div v-else-if="files.length === 0" class="text-center py-8 text-gatales-text-secondary">
              <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <p>No hay archivos en la base de conocimientos</p>
              <p class="text-xs mt-1">Sube archivos para que el asistente pueda usarlos</p>
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="file in files"
                :key="file.id"
                class="flex items-center justify-between p-3 bg-gatales-input rounded-lg"
              >
                <div class="flex items-center gap-3 min-w-0">
                  <div class="flex-shrink-0 w-8 h-8 bg-gatales-sidebar rounded flex items-center justify-center">
                    <svg class="w-5 h-5 text-gatales-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getFileIcon(file.extension)" />
                    </svg>
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm text-gatales-text truncate">{{ file.original_name }}</p>
                    <div class="flex items-center gap-2 text-xs text-gatales-text-secondary">
                      <span>{{ file.formatted_size }}</span>
                      <span>•</span>
                      <span class="flex items-center gap-1">
                        <span :class="['w-1.5 h-1.5 rounded-full', getStatusColor(file.status)]"></span>
                        {{ getStatusText(file.status) }}
                      </span>
                    </div>
                    <p v-if="file.error_message" class="text-xs text-red-400 mt-1">{{ file.error_message }}</p>
                  </div>
                </div>
                <button
                  @click="deleteFile(file)"
                  :disabled="isDeletingFile === file.id"
                  class="flex-shrink-0 p-2 text-red-400 hover:bg-red-500/20 rounded transition-colors"
                >
                  <svg v-if="isDeletingFile === file.id" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </template>

          <!-- Info when KB is disabled -->
          <div v-else class="text-center py-8 text-gatales-text-secondary">
            <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <p class="text-lg font-medium text-gatales-text mb-2">Base de Conocimientos Desactivada</p>
            <p class="text-sm">Activa la base de conocimientos para subir archivos que el asistente pueda usar como referencia.</p>
            <p class="text-xs mt-4 max-w-md mx-auto">
              Con la base de conocimientos activada, puedes subir documentos PDF, Word, Excel, y mas.
              El asistente buscara automaticamente en estos archivos para responder preguntas.
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 bg-gatales-sidebar p-4 border-t border-gatales-border">
          <button @click="closeFilesModal" class="btn-secondary w-full">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.line-clamp-4 {
  display: -webkit-box;
  -webkit-line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
