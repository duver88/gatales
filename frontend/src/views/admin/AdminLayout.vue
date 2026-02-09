<script setup>
import { ref, watch } from 'vue'
import { useRouter, useRoute, RouterView } from 'vue-router'
import { useAdminStore } from '../../stores/admin'
import { useThemeStore } from '../../stores/theme'
import { adminAuthApi } from '../../services/api'

const router = useRouter()
const route = useRoute()
const adminStore = useAdminStore()
const themeStore = useThemeStore()

const sidebarOpen = ref(false)
const sidebarCollapsed = ref(false)

// Profile modal
const showProfileModal = ref(false)
const profileForm = ref({ name: '', email: '' })
const profileError = ref('')
const profileSuccess = ref('')
const isSavingProfile = ref(false)

// Password modal
const showPasswordModal = ref(false)
const passwordForm = ref({ current_password: '', password: '', password_confirmation: '' })
const passwordError = ref('')
const passwordSuccess = ref('')
const isSavingPassword = ref(false)

const menuItems = [
  { name: 'Dashboard', path: '/admin/dashboard', icon: 'chart' },
  { name: 'Usuarios', path: '/admin/users', icon: 'users' },
  { name: 'Planes', path: '/admin/plans', icon: 'credit-card' },
  { name: 'Asistentes', path: '/admin/assistants', icon: 'robot' },
  { name: 'Probar IA', path: '/admin/chat', icon: 'chat' },
  { name: 'Correos', path: '/admin/emails', icon: 'mail' },
  { name: 'Configuracion', path: '/admin/settings', icon: 'settings' },
  { name: 'Webhook Logs', path: '/admin/webhook-logs', icon: 'server' },
]

function isActive(path) {
  return route.path.startsWith(path)
}

function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}

function closeSidebar() {
  sidebarOpen.value = false
}

// Close sidebar on route change (mobile)
watch(() => route.path, () => {
  sidebarOpen.value = false
})

async function handleLogout() {
  await adminStore.logout()
  router.push('/admin/login')
}

function openProfileModal() {
  profileForm.value = {
    name: adminStore.admin?.name || '',
    email: adminStore.admin?.email || '',
  }
  profileError.value = ''
  profileSuccess.value = ''
  showProfileModal.value = true
}

async function saveProfile() {
  profileError.value = ''
  profileSuccess.value = ''
  isSavingProfile.value = true
  try {
    const response = await adminAuthApi.updateProfile(profileForm.value)
    adminStore.admin = response.data.admin
    localStorage.setItem('admin', JSON.stringify(response.data.admin))
    profileSuccess.value = 'Perfil actualizado correctamente'
    setTimeout(() => { showProfileModal.value = false }, 1200)
  } catch (e) {
    profileError.value = e.response?.data?.message || 'Error al actualizar el perfil'
  } finally {
    isSavingProfile.value = false
  }
}

function openPasswordModal() {
  passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
  passwordError.value = ''
  passwordSuccess.value = ''
  showPasswordModal.value = true
}

async function savePassword() {
  passwordError.value = ''
  passwordSuccess.value = ''

  if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
    passwordError.value = 'Las contraseñas no coinciden'
    return
  }
  if (passwordForm.value.password.length < 8) {
    passwordError.value = 'La contraseña debe tener al menos 8 caracteres'
    return
  }

  isSavingPassword.value = true
  try {
    await adminAuthApi.changePassword(passwordForm.value)
    passwordSuccess.value = 'Contraseña actualizada correctamente'
    setTimeout(() => { showPasswordModal.value = false }, 1200)
  } catch (e) {
    passwordError.value = e.response?.data?.message || 'Error al cambiar la contraseña'
  } finally {
    isSavingPassword.value = false
  }
}
</script>

<template>
  <div class="flex h-screen bg-bg-dark">
    <!-- Mobile header -->
    <div class="fixed top-0 left-0 right-0 z-40 lg:hidden bg-bg-secondary border-b border-border">
      <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3">
          <!-- Logo -->
          <picture class="block w-8 h-8">
            <source srcset="/logo-64.webp" type="image/webp" />
            <img src="/logo-64.png" alt="El Cursales" class="w-full h-full object-contain" width="64" height="64" loading="lazy" />
          </picture>
          <div>
            <h1 class="text-base font-bold text-brand">El Cursales</h1>
            <p class="text-[10px] text-text-secondary">Panel Admin</p>
          </div>
        </div>
        <button
          @click="toggleSidebar"
          class="p-2 rounded-lg bg-bg-input text-text-primary hover:bg-bg-hover transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path v-if="!sidebarOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Mobile overlay -->
    <Transition name="fade">
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
        @click="closeSidebar"
      />
    </Transition>

    <!-- Sidebar -->
    <aside
      :class="[
        'fixed lg:static inset-y-0 left-0 z-50 w-64 bg-bg-secondary border-r border-border flex flex-col',
        'transform transition-transform duration-300 ease-in-out lg:transform-none',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <!-- Logo Section -->
      <div class="p-5 border-b border-border">
        <div class="flex items-center gap-3">
          <!-- Logo -->
          <picture class="block w-10 h-10">
            <source srcset="/logo-64.webp" type="image/webp" />
            <img src="/logo-64.png" alt="El Cursales" class="w-full h-full object-contain" width="64" height="64" loading="lazy" />
          </picture>
          <div>
            <h1 class="text-lg font-bold text-brand">El Cursales</h1>
            <p class="text-xs text-text-secondary">Panel de Administración</p>
          </div>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <p class="px-3 mb-3 text-xs font-semibold text-text-muted uppercase tracking-wider">Menú</p>

        <router-link
          v-for="item in menuItems"
          :key="item.path"
          :to="item.path"
          :class="[
            'flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200',
            isActive(item.path)
              ? 'bg-brand text-white shadow-lg shadow-brand/25'
              : 'text-text-secondary hover:bg-bg-hover hover:text-text-primary'
          ]"
        >
          <!-- Chart icon -->
          <svg v-if="item.icon === 'chart'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <!-- Users icon -->
          <svg v-if="item.icon === 'users'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
          <!-- Credit card icon -->
          <svg v-if="item.icon === 'credit-card'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
          </svg>
          <!-- Robot icon -->
          <svg v-if="item.icon === 'robot'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <!-- Server icon -->
          <svg v-if="item.icon === 'server'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
          </svg>
          <!-- Chat icon -->
          <svg v-if="item.icon === 'chat'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
          <!-- Mail icon -->
          <svg v-if="item.icon === 'mail'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <!-- Settings icon -->
          <svg v-if="item.icon === 'settings'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <span class="font-medium">{{ item.name }}</span>
        </router-link>
      </nav>

      <!-- Theme toggle and User info -->
      <div class="p-4 border-t border-border">
        <!-- Theme Toggle -->
        <button
          @click="themeStore.toggleTheme"
          class="w-full flex items-center justify-between px-3 py-2.5 mb-3 rounded-lg bg-bg-input hover:bg-bg-hover transition-colors"
        >
          <span class="text-sm font-medium text-text-secondary">Tema</span>
          <div class="flex items-center gap-2">
            <span class="text-xs text-text-muted">{{ themeStore.theme === 'dark' ? 'Oscuro' : 'Claro' }}</span>
            <!-- Sun icon for light mode -->
            <svg v-if="themeStore.theme === 'light'" class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <!-- Moon icon for dark mode -->
            <svg v-else class="w-5 h-5 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
          </div>
        </button>

        <!-- User info -->
        <div class="flex items-center gap-3 mb-2 p-2 rounded-lg bg-bg-input">
          <div class="w-9 h-9 rounded-full bg-brand/20 flex items-center justify-center text-brand font-semibold shrink-0">
            {{ adminStore.admin?.name?.charAt(0)?.toUpperCase() || 'A' }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-text-primary truncate">{{ adminStore.admin?.name }}</p>
            <p class="text-xs text-text-secondary truncate">{{ adminStore.admin?.email }}</p>
          </div>
        </div>

        <!-- Admin Actions -->
        <div class="flex gap-2 mb-2">
          <button
            @click="openProfileModal"
            class="flex-1 flex items-center justify-center gap-1.5 px-2 py-2 text-xs text-text-secondary bg-bg-input hover:bg-bg-hover rounded-lg transition-colors font-medium"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Perfil
          </button>
          <button
            @click="openPasswordModal"
            class="flex-1 flex items-center justify-center gap-1.5 px-2 py-2 text-xs text-text-secondary bg-bg-input hover:bg-bg-hover rounded-lg transition-colors font-medium"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            Contraseña
          </button>
        </div>

        <button
          @click="handleLogout"
          class="w-full flex items-center justify-center gap-2 px-3 py-2.5 text-sm text-error bg-error/10 hover:bg-error/20 rounded-lg transition-colors font-medium"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          Cerrar sesión
        </button>
      </div>
    </aside>

    <!-- Main content -->
    <main class="flex-1 overflow-auto pt-14 lg:pt-0 bg-bg-dark">
      <RouterView />
    </main>

    <!-- Profile Edit Modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showProfileModal"
          class="fixed inset-0 z-[60] bg-black/60 flex items-center justify-center p-4"
          @click.self="showProfileModal = false"
        >
          <div class="w-full max-w-md bg-bg-secondary border border-border rounded-xl shadow-xl">
            <div class="flex items-center justify-between p-4 border-b border-border">
              <h3 class="text-lg font-semibold text-text-primary">Editar Perfil</h3>
              <button @click="showProfileModal = false" class="p-2 rounded-lg hover:bg-bg-hover transition-colors">
                <svg class="w-5 h-5 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <form @submit.prevent="saveProfile" class="p-4 space-y-4">
              <div v-if="profileSuccess" class="bg-green-500/10 border border-green-500/50 text-green-400 px-4 py-3 rounded-lg text-sm">
                {{ profileSuccess }}
              </div>
              <div v-if="profileError" class="bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-lg text-sm">
                {{ profileError }}
              </div>
              <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Nombre</label>
                <input v-model="profileForm.name" type="text" required class="input-field" placeholder="Tu nombre" />
              </div>
              <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Correo electrónico</label>
                <input v-model="profileForm.email" type="email" required class="input-field" placeholder="admin@ejemplo.com" />
              </div>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showProfileModal = false" class="flex-1 btn-secondary">Cancelar</button>
                <button type="submit" :disabled="isSavingProfile" class="flex-1 btn-primary">
                  {{ isSavingProfile ? 'Guardando...' : 'Guardar' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Password Change Modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showPasswordModal"
          class="fixed inset-0 z-[60] bg-black/60 flex items-center justify-center p-4"
          @click.self="showPasswordModal = false"
        >
          <div class="w-full max-w-md bg-bg-secondary border border-border rounded-xl shadow-xl">
            <div class="flex items-center justify-between p-4 border-b border-border">
              <h3 class="text-lg font-semibold text-text-primary">Cambiar Contraseña</h3>
              <button @click="showPasswordModal = false" class="p-2 rounded-lg hover:bg-bg-hover transition-colors">
                <svg class="w-5 h-5 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <form @submit.prevent="savePassword" class="p-4 space-y-4">
              <div v-if="passwordSuccess" class="bg-green-500/10 border border-green-500/50 text-green-400 px-4 py-3 rounded-lg text-sm">
                {{ passwordSuccess }}
              </div>
              <div v-if="passwordError" class="bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-lg text-sm">
                {{ passwordError }}
              </div>
              <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Contraseña actual</label>
                <input v-model="passwordForm.current_password" type="password" required class="input-field" placeholder="Tu contraseña actual" />
              </div>
              <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Nueva contraseña</label>
                <input v-model="passwordForm.password" type="password" required class="input-field" placeholder="Mínimo 8 caracteres" />
              </div>
              <div>
                <label class="block text-sm font-medium text-text-secondary mb-1">Confirmar nueva contraseña</label>
                <input v-model="passwordForm.password_confirmation" type="password" required class="input-field" placeholder="Repite la nueva contraseña" />
              </div>
              <div class="flex gap-3 pt-2">
                <button type="button" @click="showPasswordModal = false" class="flex-1 btn-secondary">Cancelar</button>
                <button type="submit" :disabled="isSavingPassword" class="flex-1 btn-primary">
                  {{ isSavingPassword ? 'Guardando...' : 'Guardar' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
