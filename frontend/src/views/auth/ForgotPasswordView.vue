<script setup>
import { ref } from 'vue'
import { authApi } from '../../services/api'

const email = ref('')
const isLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

async function handleSubmit() {
  errorMessage.value = ''
  successMessage.value = ''
  isLoading.value = true

  try {
    const response = await authApi.forgotPassword(email.value)
    successMessage.value = response.data.message
    email.value = ''
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Error al enviar el correo'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="min-h-dvh flex items-center justify-center bg-gatales-bg px-4 py-8 safe-area-inset">
    <div class="w-full max-w-sm sm:max-w-md">
      <!-- Logo -->
      <div class="text-center mb-6 sm:mb-8">
        <div class="w-16 h-16 bg-gatales-accent rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-gatales-accent/30">
          <svg class="w-10 h-10 text-white" viewBox="0 0 100 100" fill="currentColor">
            <path d="M50 15 L25 35 L25 60 Q25 80 50 85 Q75 80 75 60 L75 35 Z"/>
            <path d="M25 35 L15 10 L35 30 Z"/>
            <path d="M75 35 L85 10 L65 30 Z"/>
          </svg>
        </div>
        <h1 class="text-3xl sm:text-4xl font-bold text-gatales-accent">El Cursales</h1>
        <p class="text-sm sm:text-base text-gatales-text-secondary mt-2">Tu asistente de guiones de video</p>
      </div>

      <!-- Forgot Password Card -->
      <div class="card">
        <h2 class="text-lg sm:text-xl font-semibold text-gatales-text mb-2 text-center">
          Restablecer contrasena
        </h2>
        <p class="text-sm text-gatales-text-secondary text-center mb-5 sm:mb-6">
          Ingresa tu correo y te enviaremos un enlace para restablecer tu contrasena
        </p>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Success message -->
          <div
            v-if="successMessage"
            class="bg-green-500/10 border border-green-500/50 text-green-400 px-3 sm:px-4 py-3 rounded-lg text-sm"
          >
            {{ successMessage }}
          </div>

          <!-- Error message -->
          <div
            v-if="errorMessage"
            class="bg-red-500/10 border border-red-500/50 text-red-400 px-3 sm:px-4 py-3 rounded-lg text-sm"
          >
            {{ errorMessage }}
          </div>

          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-gatales-text-secondary mb-1">
              Email
            </label>
            <input
              id="email"
              v-model="email"
              type="email"
              required
              autocomplete="email"
              inputmode="email"
              class="input-field text-base"
              placeholder="tu@email.com"
            />
          </div>

          <!-- Submit button -->
          <button
            type="submit"
            :disabled="isLoading"
            class="btn-primary w-full mt-6 py-3 text-base active:scale-[0.98] transition-transform"
          >
            <span v-if="isLoading">Enviando...</span>
            <span v-else>Enviar enlace</span>
          </button>
        </form>

        <!-- Back to login -->
        <p class="mt-5 sm:mt-6 text-center text-sm text-gatales-text-secondary">
          <router-link
            to="/login"
            class="text-gatales-accent hover:underline active:opacity-80"
          >
            Volver al inicio de sesion
          </router-link>
        </p>
      </div>
    </div>
  </div>
</template>
