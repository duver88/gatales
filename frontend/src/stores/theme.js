import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  // Get initial theme from localStorage or default to 'dark'
  const savedTheme = localStorage.getItem('theme') || 'dark'
  const theme = ref(savedTheme)

  // Apply theme to document
  function applyTheme(themeName) {
    document.documentElement.setAttribute('data-theme', themeName)
    localStorage.setItem('theme', themeName)
  }

  // Toggle between light and dark
  function toggleTheme() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
  }

  // Set specific theme
  function setTheme(themeName) {
    if (themeName === 'light' || themeName === 'dark') {
      theme.value = themeName
    }
  }

  // Watch for theme changes and apply
  watch(theme, (newTheme) => {
    applyTheme(newTheme)
  }, { immediate: true })

  return {
    theme,
    toggleTheme,
    setTheme,
  }
})
