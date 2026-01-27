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
  return Math.min((props.balance / props.monthly) * 100, 100)
})

const formattedBalance = computed(() => {
  return props.balance.toLocaleString('es-ES')
})

const formattedBalanceShort = computed(() => {
  if (props.balance >= 1000000) {
    return (props.balance / 1000000).toFixed(1) + 'M'
  }
  if (props.balance >= 1000) {
    return (props.balance / 1000).toFixed(0) + 'K'
  }
  return props.balance.toString()
})

const formattedMonthly = computed(() => {
  return props.monthly.toLocaleString('es-ES')
})

const statusColor = computed(() => {
  if (percentage.value > 50) return 'bg-gatales-accent'
  if (percentage.value > 20) return 'bg-yellow-500'
  return 'bg-red-500'
})

const statusRingColor = computed(() => {
  if (percentage.value > 50) return 'stroke-gatales-accent'
  if (percentage.value > 20) return 'stroke-yellow-500'
  return 'stroke-red-500'
})
</script>

<template>
  <!-- Mobile: Circular progress -->
  <div class="flex sm:hidden items-center gap-1.5">
    <div class="relative w-8 h-8">
      <svg class="w-8 h-8 -rotate-90" viewBox="0 0 32 32">
        <circle
          cx="16"
          cy="16"
          r="12"
          fill="none"
          stroke="currentColor"
          stroke-width="3"
          class="text-gatales-input"
        />
        <circle
          cx="16"
          cy="16"
          r="12"
          fill="none"
          :class="statusRingColor"
          stroke-width="3"
          stroke-linecap="round"
          :stroke-dasharray="`${(percentage * 75.4) / 100} 75.4`"
        />
      </svg>
      <span class="absolute inset-0 flex items-center justify-center text-[9px] font-bold text-gatales-text">
        {{ formattedBalanceShort }}
      </span>
    </div>
  </div>

  <!-- Desktop: Full display -->
  <div class="hidden sm:flex items-center gap-2 text-sm">
    <div class="flex flex-col items-end">
      <span class="text-gatales-text font-medium">
        {{ formattedBalance }}
      </span>
      <span class="text-xs text-gatales-text-secondary">
        / {{ formattedMonthly }} tokens
      </span>
    </div>

    <!-- Progress bar -->
    <div class="w-16 h-2 bg-gatales-input rounded-full overflow-hidden">
      <div
        :class="['h-full transition-all duration-300', statusColor]"
        :style="{ width: percentage + '%' }"
      />
    </div>
  </div>
</template>
