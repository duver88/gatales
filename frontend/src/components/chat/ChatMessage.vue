<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  message: {
    type: Object,
    required: true,
  },
  userAvatar: {
    type: String,
    default: null,
  },
  userName: {
    type: String,
    default: 'U',
  },
  isLastMessage: {
    type: Boolean,
    default: false,
  },
  isGenerating: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['retry', 'resend'])

const isUser = computed(() => props.message.role === 'user')
const isStreaming = computed(() => props.message.isStreaming === true)
const isThinking = computed(() => props.message.isThinking === true)
const isFailed = computed(() => props.message.isFailed === true)
const isStopped = computed(() => props.message.isStopped === true)

// Show retry for failed/stopped assistant messages
const canRetry = computed(() => !isUser.value && (isFailed.value || isStopped.value))

// Show resend button for user message if it's the last message (no assistant response)
const canResend = computed(() =>
  isUser.value &&
  props.isLastMessage &&
  !props.isGenerating
)

function handleRetry() {
  emit('retry')
}

function handleResend() {
  emit('resend')
}

// Track copied state for code blocks
const copiedIndex = ref(null)

// Copy code to clipboard
function copyCode(code, index) {
  navigator.clipboard.writeText(code).then(() => {
    copiedIndex.value = index
    setTimeout(() => {
      copiedIndex.value = null
    }, 2000)
  })
}

// Cache for formatted content
const formatCache = new Map()

// Professional markdown formatting
function formatMarkdown(content) {
  if (!content) return ''

  // Use cache for non-streaming content
  const cacheKey = content + (isStreaming.value ? '_streaming' : '')
  if (!isStreaming.value && formatCache.has(cacheKey)) {
    return formatCache.get(cacheKey)
  }

  let result = content

  // Escape HTML first to prevent XSS
  result = result
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')

  // Code blocks with language support: ```lang\ncode\n```
  let codeBlockIndex = 0
  result = result.replace(/```(\w*)\n([\s\S]*?)```/g, (match, lang, code) => {
    const index = codeBlockIndex++
    const langLabel = lang || 'code'
    const escapedCode = code.trim()
    return `<div class="code-block-wrapper" data-code-index="${index}">
      <div class="code-block-header">
        <span class="code-lang">${langLabel}</span>
        <button class="copy-btn" onclick="window.copyCodeBlock(${index}, this)" data-code="${encodeURIComponent(escapedCode)}">
          <svg class="copy-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
          <span class="copy-text">Copiar</span>
        </button>
      </div>
      <pre class="code-block"><code>${escapedCode}</code></pre>
    </div>`
  })

  // Inline code: `code` (but not inside code blocks)
  result = result.replace(/`([^`\n]+)`/g, '<code class="inline-code">$1</code>')

  // Headers: ### Header (must be at start of line)
  result = result.replace(/^### (.+)$/gm, '<h4 class="md-h4">$1</h4>')
  result = result.replace(/^## (.+)$/gm, '<h3 class="md-h3">$1</h3>')
  result = result.replace(/^# (.+)$/gm, '<h2 class="md-h2">$1</h2>')

  // Bold: **text**
  result = result.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')

  // Italic: *text* (but not ** which is bold)
  result = result.replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, '<em>$1</em>')

  // Strikethrough: ~~text~~
  result = result.replace(/~~([^~]+)~~/g, '<del>$1</del>')

  // Links: [text](url)
  result = result.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="md-link">$1</a>')

  // Horizontal rule: --- or ***
  result = result.replace(/^(-{3,}|\*{3,})$/gm, '<hr class="md-hr">')

  // Blockquotes: > text
  result = result.replace(/^&gt; (.+)$/gm, '<blockquote class="md-blockquote">$1</blockquote>')

  // Unordered lists: - item or * item
  result = result.replace(/^[\-\*] (.+)$/gm, '<li class="md-li">$1</li>')
  // Collapse blank lines between consecutive list items so they group together
  result = result.replace(/(<\/li>)\n+(<li class="md-li">)/g, '$1\n$2')
  result = result.replace(/(<li class="md-li">.*?<\/li>(\n|$))+/g, '<ul class="md-ul">$&</ul>')

  // Ordered lists: 1. item
  result = result.replace(/^\d+\. (.+)$/gm, '<li class="md-oli">$1</li>')
  // Collapse blank lines between consecutive ordered list items
  result = result.replace(/(<\/li>)\n+(<li class="md-oli">)/g, '$1\n$2')
  result = result.replace(/(<li class="md-oli">.*?<\/li>(\n|$))+/g, '<ol class="md-ol">$&</ol>')

  // Convert remaining newlines to <br> (but not inside code blocks or lists)
  result = result.replace(/\n/g, '<br>')

  // Clean up extra <br> around block elements
  result = result.replace(/<br>(<\/?(?:ul|ol|li|h[234]|blockquote|hr|div|pre))/g, '$1')
  result = result.replace(/(<\/(?:ul|ol|li|h[234]|blockquote|hr|div|pre)>)<br>/g, '$1')

  // Cache result (limit cache size)
  if (!isStreaming.value && content.length < 10000) {
    formatCache.set(cacheKey, result)
    if (formatCache.size > 50) {
      const firstKey = formatCache.keys().next().value
      formatCache.delete(firstKey)
    }
  }

  return result
}

const formattedContent = computed(() => formatMarkdown(props.message.content))

// Global function for copy button (needed because v-html doesn't support @click)
if (typeof window !== 'undefined') {
  window.copyCodeBlock = (index, button) => {
    const code = decodeURIComponent(button.dataset.code)
    navigator.clipboard.writeText(code).then(() => {
      const textEl = button.querySelector('.copy-text')
      if (textEl) {
        textEl.textContent = 'Copiado!'
        setTimeout(() => {
          textEl.textContent = 'Copiar'
        }, 2000)
      }
    })
  }
}
</script>

<template>
  <div
    :class="[
      'px-3 sm:px-4 py-3 sm:py-5 transition-colors',
      isUser ? 'message-user' : 'message-assistant'
    ]"
  >
    <div class="flex items-start gap-2 sm:gap-3.5 max-w-3xl mx-auto">
      <!-- Avatar -->
      <div
        :class="[
          'w-6 h-6 sm:w-8 sm:h-8 rounded-full flex items-center justify-center shrink-0 overflow-hidden ring-1.5 sm:ring-2 ring-offset-1 sm:ring-offset-2 ring-offset-transparent',
          isUser ? 'bg-gatales-input ring-gatales-border' : 'bg-white ring-gatales-accent/30'
        ]"
      >
        <!-- User avatar -->
        <template v-if="isUser">
          <img v-if="userAvatar" :src="userAvatar" class="w-full h-full object-cover" alt="Avatar" />
          <span v-else class="text-gatales-text text-xs sm:text-sm font-medium">
            {{ userName.charAt(0).toUpperCase() }}
          </span>
        </template>
        <!-- Assistant avatar (logo) -->
        <img v-else src="/logo-64.png" alt="El Cursales" class="w-4 h-4 sm:w-6 sm:h-6 object-contain" loading="lazy" />
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0 overflow-hidden">
        <p class="text-[11px] sm:text-xs text-gatales-text-secondary mb-0.5 sm:mb-1.5 font-medium">
          {{ isUser ? 'Tu' : 'El Cursales' }}
        </p>
        <div class="text-base text-gatales-text markdown-content leading-relaxed">
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
          <div v-else class="message-text">
            <div v-html="formattedContent"></div>
            <span v-if="isStreaming" class="streaming-cursor">|</span>

            <!-- Failed/Stopped indicator -->
            <div v-if="isFailed || isStopped" class="mt-3">
              <!-- Error message for failed -->
              <div v-if="isFailed && message.errorMessage" class="text-red-400 text-sm mb-2 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ message.errorMessage }}</span>
              </div>
              <!-- Stopped indicator -->
              <div v-else-if="isStopped" class="text-yellow-400 text-sm mb-2 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6v4H9z" />
                </svg>
                <span>Respuesta interrumpida</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Retry button for failed/stopped assistant messages -->
        <div v-if="canRetry" class="mt-2 sm:mt-3 flex justify-start gap-2">
          <button
            @click="handleRetry"
            class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1.5 text-xs font-medium text-red-400 bg-red-500/10 hover:bg-red-500/20 active:bg-red-500/30 border border-red-500/30 rounded-lg transition-colors touch-manipulation"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Reintentar
          </button>
        </div>

        <!-- Resend button for user message if no response received -->
        <div v-if="canResend" class="mt-2 sm:mt-3 flex justify-start">
          <button
            @click="handleResend"
            class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1.5 text-xs font-medium text-amber-400 bg-amber-500/10 hover:bg-amber-500/20 active:bg-amber-500/30 border border-amber-500/30 rounded-lg transition-colors touch-manipulation"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
            Reenviar mensaje
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Streaming cursor */
.streaming-cursor {
  animation: blink 0.7s infinite;
  color: var(--gatales-accent, #22c55e);
  font-weight: bold;
  margin-left: 2px;
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

.thinking-dots .dot:nth-child(1) { animation-delay: 0s; }
.thinking-dots .dot:nth-child(2) { animation-delay: 0.2s; }
.thinking-dots .dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes bounce {
  0%, 80%, 100% { opacity: 0.3; transform: translateY(0); }
  40% { opacity: 1; transform: translateY(-3px); }
}

/* Message text container */
.message-text {
  word-wrap: break-word;
  overflow-wrap: break-word;
  word-break: break-word;
}

/* Markdown styles */
.markdown-content :deep(.md-h2) {
  font-size: 1.25em;
  font-weight: 700;
  margin: 1em 0 0.5em 0;
  color: var(--gatales-text, #ffffff);
}

.markdown-content :deep(.md-h3) {
  font-size: 1.125em;
  font-weight: 600;
  margin: 0.875em 0 0.375em 0;
  color: var(--gatales-text, #ffffff);
}

.markdown-content :deep(.md-h4) {
  font-size: 1em;
  font-weight: 600;
  margin: 0.75em 0 0.25em 0;
  color: var(--gatales-text, #ffffff);
}

.markdown-content :deep(.inline-code) {
  background: var(--gatales-input, #374151);
  padding: 0.125em 0.375em;
  border-radius: 4px;
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  font-size: 0.875em;
  color: #f472b6;
}

.markdown-content :deep(.code-block-wrapper) {
  margin: 0.75em 0;
  border-radius: 8px;
  overflow: hidden;
  background: #1e1e1e;
  border: 1px solid var(--gatales-border, #374151);
  max-width: 100%;
}

.markdown-content :deep(.code-block-header) {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5em 0.75em;
  background: #2d2d2d;
  border-bottom: 1px solid #3d3d3d;
}

.markdown-content :deep(.code-lang) {
  font-size: 0.75em;
  color: #9ca3af;
  font-weight: 500;
  text-transform: lowercase;
}

.markdown-content :deep(.copy-btn) {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 0.25em 0.5em;
  background: transparent;
  border: 1px solid #4b5563;
  border-radius: 4px;
  color: #9ca3af;
  font-size: 0.7em;
  cursor: pointer;
  transition: all 0.15s;
}

.markdown-content :deep(.copy-btn:hover) {
  background: #374151;
  color: #ffffff;
  border-color: #6b7280;
}

.markdown-content :deep(.code-block) {
  margin: 0;
  padding: 0.75em;
  overflow-x: auto;
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  font-size: 0.75em;
  line-height: 1.5;
  color: #d4d4d4;
  -webkit-overflow-scrolling: touch;
}

@media (min-width: 640px) {
  .markdown-content :deep(.code-block) {
    padding: 1em;
    font-size: 0.8125em;
  }
}

.markdown-content :deep(.code-block code) {
  background: transparent;
  padding: 0;
  color: inherit;
}

.markdown-content :deep(.md-link) {
  color: var(--gatales-accent, #22c55e);
  text-decoration: underline;
  text-underline-offset: 2px;
}

.markdown-content :deep(.md-link:hover) {
  opacity: 0.8;
}

.markdown-content :deep(.md-ul),
.markdown-content :deep(.md-ol) {
  margin: 0.5em 0;
  padding-left: 1.5em;
}

.markdown-content :deep(.md-ul) {
  list-style-type: disc;
}

.markdown-content :deep(.md-ol) {
  list-style-type: decimal;
}

.markdown-content :deep(.md-li),
.markdown-content :deep(.md-oli) {
  margin: 0.25em 0;
  padding-left: 0.25em;
}

.markdown-content :deep(.md-blockquote) {
  margin: 0.5em 0;
  padding: 0.5em 1em;
  border-left: 3px solid var(--gatales-accent, #22c55e);
  background: var(--gatales-input, #374151);
  border-radius: 0 4px 4px 0;
  font-style: italic;
  color: var(--gatales-text-secondary, #9ca3af);
}

.markdown-content :deep(.md-hr) {
  margin: 1em 0;
  border: none;
  border-top: 1px solid var(--gatales-border, #374151);
}

.markdown-content :deep(strong) {
  font-weight: 600;
  color: var(--gatales-text, #ffffff);
}

/* Add spacing before bold text that starts after a line break (acts as paragraph separator) */
.markdown-content :deep(br + strong) {
  display: inline-block;
  margin-top: 0.5em;
}

.markdown-content :deep(em) {
  font-style: italic;
}

.markdown-content :deep(del) {
  text-decoration: line-through;
  opacity: 0.7;
}
</style>
