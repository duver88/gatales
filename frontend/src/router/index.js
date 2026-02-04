import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useAdminStore } from '../stores/admin'

// User views
const LoginView = () => import('../views/auth/LoginView.vue')
const SetPasswordView = () => import('../views/auth/SetPasswordView.vue')
const ForgotPasswordView = () => import('../views/auth/ForgotPasswordView.vue')
const ResetPasswordView = () => import('../views/auth/ResetPasswordView.vue')
const ChatView = () => import('../views/chat/ChatView.vue')
const TokensExhaustedView = () => import('../views/errors/TokensExhaustedView.vue')
const AccountInactiveView = () => import('../views/errors/AccountInactiveView.vue')
const FreePlanView = () => import('../views/errors/FreePlanView.vue')

// Admin views
const AdminLoginView = () => import('../views/admin/AdminLoginView.vue')
const AdminLayout = () => import('../views/admin/AdminLayout.vue')
const DashboardView = () => import('../views/admin/DashboardView.vue')
const UsersView = () => import('../views/admin/UsersView.vue')
const UserDetailView = () => import('../views/admin/UserDetailView.vue')
const PlansView = () => import('../views/admin/PlansView.vue')
const WebhookLogsView = () => import('../views/admin/WebhookLogsView.vue')
const AssistantsView = () => import('../views/admin/AssistantsView.vue')
const AdminChatView = () => import('../views/admin/AdminChatView.vue')
const EmailsView = () => import('../views/admin/EmailsView.vue')

const routes = [
  // Public routes
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { guest: true },
  },
  {
    path: '/set-password',
    name: 'set-password',
    component: SetPasswordView,
    meta: { guest: true },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: ForgotPasswordView,
    meta: { guest: true },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: ResetPasswordView,
    meta: { guest: true },
  },

  // Protected user routes
  {
    path: '/',
    redirect: '/chat',
  },
  {
    path: '/chat',
    name: 'chat',
    component: ChatView,
    meta: { requiresAuth: true },
  },

  // Error pages
  {
    path: '/tokens-exhausted',
    name: 'tokens-exhausted',
    component: TokensExhaustedView,
    meta: { requiresAuth: true },
  },
  {
    path: '/account-inactive',
    name: 'account-inactive',
    component: AccountInactiveView,
  },
  {
    path: '/free-plan',
    name: 'free-plan',
    component: FreePlanView,
    meta: { requiresAuth: true },
  },

  // Admin routes
  {
    path: '/admin/login',
    name: 'admin-login',
    component: AdminLoginView,
    meta: { adminGuest: true },
  },
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAdmin: true },
    children: [
      {
        path: '',
        redirect: '/admin/dashboard',
      },
      {
        path: 'dashboard',
        name: 'admin-dashboard',
        component: DashboardView,
      },
      {
        path: 'users',
        name: 'admin-users',
        component: UsersView,
      },
      {
        path: 'users/:id',
        name: 'admin-user-detail',
        component: UserDetailView,
      },
      {
        path: 'plans',
        name: 'admin-plans',
        component: PlansView,
      },
      {
        path: 'webhook-logs',
        name: 'admin-webhook-logs',
        component: WebhookLogsView,
      },
      {
        path: 'assistants',
        name: 'admin-assistants',
        component: AssistantsView,
      },
      {
        path: 'chat',
        name: 'admin-chat',
        component: AdminChatView,
      },
      {
        path: 'emails',
        name: 'admin-emails',
        component: EmailsView,
      },
    ],
  },

  // 404 catch-all
  {
    path: '/:pathMatch(.*)*',
    redirect: '/login',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  const adminStore = useAdminStore()

  // Admin routes
  if (to.meta.requiresAdmin) {
    if (!adminStore.isAuthenticated) {
      // Try to fetch admin if we have a token
      if (adminStore.token) {
        try {
          await adminStore.fetchAdmin()
          if (adminStore.isAuthenticated) {
            return next()
          }
        } catch (e) {
          return next('/admin/login')
        }
      }
      return next('/admin/login')
    }
    return next()
  }

  // Admin guest routes (login)
  if (to.meta.adminGuest) {
    if (adminStore.isAuthenticated) {
      return next('/admin/dashboard')
    }
    return next()
  }

  // User protected routes
  if (to.meta.requiresAuth) {
    if (!authStore.isAuthenticated) {
      // Try to fetch user if we have a token
      if (authStore.token) {
        try {
          await authStore.fetchUser()
          if (authStore.isAuthenticated) {
            // Check if user is active
            if (!authStore.isActive && to.name !== 'account-inactive') {
              return next('/account-inactive')
            }
            // Check if user has free plan and trying to access chat
            if (authStore.hasFreePlan && to.name === 'chat') {
              return next('/free-plan')
            }
            return next()
          }
        } catch (e) {
          return next('/login')
        }
      }
      return next('/login')
    }

    // Check if user is active
    if (!authStore.isActive && to.name !== 'account-inactive') {
      return next('/account-inactive')
    }

    // Check if user has free plan and trying to access chat
    if (authStore.hasFreePlan && to.name === 'chat') {
      return next('/free-plan')
    }

    return next()
  }

  // User guest routes (login, set-password)
  if (to.meta.guest) {
    if (authStore.isAuthenticated) {
      return next('/chat')
    }
    return next()
  }

  next()
})

export default router
