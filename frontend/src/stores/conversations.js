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

  const currentConversation = computed(() =>
    conversations.value.find(c => c.id === currentConversationId.value)
  )

  const hasConversations = computed(() => conversations.value.length > 0)

  async function fetchConversations() {
    isLoading.value = true
    try {
      const response = await chatApi.getConversations()
      conversations.value = response.data.conversations || []
      groupedConversations.value = response.data.grouped || {}
      return response.data
    } catch (e) {
      console.error('Error fetching conversations:', e)
      throw e
    } finally {
      isLoading.value = false
    }
  }

  async function createConversation() {
    isCreating.value = true
    try {
      const response = await chatApi.createConversation()
      const newConversation = response.data.conversation
      conversations.value.unshift(newConversation)
      currentConversationId.value = newConversation.id
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
    const conversation = conversations.value.find(c => c.id === id)
    if (conversation) {
      Object.assign(conversation, updates)
    }
  }

  function reset() {
    conversations.value = []
    groupedConversations.value = {}
    currentConversationId.value = null
    searchQuery.value = ''
    searchResults.value = []
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
