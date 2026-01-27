<script setup>
import { ref, onMounted, nextTick, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useChatStore } from '../../stores/chat'
import { useConversationsStore } from '../../stores/conversations'
import ChatMessage from '../../components/chat/ChatMessage.vue'
import ChatInput from '../../components/chat/ChatInput.vue'
import TokenIndicator from '../../components/chat/TokenIndicator.vue'
import ConversationSidebar from '../../components/chat/ConversationSidebar.vue'

const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()
const conversationsStore = useConversationsStore()

const messagesContainer = ref(null)
const showUserMenu = ref(false)
const showAssistantModal = ref(false)
const showSidebar = ref(false)

// Responsive - auto show sidebar on desktop
const isDesktop = ref(window.innerWidth >= 768)

function handleResize() {
  isDesktop.value = window.innerWidth >= 768
  if (isDesktop.value) {
    showSidebar.value = true
  }
}

const formattedTokens = computed(() => {
  const balance = authStore.tokensBalance
  return balance.toLocaleString('es-ES')
})

const formattedMonthlyTokens = computed(() => {
  const monthly = authStore.tokensMonthly
  return monthly.toLocaleString('es-ES')
})

const assistantName = computed(() => {
  return chatStore.currentAssistant?.name || 'Gatales'
})

const welcomeMessage = computed(() => {
  return chatStore.currentAssistant?.welcome_message || '?Sobre que tema te gustaria crear un video hoy?'
})

const conversationTitle = computed(() => {
  return chatStore.currentConversation?.title || 'Nueva conversacion'
})

onMounted(async () => {
  // Set up resize listener
  window.addEventListener('resize', handleResize)
  handleResize()

  try {
    // Fetch conversations first
    await conversationsStore.fetchConversations()

    // Then fetch available assistants
    await chatStore.fetchAvailableAssistants()

    // If there's a current conversation selected, load its messages
    if (conversationsStore.currentConversationId) {
      await chatStore.setCurrentConversation(conversationsStore.currentConversationId)
    } else if (conversationsStore.hasConversations) {
      // Select the most recent conversation
      const firstGroup = Object.values(conversationsStore.groupedConversations)[0]
      if (firstGroup?.conversations?.length > 0) {
        const firstConv = firstGroup.conversations[0]
        conversationsStore.selectConversation(firstConv.id)
        await chatStore.setCurrentConversation(firstConv.id)
      }
    }

    scrollToBottom()
  } catch (e) {
    console.error('Error loading chat:', e)
  }
})

async function handleSelectConversation(conversationId) {
  if (conversationId === chatStore.currentConversationId) return

  conversationsStore.selectConversation(conversationId)
  await chatStore.setCurrentConversation(conversationId)
  scrollToBottom()
  // Close sidebar on mobile
  if (!isDesktop.value) {
    showSidebar.value = false
  }
}

async function handleNewConversation() {
  try {
    const conv = await conversationsStore.createConversation()
    chatStore.clearCurrentConversation()
    chatStore.currentConversationId = conv.id
    chatStore.currentConversation = conv
    // Close sidebar on mobile
    if (!isDesktop.value) {
      showSidebar.value = false
    }
  } catch (e) {
    console.error('Error creating conversation:', e)
  }
}

function handleCloseSidebar() {
  if (!isDesktop.value) {
    showSidebar.value = false
  }
}

async function handleSelectAssistant(assistantId) {
  if (assistantId === chatStore.currentAssistant?.id) {
    showAssistantModal.value = false
    return
  }

  const confirmChange = confirm('?Cambiar de asistente? Se creara una nueva conversacion.')
  if (!confirmChange) return

  try {
    const response = await chatStore.changeAssistant(assistantId)
    showAssistantModal.value = false

    // Refresh conversations list
    await conversationsStore.fetchConversations()

    // Select the new conversation
    if (response.conversation_id) {
      conversationsStore.selectConversation(response.conversation_id)
      chatStore.currentConversationId = response.conversation_id
    }
  } catch (e) {
    console.error('Error changing assistant:', e)
  }
}

async function handleSendMessage(content) {
  try {
    // If no conversation selected, create one first
    if (!chatStore.currentConversationId) {
      const conv = await conversationsStore.createConversation()
      chatStore.currentConversationId = conv.id
      chatStore.currentConversation = conv
    }

    // Use streaming for better UX
    const response = await chatStore.sendMessageStream(content)
    await nextTick()
    scrollToBottom()

    // Update current conversation title locally if returned
    if (response?.conversation?.title) {
      chatStore.currentConversation = response.conversation
      conversationsStore.updateConversationLocally(response.conversation.id, {
        title: response.conversation.title,
        last_message_at: new Date().toISOString()
      })
    }
  } catch (e) {
    if (chatStore.freePlanBlocked) {
      router.push('/free-plan')
    } else if (chatStore.tokensExhausted) {
      router.push('/tokens-exhausted')
    }
  }
}

// Auto-scroll when messages change (new message added)
watch(() => chatStore.messages.length, () => {
  nextTick(() => scrollToBottom())
})

// Auto-scroll during streaming
watch(() => chatStore.streamingContent, () => {
  if (chatStore.isStreaming) {
    scrollToBottom()
  }
})

// Auto-scroll when thinking starts
watch(() => chatStore.isThinking, (isThinking) => {
  if (isThinking) {
    nextTick(() => scrollToBottom())
  }
})

async function handleClearHistory() {
  if (confirm('?Estas seguro de que quieres limpiar el historial de esta conversacion? Esta accion no se puede deshacer.')) {
    await chatStore.clearHistory()
    // Refresh conversations list
    await conversationsStore.fetchConversations()
  }
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}
</script>

<template>
  <div class="flex h-screen bg-gatales-bg">
    <!-- Conversation Sidebar -->
    <Transition name="slide-sidebar">
      <ConversationSidebar
        v-show="showSidebar || isDesktop"
        @select="handleSelectConversation"
        @close="handleCloseSidebar"
        class="fixed md:relative z-30 h-full"
      />
    </Transition>

    <!-- Sidebar Backdrop (mobile) -->
    <Transition name="fade">
      <div
        v-if="showSidebar && !isDesktop"
        @click="showSidebar = false"
        class="fixed inset-0 z-20 bg-black/50 md:hidden"
      />
    </Transition>

    <!-- Main Chat Area -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Header -->
      <header class="flex items-center justify-between px-2 sm:px-4 py-2 sm:py-3 bg-gatales-sidebar border-b border-gatales-border safe-area-top">
        <div class="flex items-center gap-2">
          <!-- Sidebar Toggle (mobile) -->
          <button
            @click="showSidebar = !showSidebar"
            class="p-2 rounded-lg hover:bg-gatales-input transition-colors md:hidden"
          >
            <svg class="w-5 h-5 text-gatales-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <h1 class="text-lg sm:text-xl font-bold text-gatales-accent hidden sm:block">Gatales</h1>

          <!-- Conversation Title -->
          <div class="flex items-center gap-2">
            <span class="text-sm text-gatales-text-secondary truncate max-w-[150px] sm:max-w-[200px]">
              {{ conversationTitle }}
            </span>
          </div>

          <!-- Assistant Selector Button -->
          <button
            v-if="chatStore.availableAssistants.length > 1"
            @click="showAssistantModal = true"
            class="flex items-center gap-1 px-2 py-1 rounded-lg bg-gatales-input hover:bg-gatales-border transition-colors text-xs sm:text-sm"
          >
            <svg class="w-4 h-4 text-gatales-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <span class="text-gatales-text-secondary max-w-[60px] sm:max-w-[100px] truncate">{{ assistantName }}</span>
            <svg class="w-3 h-3 text-gatales-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>

        <div class="flex items-center gap-2 sm:gap-4">
          <!-- Token Indicator -->
          <TokenIndicator
            :balance="authStore.tokensBalance"
            :monthly="authStore.tokensMonthly"
          />

          <!-- User Menu -->
          <div class="relative">
            <button
              @click="showUserMenu = !showUserMenu"
              class="flex items-center gap-1 sm:gap-2 p-1.5 sm:px-3 sm:py-2 rounded-lg hover:bg-gatales-input transition-colors"
            >
              <div class="w-8 h-8 rounded-full bg-gatales-accent flex items-center justify-center text-white font-medium text-sm">
                {{ authStore.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
              </div>
              <svg class="w-4 h-4 text-gatales-text-secondary hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Dropdown -->
            <div
              v-if="showUserMenu"
              class="absolute right-0 mt-2 w-56 sm:w-48 bg-gatales-sidebar border border-gatales-border rounded-lg shadow-lg py-1 z-50"
            >
              <div class="px-4 py-3 border-b border-gatales-border">
                <p class="text-sm font-medium text-gatales-text truncate">{{ authStore.user?.name }}</p>
                <p class="text-xs text-gatales-text-secondary truncate">{{ authStore.user?.email }}</p>
              </div>
              <button
                @click="handleClearHistory"
                class="w-full text-left px-4 py-3 text-sm text-gatales-text hover:bg-gatales-input active:bg-gatales-border"
              >
                Limpiar historial
              </button>
              <button
                @click="handleLogout"
                class="w-full text-left px-4 py-3 text-sm text-red-400 hover:bg-gatales-input active:bg-gatales-border"
              >
                Cerrar sesion
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Click outside to close menu -->
      <div
        v-if="showUserMenu"
        @click="showUserMenu = false"
        class="fixed inset-0 z-40"
      />

      <!-- Messages Area -->
      <main
        ref="messagesContainer"
        class="flex-1 overflow-y-auto overscroll-contain"
      >
        <!-- Empty State -->
        <div
          v-if="!chatStore.hasMessages && !chatStore.isLoading"
          class="h-full flex items-center justify-center px-4"
        >
          <div class="text-center max-w-sm sm:max-w-md">
            <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto mb-4 rounded-full bg-gatales-accent/20 flex items-center justify-center">
              <svg class="w-7 h-7 sm:w-8 sm:h-8 text-gatales-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
              </svg>
            </div>
            <h2 class="text-lg sm:text-xl font-semibold text-gatales-text mb-2">
              {{ assistantName }}
            </h2>
            <p class="text-sm sm:text-base text-gatales-text-secondary">
              {{ welcomeMessage }}
            </p>
          </div>
        </div>

        <!-- Messages -->
        <div v-else class="max-w-3xl mx-auto py-2 sm:py-4">
          <ChatMessage
            v-for="message in chatStore.messages"
            :key="message.id"
            :message="message"
          />

          <!-- Typing indicator (only when sending but not streaming/thinking - those show content directly) -->
          <div v-if="chatStore.isSending && !chatStore.isStreaming && !chatStore.isThinking" class="px-3 sm:px-4 py-4 sm:py-6 message-assistant">
            <div class="flex items-start gap-3 sm:gap-4 max-w-3xl mx-auto">
              <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-gatales-accent flex items-center justify-center flex-shrink-0">
                <span class="text-white text-xs sm:text-sm font-medium">G</span>
              </div>
              <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
              </div>
            </div>
          </div>
        </div>
      </main>

      <!-- Input Area -->
      <ChatInput
        @send="handleSendMessage"
        :disabled="chatStore.isSending"
      />
    </div>

    <!-- Assistant Selector Slider -->
    <Teleport to="body">
      <!-- Backdrop -->
      <Transition name="fade">
        <div
          v-if="showAssistantModal"
          class="fixed inset-0 z-50 bg-black/60"
          @click="showAssistantModal = false"
        />
      </Transition>

      <!-- Slider Panel -->
      <Transition name="slide">
        <div
          v-if="showAssistantModal"
          class="fixed inset-y-0 right-0 z-50 w-full max-w-sm bg-gatales-sidebar border-l border-gatales-border shadow-xl flex flex-col"
        >
          <!-- Header -->
          <div class="flex items-center justify-between p-4 border-b border-gatales-border">
            <div>
              <h3 class="text-lg font-semibold text-gatales-text">Elegir Asistente</h3>
              <p class="text-xs text-gatales-text-secondary mt-0.5">Selecciona tu GPT preferido</p>
            </div>
            <button
              @click="showAssistantModal = false"
              class="p-2 rounded-lg hover:bg-gatales-input transition-colors"
            >
              <svg class="w-5 h-5 text-gatales-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Assistants List -->
          <div class="flex-1 overflow-y-auto p-4 space-y-3">
            <button
              v-for="assistant in chatStore.availableAssistants"
              :key="assistant.id"
              @click="handleSelectAssistant(assistant.id)"
              :disabled="chatStore.isChangingAssistant"
              :class="[
                'w-full text-left p-4 rounded-xl border-2 transition-all duration-200',
                assistant.id === chatStore.currentAssistant?.id
                  ? 'border-gatales-accent bg-gatales-accent/10 shadow-lg shadow-gatales-accent/20'
                  : 'border-gatales-border hover:border-gatales-accent/50 hover:bg-gatales-input'
              ]"
            >
              <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-xl bg-gatales-accent/20 flex items-center justify-center shrink-0">
                  <svg class="w-6 h-6 text-gatales-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <p class="font-semibold text-gatales-text">{{ assistant.name }}</p>
                    <span
                      v-if="assistant.id === chatStore.currentAssistant?.id"
                      class="text-xs px-2 py-0.5 rounded-full bg-gatales-accent text-white"
                    >
                      Activo
                    </span>
                    <span
                      v-if="assistant.is_default"
                      class="text-xs px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-400"
                    >
                      Por defecto
                    </span>
                  </div>
                  <p v-if="assistant.description" class="text-sm text-gatales-text-secondary mt-1 line-clamp-2">
                    {{ assistant.description }}
                  </p>
                </div>
                <div v-if="assistant.id === chatStore.currentAssistant?.id" class="shrink-0">
                  <svg class="w-6 h-6 text-gatales-accent" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                </div>
              </div>
            </button>

            <!-- Loading state -->
            <div v-if="chatStore.isChangingAssistant" class="text-center py-6">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-2 border-gatales-accent border-t-transparent"></div>
              <p class="text-sm text-gatales-text-secondary mt-3">Cambiando asistente...</p>
            </div>
          </div>

          <!-- Footer info -->
          <div class="p-4 border-t border-gatales-border bg-gatales-bg/50">
            <p class="text-xs text-gatales-text-secondary text-center">
              Al cambiar de asistente, se creara una nueva conversacion.
            </p>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* Fade transition for backdrop */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Slide transition for panel */
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}

/* Slide sidebar transition */
.slide-sidebar-enter-active,
.slide-sidebar-leave-active {
  transition: transform 0.3s ease;
}
.slide-sidebar-enter-from,
.slide-sidebar-leave-to {
  transform: translateX(-100%);
}

/* Line clamp utility */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
