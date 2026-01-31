<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAdminStore } from '../../stores/admin'

const router = useRouter()
const adminStore = useAdminStore()

const email = ref('')
const password = ref('')
const errorMessage = ref('')
const showPassword = ref(false)

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
  <div class="min-h-dvh flex items-center justify-center bg-bg-dark px-4 py-8 safe-area-inset">
    <div class="w-full max-w-sm sm:max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="w-16 h-16 bg-brand rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-brand/30">
          <svg class="w-10 h-10 text-white" viewBox="0 0 100 100" fill="currentColor">
            <!-- Cat head -->
            <path d="M50 15 L25 35 L25 60 Q25 80 50 85 Q75 80 75 60 L75 35 Z"/>
            <!-- Left ear -->
            <path d="M25 35 L15 10 L35 30 Z"/>
            <!-- Right ear -->
            <path d="M75 35 L85 10 L65 30 Z"/>
          </svg>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-brand">El Cursales</h1>
        <p class="text-sm text-text-secondary mt-2">Panel de Administración</p>
      </div>

      <!-- Login Card -->
      <div class="bg-bg-card border border-border rounded-xl p-6 sm:p-8 shadow-lg">
        <h2 class="text-lg font-semibold text-text-primary mb-6 text-center">
          Iniciar Sesión
        </h2>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <!-- Error message -->
          <div
            v-if="errorMessage"
            class="bg-error/10 border border-error/30 text-error px-4 py-3 rounded-lg text-sm flex items-center gap-2"
          >
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ errorMessage }}
          </div>

          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-text-secondary mb-2">
              Email
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-5 h-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                </svg>
              </div>
              <input
                id="email"
                v-model="email"
                type="email"
                inputmode="email"
                required
                autocomplete="email"
                class="input-field pl-10 text-base"
                placeholder="admin@elcursales.com"
              />
            </div>
          </div>

          <!-- Password -->
          <div>
            <label for="password" class="block text-sm font-medium text-text-secondary mb-2">
              Contraseña
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-5 h-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                class="input-field pl-10 pr-10 text-base"
                placeholder="••••••••"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-text-muted hover:text-text-secondary transition-colors"
              >
                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Submit button -->
          <button
            type="submit"
            :disabled="adminStore.isLoading"
            class="btn-primary w-full py-3 text-base font-semibold mt-2"
          >
            <svg v-if="adminStore.isLoading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span v-if="adminStore.isLoading">Iniciando sesión...</span>
            <span v-else>Iniciar sesión</span>
          </button>
        </form>
      </div>

      <!-- Footer -->
      <p class="text-center text-text-muted text-xs mt-6">
        © {{ new Date().getFullYear() }} El Cursales. Todos los derechos reservados.
      </p>
    </div>
  </div>
</template>
