<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const password = ref('')
const passwordConfirmation = ref('')
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
        <h1 class="text-3xl sm:text-4xl font-bold text-gatales-accent">Gatales</h1>
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
            <input
              id="password"
              v-model="password"
              type="password"
              required
              minlength="8"
              autocomplete="new-password"
              class="input-field text-base"
              placeholder="Minimo 8 caracteres"
            />
          </div>

          <!-- Password Confirmation -->
          <div>
            <label for="passwordConfirmation" class="block text-sm font-medium text-gatales-text-secondary mb-1">
              Confirmar contrasena
            </label>
            <input
              id="passwordConfirmation"
              v-model="passwordConfirmation"
              type="password"
              required
              minlength="8"
              autocomplete="new-password"
              class="input-field text-base"
              placeholder="Repite tu contrasena"
            />
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
