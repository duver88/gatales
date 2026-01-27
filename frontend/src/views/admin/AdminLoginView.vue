<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdminStore } from '../../stores/admin'

const router = useRouter()
const adminStore = useAdminStore()

const email = ref('')
const password = ref('')
const errorMessage = ref('')

async function handleSubmit() {
  errorMessage.value = ''

  try {
    await adminStore.login(email.value, password.value)
    router.push('/admin/dashboard')
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Error al iniciar sesión'
  }
}
</script>

<template>
  <div class="min-h-dvh flex items-center justify-center bg-gatales-sidebar px-4 py-8 safe-area-inset">
    <div class="w-full max-w-sm sm:max-w-md">
      <!-- Logo -->
      <div class="text-center mb-6 sm:mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-gatales-accent">Gatales</h1>
        <p class="text-sm sm:text-base text-gatales-text-secondary mt-1">Panel de Administracion</p>
      </div>

      <!-- Login Card -->
      <div class="card">
        <h2 class="text-lg sm:text-xl font-semibold text-gatales-text mb-5 sm:mb-6 text-center">
          Acceso Admin
        </h2>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Error message -->
          <div
            v-if="errorMessage"
            class="bg-red-500/10 border border-red-500/50 text-red-400 px-3 sm:px-4 py-2.5 sm:py-3 rounded-lg text-sm"
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
              inputmode="email"
              required
              autocomplete="email"
              class="input-field text-base"
              placeholder="admin@gatales.com"
            />
          </div>

          <!-- Password -->
          <div>
            <label for="password" class="block text-sm font-medium text-gatales-text-secondary mb-1">
              Contrasena
            </label>
            <input
              id="password"
              v-model="password"
              type="password"
              required
              autocomplete="current-password"
              class="input-field text-base"
              placeholder="********"
            />
          </div>

          <!-- Submit button -->
          <button
            type="submit"
            :disabled="adminStore.isLoading"
            class="btn-primary w-full mt-6 py-3 text-sm sm:text-base active:scale-[0.98] transition-transform"
          >
            <span v-if="adminStore.isLoading">Iniciando sesion...</span>
            <span v-else>Iniciar sesion</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
