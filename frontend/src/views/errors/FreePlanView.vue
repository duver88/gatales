<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const upgradeUrl = computed(() => authStore.user?.hotmart_upgrade_url || '#')
const planName = computed(() => authStore.user?.current_plan || 'Plan Gratuito')

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-dvh bg-gatales-bg flex items-center justify-center px-4 py-8 safe-area-inset">
    <div class="max-w-sm sm:max-w-md w-full text-center">
      <!-- Icon -->
      <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-4 sm:mb-6 rounded-full bg-amber-500/20 flex items-center justify-center">
        <svg class="w-8 h-8 sm:w-10 sm:h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      </div>

      <!-- Title -->
      <h1 class="text-xl sm:text-2xl font-bold text-gatales-text mb-2">
        Acceso Restringido
      </h1>

      <!-- Current Plan -->
      <div class="inline-flex items-center gap-2 px-3 py-1 mb-3 sm:mb-4 rounded-full bg-amber-500/20 text-amber-500 text-xs sm:text-sm font-medium">
        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        {{ planName }}
      </div>

      <!-- Message -->
      <p class="text-sm sm:text-base text-gatales-text-secondary mb-6 sm:mb-8 px-2">
        Tienes el plan gratuito que no incluye acceso al asistente de IA.
        <br><br>
        <span class="text-gatales-text">Actualiza tu suscripcion</span> para desbloquear todas las funciones.
      </p>

      <!-- Features List -->
      <div class="bg-gatales-sidebar rounded-lg p-3 sm:p-4 mb-5 sm:mb-6 text-left">
        <p class="text-xs sm:text-sm font-medium text-gatales-text mb-2 sm:mb-3">Con un plan de pago obtendras:</p>
        <ul class="space-y-2">
          <li class="flex items-center gap-2 text-xs sm:text-sm text-gatales-text-secondary">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Acceso ilimitado al asistente de IA
          </li>
          <li class="flex items-center gap-2 text-xs sm:text-sm text-gatales-text-secondary">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Miles de tokens mensuales
          </li>
          <li class="flex items-center gap-2 text-xs sm:text-sm text-gatales-text-secondary">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Creacion de guiones profesionales
          </li>
          <li class="flex items-center gap-2 text-xs sm:text-sm text-gatales-text-secondary">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Hooks y CTAs optimizados
          </li>
        </ul>
      </div>

      <!-- Actions -->
      <div class="space-y-3">
        <a
          :href="upgradeUrl"
          target="_blank"
          class="block w-full px-6 py-3 bg-gatales-accent hover:bg-gatales-accent/90 active:scale-[0.98] text-white font-medium rounded-lg transition-all text-sm sm:text-base"
        >
          Actualizar Suscripcion
        </a>
        <button
          @click="handleLogout"
          class="w-full px-6 py-3 bg-gatales-input hover:bg-gatales-input/80 active:scale-[0.98] text-gatales-text font-medium rounded-lg transition-all text-sm sm:text-base"
        >
          Cerrar Sesion
        </button>
      </div>

      <!-- Support -->
      <p class="mt-5 sm:mt-6 text-xs text-gatales-text-secondary">
        ¿Tienes dudas? Contacta a soporte
      </p>
    </div>
  </div>
</template>
