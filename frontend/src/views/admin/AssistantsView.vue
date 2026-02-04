<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '../../services/api'

const assistants = ref([])
const availableProviders = ref({})
const availableModels = ref({})
const reasoningEffortOptions = ref({})
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
  provider: 'openai',
  model: 'gpt-4o-mini',
  reasoning_effort: 'minimal',
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
const showPromptTips = ref(false)
const showPromptFullscreen = ref(false)

// Prompt templates
const promptTemplates = {
  guiones: `## ROL
Eres un experto guionista especializado en crear guiones de video para redes sociales y YouTube. Tienes años de experiencia creando contenido viral que conecta emocionalmente con la audiencia.

## PERSONALIDAD
- Tono: Amigable y profesional
- Estilo: Creativo pero estructurado
- Actitud: Entusiasta y motivador

## INSTRUCCIONES PRINCIPALES
1. Siempre pregunta primero sobre el negocio/producto del usuario si no lo conoces
2. Crea guiones con estructura clara: gancho, desarrollo, llamada a accion
3. Adapta el lenguaje al publico objetivo del usuario
4. Incluye indicaciones de tono y emocion en el guion
5. Ofrece variantes cuando sea apropiado

## FORMATO DE RESPUESTA
- Usa formato de guion con indicaciones claras
- Incluye tiempos aproximados
- Marca las secciones (GANCHO, DESARROLLO, CTA)
- Agrega notas de produccion cuando sea util

## RESTRICCIONES
- No uses lenguaje ofensivo
- Evita promesas exageradas o falsas
- No copies contenido de otros creadores`,

  asistente: `## ROL
Eres un asistente virtual inteligente y servicial. Tu objetivo es ayudar a los usuarios de manera eficiente y clara.

## PERSONALIDAD
- Tono: Amigable y profesional
- Estilo: Claro y conciso
- Actitud: Paciente y comprensivo

## INSTRUCCIONES
1. Responde de manera clara y directa
2. Si no entiendes algo, pide clarificacion
3. Ofrece ayuda adicional cuando sea relevante
4. Mantiene un tono positivo y constructivo

## FORMATO
- Usa listas para instrucciones paso a paso
- Resalta informacion importante
- Manten respuestas concisas pero completas

## RESTRICCIONES
- No inventes informacion
- Si no sabes algo, admitelo honestamente
- Evita respuestas demasiado largas`,

  experto: `## ROL
Eres un experto tecnico con amplio conocimiento en tu area. Proporcionas informacion precisa y detallada.

## PERSONALIDAD
- Tono: Profesional y tecnico
- Estilo: Detallado y preciso
- Actitud: Educativo y paciente

## INSTRUCCIONES
1. Explica conceptos tecnicos de manera accesible
2. Proporciona ejemplos practicos
3. Ofrece alternativas cuando sea posible
4. Incluye advertencias sobre posibles problemas

## FORMATO
- Usa codigo formateado cuando sea relevante
- Incluye explicaciones paso a paso
- Organiza la informacion con encabezados claros

## RESTRICCIONES
- No des consejos sin advertir sobre riesgos
- Verifica la precision de la informacion tecnica
- Admite cuando algo esta fuera de tu expertise`,

  creativo: `## ROL
Eres un escritor creativo con talento para crear contenido original, atractivo y memorable.

## PERSONALIDAD
- Tono: Creativo e inspirador
- Estilo: Expresivo y unico
- Actitud: Imaginativo y entusiasta

## INSTRUCCIONES
1. Crea contenido original y atractivo
2. Adapta el estilo al proposito del contenido
3. Usa tecnicas narrativas efectivas
4. Incorpora elementos que generen engagement

## FORMATO
- Varia la estructura segun el tipo de contenido
- Usa metaforas y lenguaje visual
- Crea ritmo y fluidez en el texto

## RESTRICCIONES
- Manten originalidad, no copies
- Respeta el tono de marca del usuario
- Evita cliches cuando sea posible`
}

function applyPromptTemplate(templateName) {
  if (promptTemplates[templateName]) {
    if (formData.value.system_prompt && formData.value.system_prompt.trim() !== '' && formData.value.system_prompt !== defaultFormData.system_prompt) {
      if (!confirm('¿Reemplazar las instrucciones actuales con esta plantilla?')) {
        return
      }
    }
    formData.value.system_prompt = promptTemplates[templateName]
  }
}

// Computed: current provider's models
const currentProviderModels = computed(() => {
  const provider = formData.value.provider || 'openai'
  return availableModels.value[provider] || {}
})

// Computed: check if DeepSeek is selected
const isDeepSeek = computed(() => formData.value.provider === 'deepseek')

// Computed to check if selected model doesn't support custom temperature (o1 and GPT-5 only - DeepSeek DOES support it)
const noTemperatureSupport = computed(() => {
  if (isDeepSeek.value) return false // DeepSeek supports temperature
  const model = formData.value.model || ''
  return model.startsWith('o1') || model.startsWith('gpt-5')
})

// Computed to check if model uses new API format (GPT-5, o1) - NOT DeepSeek
// These models don't support sampling parameters (top_p, frequency_penalty, presence_penalty)
const isNewModel = computed(() => {
  if (isDeepSeek.value) return false // DeepSeek supports all sampling params
  const model = formData.value.model || ''
  return model.startsWith('gpt-5') || model.startsWith('o1')
})

// Computed: sampling parameters are not supported for new OpenAI models (but DeepSeek supports them)
const noSamplingSupport = computed(() => isNewModel.value)

// Computed to check if model supports reasoning effort (GPT-5 only, not DeepSeek)
const supportsReasoningEffort = computed(() => {
  if (isDeepSeek.value) return false // DeepSeek has its own thinking mode, not reasoning_effort
  const model = formData.value.model || ''
  return model.startsWith('gpt-5')
})

// Computed: check if advanced params like seed/n_completions are supported (not for DeepSeek)
const supportsAdvancedParams = computed(() => !isDeepSeek.value)

// Computed to check if filesAssistant has reasoning conflict (GPT-5 with reasoning enabled)
const hasReasoningConflict = computed(() => {
  if (!filesAssistant.value) return false
  const model = filesAssistant.value.model || ''
  const reasoningEffort = filesAssistant.value.reasoning_effort || 'minimal'
  // GPT-5 with any reasoning effort conflicts with Knowledge Base
  return model.startsWith('gpt-5') && reasoningEffort !== 'none'
})

// Computed to check if filesAssistant already has Knowledge Base enabled
const hasKnowledgeBaseActive = computed(() => {
  return filesAssistant.value?.use_knowledge_base === true
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
    availableProviders.value = response.data.available_providers || {}
    availableModels.value = response.data.available_models || {}
    reasoningEffortOptions.value = response.data.reasoning_effort_options || {}
  } catch (e) {
    error.value = 'Error al cargar los asistentes'
  } finally {
    isLoading.value = false
  }
}

// Watch provider changes to reset model
function onProviderChange() {
  const provider = formData.value.provider
  const models = availableModels.value[provider] || {}
  const modelKeys = Object.keys(models)
  // Set first model of new provider
  if (modelKeys.length > 0 && !models[formData.value.model]) {
    formData.value.model = modelKeys[0]
  }
  // DeepSeek doesn't support knowledge base
  if (provider === 'deepseek') {
    formData.value.use_knowledge_base = false
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
          <span :class="[
            'px-2 py-1 rounded font-medium',
            assistant.provider === 'deepseek' ? 'bg-blue-500/20 text-blue-400' : 'bg-green-500/20 text-green-400'
          ]">
            {{ assistant.provider === 'deepseek' ? 'DeepSeek' : 'OpenAI' }}
          </span>
          <span class="px-2 py-1 bg-gatales-input rounded">{{ assistant.model }}</span>
          <span class="px-2 py-1 bg-gatales-input rounded">
            Temp: {{ (assistant.model?.startsWith('o1') || assistant.model?.startsWith('gpt-5')) ? 'N/A' : assistant.temperature }}
          </span>
          <!-- Reasoning Effort indicator for GPT-5 -->
          <span
            v-if="assistant.model?.startsWith('gpt-5')"
            :class="[
              'px-2 py-1 rounded',
              assistant.reasoning_effort === 'minimal' || assistant.reasoning_effort === 'none' ? 'bg-green-500/20 text-green-400' :
              assistant.reasoning_effort === 'low' || assistant.reasoning_effort === 'medium' ? 'bg-yellow-500/20 text-yellow-400' :
              'bg-red-500/20 text-red-400'
            ]"
          >
            Reasoning: {{ assistant.reasoning_effort || 'minimal' }}
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

          <!-- Provider & Model Config -->
          <div class="border-t border-gatales-border pt-4">
            <h3 class="text-sm font-semibold text-gatales-text mb-3">Proveedor y Modelo</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Proveedor de IA</label>
                <select v-model="formData.provider" @change="onProviderChange" class="input-field">
                  <option v-for="(label, value) in availableProviders" :key="value" :value="value">
                    {{ label }}
                  </option>
                </select>
                <p class="text-xs text-gatales-text-secondary mt-1">OpenAI o DeepSeek</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Modelo</label>
                <select v-model="formData.model" class="input-field">
                  <option v-for="(label, value) in currentProviderModels" :key="value" :value="value">
                    {{ label }}
                  </option>
                </select>
                <p class="text-xs text-gatales-text-secondary mt-1">Modelo de {{ formData.provider === 'deepseek' ? 'DeepSeek' : 'OpenAI' }} a utilizar</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Max Tokens</label>
                <input v-model.number="formData.max_tokens" type="number" min="100" max="16000" class="input-field" />
                <p class="text-xs text-gatales-text-secondary mt-1">Maximo de tokens en la respuesta</p>
              </div>
              <!-- Reasoning Effort (GPT-5 only) - disabled if Knowledge Base is active -->
              <div v-if="supportsReasoningEffort" class="sm:col-span-2">
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">
                  Reasoning Effort
                  <span class="text-yellow-400 ml-1">(IMPORTANTE: afecta velocidad)</span>
                </label>
                <select
                  v-model="formData.reasoning_effort"
                  class="input-field"
                  :disabled="formData.use_knowledge_base"
                  :class="{ 'opacity-50 cursor-not-allowed': formData.use_knowledge_base }"
                >
                  <option v-for="(label, value) in reasoningEffortOptions" :key="value" :value="value">
                    {{ label }}
                  </option>
                </select>
                <div class="mt-2 p-2 bg-gatales-input rounded text-xs">
                  <p class="text-gatales-text mb-1"><strong>Guia de velocidad:</strong></p>
                  <ul class="text-gatales-text-secondary space-y-0.5">
                    <li><span class="text-green-400">minimal/none</span> = Respuesta rapida (recomendado)</li>
                    <li><span class="text-yellow-400">low/medium</span> = Mas lento, mejor razonamiento</li>
                    <li><span class="text-red-400">high/xhigh</span> = MUY lento, maxima calidad</li>
                  </ul>
                </div>
                <p v-if="formData.use_knowledge_base" class="text-xs text-orange-400 mt-1">
                  ⚠️ Knowledge Base activo: Reasoning Effort deshabilitado (no son compatibles)
                </p>
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

          <!-- System Prompt - Improved -->
          <div class="border-t border-gatales-border pt-4">
            <div class="flex justify-between items-start mb-3">
              <div>
                <h3 class="text-sm font-semibold text-gatales-text">Instrucciones del Sistema (System Prompt)</h3>
                <p class="text-xs text-gatales-text-secondary mt-1">Define como debe comportarse y responder el asistente</p>
              </div>
              <div class="flex items-center gap-2">
                <span :class="['text-xs px-2 py-1 rounded', formData.system_prompt.length > 4000 ? 'bg-red-500/20 text-red-400' : 'bg-gatales-input text-gatales-text-secondary']">
                  {{ formData.system_prompt.length }} caracteres
                </span>
                <button
                  type="button"
                  @click="showPromptFullscreen = true"
                  class="p-2 bg-gatales-input hover:bg-gatales-border rounded transition-colors text-gatales-text"
                  title="Expandir a pantalla completa"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Provider-specific tips -->
            <div :class="['mb-4 p-3 rounded-lg border', isDeepSeek ? 'bg-blue-500/10 border-blue-500/20' : 'bg-green-500/10 border-green-500/20']">
              <div class="flex items-center gap-2 mb-2">
                <span :class="['text-xs font-medium px-2 py-0.5 rounded', isDeepSeek ? 'bg-blue-500/20 text-blue-400' : 'bg-green-500/20 text-green-400']">
                  {{ isDeepSeek ? 'DeepSeek' : 'OpenAI GPT' }}
                </span>
                <span class="text-xs text-gatales-text-secondary">Tips para este modelo:</span>
              </div>
              <div v-if="isDeepSeek" class="text-xs text-blue-300/80 space-y-1">
                <p>• DeepSeek responde bien a instrucciones <strong>directas y claras</strong></p>
                <p>• Usa formato de <strong>lista numerada</strong> para instrucciones</p>
                <p>• Especifica el <strong>idioma de respuesta</strong> explicitamente</p>
                <p>• Evita instrucciones muy largas - se mas <strong>conciso</strong></p>
              </div>
              <div v-else class="text-xs text-green-300/80 space-y-1">
                <p>• GPT entiende bien instrucciones <strong>detalladas y con contexto</strong></p>
                <p>• Usa <strong>encabezados con ##</strong> para organizar secciones</p>
                <p>• Puedes incluir <strong>ejemplos de conversacion</strong></p>
                <p>• Funciona bien con instrucciones <strong>largas y especificas</strong></p>
              </div>
            </div>

            <!-- Quick Templates -->
            <div class="mb-4">
              <label class="block text-xs font-medium text-gatales-text-secondary mb-2">Plantillas:</label>
              <div class="flex flex-wrap gap-2">
                <button type="button" @click="applyPromptTemplate('guiones')" class="text-xs px-3 py-1.5 bg-gatales-input hover:bg-gatales-border rounded transition-colors text-gatales-text">
                  Guionista
                </button>
                <button type="button" @click="applyPromptTemplate('asistente')" class="text-xs px-3 py-1.5 bg-gatales-input hover:bg-gatales-border rounded transition-colors text-gatales-text">
                  Asistente
                </button>
                <button type="button" @click="applyPromptTemplate('experto')" class="text-xs px-3 py-1.5 bg-gatales-input hover:bg-gatales-border rounded transition-colors text-gatales-text">
                  Experto
                </button>
                <button type="button" @click="applyPromptTemplate('creativo')" class="text-xs px-3 py-1.5 bg-gatales-input hover:bg-gatales-border rounded transition-colors text-gatales-text">
                  Creativo
                </button>
              </div>
            </div>

            <!-- Main Textarea (compact view) -->
            <div class="relative">
              <textarea
                v-model="formData.system_prompt"
                rows="8"
                class="input-field font-mono text-sm leading-relaxed pr-10"
                required
                placeholder="Escribe aqui las instrucciones..."
              ></textarea>
              <button
                type="button"
                @click="showPromptFullscreen = true"
                class="absolute top-2 right-2 p-1.5 bg-gatales-sidebar/80 hover:bg-gatales-border rounded text-gatales-text-secondary hover:text-gatales-text transition-colors"
                title="Expandir"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                </svg>
              </button>
            </div>
            <p class="text-xs text-gatales-text-secondary mt-2">
              <span class="text-gatales-accent cursor-pointer hover:underline" @click="showPromptFullscreen = true">
                Click aqui o en el icono para expandir
              </span>
              y ver/editar las instrucciones completas
            </p>
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
            <p v-if="noSamplingSupport" class="text-xs text-yellow-400 mb-3">
              Los modelos GPT-5 y o1 no soportan parametros de muestreo personalizados
            </p>
            <p v-else class="text-xs text-gatales-text-secondary mb-3">Controlan como el modelo selecciona los tokens</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Top P ({{ noSamplingSupport ? 'N/A' : formData.top_p }})</label>
                <div class="flex items-center gap-3">
                  <input
                    v-model.number="formData.top_p"
                    type="range"
                    min="0"
                    max="1"
                    step="0.05"
                    :disabled="noSamplingSupport"
                    :class="['flex-1 accent-gatales-accent', noSamplingSupport ? 'opacity-50 cursor-not-allowed' : '']"
                  />
                  <span class="text-sm text-gatales-text w-10 text-right">{{ noSamplingSupport ? '-' : formData.top_p }}</span>
                </div>
                <p class="text-xs text-gatales-text-secondary mt-1">Nucleus sampling</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Freq. Penalty ({{ noSamplingSupport ? 'N/A' : formData.frequency_penalty }})</label>
                <div class="flex items-center gap-3">
                  <input
                    v-model.number="formData.frequency_penalty"
                    type="range"
                    min="0"
                    max="2"
                    step="0.1"
                    :disabled="noSamplingSupport"
                    :class="['flex-1 accent-gatales-accent', noSamplingSupport ? 'opacity-50 cursor-not-allowed' : '']"
                  />
                  <span class="text-sm text-gatales-text w-10 text-right">{{ noSamplingSupport ? '-' : formData.frequency_penalty }}</span>
                </div>
                <p class="text-xs text-gatales-text-secondary mt-1">Penaliza repeticion</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Presence Penalty ({{ noSamplingSupport ? 'N/A' : formData.presence_penalty }})</label>
                <div class="flex items-center gap-3">
                  <input
                    v-model.number="formData.presence_penalty"
                    type="range"
                    min="0"
                    max="2"
                    step="0.1"
                    :disabled="noSamplingSupport"
                    :class="['flex-1 accent-gatales-accent', noSamplingSupport ? 'opacity-50 cursor-not-allowed' : '']"
                  />
                  <span class="text-sm text-gatales-text w-10 text-right">{{ noSamplingSupport ? '-' : formData.presence_penalty }}</span>
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
              <p v-if="isNewModel" class="text-xs text-yellow-400 mb-3 p-2 bg-yellow-500/10 rounded">
                Nota: Algunos parametros avanzados pueden no aplicar a GPT-5 y o1
              </p>
              <p v-if="isDeepSeek" class="text-xs text-blue-400 mb-3 p-2 bg-blue-500/10 rounded">
                DeepSeek: Seed y N Completions no estan soportados
              </p>

              <!-- Seed y N Completions - Solo OpenAI -->
              <div v-if="supportsAdvancedParams" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                <div v-if="!isDeepSeek" class="flex items-center gap-2">
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
          <!-- DeepSeek Warning -->
          <div v-if="filesAssistant?.provider === 'deepseek'" class="p-4 bg-orange-500/20 border border-orange-500/30 rounded-lg">
            <div class="flex items-start gap-3">
              <svg class="w-5 h-5 text-orange-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <div>
                <h3 class="text-sm font-medium text-orange-400">DeepSeek no soporta Knowledge Base</h3>
                <p class="text-xs text-orange-300/80 mt-1">
                  Este asistente usa DeepSeek como proveedor. La base de conocimientos solo esta disponible para asistentes que usan OpenAI.
                </p>
              </div>
            </div>
          </div>

          <!-- Enable/Disable Toggle -->
          <div v-else class="flex items-center justify-between p-4 bg-gatales-input rounded-lg">
            <div>
              <h3 class="text-sm font-medium text-gatales-text">Habilitar Base de Conocimientos</h3>
              <p class="text-xs text-gatales-text-secondary mt-1">
                Permite subir archivos (PDF, DOCX, TXT, etc.) que el asistente usara para responder preguntas
              </p>
              <!-- Warning if reasoning is active -->
              <p v-if="hasReasoningConflict && !hasKnowledgeBaseActive" class="text-xs text-orange-400 mt-2">
                ⚠️ Este asistente usa GPT-5 con Reasoning Effort activo. Si activas Knowledge Base, el Reasoning se desactivara automaticamente.
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

          <!-- Content when KB is enabled (OpenAI only) -->
          <template v-if="filesAssistant?.provider !== 'deepseek' && filesAssistant?.use_knowledge_base">
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

          <!-- Info when KB is disabled (OpenAI only) -->
          <div v-if="filesAssistant?.provider !== 'deepseek' && !filesAssistant?.use_knowledge_base" class="text-center py-8 text-gatales-text-secondary">
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

    <!-- Fullscreen Prompt Editor Modal -->
    <div v-if="showPromptFullscreen" class="fixed inset-0 z-[60] bg-gatales-bg flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between p-4 border-b border-gatales-border bg-gatales-sidebar">
        <div class="flex items-center gap-3">
          <h2 class="text-lg font-semibold text-gatales-text">Editor de Instrucciones</h2>
          <span :class="['text-xs px-2 py-1 rounded', isDeepSeek ? 'bg-blue-500/20 text-blue-400' : 'bg-green-500/20 text-green-400']">
            {{ isDeepSeek ? 'DeepSeek' : 'OpenAI GPT' }}
          </span>
          <span :class="['text-xs px-2 py-1 rounded', formData.system_prompt.length > 4000 ? 'bg-red-500/20 text-red-400' : 'bg-gatales-input text-gatales-text-secondary']">
            {{ formData.system_prompt.length }} caracteres
          </span>
        </div>
        <button @click="showPromptFullscreen = false" class="p-2 hover:bg-gatales-input rounded transition-colors text-gatales-text">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 flex overflow-hidden">
        <!-- Left: Editor -->
        <div class="flex-1 flex flex-col p-4 overflow-hidden">
          <textarea
            v-model="formData.system_prompt"
            class="flex-1 w-full bg-gatales-input border border-gatales-border rounded-lg p-4 text-gatales-text font-mono text-sm leading-relaxed resize-none focus:outline-none focus:ring-2 focus:ring-gatales-accent"
            placeholder="Escribe aqui las instrucciones para el asistente..."
          ></textarea>
        </div>

        <!-- Right: Tips & Templates Panel -->
        <div class="w-96 border-l border-gatales-border bg-gatales-sidebar overflow-y-auto">
          <!-- Model Info Header -->
          <div :class="['p-4 border-b border-gatales-border', isDeepSeek ? 'bg-blue-500/5' : 'bg-green-500/5']">
            <div class="flex items-center gap-3 mb-2">
              <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', isDeepSeek ? 'bg-blue-500/20' : 'bg-green-500/20']">
                <svg class="w-4 h-4" :class="isDeepSeek ? 'text-blue-400' : 'text-green-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <h3 class="text-sm font-semibold text-gatales-text">{{ isDeepSeek ? 'DeepSeek' : 'OpenAI GPT' }}</h3>
                <p class="text-xs text-gatales-text-secondary">{{ formData.model }}</p>
              </div>
            </div>
          </div>

          <div class="p-4 space-y-5">
            <!-- Model Capabilities -->
            <div>
              <h4 class="text-xs font-semibold text-gatales-text-secondary uppercase tracking-wider mb-3">Capacidades del modelo</h4>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                  <span class="text-gatales-text-secondary">Prompts largos</span>
                  <span :class="isDeepSeek ? 'text-yellow-400' : 'text-green-400'">{{ isDeepSeek ? 'Moderado' : 'Excelente' }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                  <span class="text-gatales-text-secondary">Seguir instrucciones</span>
                  <span :class="isDeepSeek ? 'text-green-400' : 'text-green-400'">{{ isDeepSeek ? 'Muy bueno' : 'Excelente' }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                  <span class="text-gatales-text-secondary">Creatividad</span>
                  <span class="text-green-400">{{ isDeepSeek ? 'Bueno' : 'Excelente' }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                  <span class="text-gatales-text-secondary">Costo por token</span>
                  <span :class="isDeepSeek ? 'text-green-400' : 'text-yellow-400'">{{ isDeepSeek ? 'Bajo' : 'Medio-Alto' }}</span>
                </div>
              </div>
            </div>

            <!-- Best Practices -->
            <div>
              <h4 class="text-xs font-semibold text-gatales-text-secondary uppercase tracking-wider mb-3">Mejores practicas</h4>
              <div v-if="isDeepSeek" class="space-y-2 text-xs text-gatales-text-secondary">
                <div class="flex gap-2">
                  <span class="text-blue-400 shrink-0">1.</span>
                  <span>Usa instrucciones <strong class="text-gatales-text">directas y concisas</strong></span>
                </div>
                <div class="flex gap-2">
                  <span class="text-blue-400 shrink-0">2.</span>
                  <span>Formato de <strong class="text-gatales-text">lista numerada</strong> para pasos</span>
                </div>
                <div class="flex gap-2">
                  <span class="text-blue-400 shrink-0">3.</span>
                  <span>Especifica el <strong class="text-gatales-text">idioma de respuesta</strong></span>
                </div>
                <div class="flex gap-2">
                  <span class="text-blue-400 shrink-0">4.</span>
                  <span>Evita prompts excesivamente largos</span>
                </div>
                <div class="flex gap-2">
                  <span class="text-blue-400 shrink-0">5.</span>
                  <span>Incluye <strong class="text-gatales-text">ejemplos breves</strong> si es necesario</span>
                </div>
              </div>
              <div v-else class="space-y-2 text-xs text-gatales-text-secondary">
                <div class="flex gap-2">
                  <span class="text-green-400 shrink-0">1.</span>
                  <span>Usa <strong class="text-gatales-text">## Encabezados</strong> para estructurar</span>
                </div>
                <div class="flex gap-2">
                  <span class="text-green-400 shrink-0">2.</span>
                  <span>Puedes ser <strong class="text-gatales-text">detallado y especifico</strong></span>
                </div>
                <div class="flex gap-2">
                  <span class="text-green-400 shrink-0">3.</span>
                  <span>Incluye <strong class="text-gatales-text">ejemplos de conversacion</strong></span>
                </div>
                <div class="flex gap-2">
                  <span class="text-green-400 shrink-0">4.</span>
                  <span>Define claramente <strong class="text-gatales-text">personalidad y tono</strong></span>
                </div>
                <div class="flex gap-2">
                  <span class="text-green-400 shrink-0">5.</span>
                  <span>Usa <strong class="text-gatales-text">markdown</strong> para formato</span>
                </div>
              </div>
            </div>

            <!-- Structure Guide -->
            <div>
              <h4 class="text-xs font-semibold text-gatales-text-secondary uppercase tracking-wider mb-3">Estructura recomendada</h4>
              <div class="space-y-1.5">
                <button
                  type="button"
                  @click="formData.system_prompt += '\n\n## ROL\n'"
                  class="w-full flex items-center gap-2 text-xs px-3 py-2 bg-gatales-input hover:bg-gatales-border rounded transition-colors text-left"
                >
                  <span class="w-2 h-2 rounded-full bg-green-500 shrink-0"></span>
                  <span class="text-gatales-text font-medium">ROL</span>
                  <span class="text-gatales-text-secondary ml-auto">Quien es</span>
                </button>
                <button
                  type="button"
                  @click="formData.system_prompt += '\n\n## PERSONALIDAD\n'"
                  class="w-full flex items-center gap-2 text-xs px-3 py-2 bg-gatales-input hover:bg-gatales-border rounded transition-colors text-left"
                >
                  <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
                  <span class="text-gatales-text font-medium">PERSONALIDAD</span>
                  <span class="text-gatales-text-secondary ml-auto">Tono y estilo</span>
                </button>
                <button
                  type="button"
                  @click="formData.system_prompt += '\n\n## INSTRUCCIONES\n'"
                  class="w-full flex items-center gap-2 text-xs px-3 py-2 bg-gatales-input hover:bg-gatales-border rounded transition-colors text-left"
                >
                  <span class="w-2 h-2 rounded-full bg-yellow-500 shrink-0"></span>
                  <span class="text-gatales-text font-medium">INSTRUCCIONES</span>
                  <span class="text-gatales-text-secondary ml-auto">Que hacer</span>
                </button>
                <button
                  type="button"
                  @click="formData.system_prompt += '\n\n## FORMATO\n'"
                  class="w-full flex items-center gap-2 text-xs px-3 py-2 bg-gatales-input hover:bg-gatales-border rounded transition-colors text-left"
                >
                  <span class="w-2 h-2 rounded-full bg-purple-500 shrink-0"></span>
                  <span class="text-gatales-text font-medium">FORMATO</span>
                  <span class="text-gatales-text-secondary ml-auto">Como responder</span>
                </button>
                <button
                  type="button"
                  @click="formData.system_prompt += '\n\n## RESTRICCIONES\n'"
                  class="w-full flex items-center gap-2 text-xs px-3 py-2 bg-gatales-input hover:bg-gatales-border rounded transition-colors text-left"
                >
                  <span class="w-2 h-2 rounded-full bg-red-500 shrink-0"></span>
                  <span class="text-gatales-text font-medium">RESTRICCIONES</span>
                  <span class="text-gatales-text-secondary ml-auto">Que evitar</span>
                </button>
              </div>
            </div>

            <!-- Templates -->
            <div>
              <h4 class="text-xs font-semibold text-gatales-text-secondary uppercase tracking-wider mb-3">Plantillas predefinidas</h4>
              <div class="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  @click="applyPromptTemplate('guiones')"
                  class="flex flex-col items-start gap-1 text-xs p-3 bg-gatales-input hover:bg-gatales-border rounded-lg transition-colors text-left"
                >
                  <span class="text-gatales-text font-medium">Guionista</span>
                  <span class="text-gatales-text-secondary text-[10px]">Videos y redes sociales</span>
                </button>
                <button
                  type="button"
                  @click="applyPromptTemplate('asistente')"
                  class="flex flex-col items-start gap-1 text-xs p-3 bg-gatales-input hover:bg-gatales-border rounded-lg transition-colors text-left"
                >
                  <span class="text-gatales-text font-medium">Asistente</span>
                  <span class="text-gatales-text-secondary text-[10px]">Uso general</span>
                </button>
                <button
                  type="button"
                  @click="applyPromptTemplate('experto')"
                  class="flex flex-col items-start gap-1 text-xs p-3 bg-gatales-input hover:bg-gatales-border rounded-lg transition-colors text-left"
                >
                  <span class="text-gatales-text font-medium">Experto</span>
                  <span class="text-gatales-text-secondary text-[10px]">Tecnico especializado</span>
                </button>
                <button
                  type="button"
                  @click="applyPromptTemplate('creativo')"
                  class="flex flex-col items-start gap-1 text-xs p-3 bg-gatales-input hover:bg-gatales-border rounded-lg transition-colors text-left"
                >
                  <span class="text-gatales-text font-medium">Creativo</span>
                  <span class="text-gatales-text-secondary text-[10px]">Escritura y contenido</span>
                </button>
              </div>
            </div>

            <!-- Model-specific notes -->
            <div :class="['p-3 rounded-lg text-xs', isDeepSeek ? 'bg-blue-500/10 border border-blue-500/20' : 'bg-green-500/10 border border-green-500/20']">
              <p :class="['font-medium mb-1', isDeepSeek ? 'text-blue-400' : 'text-green-400']">
                {{ isDeepSeek ? 'Nota para DeepSeek' : 'Nota para GPT' }}
              </p>
              <p v-if="isDeepSeek" class="text-gatales-text-secondary">
                DeepSeek funciona mejor con instrucciones concisas. Evita bloques de texto muy extensos y prioriza la claridad sobre el detalle.
              </p>
              <p v-else class="text-gatales-text-secondary">
                GPT puede procesar instrucciones complejas y detalladas. Aprovecha la estructuracion con markdown y ejemplos para mejores resultados.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="p-4 border-t border-gatales-border bg-gatales-sidebar flex justify-end gap-3">
        <button type="button" @click="showPromptFullscreen = false" class="btn-primary">
          Listo
        </button>
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
