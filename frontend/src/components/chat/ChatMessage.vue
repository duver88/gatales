<script setup>
import { computed } from 'vue'

const props = defineProps({
  message: {
    type: Object,
    required: true,
  },
})

const isUser = computed(() => props.message.role === 'user')
const isStreaming = computed(() => props.message.isStreaming === true)
const isThinking = computed(() => props.message.isThinking === true)

// Simple markdown-like formatting (basic implementation)
const formattedContent = computed(() => {
  let content = props.message.content || ''

  // Convert **bold** to <strong>
  content = content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')

  // Convert *italic* to <em>
  content = content.replace(/\*(.+?)\*/g, '<em>$1</em>')

  // Convert `code` to <code>
  content = content.replace(/`(.+?)`/g, '<code>$1</code>')

  // Convert newlines to <br>
  content = content.replace(/\n/g, '<br>')

  return content
})
</script>

<template>
  <div
    :class="[
      'px-3 sm:px-4 py-4 sm:py-6',
      isUser ? 'message-user' : 'message-assistant'
    ]"
  >
    <div class="flex items-start gap-2.5 sm:gap-4 max-w-3xl mx-auto">
      <!-- Avatar -->
      <div
        :class="[
          'w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center shrink-0',
          isUser ? 'bg-gatales-input' : 'bg-gatales-accent'
        ]"
      >
        <span v-if="isUser" class="text-gatales-text">
          <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </span>
        <span v-else class="text-white text-xs sm:text-sm font-medium">G</span>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0 overflow-hidden">
        <p class="text-[10px] sm:text-xs text-gatales-text-secondary mb-0.5 sm:mb-1">
          {{ isUser ? 'Tu' : 'Gatales' }}
        </p>
        <div class="text-sm sm:text-base text-gatales-text markdown-content prose prose-invert max-w-none break-words">
          <!-- Thinking indicator -->
          <div v-if="isThinking" class="thinking-indicator">
            <span class="thinking-icon">🍳</span>
            <span class="thinking-text">Cocinando respuesta</span>
            <span class="thinking-dots">
              <span class="dot">.</span>
              <span class="dot">.</span>
              <span class="dot">.</span>
            </span>
          </div>
          <!-- Message content -->
          <template v-else>
            <span v-html="formattedContent"></span>
            <span v-if="isStreaming" class="streaming-cursor">|</span>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.streaming-cursor {
  animation: blink 0.7s infinite;
  color: var(--gatales-accent, #22c55e);
  font-weight: bold;
}

@keyframes blink {
  0%, 50% { opacity: 1; }
  51%, 100% { opacity: 0; }
}

/* Thinking indicator */
.thinking-indicator {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--gatales-text-secondary, #9ca3af);
  padding: 4px 0;
}

.thinking-icon {
  animation: wobble 1s ease-in-out infinite;
}

.thinking-text {
  font-style: italic;
  font-size: 0.9em;
}

.thinking-dots {
  display: inline-flex;
}

@keyframes wobble {
  0%, 100% { transform: rotate(-5deg); }
  50% { transform: rotate(5deg); }
}

.thinking-dots .dot {
  animation: bounce 1.4s infinite ease-in-out both;
  font-weight: bold;
}

.thinking-dots .dot:nth-child(1) {
  animation-delay: 0s;
}

.thinking-dots .dot:nth-child(2) {
  animation-delay: 0.2s;
}

.thinking-dots .dot:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes bounce {
  0%, 80%, 100% {
    opacity: 0.3;
    transform: translateY(0);
  }
  40% {
    opacity: 1;
    transform: translateY(-3px);
  }
}
</style>
