import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true, // Required for Sanctum cookies
  timeout: 30000, // 30 second timeout for regular requests
})

// Request interceptor to add auth token
api.interceptors.request.use(
  (config) => {
    // Use admin token for admin routes, auth token for user routes
    const isAdminRoute = config.url?.startsWith('/admin')
    const token = isAdminRoute
      ? localStorage.getItem('admin_token')
      : localStorage.getItem('auth_token')

    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor to handle errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Handle 401 Unauthorized
    if (error.response?.status === 401) {
      const isAdminRoute = error.config?.url?.startsWith('/admin')

      if (isAdminRoute) {
        localStorage.removeItem('admin_token')
        localStorage.removeItem('admin')
        if (window.location.pathname !== '/admin/login') {
          window.location.href = '/admin/login'
        }
      } else {
        localStorage.removeItem('auth_token')
        localStorage.removeItem('user')
        if (window.location.pathname !== '/login') {
          window.location.href = '/login'
        }
      }
    }

    return Promise.reject(error)
  }
)

// Auth API
export const authApi = {
  setPassword: (data) => api.post('/auth/set-password', data),
  login: (data) => api.post('/auth/login', data),
  logout: () => api.post('/auth/logout'),
  me: (refresh = false) => api.get('/auth/me', { params: { refresh: refresh ? 1 : 0 } }),
  changePassword: (data) => api.post('/auth/change-password', data),
  updateProfile: (data) => api.patch('/auth/profile', data),
  forgotPassword: (email) => api.post('/auth/forgot-password', { email }),
  resetPassword: (data) => api.post('/auth/reset-password', data),
  uploadAvatar: (file) => {
    const formData = new FormData()
    formData.append('avatar', file)
    return api.post('/auth/avatar', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  deleteAvatar: () => api.delete('/auth/avatar'),
}

// Active stream controller for cancellation
let activeStreamController = null

// Chat API (legacy endpoints for backward compatibility)
export const chatApi = {
  getMessages: () => api.get('/chat/messages'),
  sendMessage: (message) => api.post('/chat/send', { message }),
  clearHistory: () => api.delete('/chat/clear'),
  // Assistants (user-facing)
  getAvailableAssistants: () => api.get('/assistants'),
  changeMyAssistant: (assistantId) => api.patch('/user/assistant', { assistant_id: assistantId }),

  // Conversations (new - ChatGPT-style history)
  getConversations: (params) => api.get('/conversations', { params }),
  createConversation: () => api.post('/conversations'),
  getConversation: (id) => api.get(`/conversations/${id}`),
  updateConversation: (id, data) => api.patch(`/conversations/${id}`, data),
  deleteConversation: (id) => api.delete(`/conversations/${id}`),
  archiveConversation: (id) => api.post(`/conversations/${id}/archive`),
  unarchiveConversation: (id) => api.post(`/conversations/${id}/unarchive`),
  searchConversations: (q) => api.get('/conversations/search', { params: { q } }),
  getArchivedConversations: () => api.get('/conversations/archived'),
  getConversationMessages: (id) => api.get(`/conversations/${id}/messages`),
  sendConversationMessage: (id, message) => api.post(`/conversations/${id}/messages`, { message }),
  clearConversationMessages: (id) => api.delete(`/conversations/${id}/messages`),

  // Stop active stream
  stopStream: () => {
    if (activeStreamController) {
      activeStreamController.abort()
      activeStreamController = null
      return true
    }
    return false
  },

  // Streaming message (SSE)
  sendConversationMessageStream: async (id, message, onChunk, onDone, onError) => {
    const token = localStorage.getItem('auth_token')
    const baseURL = import.meta.env.VITE_API_URL
    let receivedDone = false
    let wasCancelled = false

    // AbortController with 15 minute timeout for GPT-5 with knowledge base
    const controller = new AbortController()
    activeStreamController = controller // Store for cancellation
    const timeoutId = setTimeout(() => controller.abort(), 900000) // 15 minutes

    try {
      const response = await fetch(`${baseURL}/conversations/${id}/messages/stream`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({ message }),
        signal: controller.signal,
      })

      if (!response.ok) {
        const errorText = await response.text()
        throw new Error(errorText || `HTTP ${response.status}`)
      }

      const reader = response.body.getReader()
      const decoder = new TextDecoder()
      let buffer = ''
      let currentEvent = null  // Persist across chunks to handle split events

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })

        // Process complete SSE events
        const lines = buffer.split('\n')
        buffer = lines.pop() || '' // Keep incomplete line in buffer

        for (const line of lines) {
          // Skip empty lines and SSE comments
          if (!line || line.startsWith(':')) continue

          if (line.startsWith('event: ')) {
            currentEvent = line.slice(7).trim()
          } else if (line.startsWith('data: ') && currentEvent) {
            try {
              const data = JSON.parse(line.slice(6))

              if (currentEvent === 'content') {
                // Ensure text exists and is not empty before calling onChunk
                if (data.text != null && data.text !== '') {
                  onChunk(data.text)
                }
              } else if (currentEvent === 'done') {
                receivedDone = true
                onDone(data)
              } else if (currentEvent === 'error') {
                receivedDone = true
                onError(data)
              } else if (currentEvent === 'start') {
                // Optional: handle start event
              }
            } catch (e) {
              console.error('Error parsing SSE data:', e, 'Line:', line)
            }
            currentEvent = null
          }
        }
      }

      // Flush any remaining bytes from decoder
      const remaining = decoder.decode()
      if (remaining) {
        buffer += remaining
      }

      // Process any remaining complete lines in buffer
      if (buffer.trim()) {
        const finalLines = buffer.split('\n')
        for (const line of finalLines) {
          if (line.startsWith('event: ')) {
            currentEvent = line.slice(7).trim()
          } else if (line.startsWith('data: ') && currentEvent) {
            try {
              const data = JSON.parse(line.slice(6))
              if (currentEvent === 'content' && data.text != null && data.text !== '') {
                onChunk(data.text)
              } else if (currentEvent === 'done') {
                receivedDone = true
                onDone(data)
              } else if (currentEvent === 'error') {
                receivedDone = true
                onError(data)
              }
            } catch (e) {
              // Final buffer might be incomplete, ignore parse errors
            }
            currentEvent = null
          }
        }
      }

      // Si el stream terminó sin evento 'done', llamar onDone con datos mínimos
      if (!receivedDone && !wasCancelled) {
        console.warn('Stream ended without done event')
        onDone({ message_id: null, tokens_used: 0, tokens_balance: null, conversation: null })
      }
    } catch (error) {
      if (error.name === 'AbortError') {
        // Check if it was manually cancelled or timed out
        if (activeStreamController === null) {
          wasCancelled = true
          onDone({ message_id: null, tokens_used: 0, tokens_balance: null, conversation: null, cancelled: true })
        } else {
          onError({ message: 'La solicitud tardó demasiado. Por favor, intenta de nuevo.' })
        }
      } else {
        onError({ message: error.message })
      }
    } finally {
      clearTimeout(timeoutId)
      activeStreamController = null
    }
  },

  // Regenerate last assistant response (SSE streaming)
  regenerateStream: async (conversationId, onChunk, onDone, onError) => {
    const token = localStorage.getItem('auth_token')
    const baseURL = import.meta.env.VITE_API_URL
    let receivedDone = false
    let wasCancelled = false

    const controller = new AbortController()
    activeStreamController = controller
    const timeoutId = setTimeout(() => controller.abort(), 900000)

    try {
      const response = await fetch(`${baseURL}/conversations/${conversationId}/messages/regenerate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({}),
        signal: controller.signal,
      })

      if (!response.ok) {
        const errorText = await response.text()
        throw new Error(errorText || `HTTP ${response.status}`)
      }

      const reader = response.body.getReader()
      const decoder = new TextDecoder()
      let buffer = ''
      let currentEvent = null

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })

        const lines = buffer.split('\n')
        buffer = lines.pop() || ''

        for (const line of lines) {
          if (!line || line.startsWith(':')) continue

          if (line.startsWith('event: ')) {
            currentEvent = line.slice(7).trim()
          } else if (line.startsWith('data: ') && currentEvent) {
            try {
              const data = JSON.parse(line.slice(6))
              if (currentEvent === 'content') {
                if (data.text != null && data.text !== '') {
                  onChunk(data.text)
                }
              } else if (currentEvent === 'done') {
                receivedDone = true
                onDone(data)
              } else if (currentEvent === 'error') {
                receivedDone = true
                onError(data)
              }
            } catch (e) {
              console.error('Error parsing SSE data:', e)
            }
            currentEvent = null
          }
        }
      }

      const remaining = decoder.decode()
      if (remaining) buffer += remaining

      if (buffer.trim()) {
        const finalLines = buffer.split('\n')
        for (const line of finalLines) {
          if (line.startsWith('event: ')) {
            currentEvent = line.slice(7).trim()
          } else if (line.startsWith('data: ') && currentEvent) {
            try {
              const data = JSON.parse(line.slice(6))
              if (currentEvent === 'content' && data.text != null && data.text !== '') {
                onChunk(data.text)
              } else if (currentEvent === 'done') {
                receivedDone = true
                onDone(data)
              } else if (currentEvent === 'error') {
                receivedDone = true
                onError(data)
              }
            } catch (e) { /* ignore */ }
            currentEvent = null
          }
        }
      }

      if (!receivedDone && !wasCancelled) {
        onDone({ message_id: null, tokens_used: 0, tokens_balance: null, conversation: null })
      }
    } catch (error) {
      if (error.name === 'AbortError') {
        if (activeStreamController === null) {
          wasCancelled = true
          onDone({ message_id: null, tokens_used: 0, tokens_balance: null, conversation: null, cancelled: true })
        } else {
          onError({ message: 'La solicitud tardó demasiado. Por favor, intenta de nuevo.' })
        }
      } else {
        onError({ message: error.message })
      }
    } finally {
      clearTimeout(timeoutId)
      activeStreamController = null
    }
  },
}

// Admin Auth API
export const adminAuthApi = {
  login: (data) => api.post('/admin/auth/login', data),
  logout: () => api.post('/admin/auth/logout'),
  me: () => api.get('/admin/auth/me'),
}

// Admin API
export const adminApi = {
  // Dashboard
  getDashboard: () => api.get('/admin/dashboard'),
  getTokenStats: () => api.get('/admin/stats/tokens'),
  getOpenAIStats: () => api.get('/admin/stats/openai'),
  getProviderStats: () => api.get('/admin/stats/providers'),

  // Users
  getUsers: (params) => api.get('/admin/users', { params }),
  createUser: (data) => api.post('/admin/users', data),
  getUser: (id) => api.get(`/admin/users/${id}`),
  updateUser: (id, data) => api.patch(`/admin/users/${id}`, data),
  activateUser: (id) => api.post(`/admin/users/${id}/activate`),
  deactivateUser: (id) => api.post(`/admin/users/${id}/deactivate`),
  addTokens: (id, amount) => api.post(`/admin/users/${id}/add-tokens`, { amount }),
  changePlan: (id, data) => api.patch(`/admin/users/${id}/plan`, data),

  // Plans
  getPlans: () => api.get('/admin/plans'),
  createPlan: (data) => api.post('/admin/plans', data),
  updatePlan: (id, data) => api.patch(`/admin/plans/${id}`, data),
  deletePlan: (id) => api.delete(`/admin/plans/${id}`),

  // Webhook Logs
  getWebhookLogs: (params) => api.get('/admin/webhook-logs', { params }),
  getWebhookLog: (id) => api.get(`/admin/webhook-logs/${id}`),

  // AI Settings
  getAiSettings: () => api.get('/admin/ai-settings'),
  updateAiSettings: (settings) => api.post('/admin/ai-settings', { settings }),
  testAiSettings: (message) => api.post('/admin/ai-settings/test', { message }),

  // Assistants
  getAssistants: () => api.get('/admin/assistants'),
  getAssistant: (id) => api.get(`/admin/assistants/${id}`),
  createAssistant: (data) => api.post('/admin/assistants', data),
  updateAssistant: (id, data) => api.patch(`/admin/assistants/${id}`, data),
  deleteAssistant: (id) => api.delete(`/admin/assistants/${id}`),
  setDefaultAssistant: (id) => api.post(`/admin/assistants/${id}/set-default`),
  duplicateAssistant: (id) => api.post(`/admin/assistants/${id}/duplicate`),
  testAssistant: (id, message, context = []) => api.post(`/admin/assistants/${id}/test`, { message, context }),
  assignUserAssistant: (userId, assistantId) => api.patch(`/admin/users/${userId}/assistant`, { assistant_id: assistantId }),

  // Assistant Files (Knowledge Base)
  getAssistantFiles: (assistantId) => api.get(`/admin/assistants/${assistantId}/files`),
  uploadAssistantFile: (assistantId, file) => {
    const formData = new FormData()
    formData.append('file', file)
    return api.post(`/admin/assistants/${assistantId}/files`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  deleteAssistantFile: (assistantId, fileId) => api.delete(`/admin/assistants/${assistantId}/files/${fileId}`),
  enableKnowledgeBase: (assistantId) => api.post(`/admin/assistants/${assistantId}/knowledge-base/enable`),
  disableKnowledgeBase: (assistantId) => api.post(`/admin/assistants/${assistantId}/knowledge-base/disable`),
  syncAssistant: (assistantId) => api.post(`/admin/assistants/${assistantId}/sync`),

  // Test Conversations (admin testing chat history)
  getTestConversations: (assistantId) => api.get('/admin/test-conversations', { params: { assistant_id: assistantId } }),
  createTestConversation: (assistantId) => api.post('/admin/test-conversations', { assistant_id: assistantId }),
  getTestConversation: (id) => api.get(`/admin/test-conversations/${id}`),
  sendTestConversationMessage: (id, message, context = []) => api.post(`/admin/test-conversations/${id}/messages`, { message, context }),
  deleteTestConversation: (id) => api.delete(`/admin/test-conversations/${id}`),
  clearAllTestConversations: (assistantId) => api.delete('/admin/test-conversations/clear-all', { params: { assistant_id: assistantId } }),

  // Email Logs (monitoring)
  getEmailStats: () => api.get('/admin/emails/stats'),
  getBouncedEmails: () => api.get('/admin/emails/bounced'),
  getEmailLogs: (params) => api.get('/admin/emails', { params }),
  getEmailLog: (id) => api.get(`/admin/emails/${id}`),
  resendEmail: (id) => api.post(`/admin/emails/${id}/resend`),

  // Streaming message for admin test (SSE)
  sendTestConversationMessageStream: async (id, message, onChunk, onDone, onError) => {
    const token = localStorage.getItem('admin_token')
    const baseURL = import.meta.env.VITE_API_URL
    let receivedDone = false

    // AbortController with 15 minute timeout for GPT-5 with knowledge base
    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), 900000) // 15 minutes

    try {
      const response = await fetch(`${baseURL}/admin/test-conversations/${id}/messages/stream`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'text/event-stream',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({ message }),
        signal: controller.signal,
      })

      if (!response.ok) {
        const errorText = await response.text()
        throw new Error(errorText || `HTTP ${response.status}`)
      }

      const reader = response.body.getReader()
      const decoder = new TextDecoder()
      let buffer = ''
      let currentEvent = null  // MOVED OUTSIDE loop to persist across chunks

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })

        const lines = buffer.split('\n')
        buffer = lines.pop() || ''

        for (const line of lines) {
          // Skip empty lines and SSE comments
          if (!line || line.startsWith(':')) continue

          if (line.startsWith('event: ')) {
            currentEvent = line.slice(7).trim()
          } else if (line.startsWith('data: ') && currentEvent) {
            try {
              const data = JSON.parse(line.slice(6))
              if (currentEvent === 'content') {
                // Ensure text exists and is not empty before calling onChunk
                if (data.text != null && data.text !== '') {
                  onChunk(data.text)
                }
              } else if (currentEvent === 'done') {
                receivedDone = true
                onDone(data)
              } else if (currentEvent === 'error') {
                receivedDone = true
                onError(data)
              }
            } catch (e) {
              console.error('Error parsing SSE:', e, 'Line:', line)
            }
            currentEvent = null
          }
        }
      }

      // Flush any remaining bytes from decoder
      const remaining = decoder.decode()
      if (remaining) {
        buffer += remaining
      }

      // Process any remaining complete lines in buffer
      if (buffer.trim()) {
        const finalLines = buffer.split('\n')
        for (const line of finalLines) {
          if (line.startsWith('event: ')) {
            currentEvent = line.slice(7).trim()
          } else if (line.startsWith('data: ') && currentEvent) {
            try {
              const data = JSON.parse(line.slice(6))
              if (currentEvent === 'content' && data.text != null && data.text !== '') {
                onChunk(data.text)
              } else if (currentEvent === 'done') {
                receivedDone = true
                onDone(data)
              } else if (currentEvent === 'error') {
                receivedDone = true
                onError(data)
              }
            } catch (e) {
              // Final buffer might be incomplete, ignore parse errors
            }
            currentEvent = null
          }
        }
      }

      // Si el stream terminó sin evento 'done', llamar onDone con datos mínimos
      if (!receivedDone) {
        console.warn('Admin stream ended without done event')
        onDone({ message_id: null, tokens_used: 0, conversation: null })
      }
    } catch (error) {
      if (error.name === 'AbortError') {
        onError({ message: 'La solicitud tardó demasiado. Por favor, intenta de nuevo.' })
      } else {
        onError({ message: error.message })
      }
    } finally {
      clearTimeout(timeoutId)
    }
  },
}

export default api
