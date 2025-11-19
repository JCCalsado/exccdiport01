<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  ArcElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  ArcElement,
  Title,
  Tooltip,
  Legend
)

const props = defineProps({
  data: {
    type: Object,
    required: true
  },
  chartType: {
    type: String,
    default: 'pie', // pie, doughnut, polarArea
    validator: (value) => ['pie', 'doughnut', 'polarArea'].includes(value)
  },
  title: {
    type: String,
    default: 'Payment Methods'
  },
  showLegend: {
    type: Boolean,
    default: true
  },
  showLabels: {
    type: Boolean,
    default: true
  },
  height: {
    type: Number,
    default: 300
  }
})

const emit = defineEmits(['segment-click', 'chart-ready'])

const chartCanvas = ref(null)
const chartInstance = ref(null)

// Payment method colors and icons
const paymentMethodConfig = {
  gcash: {
    color: 'rgba(0, 168, 107, 0.8)',  // GCash green
    backgroundColor: 'rgba(0, 168, 107, 0.2)',
    borderColor: 'rgba(0, 168, 107, 1)',
    icon: '💵'
  },
  paypal: {
    color: 'rgba(0, 48, 135, 0.8)',  // PayPal blue
    backgroundColor: 'rgba(0, 48, 135, 0.2)',
    borderColor: 'rgba(0, 48, 135, 1)',
    icon: '💳'
  },
  stripe: {
    color: 'rgba(99, 102, 241, 0.8)', // Stripe purple
    backgroundColor: 'rgba(99, 102, 241, 0.2)',
    borderColor: 'rgba(99, 102, 241, 1)',
    icon: '🔵'
  },
  cash: {
    color: 'rgba(34, 197, 94, 0.8)',  // Green for cash
    backgroundColor: 'rgba(34, 197, 94, 0.2)',
    borderColor: 'rgba(34, 197, 94, 1)',
    icon: '💰'
  },
  bank_transfer: {
    color: 'rgba(59, 130, 246, 0.8)', // Blue for bank
    backgroundColor: 'rgba(59, 130, 246, 0.2)',
    borderColor: 'rgba(59, 130, 246, 1)',
    icon: '🏦'
  },
  credit_card: {
    color: 'rgba(239, 68, 68, 0.8)',   // Red for credit card
    backgroundColor: 'rgba(239, 68, 68, 0.2)',
    borderColor: 'rgba(239, 68, 68, 1)',
    icon: '💳'
  },
  debit_card: {
    color: 'rgba(245, 158, 11, 0.8)', // Orange for debit card
    backgroundColor: 'rgba(245, 158, 11, 0.2)',
    borderColor: 'rgba(245, 158, 11, 1)',
    icon: '💳'
  }
}

// Computed properties for chart options
const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: props.showLegend,
      position: props.chartType === 'polarArea' ? 'right' : 'bottom',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12,
        },
        generateLabels: function (chart) {
          const data = chart.data
          if (data.labels.length && data.datasets.length) {
            const dataset = data.datasets[0]
            const total = dataset.data.reduce((a, b) => a + b, 0)

            return data.labels.map((label, i) => {
              const value = dataset.data[i]
              const percentage = ((value / total) * 100).toFixed(1)
              const methodConfig = getMethodConfig(label.toLowerCase())

              return {
                text: `${methodConfig.icon} ${label} (${percentage}%)`,
                fillStyle: methodConfig.color,
                strokeStyle: methodConfig.borderColor,
                lineWidth: 2,
                hidden: false,
                index: i
              }
            })
          }
          return []
        }
      }
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleFont: {
        size: 14,
        weight: 'bold'
      },
      bodyFont: {
        size: 12
      },
      padding: 12,
      displayColors: true,
      callbacks: {
        label: function (context) {
          const label = context.label || ''
          const value = context.parsed
          const total = context.dataset.data.reduce((a, b) => a + b, 0)
          const percentage = ((value / total) * 100).toFixed(1)

          return [
            `${label}: ${formatCurrency(value)}`,
            `${percentage}% of total`
          ]
        },
        afterLabel: function (context) {
          const label = context.label || ''
          const methodConfig = getMethodConfig(label.toLowerCase())

          // Add additional info if available
          const stats = props.data.method_stats?.[label.toLowerCase()]
          if (stats) {
            return [
              `Transactions: ${stats.transaction_count}`,
              `Average: ${formatCurrency(stats.average_amount)}`,
              `Success Rate: ${stats.success_rate?.toFixed(1)}%`
            ]
          }
          return []
        }
      }
    },
    title: {
      display: !!props.title,
      text: props.title,
      font: {
        size: 16,
        weight: 'bold'
      },
      padding: {
        top: 10,
        bottom: 30
      }
    }
  },
  onClick: (event, activeElements) => {
    if (activeElements.length > 0) {
      const index = activeElements[0].index
      const datasetIndex = activeElements[0].datasetIndex
      const label = props.data.labels[index]
      const value = props.data.datasets[datasetIndex].data[index]

      emit('segment-click', {
        index,
        datasetIndex,
        label,
        value,
        methodStats: props.data.method_stats?.[label.toLowerCase()],
        originalEvent: event
      })
    }
  },
  animation: {
    animateRotate: true,
    animateScale: props.chartType === 'polarArea',
    duration: 1000,
    easing: 'easeInOutQuart'
  }
}))

// Process data for Chart.js
const processedData = computed(() => {
  if (!props.data?.labels || !props.data?.datasets) {
    return { labels: [], datasets: [] }
  }

  // Process labels and apply payment method styling
  const processedLabels = props.data.labels.map(label => {
    const methodConfig = getMethodConfig(label.toLowerCase())
    return {
      text: label,
      color: methodConfig.color
    }
  })

  // Process datasets with payment method colors
  const datasets = props.data.datasets.map((dataset) => ({
    ...dataset,
    backgroundColor: props.data.labels.map((label, index) => {
      const methodConfig = getMethodConfig(label.toLowerCase())
      return dataset.backgroundColor?.[index] || methodConfig.backgroundColor
    }),
    borderColor: props.data.labels.map((label, index) => {
      const methodConfig = getMethodConfig(label.toLowerCase())
      return dataset.borderColor?.[index] || methodConfig.borderColor
    }),
    borderWidth: 2,
    hoverBorderWidth: 3,
  }))

  return {
    labels: props.data.labels,
    datasets
  }
})

// Get payment method configuration
const getMethodConfig = (method) => {
  return paymentMethodConfig[method] || {
    color: 'rgba(107, 114, 128, 0.8)',    // Gray default
    backgroundColor: 'rgba(107, 114, 128, 0.2)',
    borderColor: 'rgba(107, 114, 128, 1)',
    icon: '💳'
  }
}

// Format currency
const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP'
  }).format(value)
}

// Initialize chart
const initChart = () => {
  if (!chartCanvas.value) return

  // Destroy existing chart instance
  if (chartInstance.value) {
    chartInstance.value.destroy()
  }

  // Create new chart
  chartInstance.value = new ChartJS(chartCanvas.value, {
    type: props.chartType,
    data: processedData.value,
    options: chartOptions.value
  })

  emit('chart-ready', chartInstance.value)
}

// Update chart when data changes
const updateChart = () => {
  if (!chartInstance.value) {
    initChart()
    return
  }

  chartInstance.value.data = processedData.value
  chartInstance.value.update('none') // Update without animation for better performance
}

// Lifecycle hooks
onMounted(() => {
  initChart()
})

// Watch for data changes
watch(() => props.data, () => {
  updateChart()
}, { deep: true })

watch(() => props.chartType, () => {
  initChart()
})

// Watch for canvas reference
watch(chartCanvas, (newCanvas) => {
  if (newCanvas) {
    initChart()
  }
})
</script>

<template>
  <div class="payment-method-chart-container">
    <!-- Chart Summary Cards (Optional) -->
    <div v-if="data.summary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-600">Total Methods</span>
          <span class="text-2xl">{{ data.summary.total_methods || 0 }}</span>
        </div>
      </div>
      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-600">Most Used</span>
          <div class="flex items-center gap-1">
            <span>{{ getMethodConfig(data.summary.most_used_method || '').icon }}</span>
            <span class="text-sm font-medium">{{ formatTitle(data.summary.most_used_method || '') }}</span>
          </div>
        </div>
      </div>
      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-600">Highest Revenue</span>
          <div class="flex items-center gap-1">
            <span>{{ getMethodConfig(data.summary.highest_revenue_method || '').icon }}</span>
            <span class="text-sm font-medium">{{ formatTitle(data.summary.highest_revenue_method || '') }}</span>
          </div>
        </div>
      </div>
      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-gray-600">Avg Success Rate</span>
          <span class="text-2xl font-bold text-green-600">
            {{ getAverageSuccessRate() }}%
          </span>
        </div>
      </div>
    </div>

    <!-- Chart -->
    <canvas
      ref="chartCanvas"
      :height="height"
      class="w-full"
    />
  </div>
</template>

<style scoped>
.payment-method-chart-container {
  position: relative;
  width: 100%;
}

.payment-method-chart-container canvas {
  max-width: 100%;
  height: auto;
}
</style>