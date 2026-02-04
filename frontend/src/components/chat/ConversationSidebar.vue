<script setup>
import { ref, computed, watch } from 'vue'
import { useConversationsStore } from '../../stores/conversations'

const emit = defineEmits(['select', 'close'])

const conversationsStore = useConversationsStore()

const searchInput = ref('')
const showContextMenu = ref(null)
const editingId = ref(null)
const editingTitle = ref('')

// Debounced search
let searchTimeout = null
watch(searchInput, (value) => {
  clearTimeout(searchTimeout)
  if (value.length >= 2) {
    searchTimeout = setTimeout(() => {
      conversationsStore.searchConversations(value)
    }, 300)
  } else {
    conversationsStore.clearSearch()
  }
})

const displayConversations = computed(() => {
  if (searchInput.value.length >= 2 && conversationsStore.searchResults.length > 0) {
    return { search: { title: 'Resultados', conversations: conversationsStore.searchResults } }
  }
  return conversationsStore.groupedConversations
})

async function handleNewConversation() {
  try {
    const conv = await conversationsStore.createConversation()
    emit('select', conv.id)
    emit('close')
  } catch (e) {
    console.error('Error creating conversation:', e)
  }
}

function handleSelect(id) {
  conversationsStore.selectConversation(id)
  emit('select', id)
  emit('close')
}

function openContextMenu(e, id) {
  e.preventDefault()
  e.stopPropagation()
  showContextMenu.value = id
}

function closeContextMenu() {
  showContextMenu.value = null
}

function startEditing(conv) {
  editingId.value = conv.id
  editingTitle.value = conv.title || 'Nueva conversacion'
  closeContextMenu()
}

async function saveTitle() {
  if (editingId.value && editingTitle.value.trim()) {
    try {
      await conversationsStore.updateConversationTitle(editingId.value, editingTitle.value.trim())
    } catch (e) {
      console.error('Error updating title:', e)
    }
  }
  editingId.value = null
  editingTitle.value = ''
}

function cancelEdit() {
  editingId.value = null
  editingTitle.value = ''
}

async function handleArchive(id) {
  closeContextMenu()
  try {
    await conversationsStore.archiveConversation(id)
  } catch (e) {
    console.error('Error archiving conversation:', e)
  }
}

async function handleDelete(id) {
  closeContextMenu()
  if (confirm('¿Eliminar esta conversacion?')) {
    try {
      await conversationsStore.deleteConversation(id)
    } catch (e) {
      console.error('Error deleting conversation:', e)
    }
  }
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
</script>

<template>
  <aside class="w-64 bg-gatales-sidebar border-r border-gatales-border flex flex-col h-full">
    <!-- Header - New Chat Button -->
    <div class="p-2">
      <button
        @click="handleNewConversation"
        :disabled="conversationsStore.isCreating"
        class="w-full flex items-center gap-3 px-3 py-2 text-gatales-text hover:bg-gatales-input rounded-lg transition-colors disabled:opacity-50 text-sm"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nueva conversacion
      </button>
    </div>

    <!-- Search -->
    <div class="px-2 pb-2">
      <div class="relative">
        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gatales-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="searchInput"
          type="text"
          placeholder="Buscar..."
          class="w-full pl-8 pr-3 py-1.5 bg-gatales-input border border-gatales-border rounded-md text-xs text-gatales-text placeholder-gatales-text-secondary focus:outline-none focus:ring-1 focus:ring-gatales-accent focus:border-transparent"
        />
      </div>
    </div>

    <!-- Conversations List -->
    <div class="flex-1 overflow-y-auto" @click="closeContextMenu">
      <!-- Loading -->
      <div v-if="conversationsStore.isLoading" class="flex justify-center py-6">
        <svg class="animate-spin h-5 w-5 text-gatales-accent" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>

      <!-- Empty State -->
      <div v-else-if="!conversationsStore.hasConversations && !searchInput" class="p-3 text-center">
        <p class="text-gatales-text-secondary text-xs">No hay conversaciones</p>
      </div>

      <!-- Grouped Conversations -->
      <div v-else class="py-1">
        <template v-for="(group, key) in displayConversations" :key="key">
          <div v-if="group.conversations?.length > 0" class="mb-3">
            <!-- Group Title -->
            <div class="px-3 py-1 text-[10px] font-medium text-gatales-text-secondary uppercase tracking-wider">
              {{ group.title }}
            </div>

            <!-- Conversations in Group -->
            <div class="space-y-0.5 px-1">
              <div
                v-for="conv in group.conversations"
                :key="conv.id"
                @click="handleSelect(conv.id)"
                @contextmenu="(e) => openContextMenu(e, conv.id)"
                :class="[
                  'group relative flex items-center gap-2 px-2 py-1.5 rounded-md cursor-pointer transition-colors',
                  conversationsStore.currentConversationId === conv.id
                    ? 'bg-gatales-accent/10 text-gatales-accent'
                    : 'hover:bg-gatales-input text-gatales-text'
                ]"
              >
                <!-- Chat Icon -->
                <svg class="w-3.5 h-3.5 shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>

                <!-- Title (editable) -->
                <div class="flex-1 min-w-0">
                  <input
                    v-if="editingId === conv.id"
                    v-model="editingTitle"
                    @blur="saveTitle"
                    @keyup.enter="saveTitle"
                    @keyup.escape="cancelEdit"
                    @click.stop
                    class="w-full bg-gatales-input border border-gatales-border rounded px-1.5 py-0.5 text-xs focus:outline-none focus:ring-1 focus:ring-gatales-accent"
                    autofocus
                  />
                  <p v-else :class="[
                    'text-xs truncate',
                    conv.message_count === 0 ? 'italic opacity-60' : ''
                  ]">{{ conv.title || 'Nueva conversacion' }}</p>
                </div>

                <!-- Actions Button (on hover) -->
                <button
                  @click.stop="openContextMenu($event, conv.id)"
                  class="hidden group-hover:flex p-0.5 rounded hover:bg-gatales-border transition-colors"
                >
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                  </svg>
                </button>

                <!-- Context Menu -->
                <Transition name="fade">
                  <div
                    v-if="showContextMenu === conv.id"
                    class="absolute right-0 top-full mt-0.5 z-50 w-32 bg-gatales-sidebar border border-gatales-border rounded-md shadow-lg py-0.5"
                    @click.stop
                  >
                    <button
                      @click="startEditing(conv)"
                      class="w-full text-left px-2.5 py-1.5 text-xs text-gatales-text hover:bg-gatales-input"
                    >
                      Renombrar
                    </button>
                    <button
                      @click="handleArchive(conv.id)"
                      class="w-full text-left px-2.5 py-1.5 text-xs text-gatales-text hover:bg-gatales-input"
                    >
                      Archivar
                    </button>
                    <button
                      @click="handleDelete(conv.id)"
                      class="w-full text-left px-2.5 py-1.5 text-xs text-red-400 hover:bg-gatales-input"
                    >
                      Eliminar
                    </button>
                  </div>
                </Transition>
              </div>
            </div>
          </div>
        </template>

        <!-- No Search Results -->
        <div v-if="searchInput.length >= 2 && conversationsStore.searchResults.length === 0 && !conversationsStore.isSearching" class="p-3 text-center">
          <p class="text-gatales-text-secondary text-xs">Sin resultados</p>
        </div>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
