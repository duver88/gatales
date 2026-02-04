import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('auth_token'))
  const isLoading = ref(false)
  const error = ref(null)

  // Request deduplication - prevent multiple simultaneous fetchUser calls
  let fetchUserPromise = null
  let lastFetchTime = 0
  const CACHE_DURATION = 30000 // 30 seconds cache

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isActive = computed(() => user.value?.status === 'active')
  const tokensBalance = computed(() => user.value?.tokens_balance ?? 0)
  const tokensMonthly = computed(() => user.value?.subscription?.tokens_monthly ?? 0)
  const hasFreePlan = computed(() => user.value?.has_free_plan ?? false)
  const currentPlan = computed(() => user.value?.current_plan ?? 'Sin plan')

  // Initialize user from localStorage
  const storedUser = localStorage.getItem('user')
  if (storedUser) {
    try {
      user.value = JSON.parse(storedUser)
    } catch (e) {
      localStorage.removeItem('user')
    }
  }

  async function setPassword(passwordToken, password, passwordConfirmation) {
    isLoading.value = true
    error.value = null

    try {
      const response = await authApi.setPassword({
        token: passwordToken,
        password,
        password_confirmation: passwordConfirmation,
      })

      token.value = response.data.token
      user.value = response.data.user
      localStorage.setItem('auth_token', response.data.token)
      localStorage.setItem('user', JSON.stringify(response.data.user))

      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Error al establecer la contraseña'
      throw e
    } finally {
      isLoading.value = false
    }
  }

  async function login(email, password) {
    isLoading.value = true
    error.value = null

    try {
      const response = await authApi.login({ email, password })

      token.value = response.data.token
      user.value = response.data.user
      localStorage.setItem('auth_token', response.data.token)
      localStorage.setItem('user', JSON.stringify(response.data.user))

      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Error al iniciar sesión'
      throw e
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch (e) {
      // Ignore errors during logout
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
    }
  }

  async function fetchUser(force = false) {
    if (!token.value) return null

    // Return cached user if available and not forcing refresh
    const now = Date.now()
    if (!force && user.value && (now - lastFetchTime) < CACHE_DURATION) {
      return user.value
    }

    // Deduplicate concurrent requests - return existing promise if one is in progress
    if (fetchUserPromise) {
      return fetchUserPromise
    }

    isLoading.value = true
    fetchUserPromise = (async () => {
      try {
        const response = await authApi.me()
        user.value = response.data.user
        localStorage.setItem('user', JSON.stringify(response.data.user))
        lastFetchTime = Date.now()
        return response.data.user
      } catch (e) {
        // If token is invalid, clear it
        if (e.response?.status === 401) {
          token.value = null
          user.value = null
          localStorage.removeItem('auth_token')
          localStorage.removeItem('user')
        }
        throw e
      } finally {
        isLoading.value = false
        fetchUserPromise = null
      }
    })()

    return fetchUserPromise
  }

  function updateTokensBalance(newBalance) {
    if (user.value) {
      user.value.tokens_balance = newBalance
      localStorage.setItem('user', JSON.stringify(user.value))
    }
  }

  async function resetPassword(resetToken, password, passwordConfirmation) {
    isLoading.value = true
    error.value = null

    try {
      const response = await authApi.resetPassword({
        token: resetToken,
        password,
        password_confirmation: passwordConfirmation,
      })

      token.value = response.data.token
      user.value = response.data.user
      localStorage.setItem('auth_token', response.data.token)
      localStorage.setItem('user', JSON.stringify(response.data.user))

      return response.data
    } catch (e) {
      error.value = e.response?.data?.message || 'Error al restablecer la contraseña'
      throw e
    } finally {
      isLoading.value = false
    }
  }

  return {
    user,
    token,
    isLoading,
    error,
    isAuthenticated,
    isActive,
    tokensBalance,
    tokensMonthly,
    hasFreePlan,
    currentPlan,
    setPassword,
    login,
    logout,
    fetchUser,
    updateTokensBalance,
    resetPassword,
  }
})
