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

// Filter only email-related settings
const emailSettings = computed(() => {
  return settings.value.filter(s => s.key === 'supervision_email')
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
    // Only save email-related settings
    const settingsToSave = emailSettings.value.map(s => ({
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
      <h1 class="text-2xl font-bold text-gatales-text">Configuracion</h1>
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
      <!-- Email Supervision -->
      <div v-if="emailSettings.length" class="card">
        <div class="flex items-center gap-2 mb-4">
          <svg class="w-5 h-5 text-gatales-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <h2 class="text-lg font-semibold text-gatales-text">Supervision de Correos</h2>
        </div>
        <p class="text-sm text-gatales-text-secondary mb-4">
          Configura un email de supervision para recibir una copia de todos los correos enviados por la aplicacion. Util para verificar que los emails se envian correctamente.
        </p>
        <div class="space-y-4">
          <template v-for="setting in emailSettings" :key="setting.key">
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
        <h2 class="text-lg font-semibold text-gatales-text mb-4">Probar Configuracion IA</h2>
        <p class="text-sm text-gatales-text-secondary mb-4">
          Prueba la configuracion actual del asistente enviando un mensaje de prueba.
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
