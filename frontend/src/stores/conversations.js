import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { chatApi } from '../services/api'

export const useConversationsStore = defineStore('conversations', () => {
  const conversations = ref([])
  const groupedConversations = ref({})
  const currentConversationId = ref(null)
  const isLoading = ref(false)
  const isCreating = ref(false)
  const searchQuery = ref('')
  const searchResults = ref([])
  const isSearching = ref(false)

  // Request deduplication
  let fetchPromise = null
  let lastFetchTime = 0
  const CACHE_DURATION = 60000 // 1 minute cache

  const currentConversation = computed(() =>
    conversations.value.find(c => c.id === currentConversationId.value)
  )

  const hasConversations = computed(() => conversations.value.length > 0)

  async function fetchConversations(force = false) {
    const now = Date.now()

    // Return cached data if available and not forcing
    if (!force && conversations.value.length > 0 && (now - lastFetchTime) < CACHE_DURATION) {
      return { conversations: conversations.value, grouped: groupedConversations.value }
    }

    // Deduplicate concurrent requests
    if (fetchPromise) {
      return fetchPromise
    }

    isLoading.value = true
    fetchPromise = (async () => {
      try {
        const response = await chatApi.getConversations()
        conversations.value = response.data.conversations || []
        groupedConversations.value = response.data.grouped || {}
        lastFetchTime = Date.now()
        return response.data
      } catch (e) {
        console.error('Error fetching conversations:', e)
        throw e
      } finally {
        isLoading.value = false
        fetchPromise = null
      }
    })()

    return fetchPromise
  }

  async function createConversation() {
    isCreating.value = true
    try {
      const response = await chatApi.createConversation()
      const newConversation = response.data.conversation
      conversations.value.unshift(newConversation)
      currentConversationId.value = newConversation.id

      // Also add to groupedConversations so it appears in sidebar immediately
      if (!groupedConversations.value.today) {
        groupedConversations.value.today = { title: 'Hoy', conversations: [] }
      }
      // Add to beginning of "today" group
      groupedConversations.value.today.conversations.unshift(newConversation)

      return newConversation
    } catch (e) {
      console.error('Error creating conversation:', e)
      throw e
    } finally {
      isCreating.value = false
    }
  }

  async function selectConversation(id) {
    currentConversationId.value = id
  }

  async function updateConversationTitle(id, title) {
    try {
      await chatApi.updateConversation(id, { title })
      const conversation = conversations.value.find(c => c.id === id)
      if (conversation) {
        conversation.title = title
      }
    } catch (e) {
      console.error('Error updating conversation title:', e)
      throw e
    }
  }

  async function deleteConversation(id) {
    try {
      await chatApi.deleteConversation(id)
      conversations.value = conversations.value.filter(c => c.id !== id)

      // Also remove from grouped conversations
      for (const key in groupedConversations.value) {
        const group = groupedConversations.value[key]
        if (group.conversations) {
          group.conversations = group.conversations.filter(c => c.id !== id)
        }
      }

      // If deleted current conversation, select another
      if (currentConversationId.value === id) {
        currentConversationId.value = conversations.value[0]?.id || null
      }
    } catch (e) {
      console.error('Error deleting conversation:', e)
      throw e
    }
  }

  async function archiveConversation(id) {
    try {
      await chatApi.archiveConversation(id)
      conversations.value = conversations.value.filter(c => c.id !== id)

      // Also remove from grouped conversations
      for (const key in groupedConversations.value) {
        const group = groupedConversations.value[key]
        if (group.conversations) {
          group.conversations = group.conversations.filter(c => c.id !== id)
        }
      }

      if (currentConversationId.value === id) {
        currentConversationId.value = conversations.value[0]?.id || null
      }
    } catch (e) {
      console.error('Error archiving conversation:', e)
      throw e
    }
  }

  async function searchConversations(query) {
    if (!query || query.length < 2) {
      searchResults.value = []
      return
    }

    isSearching.value = true
    searchQuery.value = query

    try {
      const response = await chatApi.searchConversations(query)
      searchResults.value = response.data.conversations || []
    } catch (e) {
      console.error('Error searching conversations:', e)
      searchResults.value = []
    } finally {
      isSearching.value = false
    }
  }

  function clearSearch() {
    searchQuery.value = ''
    searchResults.value = []
  }

  // Update local conversation data when messages are sent
  function updateConversationLocally(id, updates) {
    // Update in flat conversations list
    const conversation = conversations.value.find(c => c.id === id)
    if (conversation) {
      Object.assign(conversation, updates)
    }

    // Also update in grouped conversations
    for (const key in groupedConversations.value) {
      const group = groupedConversations.value[key]
      if (group.conversations) {
        const groupedConv = group.conversations.find(c => c.id === id)
        if (groupedConv) {
          Object.assign(groupedConv, updates)
          break
        }
      }
    }
  }

  function reset() {
    conversations.value = []
    groupedConversations.value = {}
    currentConversationId.value = null
    searchQuery.value = ''
    searchResults.value = []
    lastFetchTime = 0 // Clear cache on reset
  }

  return {
    // State
    conversations,
    groupedConversations,
    currentConversationId,
    currentConversation,
    isLoading,
    isCreating,
    searchQuery,
    searchResults,
    isSearching,
    hasConversations,
    // Actions
    fetchConversations,
    createConversation,
    selectConversation,
    updateConversationTitle,
    deleteConversation,
    archiveConversation,
    searchConversations,
    clearSearch,
    updateConversationLocally,
    reset,
  }
})
