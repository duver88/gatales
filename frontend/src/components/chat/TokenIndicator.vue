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

const statusTextColor = computed(() => {
  if (percentage.value > 50) return 'text-gatales-accent'
  if (percentage.value > 20) return 'text-yellow-500'
  return 'text-red-500'
})

const statusLabel = computed(() => {
  if (percentage.value > 50) return 'Disponible'
  if (percentage.value > 20) return 'Bajo'
  return 'Agotandose'
})
</script>

<template>
  <!-- Mobile: Circular progress with percentage -->
  <div class="flex sm:hidden items-center gap-1.5">
    <div class="relative w-9 h-9">
      <svg class="w-9 h-9 -rotate-90" viewBox="0 0 36 36">
        <circle
          cx="18"
          cy="18"
          r="14"
          fill="none"
          stroke="currentColor"
          stroke-width="3"
          class="text-gatales-input"
        />
        <circle
          cx="18"
          cy="18"
          r="14"
          fill="none"
          :class="statusRingColor"
          stroke-width="3"
          stroke-linecap="round"
          :stroke-dasharray="`${(percentage * 87.96) / 100} 87.96`"
        />
      </svg>
      <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-gatales-text">
        {{ percentage }}%
      </span>
    </div>
  </div>

  <!-- Desktop: Percentage display with progress bar -->
  <div class="hidden sm:flex items-center gap-3 px-3 py-1.5 bg-gatales-input/50 rounded-lg">
    <div class="flex flex-col items-end">
      <div class="flex items-center gap-1.5">
        <span :class="['text-lg font-bold', statusTextColor]">
          {{ percentage }}%
        </span>
      </div>
      <span class="text-[10px] text-gatales-text-secondary">
        {{ statusLabel }}
      </span>
    </div>

    <!-- Progress bar -->
    <div class="w-20 h-2.5 bg-gatales-input rounded-full overflow-hidden">
      <div
        :class="['h-full transition-all duration-300 rounded-full', statusColor]"
        :style="{ width: percentage + '%' }"
      />
    </div>
  </div>
</template>
