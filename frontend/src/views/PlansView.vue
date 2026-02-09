<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const plans = [
  {
    id: 'pro',
    name: 'Pro',
    badge: null,
    description: 'Ideal para comenzar a crear guiones profesionales',
    features: [
      'Acceso al asistente de IA',
      'Tokens mensuales incluidos',
      'Creacion de guiones de video',
      'Historial de conversaciones',
    ],
    url: 'https://pay.hotmart.com/D104282667I?off=6qkjyj67&bid=1770611375412',
    highlight: false,
    color: '#00be88',
  },
  {
    id: 'super-pro',
    name: 'Super Pro',
    badge: 'Recomendado',
    description: 'Para creadores que necesitan el maximo rendimiento',
    features: [
      'Todo lo del plan Pro',
      'Muchos mas tokens mensuales',
      'Asistentes exclusivos premium',
      'Soporte prioritario',
    ],
    url: 'https://pay.hotmart.com/D104282667I?off=i909yaee&bid=1770612278658',
    highlight: true,
    color: '#f59e0b',
  },
]
</script>

<template>
  <div class="min-h-dvh bg-gatales-bg safe-area-inset">
    <!-- Header -->
    <header class="border-b border-gatales-border bg-gatales-bg/80 backdrop-blur-sm sticky top-0 z-10">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between">
        <router-link to="/login" class="flex items-center gap-2.5">
          <picture class="block w-8 h-8">
            <source srcset="/logo-192.webp" type="image/webp" />
            <img src="/logo-192.png" alt="El Cursales" class="w-full h-full object-contain" width="192" height="192" />
          </picture>
          <span class="text-lg font-bold text-gatales-accent">El Cursales</span>
        </router-link>
        <router-link
          to="/login"
          class="text-sm text-gatales-text-secondary hover:text-gatales-accent transition-colors"
        >
          Iniciar sesion
        </router-link>
      </div>
    </header>

    <!-- Hero -->
    <section class="px-4 sm:px-6 pt-12 sm:pt-20 pb-8 sm:pb-12 text-center">
      <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl sm:text-4xl font-bold text-gatales-text leading-tight">
          Crea guiones de video <span class="text-gatales-accent">profesionales</span> con IA
        </h1>
        <p class="text-sm sm:text-lg text-gatales-text-secondary mt-3 sm:mt-4 leading-relaxed">
          Elige el plan que mejor se adapte a tus necesidades y comienza a crear contenido hoy mismo
        </p>
      </div>
    </section>

    <!-- Plans -->
    <section class="px-4 sm:px-6 pb-16 sm:pb-24">
      <div class="max-w-3xl mx-auto grid grid-cols-1 sm:grid-cols-2 gap-5 sm:gap-8">
        <div
          v-for="plan in plans"
          :key="plan.id"
          class="plan-card relative"
          :style="{ '--plan-color': plan.color }"
        >
          <!-- Badge (fuera del inner para que quede sobre el borde) -->
          <div
            v-if="plan.badge"
            class="absolute -top-3 left-1/2 -translate-x-1/2 z-10 px-4 py-1 rounded-full bg-amber-500 text-white text-xs font-semibold"
          >
            {{ plan.badge }}
          </div>

          <div class="plan-card-inner rounded-2xl p-5 sm:p-7 bg-gatales-sidebar">

            <!-- Plan name -->
            <div class="mb-5 sm:mb-6">
              <h2
                :class="[
                  'text-xl sm:text-2xl font-bold',
                  plan.highlight ? 'text-amber-500' : 'text-gatales-accent',
                ]"
              >
                {{ plan.name }}
              </h2>
              <p class="text-xs sm:text-sm text-gatales-text-secondary mt-1 leading-relaxed">
                {{ plan.description }}
              </p>
            </div>

            <!-- Features -->
            <ul class="space-y-2.5 sm:space-y-3 mb-6 sm:mb-8">
              <li
                v-for="(feature, idx) in plan.features"
                :key="idx"
                class="flex items-start gap-2.5 text-sm sm:text-base text-gatales-text"
              >
                <svg class="w-4.5 h-4.5 sm:w-5 sm:h-5 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ feature }}
              </li>
            </ul>

            <!-- CTA -->
            <a
              :href="plan.url"
              target="_blank"
              :class="[
                'block w-full text-center py-3 sm:py-3.5 rounded-xl font-semibold text-sm sm:text-base transition-all active:scale-[0.98]',
                plan.highlight
                  ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-md shadow-amber-500/20'
                  : 'bg-gatales-accent hover:bg-gatales-accent-hover text-white',
              ]"
            >
              Elegir {{ plan.name }}
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-gatales-border py-6 sm:py-8 text-center">
      <p class="text-xs text-gatales-text-secondary">
        Al suscribirte aceptas los terminos y condiciones de servicio
      </p>
    </footer>
  </div>
</template>

<style scoped>
.plan-card {
  padding: 2px;
  border-radius: 1rem;
  background: conic-gradient(from var(--angle, 0deg), transparent 55%, var(--plan-color, #00be88) 78%, transparent 100%);
  animation: spin-border 4s linear infinite;
}

.plan-card-inner {
  border-radius: calc(1rem - 2px);
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
