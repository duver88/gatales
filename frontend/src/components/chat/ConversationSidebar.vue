<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useConversationsStore } from '../../stores/conversations'

const emit = defineEmits(['select', 'close'])

const conversationsStore = useConversationsStore()

const searchInput = ref('')
const showContextMenu = ref(null)
const editingId = ref(null)
const editingTitle = ref('')
const editInputRef = ref(null)

// Delete confirmation
const deleteConfirm = ref(null)

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
  nextTick(() => {
    if (editInputRef.value) {
      editInputRef.value.select()
    }
  })
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

function handleDelete(id) {
  closeContextMenu()
  deleteConfirm.value = id
}

async function confirmDelete() {
  if (deleteConfirm.value) {
    try {
      await conversationsStore.deleteConversation(deleteConfirm.value)
    } catch (e) {
      console.error('Error deleting conversation:', e)
    }
  }
  deleteConfirm.value = null
}

function cancelDelete() {
  deleteConfirm.value = null
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
  <aside class="w-[280px] sm:w-64 bg-gatales-sidebar border-r border-gatales-border flex flex-col h-full safe-area-top safe-area-bottom">
    <!-- Header - New Chat Button -->
    <div class="p-2">
      <button
        @click="handleNewConversation"
        :disabled="conversationsStore.isCreating"
        class="w-full flex items-center gap-3 px-3 py-2.5 sm:py-2 text-gatales-text hover:bg-gatales-input active:bg-gatales-border rounded-lg transition-colors disabled:opacity-50 text-sm touch-manipulation"
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
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gatales-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="searchInput"
          type="text"
          placeholder="Buscar..."
          class="w-full pl-9 pr-3 py-2 bg-gatales-input border border-gatales-border rounded-lg text-sm text-gatales-text placeholder-gatales-text-secondary focus:outline-none focus:ring-1 focus:ring-gatales-accent focus:border-transparent"
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
            <div class="px-3 py-1.5 text-xs font-semibold text-gatales-text-secondary uppercase tracking-wider">
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
                  'group relative flex items-center gap-2.5 px-2.5 py-2.5 sm:py-2 rounded-lg cursor-pointer transition-colors touch-manipulation',
                  conversationsStore.currentConversationId === conv.id
                    ? 'bg-gatales-accent/10 text-gatales-accent'
                    : 'hover:bg-gatales-input active:bg-gatales-input text-gatales-text'
                ]"
              >
                <!-- Chat Icon -->
                <svg class="w-4 h-4 shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012-2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                </svg>

                <!-- Title (editable) -->
                <div class="flex-1 min-w-0">
                  <input
                    v-if="editingId === conv.id"
                    ref="editInputRef"
                    v-model="editingTitle"
                    @blur="saveTitle"
                    @keyup.enter="saveTitle"
                    @keyup.escape="cancelEdit"
                    @click.stop
                    class="w-full bg-gatales-input border border-gatales-border rounded px-2 py-1 text-sm text-gatales-text focus:outline-none focus:ring-1 focus:ring-gatales-accent"
                  />
                  <p v-else :class="[
                    'text-sm truncate',
                    conv.message_count === 0 ? 'italic opacity-60' : ''
                  ]">{{ conv.title || 'Nueva conversacion' }}</p>
                </div>

                <!-- Actions Button (visible on mobile, hover on desktop) -->
                <button
                  @click.stop="openContextMenu($event, conv.id)"
                  class="flex sm:hidden sm:group-hover:flex p-1 rounded hover:bg-gatales-border active:bg-gatales-border transition-colors touch-manipulation opacity-50 sm:opacity-100"
                >
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                  </svg>
                </button>

                <!-- Context Menu -->
                <Transition name="fade">
                  <div
                    v-if="showContextMenu === conv.id"
                    class="absolute right-0 top-full mt-0.5 z-50 w-36 bg-gatales-sidebar border border-gatales-border rounded-lg shadow-lg py-1"
                    @click.stop
                  >
                    <button
                      @click="startEditing(conv)"
                      class="w-full text-left px-3 py-2 text-sm text-gatales-text hover:bg-gatales-input active:bg-gatales-input transition-colors"
                    >
                      Renombrar
                    </button>
                    <button
                      @click="handleArchive(conv.id)"
                      class="w-full text-left px-3 py-2 text-sm text-gatales-text hover:bg-gatales-input active:bg-gatales-input transition-colors"
                    >
                      Archivar
                    </button>
                    <button
                      @click="handleDelete(conv.id)"
                      class="w-full text-left px-3 py-2 text-sm text-red-400 hover:bg-gatales-input active:bg-gatales-input transition-colors"
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

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="deleteConfirm"
          class="fixed inset-0 z-100 flex items-center justify-center p-4"
        >
          <div class="absolute inset-0 bg-black/60" @click="cancelDelete"></div>
          <div class="relative bg-gatales-sidebar border border-gatales-border rounded-xl shadow-2xl w-full max-w-xs p-5">
            <p class="text-sm text-gatales-text text-center mb-5">
              Quieres eliminar esta conversacion?
            </p>
            <div class="flex gap-3">
              <button
                @click="cancelDelete"
                class="flex-1 py-2.5 rounded-lg text-sm font-medium text-gatales-text bg-gatales-input hover:bg-gatales-border active:bg-gatales-border transition-colors"
              >
                Cancelar
              </button>
              <button
                @click="confirmDelete"
                class="flex-1 py-2.5 rounded-lg text-sm font-medium text-white bg-red-500 hover:bg-red-600 active:bg-red-600 transition-colors"
              >
                Eliminar
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
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

.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
