import { defineStore } from 'pinia'
import { ref, computed, nextTick } from 'vue'
import { chatApi } from '../services/api'
import { useAuthStore } from './auth'
import { useConversationsStore } from './conversations'

// Model pricing per million tokens (synced with backend TokenService)
const MODEL_PRICING = {
  // OpenAI models
  'gpt-5.2': { input: 5.00, output: 15.00 },
  'gpt-5.2-mini': { input: 0.30, output: 1.20 },
  'gpt-5.2-codex': { input: 3.00, output: 12.00 },
  'gpt-5.1': { input: 4.00, output: 12.00 },
  'gpt-5.1-mini': { input: 0.25, output: 1.00 },
  'gpt-5.1-codex': { input: 2.50, output: 10.00 },
  'gpt-5': { input: 3.00, output: 10.00 },
  'gpt-5-mini': { input: 0.20, output: 0.80 },
  'gpt-4o': { input: 2.50, output: 10.00 },
  'gpt-4o-mini': { input: 0.15, output: 0.60 },
  'gpt-4-turbo': { input: 10.00, output: 30.00 },
  'gpt-4': { input: 30.00, output: 60.00 },
  'gpt-3.5-turbo': { input: 0.50, output: 1.50 },
  'o1': { input: 15.00, output: 60.00 },
  'o1-mini': { input: 3.00, output: 12.00 },
  // DeepSeek models
  'deepseek-chat': { input: 0.14, output: 0.28 },
  'deepseek-reasoner': { input: 0.55, output: 2.19 },
}

function calculateCost(model, inputTokens, outputTokens) {
  const pricing = MODEL_PRICING[model] || MODEL_PRICING['gpt-4o-mini']
  const inputCost = (inputTokens / 1000000) * pricing.input
  const outputCost = (outputTokens / 1000000) * pricing.output
  return inputCost + outputCost
}

// Unique ID generator to prevent collisions
let idCounter = 0
function generateTempId(prefix = 'temp') {
  return `${prefix}_${Date.now()}_${++idCounter}_${Math.random().toString(36).substr(2, 9)}`
}

export const useChatStore = defineStore('chat', () => {
  const messages = ref([])
  const isLoading = ref(false)
  const isSending = ref(false)
  const error = ref(null)
  const tokensExhausted = ref(false)
  const tokensExhaustedData = ref(null)
  const freePlanBlocked = ref(false)
  const freePlanData = ref(null)

  // Assistant state
  const currentAssistant = ref(null)
  const availableAssistants = ref([])
  const isChangingAssistant = ref(false)

  // Caching for assistants (they don't change often)
  let assistantsFetchPromise = null
  let assistantsLastFetch = 0
  const ASSISTANTS_CACHE_DURATION = 300000 // 5 minutes cache

  // Conversation state
  const currentConversationId = ref(null)
  const currentConversation = ref(null)

  const hasMessages = computed(() => messages.value.length > 0)

  /**
   * Fetch messages for a specific conversation
   */
  async function fetchMessages(conversationId = null) {
    isLoading.value = true
    error.value = null

    try {
      let response
      if (conversationId) {
        // Fetch messages for specific conversation
        response = await chatApi.getConversationMessages(conversationId)
        currentConversationId.value = conversationId
        currentConversation.value = response.data.conversation
      } else {
        // Legacy: get messages (will use or create default conversation)
        response = await chatApi.getMessages()
        if (response.data.conversation_id) {
          currentConversationId.value = response.data.conversation_id
        }
      }

      messages.value = response.data.messages

      // Set lastUserMessageContent for regenerate functionality
      // Find the last user message in the loaded messages
      const lastUserMsg = [...response.data.messages].reverse().find(m => m.role === 'user')
      if (lastUserMsg) {
        lastUserMessageContent.value = lastUserMsg.content
      }

      // Store assistant info
      if (response.data.assistant) {
        currentAssistant.value = response.data.assistant
      }

      // Update tokens balance
      const authStore = useAuthStore()
      authStore.updateTokensBalance(response.data.tokens_balance)

      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Error al cargar los mensajes'
      throw e
    } finally {
      isLoading.value = false
    }
  }

  async function fetchAvailableAssistants(force = false) {
    const now = Date.now()

    // Return cached data if available
    if (!force && availableAssistants.value.length > 0 && (now - assistantsLastFetch) < ASSISTANTS_CACHE_DURATION) {
      return { assistants: availableAssistants.value }
    }

    // Deduplicate concurrent requests
    if (assistantsFetchPromise) {
      return assistantsFetchPromise
    }

    assistantsFetchPromise = (async () => {
      try {
        const response = await chatApi.getAvailableAssistants()
        availableAssistants.value = response.data.assistants
        assistantsLastFetch = Date.now()
        return response.data
      } catch (e) {
        console.error('Error fetching assistants:', e)
        throw e
      } finally {
        assistantsFetchPromise = null
      }
    })()

    return assistantsFetchPromise
  }

  async function changeAssistant(assistantId) {
    isChangingAssistant.value = true
    error.value = null

    try {
      const response = await chatApi.changeMyAssistant(assistantId)

      // Update current assistant
      currentAssistant.value = response.data.assistant

      // Clear messages (backend already clears history)
      messages.value = []

      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Error al cambiar asistente'
      throw e
    } finally {
      isChangingAssistant.value = false
    }
  }

  // Current streaming message for real-time updates
  const streamingContent = ref('')
  const isStreaming = ref(false)
  const isThinking = ref(false) // Shows "thinking" before streaming starts

  // Track last user message for retry functionality
  const lastUserMessageContent = ref('')
  const lastFailedMessageId = ref(null)

  // Token usage tracking for last message
  const lastMessageTokens = ref({
    input: 0,
    output: 0,
    model: null,
    cost: 0
  })

  /**
   * Stop current streaming response
   */
  function stopStreaming() {
    const stopped = chatApi.stopStream()
    if (stopped) {
      isStreaming.value = false
      isThinking.value = false
      isSending.value = false
      // Mark the streaming message as stopped (user can regenerate)
      const streamingMsg = messages.value.find(m => m.isStreaming)
      if (streamingMsg) {
        streamingMsg.isStreaming = false
        streamingMsg.isThinking = false
        streamingMsg.isStopped = true
        streamingMsg.content = streamingContent.value || ''
        lastFailedMessageId.value = streamingMsg.id
      }
    }
    return stopped
  }

  /**
   * Retry a failed or stopped response (re-sends the user message)
   */
  async function retryLastMessage() {
    if (!lastUserMessageContent.value || isSending.value) return

    // Find and remove the failed assistant message
    const failedMsgIndex = messages.value.findIndex(m =>
      m.isFailed || m.isStopped || m.id === lastFailedMessageId.value
    )
    if (failedMsgIndex !== -1) {
      messages.value.splice(failedMsgIndex, 1)
    }

    // Also remove the corresponding user message (it will be re-added by sendMessageStream)
    const lastUserMsgIndex = messages.value.length - 1
    if (lastUserMsgIndex >= 0 && messages.value[lastUserMsgIndex].role === 'user') {
      messages.value.splice(lastUserMsgIndex, 1)
    }

    // Clear error state
    error.value = null
    lastFailedMessageId.value = null

    // Resend the message
    return sendMessageStream(lastUserMessageContent.value, currentConversationId.value)
  }

  /**
   * Regenerate the last assistant response without duplicating the user message
   */
  async function regenerateResponse() {
    if (isSending.value || !currentConversationId.value) return

    isSending.value = true
    isThinking.value = true
    isStreaming.value = false
    streamingContent.value = ''
    error.value = null

    // Remove the last assistant message from UI (backend will also delete it)
    const lastAssistantIdx = messages.value.length - 1
    if (lastAssistantIdx >= 0 && messages.value[lastAssistantIdx].role === 'assistant') {
      messages.value.splice(lastAssistantIdx, 1)
    }

    // Add placeholder for streaming assistant message
    const assistantMessageId = generateTempId('regen')
    const assistantMessage = {
      id: assistantMessageId,
      role: 'assistant',
      content: '',
      created_at: new Date().toISOString(),
      isStreaming: true,
      isThinking: true,
    }
    messages.value.push(assistantMessage)

    await nextTick()

    return new Promise((resolve, reject) => {
      chatApi.regenerateStream(
        currentConversationId.value,
        // onChunk
        (chunk) => {
          if (!chunk || chunk.length === 0) return
          if (isThinking.value) {
            isThinking.value = false
            isStreaming.value = true
          }
          streamingContent.value += chunk
          const msgIndex = messages.value.findIndex(m => m.id === assistantMessageId)
          if (msgIndex !== -1) {
            messages.value[msgIndex] = {
              ...messages.value[msgIndex],
              content: streamingContent.value,
              isThinking: false,
              isStreaming: true,
            }
          }
        },
        // onDone
        (data) => {
          isThinking.value = false
          isStreaming.value = false
          isSending.value = false

          const msgIndex = messages.value.findIndex(m => m.id === assistantMessageId)
          if (msgIndex !== -1) {
            messages.value[msgIndex].id = data.message_id
            messages.value[msgIndex].isStreaming = false
            messages.value[msgIndex].isThinking = false
          }

          if (data.conversation) {
            currentConversation.value = data.conversation
          }

          if (data.tokens_input !== undefined && data.tokens_output !== undefined) {
            const cost = calculateCost(data.model, data.tokens_input, data.tokens_output)
            lastMessageTokens.value = {
              input: data.tokens_input,
              output: data.tokens_output,
              model: data.model,
              cost: cost
            }
          }

          const authStore = useAuthStore()
          authStore.updateTokensBalance(data.tokens_balance)

          resolve(data)
        },
        // onError
        (errorData) => {
          isThinking.value = false
          isStreaming.value = false
          isSending.value = false

          const errorMsg = errorData.message || 'Error al regenerar la respuesta'
          error.value = errorMsg

          const msgIndex = messages.value.findIndex(m => m.id === assistantMessageId)
          if (msgIndex !== -1) {
            messages.value[msgIndex].isStreaming = false
            messages.value[msgIndex].isThinking = false
            messages.value[msgIndex].isFailed = true
            messages.value[msgIndex].errorMessage = errorMsg
            messages.value[msgIndex].content = streamingContent.value || ''
          }
          lastFailedMessageId.value = assistantMessageId

          reject(new Error(errorMsg))
        }
      )
    })
  }

  /**
   * Check if there's a failed message that can be retried
   */
  function canRetry() {
    return lastFailedMessageId.value !== null ||
           messages.value.some(m => m.isFailed || m.isStopped)
  }

  async function sendMessage(content, conversationId = null) {
    if (!content.trim() || isSending.value) return

    const targetConversationId = conversationId || currentConversationId.value

    isSending.value = true
    error.value = null
    tokensExhausted.value = false

    // Add user message immediately for better UX
    const userMessage = {
      id: generateTempId('user'),
      role: 'user',
      content: content.trim(),
      created_at: new Date().toISOString(),
    }
    messages.value.push(userMessage)

    try {
      let response
      if (targetConversationId) {
        response = await chatApi.sendConversationMessage(targetConversationId, content.trim())
      } else {
        // Legacy endpoint
        response = await chatApi.sendMessage(content.trim())
      }

      // Add assistant response
      messages.value.push(response.data.message)

      // Update conversation info if available
      if (response.data.conversation) {
        currentConversation.value = response.data.conversation
        currentConversationId.value = response.data.conversation.id
      }

      // Update tokens balance
      const authStore = useAuthStore()
      authStore.updateTokensBalance(response.data.tokens_balance)

      return response.data
    } catch (e) {
      // Remove the user message if there was an error
      messages.value = messages.value.filter(m => m.id !== userMessage.id)

      // Check if free plan blocked (403)
      if (e.response?.status === 403 && e.response?.data?.error === 'free_plan') {
        freePlanBlocked.value = true
        freePlanData.value = e.response.data
        error.value = e.response.data.message
      }
      // Check if tokens exhausted (402)
      else if (e.response?.status === 402) {
        tokensExhausted.value = true
        tokensExhaustedData.value = e.response.data
        error.value = e.response.data.message
      } else {
        error.value = e.response?.data?.message || 'Error al enviar el mensaje'
      }

      throw e
    } finally {
      isSending.value = false
    }
  }

  /**
   * Send message with streaming response
   */
  async function sendMessageStream(content, conversationId = null) {
    if (!content.trim() || isSending.value) return

    const targetConversationId = conversationId || currentConversationId.value
    if (!targetConversationId) {
      // Fall back to non-streaming if no conversation
      return sendMessage(content, conversationId)
    }

    isSending.value = true
    isThinking.value = true // Show "thinking" indicator
    isStreaming.value = false
    streamingContent.value = ''
    error.value = null
    tokensExhausted.value = false
    freePlanBlocked.value = false
    lastFailedMessageId.value = null

    // Store for retry
    lastUserMessageContent.value = content.trim()

    // Add user message immediately
    const userMessage = {
      id: generateTempId('user'),
      role: 'user',
      content: content.trim(),
      created_at: new Date().toISOString(),
    }
    messages.value.push(userMessage)

    // Add placeholder for streaming assistant message
    const assistantMessageId = generateTempId('assistant')
    const assistantMessage = {
      id: assistantMessageId,
      role: 'assistant',
      content: '',
      created_at: new Date().toISOString(),
      isStreaming: true,
      isThinking: true, // Start in thinking mode
    }
    messages.value.push(assistantMessage)

    // Wait for Vue to render the thinking indicator before starting the stream
    await nextTick()

    return new Promise((resolve, reject) => {
      chatApi.sendConversationMessageStream(
        targetConversationId,
        content.trim(),
        // onChunk
        (chunk) => {
          // Skip empty chunks
          if (!chunk || chunk.length === 0) return

          // First real chunk received - switch from thinking to streaming
          if (isThinking.value) {
            isThinking.value = false
            isStreaming.value = true
          }

          streamingContent.value += chunk

          // Update the assistant message - replace entire object to force Vue reactivity
          const msgIndex = messages.value.findIndex(m => m.id === assistantMessageId)
          if (msgIndex !== -1) {
            messages.value[msgIndex] = {
              ...messages.value[msgIndex],
              content: streamingContent.value,
              isThinking: false,
              isStreaming: true,
            }
          }
        },
        // onDone
        (data) => {
          isThinking.value = false
          isStreaming.value = false
          isSending.value = false

          // Update the assistant message with final data
          const msgIndex = messages.value.findIndex(m => m.id === assistantMessageId)
          if (msgIndex !== -1) {
            messages.value[msgIndex].id = data.message_id
            messages.value[msgIndex].isStreaming = false
            messages.value[msgIndex].isThinking = false
          }

          // Update conversation info
          if (data.conversation) {
            currentConversation.value = data.conversation
            // Also update in conversations store (for sidebar)
            const conversationsStore = useConversationsStore()
            conversationsStore.updateConversationLocally(data.conversation.id, {
              title: data.conversation.title,
              message_count: (currentConversation.value.message_count || 0) + 1,
              last_message_at: new Date().toISOString(),
            })
          }

          // Update token usage tracking
          if (data.tokens_input !== undefined && data.tokens_output !== undefined) {
            const cost = calculateCost(data.model, data.tokens_input, data.tokens_output)
            lastMessageTokens.value = {
              input: data.tokens_input,
              output: data.tokens_output,
              model: data.model,
              cost: cost
            }
          }

          // Update tokens balance
          const authStore = useAuthStore()
          authStore.updateTokensBalance(data.tokens_balance)

          resolve(data)
        },
        // onError
        (errorData) => {
          isThinking.value = false
          isStreaming.value = false
          isSending.value = false

          const errorMsg = errorData.message || 'Error al enviar el mensaje'

          // For free_plan and tokens_exhausted, remove messages (user needs to upgrade)
          if (errorMsg === 'free_plan') {
            freePlanBlocked.value = true
            error.value = 'Plan gratuito no permite chat'
            messages.value = messages.value.filter(m =>
              m.id !== userMessage.id && m.id !== assistantMessageId
            )
          } else if (errorMsg === 'tokens_exhausted') {
            tokensExhausted.value = true
            error.value = 'Tokens agotados'
            messages.value = messages.value.filter(m =>
              m.id !== userMessage.id && m.id !== assistantMessageId
            )
          } else {
            // For other errors, keep messages but mark assistant message as failed
            error.value = errorMsg
            const msgIndex = messages.value.findIndex(m => m.id === assistantMessageId)
            if (msgIndex !== -1) {
              messages.value[msgIndex].isStreaming = false
              messages.value[msgIndex].isThinking = false
              messages.value[msgIndex].isFailed = true
              messages.value[msgIndex].errorMessage = errorMsg
              messages.value[msgIndex].content = streamingContent.value || ''
            }
            // Store the failed message ID for retry
            lastFailedMessageId.value = assistantMessageId
          }

          reject(new Error(errorMsg))
        }
      )
    })
  }

  async function clearHistory(conversationId = null) {
    const targetConversationId = conversationId || currentConversationId.value

    isLoading.value = true
    error.value = null

    try {
      if (targetConversationId) {
        await chatApi.clearConversationMessages(targetConversationId)
      } else {
        await chatApi.clearHistory()
      }
      messages.value = []
      return true
    } catch (e) {
      error.value = e.response?.data?.message || 'Error al limpiar el historial'
      throw e
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Set current conversation and load its messages
   */
  async function setCurrentConversation(conversationId) {
    currentConversationId.value = conversationId
    messages.value = []
    await fetchMessages(conversationId)
  }

  /**
   * Clear current conversation state
   */
  function clearCurrentConversation() {
    currentConversationId.value = null
    currentConversation.value = null
    messages.value = []
  }

  function clearError() {
    error.value = null
    tokensExhausted.value = false
    tokensExhaustedData.value = null
    freePlanBlocked.value = false
    freePlanData.value = null
  }

  return {
    messages,
    isLoading,
    isSending,
    error,
    tokensExhausted,
    tokensExhaustedData,
    freePlanBlocked,
    freePlanData,
    hasMessages,
    // Streaming state
    streamingContent,
    isStreaming,
    isThinking,
    // Retry state
    lastFailedMessageId,
    // Token usage
    lastMessageTokens,
    // Assistant state
    currentAssistant,
    availableAssistants,
    isChangingAssistant,
    // Conversation state
    currentConversationId,
    currentConversation,
    // Methods
    fetchMessages,
    sendMessage,
    sendMessageStream,
    stopStreaming,
    retryLastMessage,
    regenerateResponse,
    canRetry,
    clearHistory,
    clearError,
    fetchAvailableAssistants,
    changeAssistant,
    setCurrentConversation,
    clearCurrentConversation,
  }
})
