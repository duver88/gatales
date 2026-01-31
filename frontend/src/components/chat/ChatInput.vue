<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
  isStreaming: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['send', 'stop'])

const message = ref('')
const textarea = ref(null)

const canSend = computed(() => message.value.trim().length > 0 && !props.disabled)

function handleSubmit() {
  if (!canSend.value) return

  emit('send', message.value)
  message.value = ''

  // Reset textarea height
  if (textarea.value) {
    textarea.value.style.height = 'auto'
  }
}

function handleKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    handleSubmit()
  }
}

function autoResize() {
  if (textarea.value) {
    textarea.value.style.height = 'auto'
    textarea.value.style.height = Math.min(textarea.value.scrollHeight, 200) + 'px'
  }
}
</script>

<template>
  <div class="border-t border-gatales-border bg-gatales-bg p-2 sm:p-4 safe-area-bottom">
    <div class="max-w-3xl mx-auto">
      <form @submit.prevent="handleSubmit" class="relative">
        <textarea
          ref="textarea"
          v-model="message"
          @keydown="handleKeydown"
          @input="autoResize"
          :disabled="disabled"
          placeholder="Escribe tu mensaje..."
          rows="1"
          class="w-full bg-gatales-input text-gatales-text text-base rounded-xl py-2.5 sm:py-3 pl-3 sm:pl-4 pr-11 sm:pr-12
                 border border-gatales-border focus:border-gatales-accent
                 focus:outline-none focus:ring-1 focus:ring-gatales-accent
                 placeholder-gatales-text-secondary resize-none
                 disabled:opacity-50 disabled:cursor-not-allowed"
        />

        <!-- Stop button (shown when streaming) -->
        <button
          v-if="isStreaming"
          type="button"
          @click="emit('stop')"
          class="absolute right-1.5 sm:right-2 bottom-1.5 sm:bottom-2 p-2 rounded-lg
                 bg-red-500 text-white
                 hover:bg-red-600 active:scale-95
                 transition-all touch-manipulation"
          title="Detener respuesta"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6h12v12H6z" />
          </svg>
        </button>

        <!-- Send button (hidden when streaming) -->
        <button
          v-else
          type="submit"
          :disabled="!canSend"
          class="absolute right-1.5 sm:right-2 bottom-1.5 sm:bottom-2 p-2 rounded-lg
                 bg-gatales-accent text-white
                 hover:bg-gatales-accent-hover active:scale-95
                 disabled:opacity-50 disabled:cursor-not-allowed
                 transition-all touch-manipulation"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
          </svg>
        </button>
      </form>

      <p class="hidden sm:block text-xs text-gatales-text-secondary text-center mt-2">
        Presiona Enter para enviar, Shift+Enter para nueva linea
      </p>
    </div>
  </div>
</template>
