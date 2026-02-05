<script setup>
import { computed } from 'vue'

const props = defineProps({
  balance: {
    type: Number,
    required: true,
  },
  monthly: {
    type: Number,
    required: true,
  },
})

const percentage = computed(() => {
  if (props.monthly === 0) return 0
  return Math.min(Math.round((props.balance / props.monthly) * 100), 100)
})

// Status based on percentage
const status = computed(() => {
  if (percentage.value > 60) return 'healthy'
  if (percentage.value > 25) return 'warning'
  return 'critical'
})

const statusConfig = computed(() => {
  const configs = {
    healthy: {
      gradient: 'from-emerald-500 to-green-400',
      bg: 'bg-emerald-500/10',
      text: 'text-emerald-400',
      ring: 'stroke-emerald-400',
      label: 'Disponible',
      icon: 'check'
    },
    warning: {
      gradient: 'from-amber-500 to-yellow-400',
      bg: 'bg-amber-500/10',
      text: 'text-amber-400',
      ring: 'stroke-amber-400',
      label: 'Bajo',
      icon: 'alert'
    },
    critical: {
      gradient: 'from-red-500 to-rose-400',
      bg: 'bg-red-500/10',
      text: 'text-red-400',
      ring: 'stroke-red-400',
      label: 'Critico',
      icon: 'warning'
    }
  }
  return configs[status.value]
})
</script>

<template>
  <!-- Mobile: Compact circular indicator -->
  <div class="flex sm:hidden items-center gap-2">
    <div class="relative w-10 h-10">
      <!-- Background circle -->
      <svg class="w-10 h-10 -rotate-90" viewBox="0 0 36 36">
        <circle
          cx="18"
          cy="18"
          r="15"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
          class="text-gatales-border/50"
        />
        <!-- Progress circle -->
        <circle
          cx="18"
          cy="18"
          r="15"
          fill="none"
          :class="statusConfig.ring"
          stroke-width="2.5"
          stroke-linecap="round"
          :stroke-dasharray="`${(percentage * 94.25) / 100} 94.25`"
          class="transition-all duration-500 ease-out"
        />
      </svg>
      <!-- Percentage text -->
      <span class="absolute inset-0 flex items-center justify-center text-[11px] font-semibold text-gatales-text">
        {{ percentage }}%
      </span>
    </div>
  </div>

  <!-- Desktop: Professional token meter -->
  <div class="hidden sm:flex items-center gap-3 px-3 py-2 bg-gatales-sidebar/80 backdrop-blur-sm rounded-xl border border-gatales-border/50">
    <!-- Token icon -->
    <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', statusConfig.bg]">
      <svg class="w-4 h-4" :class="statusConfig.text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
      </svg>
    </div>

    <!-- Info section -->
    <div class="flex flex-col min-w-0">
      <!-- Percentage and status -->
      <div class="flex items-center gap-2">
        <span :class="['text-base font-bold tabular-nums', statusConfig.text]">
          {{ percentage }}%
        </span>
        <span :class="['text-[10px] font-medium px-1.5 py-0.5 rounded-full', statusConfig.bg, statusConfig.text]">
          {{ statusConfig.label }}
        </span>
      </div>
    </div>

    <!-- Progress bar -->
    <div class="w-16 flex flex-col gap-1">
      <div class="h-1.5 bg-gatales-input rounded-full overflow-hidden">
        <div
          :class="['h-full rounded-full bg-gradient-to-r transition-all duration-500 ease-out', statusConfig.gradient]"
          :style="{ width: percentage + '%' }"
        />
      </div>
    </div>
  </div>
</template>
