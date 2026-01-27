import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { adminAuthApi, adminApi } from '../services/api'

export const useAdminStore = defineStore('admin', () => {
  const admin = ref(null)
  const token = ref(localStorage.getItem('admin_token'))
  const isLoading = ref(false)
  const error = ref(null)

  const isAuthenticated = computed(() => !!token.value && !!admin.value)

  // Initialize admin from localStorage
  const storedAdmin = localStorage.getItem('admin')
  if (storedAdmin) {
    try {
      admin.value = JSON.parse(storedAdmin)
    } catch (e) {
      localStorage.removeItem('admin')
    }
  }

  async function login(email, password) {
    isLoading.value = true
    error.value = null

    try {
      const response = await adminAuthApi.login({ email, password })

      token.value = response.data.token
      admin.value = response.data.admin
      localStorage.setItem('admin_token', response.data.token)
      localStorage.setItem('admin', JSON.stringify(response.data.admin))

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
      await adminAuthApi.logout()
    } catch (e) {
      // Ignore errors during logout
    } finally {
      token.value = null
      admin.value = null
      localStorage.removeItem('admin_token')
      localStorage.removeItem('admin')
    }
  }

  async function fetchAdmin() {
    if (!token.value) return null

    isLoading.value = true
    try {
      const response = await adminAuthApi.me()
      admin.value = response.data.admin
      localStorage.setItem('admin', JSON.stringify(response.data.admin))
      return response.data.admin
    } catch (e) {
      if (e.response?.status === 401) {
        token.value = null
        admin.value = null
        localStorage.removeItem('admin_token')
        localStorage.removeItem('admin')
      }
      throw e
    } finally {
      isLoading.value = false
    }
  }

  return {
    admin,
    token,
    isLoading,
    error,
    isAuthenticated,
    login,
    logout,
    fetchAdmin,
  }
})
