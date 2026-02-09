<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const password = ref('')
const passwordConfirmation = ref('')
const showPassword = ref(false)
const errorMessage = ref('')
const token = ref('')

onMounted(() => {
  token.value = route.query.token || ''

  if (!token.value) {
    errorMessage.value = 'El enlace no es válido. Por favor verifica el link en tu email.'
  }
})

async function handleSubmit() {
  errorMessage.value = ''

  if (password.value !== passwordConfirmation.value) {
    errorMessage.value = 'Las contraseñas no coinciden'
    return
  }

  if (password.value.length < 8) {
    errorMessage.value = 'La contraseña debe tener al menos 8 caracteres'
    return
  }

  try {
    await authStore.setPassword(token.value, password.value, passwordConfirmation.value)
    router.push('/chat')
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Error al establecer la contraseña'
  }
}
</script>

<template>
  <div class="min-h-dvh flex items-center justify-center bg-gatales-bg px-4 py-8 safe-area-inset">
    <div class="w-full max-w-sm sm:max-w-md">
      <!-- Logo -->
      <div class="text-center mb-6 sm:mb-8">
        <picture class="block w-16 h-16 mx-auto mb-4">
          <source srcset="/logo-192.webp" type="image/webp" />
          <img src="/logo-192.png" alt="El Cursales" class="w-full h-full object-contain" width="192" height="192" />
        </picture>
        <h1 class="text-3xl sm:text-4xl font-bold text-gatales-accent">El Cursales</h1>
        <p class="text-sm sm:text-base text-gatales-text-secondary mt-2">Tu asistente de guiones de video</p>
      </div>

      <!-- Set Password Card -->
      <div class="card">
        <h2 class="text-lg sm:text-xl font-semibold text-gatales-text mb-2 text-center">
          Bienvenido!
        </h2>
        <p class="text-sm sm:text-base text-gatales-text-secondary text-center mb-5 sm:mb-6">
          Establece tu contrasena para comenzar
        </p>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Error message -->
          <div
            v-if="errorMessage"
            class="bg-red-500/10 border border-red-500/50 text-red-400 px-3 sm:px-4 py-3 rounded-lg text-sm"
          >
            {{ errorMessage }}
          </div>

          <!-- Password -->
          <div>
            <label for="password" class="block text-sm font-medium text-gatales-text-secondary mb-1">
              Nueva contrasena
            </label>
            <div class="relative">
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                minlength="8"
                autocomplete="new-password"
                class="input-field text-base pr-10"
                placeholder="Minimo 8 caracteres"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gatales-text-secondary hover:text-gatales-text transition-colors"
                tabindex="-1"
                :aria-label="showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena'"
              >
                <svg v-if="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Password Confirmation -->
          <div>
            <label for="passwordConfirmation" class="block text-sm font-medium text-gatales-text-secondary mb-1">
              Confirmar contrasena
            </label>
            <div class="relative">
              <input
                id="passwordConfirmation"
                v-model="passwordConfirmation"
                :type="showPassword ? 'text' : 'password'"
                required
                minlength="8"
                autocomplete="new-password"
                class="input-field text-base pr-10"
                placeholder="Repite tu contrasena"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gatales-text-secondary hover:text-gatales-text transition-colors"
                tabindex="-1"
                :aria-label="showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena'"
              >
                <svg v-if="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Submit button -->
          <button
            type="submit"
            :disabled="authStore.isLoading || !token"
            class="btn-primary w-full mt-6 py-3 text-base active:scale-[0.98] transition-transform"
          >
            <span v-if="authStore.isLoading">Guardando...</span>
            <span v-else>Guardar y continuar</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
