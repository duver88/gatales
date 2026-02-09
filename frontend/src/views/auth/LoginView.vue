<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const errorMessage = ref('')

async function handleSubmit() {
  errorMessage.value = ''

  try {
    await authStore.login(email.value, password.value)
    router.push('/chat')
  } catch (e) {
    if (e.response?.data?.status === 'inactive') {
      router.push('/account-inactive')
    } else {
      errorMessage.value = e.response?.data?.message || 'Error al iniciar sesión'
    }
  }
}
</script>

<template>
  <main class="min-h-dvh bg-gatales-bg px-4 py-6 sm:py-8 safe-area-inset flex flex-col items-center">
    <div class="flex-1"></div>

    <!-- Login content -->
    <div class="w-full max-w-sm sm:max-w-md">
      <!-- Logo -->
      <div class="text-center mb-5 sm:mb-8">
        <picture class="block w-20 h-20 sm:w-24 sm:h-24 mx-auto mb-3 sm:mb-4">
          <source srcset="/logo-192.webp" type="image/webp" />
          <img src="/logo-192.png" alt="El Cursales" class="w-full h-full object-contain" fetchpriority="high" width="192" height="192" />
        </picture>
        <h1 class="text-2xl sm:text-4xl font-bold text-gatales-accent">El Cursales</h1>
        <p class="text-xs sm:text-base text-gatales-text-secondary mt-1.5 sm:mt-2">Tu asistente de guiones de video</p>
      </div>

      <!-- Login Card -->
      <div class="card">
        <h2 class="text-base sm:text-xl font-semibold text-gatales-text mb-4 sm:mb-6 text-center">
          Iniciar sesion
        </h2>

        <form @submit.prevent="handleSubmit" class="space-y-3.5 sm:space-y-4">
          <!-- Error message -->
          <div
            v-if="errorMessage"
            class="bg-red-500/10 border border-red-500/50 text-red-400 px-3 py-2.5 sm:py-3 rounded-lg text-xs sm:text-sm"
          >
            {{ errorMessage }}
          </div>

          <!-- Email -->
          <div>
            <label for="email" class="block text-xs sm:text-sm font-medium text-gatales-text-secondary mb-1">
              Email
            </label>
            <input
              id="email"
              v-model="email"
              type="email"
              required
              autocomplete="email"
              inputmode="email"
              class="input-field text-sm sm:text-base"
              placeholder="tu@email.com"
            />
          </div>

          <!-- Password -->
          <div>
            <div class="flex justify-between items-center mb-1">
              <label for="password" class="block text-xs sm:text-sm font-medium text-gatales-text-secondary">
                Contrasena
              </label>
              <router-link
                to="/forgot-password"
                class="text-[11px] sm:text-xs text-gatales-accent hover:underline active:opacity-80"
              >
                Olvide mi contrasena
              </router-link>
            </div>
            <div class="relative">
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                class="input-field text-sm sm:text-base pr-10"
                placeholder="••••••••"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gatales-text-secondary hover:text-gatales-text transition-colors"
                tabindex="-1"
                :aria-label="showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena'"
              >
                <svg v-if="showPassword" class="w-4.5 h-4.5 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                </svg>
                <svg v-else class="w-4.5 h-4.5 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Submit button -->
          <button
            type="submit"
            :disabled="authStore.isLoading"
            class="btn-primary w-full mt-5 sm:mt-6 py-2.5 sm:py-3 text-sm sm:text-base active:scale-[0.98] transition-transform"
          >
            <span v-if="authStore.isLoading">Iniciando sesion...</span>
            <span v-else>Iniciar sesion</span>
          </button>
        </form>
      </div>

      <!-- Link to plans -->
      <div class="mt-4 sm:mt-5 flex justify-center">
        <router-link to="/planes" class="plans-btn">
          <span class="plans-btn-inner">
            Aun no tengo suscripcion
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </span>
        </router-link>
      </div>
    </div>

    <div class="flex-1"></div>
  </main>
</template>

<style scoped>
.plans-btn {
  position: relative;
  padding: 1px;
  border-radius: 0.75rem;
  background: conic-gradient(from var(--angle, 0deg), transparent 60%, #00be88 80%, transparent 100%);
  animation: spin-border 4s linear infinite;
}

.plans-btn-inner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.6rem 1.5rem;
  border-radius: calc(0.75rem - 1px);
  background-color: var(--color-gatales-bg, #000);
  font-size: 0.8rem;
  color: var(--color-gatales-text-secondary, #999);
  transition: color 0.2s;
}

@media (min-width: 640px) {
  .plans-btn-inner {
    font-size: 0.875rem;
    padding: 0.65rem 1.75rem;
  }
}

.plans-btn:hover .plans-btn-inner {
  color: #00be88;
}

@keyframes spin-border {
  to {
    --angle: 360deg;
  }
}

@property --angle {
  syntax: "<angle>";
  initial-value: 0deg;
  inherits: false;
}
</style>
