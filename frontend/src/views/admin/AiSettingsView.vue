<script setup>
import { ref, onMounted, computed, defineComponent, h } from 'vue'
import { adminApi } from '../../services/api'

// SettingField component for rendering different field types
const SettingField = defineComponent({
  props: {
    setting: { type: Object, required: true }
  },
  emits: ['update'],
  setup(props, { emit }) {
    const updateValue = (value) => {
      emit('update', props.setting.key, value)
    }

    return () => {
      const { setting } = props

      // Select type
      if (setting.type === 'select') {
        return h('div', [
          h('label', { class: 'block text-sm font-medium text-gatales-text mb-1' }, setting.label),
          h('select', {
            value: setting.value,
            onChange: (e) => updateValue(e.target.value),
            class: 'input-field'
          }, Object.entries(setting.options || {}).map(([value, label]) =>
            h('option', { value, key: value }, label)
          )),
          h('p', { class: 'text-xs text-gatales-text-secondary mt-1' }, setting.description)
        ])
      }

      // Number type with range slider
      if (setting.type === 'number') {
        const min = setting.options?.min ?? 0
        const max = setting.options?.max ?? 100
        const step = setting.options?.step ?? 1
        const useSlider = max <= 10 || (max - min) <= 100

        return h('div', [
          h('label', { class: 'block text-sm font-medium text-gatales-text mb-1' }, setting.label),
          useSlider
            ? h('div', { class: 'flex items-center gap-4' }, [
                h('input', {
                  type: 'range',
                  value: setting.value,
                  onInput: (e) => updateValue(e.target.value),
                  min,
                  max,
                  step,
                  class: 'flex-1 accent-gatales-accent'
                }),
                h('span', { class: 'text-gatales-text w-20 text-right font-mono' }, setting.value)
              ])
            : h('input', {
                type: 'number',
                value: setting.value,
                onInput: (e) => updateValue(e.target.value),
                min,
                max,
                step,
                class: 'input-field w-32'
              }),
          h('p', { class: 'text-xs text-gatales-text-secondary mt-1' }, setting.description)
        ])
      }

      // String type
      if (setting.type === 'string') {
        return h('div', [
          h('label', { class: 'block text-sm font-medium text-gatales-text mb-1' }, setting.label),
          h('input', {
            type: 'text',
            value: setting.value,
            onInput: (e) => updateValue(e.target.value),
            class: 'input-field',
            placeholder: setting.description
          }),
          h('p', { class: 'text-xs text-gatales-text-secondary mt-1' }, setting.description)
        ])
      }

      // Text type (textarea)
      if (setting.type === 'text') {
        return h('div', [
          h('label', { class: 'block text-sm font-medium text-gatales-text mb-1' }, setting.label),
          h('textarea', {
            value: setting.value,
            onInput: (e) => updateValue(e.target.value),
            rows: 3,
            class: 'input-field',
            placeholder: setting.description
          }),
          h('p', { class: 'text-xs text-gatales-text-secondary mt-1' }, setting.description)
        ])
      }

      // Fallback for unknown types
      return h('div', [
        h('label', { class: 'block text-sm font-medium text-gatales-text mb-1' }, setting.label),
        h('input', {
          type: 'text',
          value: setting.value,
          onInput: (e) => updateValue(e.target.value),
          class: 'input-field'
        }),
        h('p', { class: 'text-xs text-gatales-text-secondary mt-1' }, setting.description)
      ])
    }
  }
})

const settings = ref([])
const isLoading = ref(true)
const isSaving = ref(false)
const isTesting = ref(false)
const error = ref(null)
const success = ref(null)
const testMessage = ref('Crea un hook corto para un video sobre productividad')
const testResponse = ref(null)

// Group settings by category
const groupedSettings = computed(() => {
  const groups = {
    model: { title: 'Modelo y Configuracion Basica', icon: 'cog', items: [] },
    prompt: { title: 'Instrucciones del Asistente', icon: 'document', items: [] },
    sampling: { title: 'Parametros de Muestreo', icon: 'adjustments', items: [] },
    response: { title: 'Opciones de Respuesta', icon: 'chat', items: [] },
    advanced: { title: 'Configuracion Avanzada', icon: 'code', items: [] },
    assistant: { title: 'Asistente y Contexto', icon: 'user', items: [] },
    email: { title: 'Supervision de Correos', icon: 'mail', items: [] },
  }

  const categoryMap = {
    // Model & Basic
    'model': 'model',
    'temperature': 'model',
    'max_tokens': 'model',
    // System Prompt
    'system_prompt': 'prompt',
    // Sampling
    'top_p': 'sampling',
    'frequency_penalty': 'sampling',
    'presence_penalty': 'sampling',
    // Response Options
    'response_format': 'response',
    'stop_sequences': 'response',
    'n_completions': 'response',
    // Advanced
    'seed': 'advanced',
    'stream': 'advanced',
    'logprobs': 'advanced',
    'include_user_id': 'advanced',
    'filter_unsafe_content': 'advanced',
    // Assistant & Context
    'assistant_name': 'assistant',
    'welcome_message': 'assistant',
    'context_messages': 'assistant',
    // Email Supervision
    'supervision_email': 'email',
  }

  settings.value.forEach(setting => {
    const category = categoryMap[setting.key] || 'advanced'
    groups[category].items.push(setting)
  })

  return groups
})

onMounted(async () => {
  await fetchSettings()
})

async function fetchSettings() {
  isLoading.value = true
  error.value = null
  try {
    const response = await adminApi.getAiSettings()
    settings.value = response.data.settings
  } catch (e) {
    error.value = 'Error al cargar la configuracion'
  } finally {
    isLoading.value = false
  }
}

async function saveSettings() {
  isSaving.value = true
  error.value = null
  success.value = null

  try {
    const settingsToSave = settings.value.map(s => ({
      key: s.key,
      value: s.value,
    }))

    await adminApi.updateAiSettings(settingsToSave)
    success.value = 'Configuracion guardada correctamente'

    setTimeout(() => {
      success.value = null
    }, 3000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar'
  } finally {
    isSaving.value = false
  }
}

async function testSettings() {
  isTesting.value = true
  testResponse.value = null
  error.value = null

  try {
    const response = await adminApi.testAiSettings(testMessage.value)
    testResponse.value = response.data
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al probar la configuracion'
  } finally {
    isTesting.value = false
  }
}

function getSettingValue(key) {
  const setting = settings.value.find(s => s.key === key)
  return setting?.value
}

function updateSetting(key, value) {
  const setting = settings.value.find(s => s.key === key)
  if (setting) {
    setting.value = value
  }
}
</script>

<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gatales-text">Configuracion de IA</h1>
      <button
        @click="saveSettings"
        :disabled="isSaving"
        class="btn-primary"
      >
        {{ isSaving ? 'Guardando...' : 'Guardar Cambios' }}
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

    <div v-else class="space-y-6">
      <!-- Model Configuration -->
      <div class="card">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 text-gatales-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <h2 class="text-lg font-semibold text-gatales-text">{{ groupedSettings.model.title }}</h2>
        </div>
        <div class="space-y-4">
          <template v-for="setting in groupedSettings.model.items" :key="setting.key">
            <SettingField :setting="setting" @update="updateSetting" />
          </template>
        </div>
      </div>

      <!-- System Prompt -->
      <div class="card">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 text-gatales-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <h2 class="text-lg font-semibold text-gatales-text">{{ groupedSettings.prompt.title }}</h2>
        </div>
        <div v-for="setting in groupedSettings.prompt.items" :key="setting.key">
          <label class="block text-sm font-medium text-gatales-text mb-1">
            {{ setting.label }}
          </label>
          <textarea
            :value="setting.value"
            @input="updateSetting(setting.key, $event.target.value)"
            rows="12"
            class="input-field font-mono text-sm"
          ></textarea>
          <p class="text-xs text-gatales-text-secondary mt-1">{{ setting.description }}</p>
        </div>
      </div>

      <!-- Sampling Parameters -->
      <div v-if="groupedSettings.sampling.items.length" class="card">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 text-gatales-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
          </svg>
          <h2 class="text-lg font-semibold text-gatales-text">{{ groupedSettings.sampling.title }}</h2>
        </div>
        <p class="text-sm text-gatales-text-secondary mb-4">
          Controlan como el modelo selecciona los siguientes tokens. Ajusta para obtener respuestas mas creativas o mas precisas.
        </p>
        <div class="space-y-4">
          <template v-for="setting in groupedSettings.sampling.items" :key="setting.key">
            <SettingField :setting="setting" @update="updateSetting" />
          </template>
        </div>
      </div>

      <!-- Response Options -->
      <div v-if="groupedSettings.response.items.length" class="card">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 text-gatales-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
          <h2 class="text-lg font-semibold text-gatales-text">{{ groupedSettings.response.title }}</h2>
        </div>
        <p class="text-sm text-gatales-text-secondary mb-4">
          Configuracion del formato y comportamiento de las respuestas generadas.
        </p>
        <div class="space-y-4">
          <template v-for="setting in groupedSettings.response.items" :key="setting.key">
            <SettingField :setting="setting" @update="updateSetting" />
          </template>
        </div>
      </div>

      <!-- Advanced Settings -->
      <div v-if="groupedSettings.advanced.items.length" class="card">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 text-gatales-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
          </svg>
          <h2 class="text-lg font-semibold text-gatales-text">{{ groupedSettings.advanced.title }}</h2>
        </div>
        <p class="text-sm text-gatales-text-secondary mb-4">
          Opciones avanzadas para desarrollo y depuracion. Modifica con precaucion.
        </p>
        <div class="space-y-4">
          <template v-for="setting in groupedSettings.advanced.items" :key="setting.key">
            <SettingField :setting="setting" @update="updateSetting" />
          </template>
        </div>
      </div>

      <!-- Assistant & Context -->
      <div v-if="groupedSettings.assistant.items.length" class="card">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 text-gatales-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          <h2 class="text-lg font-semibold text-gatales-text">{{ groupedSettings.assistant.title }}</h2>
        </div>
        <p class="text-sm text-gatales-text-secondary mb-4">
          Personaliza la identidad del asistente y cuanto contexto de conversacion se mantiene.
        </p>
        <div class="space-y-4">
          <template v-for="setting in groupedSettings.assistant.items" :key="setting.key">
            <SettingField :setting="setting" @update="updateSetting" />
          </template>
        </div>
      </div>

      <!-- Email Supervision -->
      <div v-if="groupedSettings.email.items.length" class="card">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 text-gatales-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <h2 class="text-lg font-semibold text-gatales-text">{{ groupedSettings.email.title }}</h2>
        </div>
        <p class="text-sm text-gatales-text-secondary mb-4">
          Configura un email de supervision para recibir una copia de todos los correos enviados por la aplicacion. Util para verificar que los emails se envian correctamente.
        </p>
        <div class="space-y-4">
          <template v-for="setting in groupedSettings.email.items" :key="setting.key">
            <SettingField :setting="setting" @update="updateSetting" />
          </template>
        </div>
        <div class="mt-4 p-3 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
          <p class="text-xs text-yellow-400">
            <strong>Nota:</strong> Los correos de supervision se envian como copia con el prefijo [SUPERVISION] en el asunto. Incluyen informacion del destinatario original.
          </p>
        </div>
      </div>

      <!-- Test Section -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gatales-text mb-4">Probar Configuracion</h2>
        <p class="text-sm text-gatales-text-secondary mb-4">
          Prueba la configuracion actual enviando un mensaje de prueba.
        </p>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gatales-text mb-1">
              Mensaje de prueba
            </label>
            <input
              v-model="testMessage"
              type="text"
              class="input-field"
              placeholder="Escribe un mensaje de prueba..."
            />
          </div>

          <button
            @click="testSettings"
            :disabled="isTesting || !testMessage"
            class="btn-secondary"
          >
            {{ isTesting ? 'Probando...' : 'Probar IA' }}
          </button>

          <!-- Test Response -->
          <div v-if="testResponse" class="mt-4 p-4 bg-gatales-input rounded-lg">
            <div class="flex justify-between items-start mb-2">
              <span class="text-sm font-medium text-gatales-accent">Respuesta:</span>
              <span class="text-xs text-gatales-text-secondary">
                {{ testResponse.tokens_used }} tokens | {{ testResponse.model }}
              </span>
            </div>
            <p class="text-gatales-text whitespace-pre-wrap">{{ testResponse.response }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
