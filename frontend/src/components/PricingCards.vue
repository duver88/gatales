<script setup>
defineProps({
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  highlightPlan: {
    type: String,
    default: 'super-pro', // 'pro' or 'super-pro'
  },
})

const plans = [
  {
    id: 'pro',
    name: 'Pro',
    badge: null,
    price: null,
    description: 'Ideal para comenzar a crear guiones profesionales',
    features: [
      'Acceso al asistente de IA',
      'Tokens mensuales incluidos',
      'Creacion de guiones de video',
      'Historial de conversaciones',
    ],
    url: 'https://pay.hotmart.com/D104282667I?off=6qkjyj67&bid=1770611375412',
    color: 'gatales-accent',
    bgGradient: 'from-gatales-accent/10 to-gatales-accent/5',
    borderColor: 'border-gatales-accent/30',
    btnClass: 'bg-gatales-accent hover:bg-gatales-accent-hover text-white',
  },
  {
    id: 'super-pro',
    name: 'Super Pro',
    badge: 'Recomendado',
    price: null,
    description: 'Para creadores que necesitan el maximo rendimiento',
    features: [
      'Todo lo del plan Pro',
      'Muchos mas tokens mensuales',
      'Asistentes exclusivos premium',
      'Soporte prioritario',
    ],
    url: 'https://pay.hotmart.com/D104282667I?off=i909yaee&bid=1770612278658',
    color: 'amber-500',
    bgGradient: 'from-amber-500/10 to-amber-600/5',
    borderColor: 'border-amber-500/30',
    btnClass: 'bg-amber-500 hover:bg-amber-600 text-white',
  },
]
</script>

<template>
  <div class="w-full">
    <!-- Section title -->
    <div v-if="title" class="text-center mb-4 sm:mb-6">
      <h2 class="text-lg sm:text-xl font-bold text-gatales-text">{{ title }}</h2>
      <p v-if="subtitle" class="text-xs sm:text-sm text-gatales-text-secondary mt-1">{{ subtitle }}</p>
    </div>

    <!-- Cards grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
      <div
        v-for="plan in plans"
        :key="plan.id"
        :class="[
          'relative rounded-xl border p-4 sm:p-5 transition-all',
          plan.id === highlightPlan
            ? `bg-gradient-to-br ${plan.bgGradient} ${plan.borderColor} shadow-lg`
            : 'bg-gatales-sidebar border-gatales-border hover:border-gatales-text-secondary/30',
        ]"
      >
        <!-- Recommended badge -->
        <div
          v-if="plan.badge && plan.id === highlightPlan"
          class="absolute -top-2.5 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-amber-500 text-white text-[10px] sm:text-xs font-semibold whitespace-nowrap"
        >
          {{ plan.badge }}
        </div>

        <!-- Plan name -->
        <div class="mb-3">
          <h3
            :class="[
              'text-base sm:text-lg font-bold',
              plan.id === 'super-pro' ? 'text-amber-500' : 'text-gatales-accent',
            ]"
          >
            {{ plan.name }}
          </h3>
          <p class="text-[11px] sm:text-xs text-gatales-text-secondary mt-0.5 leading-relaxed">
            {{ plan.description }}
          </p>
        </div>

        <!-- Features -->
        <ul class="space-y-1.5 sm:space-y-2 mb-4">
          <li
            v-for="(feature, idx) in plan.features"
            :key="idx"
            class="flex items-start gap-2 text-xs sm:text-sm text-gatales-text-secondary"
          >
            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ feature }}
          </li>
        </ul>

        <!-- CTA Button -->
        <a
          :href="plan.url"
          target="_blank"
          :class="[
            'block w-full text-center py-2.5 sm:py-3 rounded-lg font-semibold text-sm transition-all active:scale-[0.98]',
            plan.id === highlightPlan
              ? plan.btnClass
              : 'bg-gatales-input hover:bg-gatales-border text-gatales-text',
          ]"
        >
          Elegir {{ plan.name }}
        </a>
      </div>
    </div>
  </div>
</template>
