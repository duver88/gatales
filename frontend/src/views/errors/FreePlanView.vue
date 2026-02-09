<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import PricingCards from '../../components/PricingCards.vue'

const router = useRouter()
const authStore = useAuthStore()

const checking = ref(true)

onMounted(async () => {
  try {
    await authStore.fetchUser(true, true)
    if (!authStore.hasFreePlan) {
      router.replace('/chat')
      return
    }
  } catch (e) {
    // If error fetching, show page anyway
  } finally {
    checking.value = false
  }
})

async function handleRefreshPlan() {
  checking.value = true
  try {
    await authStore.fetchUser(true, true)
    if (!authStore.hasFreePlan) {
      window.location.href = '/chat'
    }
  } catch (e) {
    // ignore
  } finally {
    checking.value = false
  }
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-dvh bg-gatales-bg flex flex-col items-center justify-center px-4 py-6 sm:py-10 safe-area-inset">
    <!-- Loading -->
    <div v-if="checking" class="text-center">
      <svg class="animate-spin h-8 w-8 text-gatales-accent mx-auto mb-3" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="text-gatales-text-secondary text-sm">Verificando tu plan...</p>
    </div>

    <div v-else class="w-full max-w-2xl mx-auto">
      <!-- Header compacto -->
      <div class="text-center mb-6 sm:mb-8">
        <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto mb-3 sm:mb-4 rounded-full bg-amber-500/15 flex items-center justify-center">
          <svg class="w-7 h-7 sm:w-8 sm:h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <h1 class="text-xl sm:text-2xl font-bold text-gatales-text mb-1.5">
          Elige tu plan para comenzar
        </h1>
        <p class="text-sm text-gatales-text-secondary">
          Tu plan actual no incluye acceso al asistente. Desbloquea todo eligiendo un plan.
        </p>
      </div>

      <!-- Pricing Cards directamente -->
      <PricingCards />

      <!-- Acciones -->
      <div class="max-w-sm mx-auto space-y-3 mt-6 sm:mt-8">
        <button
          @click="handleRefreshPlan"
          class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-medium rounded-lg transition-all text-sm sm:text-base"
        >
          Ya actualice mi plan
        </button>
        <button
          @click="handleLogout"
          class="w-full px-6 py-3 bg-gatales-input hover:bg-gatales-input/80 active:scale-[0.98] text-gatales-text font-medium rounded-lg transition-all text-sm sm:text-base"
        >
          Cerrar Sesion
        </button>
      </div>
    </div>
  </div>
</template>
