<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const checking = ref(true)

const renewUrl = computed(() => {
  return authStore.user?.hotmart_renew_url || 'https://hotmart.com'
})

onMounted(async () => {
  try {
    // Check if user status changed (maybe they renewed)
    await authStore.fetchUser(true, true)
    if (authStore.isActive) {
      // Account is now active, redirect to chat
      window.location.href = '/chat'
      return
    }
  } catch (e) {
    // If error, show page anyway
  } finally {
    checking.value = false
  }
})

async function handleRefreshStatus() {
  checking.value = true
  try {
    // force=true to bypass local cache, refresh=true to clear backend cache
    await authStore.fetchUser(true, true)
    if (authStore.isActive) {
      // Full page reload to ensure all components get fresh data
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
  <div class="min-h-dvh flex items-center justify-center bg-gatales-bg px-4 py-8 safe-area-inset">
    <!-- Loading while checking status -->
    <div v-if="checking" class="text-center">
      <svg class="animate-spin h-8 w-8 text-gatales-accent mx-auto mb-3" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="text-gatales-text-secondary text-sm">Verificando tu cuenta...</p>
    </div>

    <div v-else class="w-full max-w-sm sm:max-w-md text-center">
      <!-- Icon -->
      <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 sm:mb-6 rounded-full bg-red-500/20 flex items-center justify-center">
        <svg class="w-8 h-8 sm:w-10 sm:h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
        </svg>
      </div>

      <!-- Title -->
      <h1 class="text-xl sm:text-2xl font-bold text-gatales-text mb-2">
        Tu cuenta esta inactiva
      </h1>

      <!-- Description -->
      <p class="text-sm sm:text-base text-gatales-text-secondary mb-5 sm:mb-6 px-2">
        Por favor renueva tu suscripcion para continuar usando El Cursales.
      </p>

      <!-- Actions -->
      <div class="space-y-3">
        <a
          :href="renewUrl"
          target="_blank"
          class="btn-primary block w-full text-center py-3 text-sm sm:text-base active:scale-[0.98] transition-transform"
        >
          Renovar suscripcion
        </a>

        <button
          @click="handleRefreshStatus"
          class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-medium rounded-lg transition-all text-sm sm:text-base"
        >
          Ya renove mi suscripcion
        </button>

        <button
          @click="handleLogout"
          class="btn-secondary w-full py-3 text-sm sm:text-base active:scale-[0.98] transition-transform"
        >
          Cerrar sesion
        </button>
      </div>
    </div>
  </div>
</template>
