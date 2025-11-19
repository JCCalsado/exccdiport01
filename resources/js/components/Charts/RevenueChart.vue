<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
  BarElement,
} from 'chart.js'

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

const props = defineProps({
  data: {
    type: Object,
    required: true
  },
  type: {
    type: String,
    default: 'line', // line, bar, mixed
    validator: (value) => ['line', 'bar', 'mixed'].includes(value)
  },
  title: {
    type: String,
    default: 'Revenue Chart'
  },
  height: {
    type: Number,
    default: 400
  },
  showLegend: {
    type: Boolean,
    default: true
  },
  timeFormat: {
    type: String,
    default: 'MMM DD' // Format for date labels
  }
})

const emit = defineEmits(['dataset-click', 'chart-ready'])

const chartCanvas = ref(null)
const chartInstance = ref(null)

// Computed properties for chart options
const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: props.showLegend,
      position: 'top',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12,
        }
      }
    },
    tooltip: {
      mode: 'index',
      intersect: false,
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
          let label = context.dataset.label || ''
          if (label) {
            label += ': '
          }
          label += formatCurrency(context.parsed.y)
          return label
        },
        footer: function (tooltipItems) {
          // Calculate total for all datasets
          let sum = 0
          tooltipItems.forEach(function(tooltipItem) {
            sum += tooltipItem.parsed.y
          })
          return 'Total: ' + formatCurrency(sum)
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
  scales: {
    x: {
      grid: {
        display: false
      },
      ticks: {
        font: {
          size: 11
        }
      }
    },
    y: {
      beginAtZero: true,
      grid: {
        color: 'rgba(0, 0, 0, 0.05)'
      },
      ticks: {
        font: {
          size: 11
        },
        callback: function (value) {
          return formatCurrency(value)
        }
      }
    }
  },
  onClick: (event, activeElements) => {
    if (activeElements.length > 0) {
      const datasetIndex = activeElements[0].datasetIndex
      const index = activeElements[0].index
      const dataset = props.data.datasets[datasetIndex]
      const label = props.data.labels[index]
      const value = dataset.data[index]

      emit('dataset-click', {
        datasetIndex,
        index,
        dataset,
        label,
        value,
        originalEvent: event
      })
    }
  },
  animation: {
    duration: 1000,
    easing: 'easeInOutQuart'
  }
}))

// Process data for Chart.js
const processedData = computed(() => {
  if (!props.data?.labels || !props.data?.datasets) {
    return { labels: [], datasets: [] }
  }

  // Process datasets to ensure proper structure
  const datasets = props.data.datasets.map((dataset, index) => ({
    ...dataset,
    label: dataset.label || `Dataset ${index + 1}`,
    data: dataset.data || [],
    borderColor: dataset.borderColor || getDefaultColors(index),
    backgroundColor: dataset.backgroundColor || getBackgroundColor(dataset.borderColor || getDefaultColors(index), 0.1),
    borderWidth: dataset.borderWidth || 2,
    fill: dataset.fill !== undefined ? dataset.fill : props.type === 'line',
    tension: dataset.tension || 0.4,
    type: dataset.type || (props.type === 'mixed' && index === 1 ? 'bar' : props.type),
    yAxisID: dataset.yAxisID || 'y',
    order: dataset.order || index
  }))

  return {
    labels: props.data.labels,
    datasets
  }
})

// Initialize chart
const initChart = () => {
  if (!chartCanvas.value) return

  // Destroy existing chart instance
  if (chartInstance.value) {
    chartInstance.value.destroy()
  }

  // Create new chart
  chartInstance.value = new ChartJS(chartCanvas.value, {
    type: props.type === 'mixed' ? 'bar' : props.type,
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

// Color helpers
const getDefaultColors = (index) => {
  const colors = [
    'rgba(54, 162, 235, 1)',   // Blue
    'rgba(255, 99, 132, 1)',   // Red
    'rgba(255, 206, 86, 1)',   // Yellow
    'rgba(75, 192, 192, 1)',   // Green
    'rgba(153, 102, 255, 1)', // Purple
    'rgba(255, 159, 64, 1)',  // Orange
  ]
  return colors[index % colors.length]
}

const getBackgroundColor = (color, opacity) => {
  if (color.includes('rgba')) {
    return color.replace(/[\d.]+\)$/, `${opacity})`)
  }
  const rgb = color.match(/\d+/g)
  if (rgb) {
    return `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${opacity})`
  }
  return `rgba(0, 0, 0, ${opacity})`
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP'
  }).format(value)
}

// Lifecycle hooks
onMounted(() => {
  initChart()
})

// Watch for data changes
watch(() => props.data, () => {
  updateChart()
}, { deep: true })

watch(() => props.type, () => {
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
  <div class="revenue-chart-container">
    <canvas
      ref="chartCanvas"
      :height="height"
      class="w-full"
    />
  </div>
</template>

<style scoped>
.revenue-chart-container {
  position: relative;
  width: 100%;
}

.revenue-chart-container canvas {
  max-width: 100%;
  height: auto;
}
</style>