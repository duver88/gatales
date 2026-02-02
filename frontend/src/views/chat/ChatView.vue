<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useChatStore } from '../../stores/chat'
import { useConversationsStore } from '../../stores/conversations'
import { useThemeStore } from '../../stores/theme'
import { authApi } from '../../services/api'
import ChatMessage from '../../components/chat/ChatMessage.vue'
import ChatInput from '../../components/chat/ChatInput.vue'
import TokenIndicator from '../../components/chat/TokenIndicator.vue'
import ConversationSidebar from '../../components/chat/ConversationSidebar.vue'

const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()
const conversationsStore = useConversationsStore()
const themeStore = useThemeStore()

const messagesContainer = ref(null)
const showUserMenu = ref(false)
const showAssistantModal = ref(false)
const showSidebar = ref(false)
const showPasswordModal = ref(false)
const showAvatarModal = ref(false)

// Password change form
const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: ''
})
const passwordError = ref('')
const passwordSuccess = ref('')
const isChangingPassword = ref(false)

// Avatar form
const avatarPreview = ref(null)
const avatarFile = ref(null)
const avatarError = ref('')
const isUploadingAvatar = ref(false)

// Responsive - auto show sidebar on desktop
const isDesktop = ref(window.innerWidth >= 768)

// Debounced resize handler
let resizeTimeout = null
function handleResize() {
  clearTimeout(resizeTimeout)
  resizeTimeout = setTimeout(() => {
    isDesktop.value = window.innerWidth >= 768
    if (isDesktop.value) {
      showSidebar.value = true
    }
  }, 150)
}

// Cleanup on unmount
onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
  clearTimeout(resizeTimeout)
  clearTimeout(scrollTimeout)
})

const formattedTokens = computed(() => {
  const balance = authStore.tokensBalance
  return balance.toLocaleString('es-ES')
})

const formattedMonthlyTokens = computed(() => {
  const monthly = authStore.tokensMonthly
  return monthly.toLocaleString('es-ES')
})

const assistantName = computed(() => {
  return chatStore.currentAssistant?.name || 'El Cursales'
})

const welcomeMessage = computed(() => {
  return chatStore.currentAssistant?.welcome_message || '?Sobre que tema te gustaria crear un video hoy?'
})

const conversationTitle = computed(() => {
  return chatStore.currentConversation?.title || 'Nueva conversacion'
})

// Token usage display
const lastTokenInfo = computed(() => {
  const tokens = chatStore.lastMessageTokens
  if (!tokens.input && !tokens.output) return null
  return {
    input: tokens.input.toLocaleString('es-ES'),
    output: tokens.output.toLocaleString('es-ES'),
    cost: tokens.cost.toFixed(6)
  }
})

const conversationTokenInfo = computed(() => {
  const conv = chatStore.currentConversation
  if (!conv?.total_tokens_input && !conv?.total_tokens_output) return null

  // Calculate cost for conversation totals using the last model or default
  const model = chatStore.lastMessageTokens.model || 'gpt-4o-mini'
  const pricing = {
    'gpt-5.2': { input: 1.25, output: 10.00 },
    'gpt-5.2-mini': { input: 0.25, output: 2.00 },
    'gpt-5.2-codex': { input: 1.50, output: 12.00 },
    'gpt-5.1': { input: 1.25, output: 10.00 },
    'gpt-5.1-mini': { input: 0.25, output: 2.00 },
    'gpt-5': { input: 1.25, output: 10.00 },
    'gpt-5-mini': { input: 0.25, output: 2.00 },
    'gpt-4o': { input: 2.50, output: 10.00 },
    'gpt-4o-mini': { input: 0.15, output: 0.60 },
    'o1': { input: 15.00, output: 60.00 },
    'o1-mini': { input: 3.00, output: 12.00 },
  }
  const modelPricing = pricing[model] || pricing['gpt-4o-mini']
  const inputCost = ((conv.total_tokens_input || 0) / 1000000) * modelPricing.input
  const outputCost = ((conv.total_tokens_output || 0) / 1000000) * modelPricing.output
  const totalCost = inputCost + outputCost

  return {
    input: (conv.total_tokens_input || 0).toLocaleString('es-ES'),
    output: (conv.total_tokens_output || 0).toLocaleString('es-ES'),
    cost: totalCost.toFixed(6)
  }
})

onMounted(async () => {
  // Set up resize listener
  window.addEventListener('resize', handleResize, { passive: true })
  handleResize()

  try {
    // Fetch conversations and assistants in PARALLEL for faster load
    const [conversationsResult] = await Promise.all([
      conversationsStore.fetchConversations(),
      chatStore.fetchAvailableAssistants()
    ])

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

// Debounced scroll to prevent jank during streaming
let scrollTimeout = null
function debouncedScrollToBottom() {
  clearTimeout(scrollTimeout)
  scrollTimeout = setTimeout(() => {
    scrollToBottom()
  }, 50)
}

// Consolidated watcher for all scroll triggers
watch(
  () => [chatStore.messages.length, chatStore.isThinking, chatStore.isStreaming],
  ([messagesLen, isThinking, isStreaming], [oldMessagesLen]) => {
    // Always scroll when new message is added
    if (messagesLen !== oldMessagesLen) {
      nextTick(() => scrollToBottom())
    }
    // Scroll when thinking starts
    else if (isThinking) {
      nextTick(() => scrollToBottom())
    }
    // Debounce scroll during streaming (called frequently)
    else if (isStreaming) {
      debouncedScrollToBottom()
    }
  }
)

// Watch streaming content separately with heavy debounce
watch(
  () => chatStore.streamingContent,
  () => {
    if (chatStore.isStreaming) {
      debouncedScrollToBottom()
    }
  }
)

function handleStopStreaming() {
  chatStore.stopStreaming()
}

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

// Password change handlers
function openPasswordModal() {
  showUserMenu.value = false
  passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
  passwordError.value = ''
  passwordSuccess.value = ''
  showPasswordModal.value = true
}

async function handleChangePassword() {
  passwordError.value = ''
  passwordSuccess.value = ''

  if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
    passwordError.value = 'Las contraseñas no coinciden'
    return
  }

  if (passwordForm.value.password.length < 8) {
    passwordError.value = 'La contraseña debe tener al menos 8 caracteres'
    return
  }

  isChangingPassword.value = true
  try {
    await authApi.changePassword(passwordForm.value)
    passwordSuccess.value = 'Contraseña actualizada correctamente'
    passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
    setTimeout(() => {
      showPasswordModal.value = false
    }, 1500)
  } catch (e) {
    passwordError.value = e.response?.data?.message || 'Error al cambiar la contraseña'
  } finally {
    isChangingPassword.value = false
  }
}

// Avatar handlers
function openAvatarModal() {
  showUserMenu.value = false
  avatarPreview.value = authStore.user?.avatar_url || null
  avatarFile.value = null
  avatarError.value = ''
  showAvatarModal.value = true
}

function handleAvatarSelect(event) {
  const file = event.target.files[0]
  if (file) {
    if (file.size > 2 * 1024 * 1024) {
      avatarError.value = 'La imagen debe ser menor a 2MB'
      return
    }
    if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
      avatarError.value = 'Solo se permiten imágenes JPG, PNG o GIF'
      return
    }
    avatarFile.value = file
    avatarPreview.value = URL.createObjectURL(file)
    avatarError.value = ''
  }
}

async function handleUploadAvatar() {
  if (!avatarFile.value) return

  isUploadingAvatar.value = true
  avatarError.value = ''
  try {
    const response = await authApi.uploadAvatar(avatarFile.value)
    authStore.user.avatar_url = response.data.avatar_url
    localStorage.setItem('user', JSON.stringify(authStore.user))
    showAvatarModal.value = false
  } catch (e) {
    avatarError.value = e.response?.data?.message || 'Error al subir la imagen'
  } finally {
    isUploadingAvatar.value = false
  }
}

async function handleDeleteAvatar() {
  if (!confirm('¿Eliminar tu foto de perfil?')) return

  isUploadingAvatar.value = true
  try {
    await authApi.deleteAvatar()
    authStore.user.avatar_url = null
    localStorage.setItem('user', JSON.stringify(authStore.user))
    avatarPreview.value = null
    showAvatarModal.value = false
  } catch (e) {
    avatarError.value = e.response?.data?.message || 'Error al eliminar la imagen'
  } finally {
    isUploadingAvatar.value = false
  }
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

          <!-- Logo + Brand -->
          <div class="hidden sm:flex items-center gap-2">
            <div class="w-8 h-8 bg-gatales-accent rounded-lg flex items-center justify-center">
              <!-- Cat head silhouette -->
              <svg class="w-6 h-6 text-white" viewBox="0 0 100 100" fill="currentColor">
                <!-- Cat head -->
                <path d="M50 15 L25 35 L25 60 Q25 80 50 85 Q75 80 75 60 L75 35 Z"/>
                <!-- Left ear -->
                <path d="M25 35 L15 10 L35 30 Z"/>
                <!-- Right ear -->
                <path d="M75 35 L85 10 L65 30 Z"/>
              </svg>
            </div>
            <h1 class="text-lg sm:text-xl font-bold text-gatales-accent">El Cursales</h1>
          </div>

          <!-- Conversation Title -->
          <div class="flex items-center gap-2">
            <span class="text-sm text-gatales-text-secondary truncate max-w-[150px] sm:max-w-[200px]">
              {{ conversationTitle }}
            </span>
            <!-- Token usage info (desktop only) -->
            <div v-if="lastTokenInfo" class="hidden lg:flex items-center gap-3 text-xs text-gatales-text-secondary">
              <span class="text-gatales-accent">|</span>
              <span>{{ lastTokenInfo.input }} in / {{ lastTokenInfo.output }} out</span>
              <span class="text-green-400">${{ lastTokenInfo.cost }}</span>
            </div>
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
          <!-- Token Usage Stats (mobile-friendly popup) -->
          <div v-if="lastTokenInfo || conversationTokenInfo" class="relative group">
            <button class="p-1.5 rounded-lg hover:bg-gatales-input transition-colors text-gatales-text-secondary">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </button>
            <!-- Popup on hover/focus -->
            <div class="absolute right-0 mt-2 w-48 bg-gatales-sidebar border border-gatales-border rounded-lg shadow-lg py-2 px-3 z-50 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">
              <p class="text-xs font-medium text-gatales-text mb-2">Uso de tokens</p>
              <div v-if="lastTokenInfo" class="text-xs text-gatales-text-secondary mb-1">
                <span class="text-gatales-accent">Ultimo:</span> {{ lastTokenInfo.input }} in / {{ lastTokenInfo.output }} out
                <span class="text-green-400 ml-1">${{ lastTokenInfo.cost }}</span>
              </div>
              <div v-if="conversationTokenInfo" class="text-xs text-gatales-text-secondary">
                <span class="text-blue-400">Total:</span> {{ conversationTokenInfo.input }} in / {{ conversationTokenInfo.output }} out
                <span class="text-green-400 ml-1">${{ conversationTokenInfo.cost }}</span>
              </div>
            </div>
          </div>

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
              <div class="w-8 h-8 rounded-full bg-gatales-accent flex items-center justify-center text-white font-medium text-sm overflow-hidden">
                <img v-if="authStore.user?.avatar_url" :src="authStore.user.avatar_url" class="w-full h-full object-cover" alt="Avatar" />
                <span v-else>{{ authStore.user?.name?.charAt(0)?.toUpperCase() || 'U' }}</span>
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
              <!-- Theme Toggle -->
              <button
                @click="themeStore.toggleTheme"
                class="w-full flex items-center justify-between px-4 py-3 text-sm text-gatales-text hover:bg-gatales-input active:bg-gatales-border"
              >
                <span>Tema {{ themeStore.theme === 'dark' ? 'Oscuro' : 'Claro' }}</span>
                <!-- Sun icon for light mode -->
                <svg v-if="themeStore.theme === 'light'" class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <!-- Moon icon for dark mode -->
                <svg v-else class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
              </button>
              <!-- Change Avatar -->
              <button
                @click="openAvatarModal"
                class="w-full flex items-center gap-3 px-4 py-3 text-sm text-gatales-text hover:bg-gatales-input active:bg-gatales-border"
              >
                <svg class="w-5 h-5 text-gatales-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Cambiar foto
              </button>
              <!-- Change Password -->
              <button
                @click="openPasswordModal"
                class="w-full flex items-center gap-3 px-4 py-3 text-sm text-gatales-text hover:bg-gatales-input active:bg-gatales-border"
              >
                <svg class="w-5 h-5 text-gatales-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
                Cambiar contraseña
              </button>
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
            :user-avatar="authStore.user?.avatar_url"
            :user-name="authStore.user?.name || 'Usuario'"
          />

          <!-- Typing indicator (only when sending but not streaming/thinking - those show content directly) -->
          <div v-if="chatStore.isSending && !chatStore.isStreaming && !chatStore.isThinking" class="px-3 sm:px-4 py-4 sm:py-6 message-assistant">
            <div class="flex items-start gap-3 sm:gap-4 max-w-3xl mx-auto">
              <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-gatales-accent flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" viewBox="0 0 100 100" fill="currentColor">
                  <path d="M50 15 L25 35 L25 60 Q25 80 50 85 Q75 80 75 60 L75 35 Z"/>
                  <path d="M25 35 L15 10 L35 30 Z"/>
                  <path d="M75 35 L85 10 L65 30 Z"/>
                </svg>
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
        @stop="handleStopStreaming"
        :disabled="chatStore.isThinking"
        :isStreaming="chatStore.isStreaming || chatStore.isThinking"
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

    <!-- Password Change Modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showPasswordModal"
          class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
          @click.self="showPasswordModal = false"
        >
          <div class="w-full max-w-md bg-gatales-sidebar border border-gatales-border rounded-xl shadow-xl">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b border-gatales-border">
              <h3 class="text-lg font-semibold text-gatales-text">Cambiar contraseña</h3>
              <button
                @click="showPasswordModal = false"
                class="p-2 rounded-lg hover:bg-gatales-input transition-colors"
              >
                <svg class="w-5 h-5 text-gatales-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleChangePassword" class="p-4 space-y-4">
              <!-- Success message -->
              <div v-if="passwordSuccess" class="bg-green-500/10 border border-green-500/50 text-green-400 px-4 py-3 rounded-lg text-sm">
                {{ passwordSuccess }}
              </div>

              <!-- Error message -->
              <div v-if="passwordError" class="bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-lg text-sm">
                {{ passwordError }}
              </div>

              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Contraseña actual</label>
                <input
                  v-model="passwordForm.current_password"
                  type="password"
                  required
                  class="input-field"
                  placeholder="Tu contraseña actual"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Nueva contraseña</label>
                <input
                  v-model="passwordForm.password"
                  type="password"
                  required
                  class="input-field"
                  placeholder="Mínimo 8 caracteres"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gatales-text-secondary mb-1">Confirmar nueva contraseña</label>
                <input
                  v-model="passwordForm.password_confirmation"
                  type="password"
                  required
                  class="input-field"
                  placeholder="Repite la nueva contraseña"
                />
              </div>

              <div class="flex gap-3 pt-2">
                <button
                  type="button"
                  @click="showPasswordModal = false"
                  class="flex-1 btn-secondary"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  :disabled="isChangingPassword"
                  class="flex-1 btn-primary"
                >
                  <svg v-if="isChangingPassword" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                  </svg>
                  {{ isChangingPassword ? 'Guardando...' : 'Guardar' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Avatar Change Modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showAvatarModal"
          class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
          @click.self="showAvatarModal = false"
        >
          <div class="w-full max-w-md bg-gatales-sidebar border border-gatales-border rounded-xl shadow-xl">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b border-gatales-border">
              <h3 class="text-lg font-semibold text-gatales-text">Cambiar foto de perfil</h3>
              <button
                @click="showAvatarModal = false"
                class="p-2 rounded-lg hover:bg-gatales-input transition-colors"
              >
                <svg class="w-5 h-5 text-gatales-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Content -->
            <div class="p-6 space-y-6">
              <!-- Error message -->
              <div v-if="avatarError" class="bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-lg text-sm">
                {{ avatarError }}
              </div>

              <!-- Avatar Preview -->
              <div class="flex flex-col items-center">
                <div class="w-28 h-28 rounded-full bg-gatales-input flex items-center justify-center overflow-hidden border-4 border-gatales-border shadow-lg">
                  <img v-if="avatarPreview" :src="avatarPreview" class="w-full h-full object-cover" alt="Avatar" />
                  <span v-else class="text-4xl font-bold text-gatales-accent">
                    {{ authStore.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                  </span>
                </div>

                <!-- File input -->
                <label class="mt-5 cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 bg-gatales-input hover:bg-gatales-border border border-gatales-border rounded-lg transition-colors">
                  <svg class="w-5 h-5 text-gatales-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  <span class="text-sm font-medium text-gatales-text">Seleccionar imagen</span>
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/gif"
                    class="hidden"
                    @change="handleAvatarSelect"
                  />
                </label>
                <p class="text-xs text-gatales-text-secondary mt-2">JPG, PNG o GIF. Máximo 2MB.</p>
              </div>

              <!-- Actions -->
              <div class="flex gap-3 pt-2">
                <button
                  v-if="authStore.user?.avatar_url"
                  type="button"
                  @click="handleDeleteAvatar"
                  :disabled="isUploadingAvatar"
                  class="px-4 py-2.5 text-sm font-medium text-red-400 border border-red-500/30 hover:bg-red-500/10 rounded-lg transition-colors"
                >
                  Eliminar
                </button>
                <button
                  type="button"
                  @click="showAvatarModal = false"
                  class="flex-1 px-4 py-2.5 text-sm font-medium text-gatales-text bg-gatales-input hover:bg-gatales-border border border-gatales-border rounded-lg transition-colors"
                >
                  Cancelar
                </button>
                <button
                  @click="handleUploadAvatar"
                  :disabled="!avatarFile || isUploadingAvatar"
                  class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-gatales-accent hover:bg-gatales-accent-hover disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors flex items-center justify-center gap-2"
                >
                  <svg v-if="isUploadingAvatar" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                  </svg>
                  {{ isUploadingAvatar ? 'Subiendo...' : 'Guardar' }}
                </button>
              </div>
            </div>
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
