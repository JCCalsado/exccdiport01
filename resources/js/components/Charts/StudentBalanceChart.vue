<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
)

const props = defineProps({
  data: {
    type: Object,
    required: true
  },
  title: {
    type: String,
    default: 'Student Balance Analysis'
  },
  height: {
    type: Number,
    default: 400
  },
  showLegend: {
    type: Boolean,
    default: true
  },
  sortBy: {
    type: String,
    default: 'balance', // balance, name, course
    validator: (value) => ['balance', 'name', 'course'].includes(value)
  }
})

const emit = defineEmits(['bar-click', 'chart-ready'])

const chartCanvas = ref(null)
const chartInstance = ref(null)

// Aging bucket colors
const agingColors = {
  '0-30': {
    backgroundColor: 'rgba(34, 197, 94, 0.8)',   // Green - recent
    borderColor: 'rgba(34, 197, 94, 1)',
    label: '0-30 days'
  },
  '31-60': {
    backgroundColor: 'rgba(251, 191, 36, 0.8)',  // Yellow - moderate
    borderColor: 'rgba(251, 191, 36, 1)',
    label: '31-60 days'
  },
  '61-90': {
    backgroundColor: 'rgba(251, 146, 60, 0.8)',  // Orange - concerning
    borderColor: 'rgba(251, 146, 60, 1)',
    label: '61-90 days'
  },
  '90+': {
    backgroundColor: 'rgba(239, 68, 68, 0.8)',   // Red - critical
    borderColor: 'rgba(239, 68, 68, 1)',
    label: '90+ days'
  }
}

// Process data for Chart.js
const processedData = computed(() => {
  if (!props.data?.aging_data) {
    return { labels: [], datasets: [] }
  }

  const labels = []
  const datasets = []

  // Prepare labels and data for each aging bucket
  Object.entries(props.data.aging_data).forEach(([bucket, students]) => {
    if (students.length > 0) {
      // Group by course for better visualization
      const courseData = {}

      students.forEach(student => {
        const course = student.course || 'Unknown'
        if (!courseData[course]) {
          courseData[course] = 0
        }
        courseData[course] += student.outstanding_balance
      })

      if (Object.keys(courseData).length > 0) {
        labels.push(agingColors[bucket]?.label || bucket)

        Object.entries(courseData).forEach(([course, balance]) => {
          let dataset = datasets.find(d => d.label === course)
          if (!dataset) {
            dataset = {
              label: course,
              data: new Array(Object.keys(props.data.aging_data).length).fill(0),
              backgroundColor: getCourseColor(course),
              borderColor: getCourseBorderColor(course),
              borderWidth: 1
            }
            datasets.push(dataset)
          }

          const bucketIndex = Object.keys(props.data.aging_data).indexOf(bucket)
          dataset.data[bucketIndex] = balance
        })
      }
    }
  })

  // Sort datasets by total balance if requested
  if (props.sortBy === 'balance') {
    datasets.sort((a, b) => {
      const totalA = a.data.reduce((sum, val) => sum + val, 0)
      const totalB = b.data.reduce((sum, val) => sum + val, 0)
      return totalB - totalA
    })
  } else if (props.sortBy === 'name') {
    datasets.sort((a, b) => a.label.localeCompare(b.label))
  }

  return {
    labels,
    datasets
  }
})

// Chart options
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
          // Calculate total for all datasets at this index
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
      stacked: true,
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
      stacked: true,
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
      const dataset = processedData.value.datasets[datasetIndex]
      const bucket = Object.keys(props.data.aging_data)[index]
      const value = dataset.data[index]

      // Get students in this bucket and course
      const bucketLabel = processedData.value.labels[index]
      const course = dataset.label
      const studentsInBucket = props.data.aging_data[bucket]?.filter(
        student => student.course === course
      ) || []

      emit('bar-click', {
        datasetIndex,
        index,
        bucket,
        bucketLabel,
        course,
        value,
        students: studentsInBucket,
        totalStudents: studentsInBucket.length,
        originalEvent: event
      })
    }
  },
  animation: {
    duration: 1000,
    easing: 'easeInOutQuart'
  }
}))

// Get course colors
const getCourseColor = (course) => {
  const courseColors = [
    'rgba(54, 162, 235, 0.8)',  // Blue
    'rgba(255, 99, 132, 0.8)',  // Red
    'rgba(255, 206, 86, 0.8)',  // Yellow
    'rgba(75, 192, 192, 0.8)',  // Green
    'rgba(153, 102, 255, 0.8)', // Purple
    'rgba(255, 159, 64, 0.8)',  // Orange
    'rgba(231, 76, 60, 0.8)',   // Dark Red
    'rgba(52, 73, 94, 0.8)',   // Dark Gray
  ]

  // Create consistent color based on course name
  const hash = course.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)
  return courseColors[hash % courseColors.length]
}

const getCourseBorderColor = (course) => {
  const color = getCourseColor(course)
  return color.replace('0.8', '1')
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
    type: 'bar',
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

watch(() => props.sortBy, () => {
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
  <div class="student-balance-chart-container">
    <!-- Summary Cards -->
    <div v-if="data.summary" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Total Outstanding</p>
            <p class="text-2xl font-bold text-red-600">
              {{ formatCurrency(data.summary.total_outstanding_balance || 0) }}
            </p>
          </div>
          <div class="text-red-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1l3 1m-3-4l-3 1" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Students with Balance</p>
            <p class="text-2xl font-bold text-orange-600">
              {{ data.summary.total_students_with_balance || 0 }}
            </p>
          </div>
          <div class="text-orange-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Avg Days Since Payment</p>
            <p class="text-2xl font-bold text-yellow-600">
              {{ Math.round(data.summary.average_days_since_payment || 0) }}
            </p>
          </div>
          <div class="text-yellow-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Critical Accounts (90+)</p>
            <p class="text-2xl font-bold text-red-600">
              {{ data.summary.bucket_summary?.['90+']?.count || 0 }}
            </p>
          </div>
          <div class="text-red-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Chart -->
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
        <div class="flex items-center space-x-4">
          <div class="flex items-center space-x-2 text-sm">
            <span class="text-gray-600">Sort by:</span>
            <select
              :value="sortBy"
              @input="$emit('update:sortBy', $event.target.value)"
              class="border border-gray-300 rounded px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="balance">Balance</option>
              <option value="name">Course Name</option>
            </select>
          </div>
        </div>
      </div>

      <canvas
        ref="chartCanvas"
        :height="height"
        class="w-full"
      />
    </div>
  </div>
</template>

<style scoped>
.student-balance-chart-container {
  position: relative;
  width: 100%;
}

.student-balance-chart-container canvas {
  max-width: 100%;
  height: auto;
}
</style>