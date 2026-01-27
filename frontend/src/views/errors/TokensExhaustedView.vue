<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useChatStore } from '../../stores/chat'

const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()

const renewalDate = computed(() => {
  const date = authStore.user?.subscription?.ends_at
  if (!date) return null

  return new Date(date).toLocaleDateString('es-ES', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
})

const upgradeUrl = computed(() => {
  return authStore.user?.hotmart_upgrade_url || 'https://hotmart.com'
})

function goToChat() {
  chatStore.clearError()
  router.push('/chat')
}
</script>

<template>
  <div class="min-h-dvh flex items-center justify-center bg-gatales-bg px-4 py-8 safe-area-inset">
    <div class="w-full max-w-sm sm:max-w-md text-center">
      <!-- Icon -->
      <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 sm:mb-6 rounded-full bg-yellow-500/20 flex items-center justify-center">
        <svg class="w-8 h-8 sm:w-10 sm:h-10 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>

      <!-- Title -->
      <h1 class="text-xl sm:text-2xl font-bold text-gatales-text mb-2">
        Tus tokens se han agotado!
      </h1>

      <!-- Description -->
      <p class="text-sm sm:text-base text-gatales-text-secondary mb-5 sm:mb-6 px-2">
        Puedes esperar a que se renueven el proximo mes o actualizar tu plan para obtener mas tokens.
      </p>

      <!-- Renewal date -->
      <div v-if="renewalDate" class="card mb-5 sm:mb-6">
        <p class="text-xs sm:text-sm text-gatales-text-secondary">Proxima renovacion</p>
        <p class="text-base sm:text-lg font-semibold text-gatales-text">{{ renewalDate }}</p>
      </div>

      <!-- Actions -->
      <div class="space-y-3">
        <a
          :href="upgradeUrl"
          target="_blank"
          class="btn-primary block w-full text-center py-3 text-sm sm:text-base active:scale-[0.98] transition-transform"
        >
          Actualizar plan
        </a>

        <button
          @click="goToChat"
          class="btn-secondary w-full py-3 text-sm sm:text-base active:scale-[0.98] transition-transform"
        >
          Volver al chat
        </button>
      </div>
    </div>
  </div>
</template>
