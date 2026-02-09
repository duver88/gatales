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
    const maxHeight = window.innerWidth < 640 ? 120 : 200
    textarea.value.style.height = Math.min(textarea.value.scrollHeight, maxHeight) + 'px'
  }
}
</script>

<template>
  <div class="border-t border-gatales-border bg-gatales-bg pt-3 sm:pt-4 pb-safe-input px-3 sm:px-6 shrink-0">
    <div class="max-w-3xl mx-auto">
      <form @submit.prevent="handleSubmit" class="flex items-end gap-3">
        <textarea
          ref="textarea"
          v-model="message"
          @keydown="handleKeydown"
          @input="autoResize"
          :disabled="disabled"
          placeholder="Escribe tu mensaje..."
          rows="1"
          class="flex-1 bg-gatales-input text-gatales-text text-base rounded-2xl py-3 px-4
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
          class="w-11 h-11 shrink-0 flex items-center justify-center rounded-xl self-center
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
          class="w-11 h-11 shrink-0 flex items-center justify-center rounded-xl self-center
                 bg-gatales-accent text-white
                 hover:bg-gatales-accent-hover active:scale-95
                 disabled:opacity-30 disabled:cursor-not-allowed
                 transition-all touch-manipulation"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
          </svg>
        </button>
      </form>

      <p class="text-[10px] sm:text-[11px] text-gatales-text-secondary/30 text-center mt-2 select-none">
        elcursales.ai
      </p>
    </div>
  </div>
</template>
