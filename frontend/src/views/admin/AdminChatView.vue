<script setup>
import { ref, computed, nextTick, onMounted, watch } from 'vue'
import { adminApi } from '../../services/api'

const assistants = ref([])
const selectedAssistantId = ref(null)
const messages = ref([])
const newMessage = ref('')
const loading = ref(false)
const loadingAssistants = ref(true)
const messagesContainer = ref(null)
const tokenUsage = ref({ prompt: 0, completion: 0, total: 0, cost: 0 })
const lastMessageTokens = ref({ input: 0, output: 0, cost: 0 })

// Precios por millón de tokens (USD)
const MODEL_PRICING = {
  'gpt-5.2': { input: 1.25, output: 10.00 },
  'gpt-5.2-mini': { input: 0.25, output: 2.00 },
  'gpt-5.2-codex': { input: 1.50, output: 12.00 },
  'gpt-5.1': { input: 1.25, output: 10.00 },
  'gpt-5.1-mini': { input: 0.25, output: 2.00 },
  'gpt-5.1-codex': { input: 1.50, output: 12.00 },
  'gpt-5': { input: 1.25, output: 10.00 },
  'gpt-5-mini': { input: 0.25, output: 2.00 },
  'gpt-4o': { input: 2.50, output: 10.00 },
  'gpt-4o-mini': { input: 0.15, output: 0.60 },
  'gpt-4-turbo': { input: 10.00, output: 30.00 },
  'o1': { input: 15.00, output: 60.00 },
  'o1-mini': { input: 3.00, output: 12.00 },
}

function calculateCost(model, inputTokens, outputTokens) {
  const pricing = MODEL_PRICING[model] || MODEL_PRICING['gpt-4o-mini']
  const inputCost = (inputTokens / 1000000) * pricing.input
  const outputCost = (outputTokens / 1000000) * pricing.output
  return inputCost + outputCost
}

// Test conversations state
const testConversations = ref([])
const currentConversationId = ref(null)
const loadingConversations = ref(false)
const creatingConversation = ref(false)
const isStreaming = ref(false)
const isThinking = ref(false)

const selectedAssistant = computed(() => {
  return assistants.value.find(a => a.id === selectedAssistantId.value)
})

const currentConversation = computed(() => {
  return testConversations.value.find(c => c.id === currentConversationId.value)
})

const filteredConversations = computed(() => {
  if (!selectedAssistantId.value) return []
  return testConversations.value.filter(c => c.assistant_id === selectedAssistantId.value)
})

onMounted(async () => {
  await loadAssistants()
})

// Load conversations when assistant changes
watch(selectedAssistantId, async (newId) => {
  if (newId) {
    await loadTestConversations(newId)
  }
})

// Auto-scroll when messages change
watch(() => messages.value.length, () => {
  scrollToBottom()
})

async function loadAssistants() {
  loadingAssistants.value = true
  try {
    const response = await adminApi.getAssistants()
    assistants.value = response.data.assistants || response.data.data || []
    // Select first assistant by default
    if (assistants.value.length > 0 && !selectedAssistantId.value) {
      selectedAssistantId.value = assistants.value[0].id
    }
  } catch (error) {
    console.error('Error loading assistants:', error)
  } finally {
    loadingAssistants.value = false
  }
}

async function loadTestConversations(assistantId) {
  loadingConversations.value = true
  try {
    const response = await adminApi.getTestConversations(assistantId)
    testConversations.value = response.data.conversations || []

    // If there are conversations, select the first one
    if (testConversations.value.length > 0) {
      await selectConversation(testConversations.value[0].id)
    } else {
      currentConversationId.value = null
      messages.value = []
      tokenUsage.value = { prompt: 0, completion: 0, total: 0, cost: 0 }
      lastMessageTokens.value = { input: 0, output: 0, cost: 0 }
    }
  } catch (error) {
    console.error('Error loading test conversations:', error)
    testConversations.value = []
  } finally {
    loadingConversations.value = false
  }
}

function selectAssistant(id) {
  if (id !== selectedAssistantId.value) {
    selectedAssistantId.value = id
    currentConversationId.value = null
    messages.value = []
    tokenUsage.value = { prompt: 0, completion: 0, total: 0, cost: 0 }
    lastMessageTokens.value = { input: 0, output: 0, cost: 0 }
  }
}

async function selectConversation(id) {
  if (id === currentConversationId.value) return

  currentConversationId.value = id
  messages.value = []
  tokenUsage.value = { prompt: 0, completion: 0, total: 0, cost: 0 }
  lastMessageTokens.value = { input: 0, output: 0, cost: 0 }

  try {
    const response = await adminApi.getTestConversation(id)
    const conversation = response.data.conversation
    const messagesData = response.data.messages || []

    // Map messages (messages is a separate key in the response)
    messages.value = messagesData.map(m => ({
      id: m.id,
      role: m.role,
      content: m.content,
      timestamp: new Date(m.created_at),
      usedKnowledgeBase: m.used_knowledge_base || false
    }))

    // Set token usage with cost calculation
    const inputTokens = conversation.total_tokens_input || 0
    const outputTokens = conversation.total_tokens_output || 0
    const model = selectedAssistant.value?.model || 'gpt-4o-mini'
    const cost = calculateCost(model, inputTokens, outputTokens)

    tokenUsage.value = {
      prompt: inputTokens,
      completion: outputTokens,
      total: inputTokens + outputTokens,
      cost: cost
    }

    scrollToBottom()
  } catch (error) {
    console.error('Error loading conversation:', error)
  }
}

async function createNewConversation() {
  if (!selectedAssistantId.value || creatingConversation.value) return

  creatingConversation.value = true
  try {
    const response = await adminApi.createTestConversation(selectedAssistantId.value)
    const newConv = response.data.conversation

    // Add to list and select
    testConversations.value.unshift(newConv)
    currentConversationId.value = newConv.id
    messages.value = []
    tokenUsage.value = { prompt: 0, completion: 0, total: 0, cost: 0 }
    lastMessageTokens.value = { input: 0, output: 0, cost: 0 }
  } catch (error) {
    console.error('Error creating conversation:', error)
  } finally {
    creatingConversation.value = false
  }
}

async function deleteConversation(id) {
  if (!confirm('Eliminar esta conversacion de prueba?')) return

  try {
    await adminApi.deleteTestConversation(id)

    // Remove from list
    testConversations.value = testConversations.value.filter(c => c.id !== id)

    // If deleted current, select another or clear
    if (currentConversationId.value === id) {
      if (testConversations.value.length > 0) {
        await selectConversation(testConversations.value[0].id)
      } else {
        currentConversationId.value = null
        messages.value = []
        tokenUsage.value = { prompt: 0, completion: 0, total: 0, cost: 0 }
        lastMessageTokens.value = { input: 0, output: 0, cost: 0 }
      }
    }
  } catch (error) {
    console.error('Error deleting conversation:', error)
  }
}

async function clearAllConversations() {
  if (!selectedAssistantId.value) return
  if (!confirm('Eliminar TODAS las conversaciones de prueba de este asistente?')) return

  try {
    await adminApi.clearAllTestConversations(selectedAssistantId.value)
    testConversations.value = []
    currentConversationId.value = null
    messages.value = []
    tokenUsage.value = { prompt: 0, completion: 0, total: 0, cost: 0 }
    lastMessageTokens.value = { input: 0, output: 0, cost: 0 }
  } catch (error) {
    console.error('Error clearing conversations:', error)
  }
}

async function sendMessage() {
  if (!newMessage.value.trim() || loading.value || !selectedAssistantId.value) return

  // Create conversation if needed
  if (!currentConversationId.value) {
    await createNewConversation()
  }

  const userMessage = newMessage.value.trim()
  newMessage.value = ''

  // Add user message to UI
  const tempUserMsg = {
    id: Date.now(),
    role: 'user',
    content: userMessage,
    timestamp: new Date()
  }
  messages.value.push(tempUserMsg)

  // Add placeholder for streaming assistant message
  const assistantMsgId = Date.now() + 1
  const assistantMsg = {
    id: assistantMsgId,
    role: 'assistant',
    content: '',
    timestamp: new Date(),
    isStreaming: true,
    isThinking: true
  }
  messages.value.push(assistantMsg)

  scrollToBottom()
  loading.value = true
  isThinking.value = true
  isStreaming.value = false

  // Use streaming for all assistants (both Chat Completions and Responses API now support streaming)
  adminApi.sendTestConversationMessageStream(
    currentConversationId.value,
    userMessage,
    // onChunk
    (chunk) => {
      // Skip empty chunks
      if (!chunk || chunk.length === 0) return

      // First real chunk received - switch from thinking to streaming
      if (isThinking.value) {
        isThinking.value = false
        isStreaming.value = true
        const msgIdx = messages.value.findIndex(m => m.id === assistantMsgId)
        if (msgIdx !== -1) {
          messages.value[msgIdx].isThinking = false
        }
      }
      const msgIdx = messages.value.findIndex(m => m.id === assistantMsgId)
      if (msgIdx !== -1) {
        messages.value[msgIdx].content += chunk
      }
      scrollToBottom()
    },
    // onDone
    (data) => {
      loading.value = false
      isThinking.value = false
      isStreaming.value = false

      const msgIdx = messages.value.findIndex(m => m.id === assistantMsgId)
      if (msgIdx !== -1) {
        messages.value[msgIdx].id = data.message_id
        messages.value[msgIdx].isStreaming = false
        messages.value[msgIdx].isThinking = false
      }

      // Update token usage with detailed info
      if (data.tokens_input !== undefined && data.tokens_output !== undefined) {
        const cost = calculateCost(data.model || selectedAssistant.value?.model, data.tokens_input, data.tokens_output)

        // Update last message tokens
        lastMessageTokens.value = {
          input: data.tokens_input,
          output: data.tokens_output,
          cost: cost
        }

        // Update totals
        tokenUsage.value.prompt += data.tokens_input
        tokenUsage.value.completion += data.tokens_output
        tokenUsage.value.total += data.tokens_input + data.tokens_output
        tokenUsage.value.cost += cost
      } else if (data.tokens_used) {
        tokenUsage.value.total += data.tokens_used
      }

      // Update conversation in list
      if (data.conversation) {
        const idx = testConversations.value.findIndex(c => c.id === currentConversationId.value)
        if (idx >= 0) {
          testConversations.value[idx] = { ...testConversations.value[idx], ...data.conversation }
        }
      }

      scrollToBottom()
    },
    // onError
    (error) => {
      loading.value = false
      isThinking.value = false
      isStreaming.value = false

      messages.value = messages.value.filter(m => m.id !== tempUserMsg.id && m.id !== assistantMsgId)
      messages.value.push({
        id: Date.now() + 2,
        role: 'error',
        content: error.message || 'Error al enviar el mensaje',
        timestamp: new Date()
      })

      scrollToBottom()
    }
  )
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

function formatTime(date) {
  return new Date(date).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now = new Date()
  const diff = now - date

  if (diff < 60000) return 'Ahora'
  if (diff < 3600000) return `${Math.floor(diff / 60000)}m`
  if (diff < 86400000) return `${Math.floor(diff / 3600000)}h`
  return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })
}

function truncateTitle(title, maxLength = 30) {
  if (!title) return 'Nueva prueba'
  return title.length > maxLength ? title.substring(0, maxLength) + '...' : title
}

// Markdown formatting for message content
function formatMarkdown(content) {
  if (!content) return ''
  return content
    // Bold: **text** - handle multiline with [\s\S]
    .replace(/\*\*([\s\S]+?)\*\*/g, '<strong>$1</strong>')
    // Italic: *text* - handle multiline with [\s\S]
    .replace(/\*(?!\*)([\s\S]+?)\*(?!\*)/g, '<em>$1</em>')
    // Inline code: `code`
    .replace(/`([^`]+)`/g, '<code class="bg-gatales-bg px-1 py-0.5 rounded text-sm">$1</code>')
    // Newlines to breaks
    .replace(/\n/g, '<br>')
}
</script>

<template>
  <div class="h-full flex">
    <!-- Sidebar - Assistants list -->
    <div class="w-64 bg-gatales-sidebar border-r border-gatales-border flex flex-col shrink-0">
      <div class="p-4 border-b border-gatales-border">
        <h2 class="text-lg font-semibold text-gatales-text">Asistentes</h2>
        <p class="text-sm text-gatales-text-secondary mt-1">Selecciona uno para probar</p>
      </div>

      <div class="flex-1 overflow-y-auto p-3 space-y-2">
        <div v-if="loadingAssistants" class="flex justify-center py-8">
          <svg class="animate-spin h-6 w-6 text-gatales-accent" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <button
          v-else
          v-for="assistant in assistants"
          :key="assistant.id"
          @click="selectAssistant(assistant.id)"
          :class="[
            'w-full text-left p-3 rounded-lg transition-colors',
            selectedAssistantId === assistant.id
              ? 'bg-gatales-accent text-white'
              : 'bg-gatales-input text-gatales-text hover:bg-gatales-border'
          ]"
        >
          <div class="flex items-center gap-3">
            <div :class="[
              'w-10 h-10 rounded-full flex items-center justify-center text-lg shrink-0',
              selectedAssistantId === assistant.id ? 'bg-white/20' : 'bg-gatales-accent/20'
            ]">
              {{ assistant.assistant_display_name?.charAt(0) || assistant.name?.charAt(0) || '?' }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-medium truncate text-sm">{{ assistant.assistant_display_name || assistant.name }}</p>
              <p :class="[
                'text-xs truncate',
                selectedAssistantId === assistant.id ? 'text-white/70' : 'text-gatales-text-secondary'
              ]">
                {{ assistant.model }}
              </p>
            </div>
          </div>
          <div class="mt-2 flex items-center gap-2 text-xs">
            <span v-if="assistant.use_knowledge_base" :class="[
              'px-2 py-0.5 rounded',
              selectedAssistantId === assistant.id ? 'bg-white/20' : 'bg-gatales-accent/20 text-gatales-accent'
            ]">
              KB
            </span>
            <span v-if="assistant.is_default" :class="[
              'px-2 py-0.5 rounded',
              selectedAssistantId === assistant.id ? 'bg-white/20' : 'bg-green-500/20 text-green-400'
            ]">
              Default
            </span>
          </div>
        </button>
      </div>
    </div>

    <!-- Sidebar - Test Conversations History -->
    <div class="w-56 bg-gatales-bg border-r border-gatales-border flex flex-col shrink-0">
      <div class="p-3 border-b border-gatales-border">
        <button
          @click="createNewConversation"
          :disabled="!selectedAssistantId || creatingConversation"
          class="w-full flex items-center justify-center gap-2 px-3 py-2 bg-gatales-accent hover:bg-gatales-accent-hover text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Nueva prueba
        </button>
      </div>

      <div class="flex-1 overflow-y-auto">
        <div v-if="loadingConversations" class="flex justify-center py-8">
          <svg class="animate-spin h-5 w-5 text-gatales-accent" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <div v-else-if="filteredConversations.length === 0 && selectedAssistantId" class="p-4 text-center">
          <p class="text-gatales-text-secondary text-sm">No hay pruebas aun</p>
          <p class="text-gatales-text-secondary text-xs mt-1">Crea una nueva para comenzar</p>
        </div>

        <div v-else class="py-2 px-2 space-y-1">
          <div
            v-for="conv in filteredConversations"
            :key="conv.id"
            @click="selectConversation(conv.id)"
            :class="[
              'group relative flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer transition-colors',
              currentConversationId === conv.id
                ? 'bg-gatales-accent/10 text-gatales-accent'
                : 'hover:bg-gatales-input text-gatales-text'
            ]"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <div class="flex-1 min-w-0">
              <p class="text-xs truncate">{{ truncateTitle(conv.title) }}</p>
              <p class="text-[10px] text-gatales-text-secondary">
                {{ (conv.total_tokens_input || 0) + (conv.total_tokens_output || 0) }} tokens
              </p>
            </div>
            <span class="text-[10px] text-gatales-text-secondary shrink-0 group-hover:hidden">
              {{ formatDate(conv.last_message_at || conv.created_at) }}
            </span>
            <button
              @click.stop="deleteConversation(conv.id)"
              class="hidden group-hover:block p-1 rounded hover:bg-red-500/20 text-red-400 transition-colors"
            >
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Clear All Button -->
      <div v-if="filteredConversations.length > 0" class="p-2 border-t border-gatales-border">
        <button
          @click="clearAllConversations"
          class="w-full text-xs text-gatales-text-secondary hover:text-red-400 py-1.5 transition-colors"
        >
          Limpiar historial
        </button>
      </div>
    </div>

    <!-- Chat area -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Header -->
      <div class="p-4 border-b border-gatales-border flex items-center justify-between bg-gatales-sidebar">
        <div v-if="selectedAssistant" class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-full bg-gatales-accent flex items-center justify-center text-white text-lg shrink-0">
            {{ selectedAssistant.assistant_display_name?.charAt(0) || selectedAssistant.name?.charAt(0) || '?' }}
          </div>
          <div class="min-w-0">
            <h3 class="font-semibold text-gatales-text truncate">{{ selectedAssistant.assistant_display_name || selectedAssistant.name }}</h3>
            <p class="text-sm text-gatales-text-secondary">
              {{ selectedAssistant.model }} · Temp: {{ selectedAssistant.temperature }}
            </p>
          </div>
        </div>
        <div v-else class="text-gatales-text-secondary">
          Selecciona un asistente
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <!-- Last message tokens -->
          <div v-if="lastMessageTokens.input > 0 || lastMessageTokens.output > 0" class="text-xs bg-gatales-accent/10 text-gatales-accent px-2 py-1 rounded-lg">
            <span class="font-medium">Ultimo:</span>
            {{ lastMessageTokens.input.toLocaleString() }} in / {{ lastMessageTokens.output.toLocaleString() }} out
            <span class="text-green-400 ml-1">${{ lastMessageTokens.cost.toFixed(4) }}</span>
          </div>
          <!-- Total token usage -->
          <div v-if="tokenUsage.total > 0" class="text-xs text-gatales-text-secondary bg-gatales-input px-2 py-1 rounded-lg">
            <span class="text-gatales-text font-medium">Total:</span>
            {{ tokenUsage.prompt.toLocaleString() }} in / {{ tokenUsage.completion.toLocaleString() }} out
            <span class="text-green-400 ml-1">${{ tokenUsage.cost.toFixed(4) }}</span>
          </div>
        </div>
      </div>

      <!-- Messages -->
      <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-4">
        <!-- Empty state -->
        <div v-if="messages.length === 0 && selectedAssistant" class="h-full flex items-center justify-center">
          <div class="text-center max-w-md">
            <div class="w-16 h-16 rounded-full bg-gatales-accent/20 flex items-center justify-center text-gatales-accent text-2xl mx-auto mb-4">
              {{ selectedAssistant.assistant_display_name?.charAt(0) || selectedAssistant.name?.charAt(0) || '?' }}
            </div>
            <h3 class="text-lg font-semibold text-gatales-text mb-2">
              Probando: {{ selectedAssistant.assistant_display_name || selectedAssistant.name }}
            </h3>
            <p class="text-gatales-text-secondary mb-4">
              {{ selectedAssistant.welcome_message || 'Escribe un mensaje para probar este asistente.' }}
            </p>
            <div class="bg-gatales-input rounded-lg p-3 text-left text-sm">
              <p class="text-gatales-text-secondary mb-2">Configuracion:</p>
              <ul class="space-y-1 text-gatales-text">
                <li><span class="text-gatales-text-secondary">Modelo:</span> {{ selectedAssistant.model }}</li>
                <li><span class="text-gatales-text-secondary">Temperatura:</span> {{ selectedAssistant.temperature }}</li>
                <li><span class="text-gatales-text-secondary">Max tokens:</span> {{ selectedAssistant.max_tokens }}</li>
                <li v-if="selectedAssistant.use_knowledge_base">
                  <span class="text-gatales-accent">Base de conocimiento activa</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- No assistant selected -->
        <div v-else-if="!selectedAssistant && !loadingAssistants" class="h-full flex items-center justify-center">
          <div class="text-center">
            <svg class="w-16 h-16 text-gatales-text-secondary mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <p class="text-gatales-text-secondary">Selecciona un asistente de la lista</p>
          </div>
        </div>

        <!-- Messages list -->
        <template v-else>
          <div
            v-for="(message, index) in messages"
            :key="message.id || index"
            :class="[
              'flex',
              message.role === 'user' ? 'justify-end' : 'justify-start'
            ]"
          >
            <div
              :class="[
                'max-w-[80%] rounded-2xl px-4 py-2.5',
                message.role === 'user'
                  ? 'bg-gatales-accent text-white rounded-br-md'
                  : message.role === 'error'
                    ? 'bg-red-500/20 text-red-400 rounded-bl-md'
                    : 'bg-gatales-input text-gatales-text rounded-bl-md'
              ]"
            >
              <!-- Thinking indicator -->
              <div v-if="message.isThinking" class="thinking-indicator">
                <span class="thinking-icon">🍳</span>
                <span class="thinking-text">Cocinando respuesta</span>
                <span class="thinking-dots">
                  <span class="dot">.</span>
                  <span class="dot">.</span>
                  <span class="dot">.</span>
                </span>
              </div>
              <!-- Message content -->
              <div v-else class="message-content">
                <span v-html="formatMarkdown(message.content)"></span>
                <span v-if="message.isStreaming" class="streaming-cursor">|</span>
              </div>
              <div :class="[
                'flex items-center gap-2 text-xs mt-1',
                message.role === 'user' ? 'text-white/60' : 'text-gatales-text-secondary'
              ]">
                <span>{{ formatTime(message.timestamp) }}</span>
                <span v-if="message.usedKnowledgeBase" class="px-1.5 py-0.5 bg-gatales-accent/20 text-gatales-accent rounded text-[10px]">
                  KB
                </span>
              </div>
            </div>
          </div>

          <!-- Loading indicator (only show when not streaming/thinking, as those show content directly) -->
          <div v-if="loading && !isStreaming && !isThinking" class="flex justify-start">
            <div class="bg-gatales-input rounded-2xl rounded-bl-md px-4 py-3">
              <div class="flex items-center gap-1">
                <span class="w-2 h-2 bg-gatales-accent rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                <span class="w-2 h-2 bg-gatales-accent rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                <span class="w-2 h-2 bg-gatales-accent rounded-full animate-bounce" style="animation-delay: 300ms"></span>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Input area -->
      <div class="p-4 border-t border-gatales-border bg-gatales-sidebar">
        <form @submit.prevent="sendMessage" class="flex gap-3">
          <input
            v-model="newMessage"
            type="text"
            placeholder="Escribe un mensaje para probar..."
            :disabled="loading || !selectedAssistantId"
            class="flex-1 bg-gatales-input border border-gatales-border rounded-xl px-4 py-3 text-gatales-text placeholder-gatales-text-secondary focus:outline-none focus:ring-2 focus:ring-gatales-accent focus:border-transparent disabled:opacity-50"
          />
          <button
            type="submit"
            :disabled="loading || !newMessage.trim() || !selectedAssistantId"
            class="px-6 py-3 bg-gatales-accent text-white rounded-xl font-medium hover:bg-gatales-accent-hover disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
          >
            <span v-if="!loading">Enviar</span>
            <svg v-if="loading" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
          </button>
        </form>
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

/* Message content markdown styling */
.message-content {
  word-wrap: break-word;
}

.message-content :deep(strong) {
  font-weight: 600;
}

.message-content :deep(em) {
  font-style: italic;
}

.message-content :deep(code) {
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}
</style>
