<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useChatStore } from '../../stores/chat'
import PricingCards from '../../components/PricingCards.vue'

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

function goToChat() {
  chatStore.clearError()
  router.push('/chat')
}
</script>

<template>
  <div class="min-h-dvh bg-gatales-bg flex flex-col items-center justify-center px-4 py-6 sm:py-10 safe-area-inset">
    <div class="w-full max-w-2xl mx-auto">
      <!-- Header compacto -->
      <div class="text-center mb-6 sm:mb-8">
        <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto mb-3 sm:mb-4 rounded-full bg-yellow-500/15 flex items-center justify-center">
          <svg class="w-7 h-7 sm:w-8 sm:h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h1 class="text-xl sm:text-2xl font-bold text-gatales-text mb-1.5">
          Tus tokens se han agotado
        </h1>
        <p class="text-sm text-gatales-text-secondary">
          Actualiza tu plan para obtener mas tokens o espera a la renovacion.
        </p>
        <div v-if="renewalDate" class="inline-flex items-center gap-2 mt-3 px-3 py-1.5 rounded-full bg-yellow-500/10 text-yellow-500 text-xs sm:text-sm font-medium">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          Renovacion: {{ renewalDate }}
        </div>
      </div>

      <!-- Pricing Cards -->
      <PricingCards highlight-plan="super-pro" />

      <!-- Acciones -->
      <div class="max-w-sm mx-auto mt-6 sm:mt-8">
        <button
          @click="goToChat"
          class="w-full px-6 py-3 bg-gatales-input hover:bg-gatales-input/80 active:scale-[0.98] text-gatales-text font-medium rounded-lg transition-all text-sm sm:text-base"
        >
          Volver al chat
        </button>
      </div>
    </div>
  </div>
</template>
