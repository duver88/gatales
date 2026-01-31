<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const renewUrl = computed(() => {
  return authStore.user?.hotmart_renew_url || 'https://hotmart.com'
})

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-dvh flex items-center justify-center bg-gatales-bg px-4 py-8 safe-area-inset">
    <div class="w-full max-w-sm sm:max-w-md text-center">
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
          @click="handleLogout"
          class="btn-secondary w-full py-3 text-sm sm:text-base active:scale-[0.98] transition-transform"
        >
          Cerrar sesion
        </button>
      </div>
    </div>
  </div>
</template>
