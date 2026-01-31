import { defineStore } from 'pinia'
import { ref, computed, nextTick } from 'vue'
import { chatApi } from '../services/api'
import { useAuthStore } from './auth'

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

  /**
   * Stop current streaming response
   */
  function stopStreaming() {
    const stopped = chatApi.stopStream()
    if (stopped) {
      isStreaming.value = false
      isThinking.value = false
      isSending.value = false
      // Mark the streaming message as complete (keep partial content)
      const streamingMsg = messages.value.find(m => m.isStreaming)
      if (streamingMsg) {
        streamingMsg.isStreaming = false
        streamingMsg.isThinking = false
        streamingMsg.content = streamingContent.value || '(Respuesta cancelada)'
      }
    }
    return stopped
  }

  async function sendMessage(content, conversationId = null) {
    if (!content.trim() || isSending.value) return

    const targetConversationId = conversationId || currentConversationId.value

    isSending.value = true
    error.value = null
    tokensExhausted.value = false

    // Add user message immediately for better UX
    const userMessage = {
      id: Date.now(),
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

    // Add user message immediately
    const userMessage = {
      id: Date.now(),
      role: 'user',
      content: content.trim(),
      created_at: new Date().toISOString(),
    }
    messages.value.push(userMessage)

    // Add placeholder for streaming assistant message
    const assistantMessageId = Date.now() + 1
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
            const msgIndex = messages.value.findIndex(m => m.id === assistantMessageId)
            if (msgIndex !== -1) {
              messages.value[msgIndex].isThinking = false
            }
          }
          streamingContent.value += chunk
          // Update the assistant message in place
          const msgIndex = messages.value.findIndex(m => m.id === assistantMessageId)
          if (msgIndex !== -1) {
            messages.value[msgIndex].content = streamingContent.value
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

          // Remove messages on error
          messages.value = messages.value.filter(m =>
            m.id !== userMessage.id && m.id !== assistantMessageId
          )

          const errorMsg = errorData.message || 'Error al enviar el mensaje'

          if (errorMsg === 'free_plan') {
            freePlanBlocked.value = true
            error.value = 'Plan gratuito no permite chat'
          } else if (errorMsg === 'tokens_exhausted') {
            tokensExhausted.value = true
            error.value = 'Tokens agotados'
          } else {
            error.value = errorMsg
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
    clearHistory,
    clearError,
    fetchAvailableAssistants,
    changeAssistant,
    setCurrentConversation,
    clearCurrentConversation,
  }
})
