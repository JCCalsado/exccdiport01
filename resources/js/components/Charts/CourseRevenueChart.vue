<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  LineElement,
  PointElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  LineElement,
  PointElement,
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
    default: 'Course Revenue Analysis'
  },
  height: {
    type: Number,
    default: 400
  },
  showLegend: {
    type: Boolean,
    default: true
  },
  chartType: {
    type: String,
    default: 'mixed', // bar, line, mixed
    validator: (value) => ['bar', 'line', 'mixed'].includes(value)
  }
})

const emit = defineEmits(['data-point-click', 'chart-ready'])

const chartCanvas = ref(null)
const chartInstance = ref(null)

// Chart colors for different metrics
const chartColors = {
  revenue: {
    backgroundColor: 'rgba(59, 130, 246, 0.8)',
    borderColor: 'rgba(59, 130, 246, 1)',
    yAxisID: 'y-revenue'
  },
  students: {
    backgroundColor: 'rgba(34, 197, 94, 0.8)',
    borderColor: 'rgba(34, 197, 94, 1)',
    yAxisID: 'y-students'
  },
  average: {
    backgroundColor: 'rgba(251, 191, 36, 0.8)',
    borderColor: 'rgba(251, 191, 36, 1)',
    yAxisID: 'y-average'
  }
}

// Process data for Chart.js
const processedData = computed(() => {
  if (!props.data?.course_data) {
    return { labels: [], datasets: [] }
  }

  const courseData = props.data.course_data
  const labels = courseData.map(course => course.course)

  const datasets = []

  // Revenue dataset (always shown)
  datasets.push({
    label: 'Total Revenue',
    data: courseData.map(course => course.total_revenue),
    backgroundColor: chartColors.revenue.backgroundColor,
    borderColor: chartColors.revenue.borderColor,
    borderWidth: 2,
    type: props.chartType === 'line' ? 'line' : 'bar',
    yAxisID: 'y-revenue',
    tension: 0.4,
    fill: props.chartType === 'line' ? false : true,
    order: 2
  })

  // Student count dataset
  datasets.push({
    label: 'Student Count',
    data: courseData.map(course => course.student_count),
    backgroundColor: chartColors.students.backgroundColor,
    borderColor: chartColors.students.borderColor,
    borderWidth: 2,
    type: 'bar',
    yAxisID: 'y-students',
    order: 1
  })

  // Average revenue per student dataset
  if (props.chartType === 'mixed') {
    datasets.push({
      label: 'Revenue per Student',
      data: courseData.map(course => course.revenue_per_student),
      backgroundColor: chartColors.average.backgroundColor,
      borderColor: chartColors.average.borderColor,
      borderWidth: 2,
      type: 'line',
      yAxisID: 'y-average',
      tension: 0.4,
      pointRadius: 4,
      pointHoverRadius: 6,
      order: 0
    })
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
  interaction: {
    mode: 'index',
    intersect: false,
  },
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
          const label = context.dataset.label || ''
          let value = context.parsed.y

          if (context.dataset.yAxisID === 'y-revenue' || context.dataset.yAxisID === 'y-average') {
            value = formatCurrency(value)
          } else {
            value = value.toLocaleString() + ' students'
          }

          return label + ': ' + value
        },
        afterLabel: function (context) {
          const courseData = props.data.course_data[context.dataIndex]
          if (courseData) {
            const additionalInfo = []

            if (context.dataset.yAxisID === 'y-revenue') {
              additionalInfo.push('Students: ' + courseData.student_count.toLocaleString())
              additionalInfo.push('Avg per student: ' + formatCurrency(courseData.revenue_per_student))
            } else if (context.dataset.yAxisID === 'y-students') {
              additionalInfo.push('Total revenue: ' + formatCurrency(courseData.total_revenue))
              additionalInfo.push('Avg per student: ' + formatCurrency(courseData.revenue_per_student))
            } else if (context.dataset.yAxisID === 'y-average') {
              additionalInfo.push('Total revenue: ' + formatCurrency(courseData.total_revenue))
              additionalInfo.push('Students: ' + courseData.student_count.toLocaleString())
            }

            return additionalInfo
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
  scales: {
    x: {
      grid: {
        display: false
      },
      ticks: {
        font: {
          size: 11
        },
        maxRotation: 45,
        minRotation: 0
      }
    },
    'y-revenue': {
      type: 'linear',
      display: true,
      position: 'left',
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
      },
      title: {
        display: true,
        text: 'Revenue (PHP)',
        font: {
          size: 12,
          weight: 'bold'
        }
      }
    },
    'y-students': {
      type: 'linear',
      display: props.chartType !== 'line',
      position: 'right',
      beginAtZero: true,
      grid: {
        drawOnChartArea: false, // only show the grid for the revenue axis
      },
      ticks: {
        font: {
          size: 11
        },
        callback: function (value) {
          return value.toLocaleString()
        }
      },
      title: {
        display: true,
        text: 'Number of Students',
        font: {
          size: 12,
          weight: 'bold'
        }
      }
    },
    'y-average': {
      type: 'linear',
      display: props.chartType === 'mixed',
      position: 'right',
      beginAtZero: true,
      grid: {
        drawOnChartArea: false,
      },
      ticks: {
        font: {
          size: 11
        },
        callback: function (value) {
          return formatCurrency(value)
        }
      },
      title: {
        display: true,
        text: 'Revenue per Student (PHP)',
        font: {
          size: 12,
          weight: 'bold'
        }
      }
    }
  },
  onClick: (event, activeElements) => {
    if (activeElements.length > 0) {
      const datasetIndex = activeElements[0].datasetIndex
      const index = activeElements[0].index
      const dataset = processedData.value.datasets[datasetIndex]
      const courseLabel = processedData.value.labels[index]
      const value = dataset.data[index]

      // Get full course data
      const courseData = props.data.course_data[index]

      emit('data-point-click', {
        datasetIndex,
        index,
        courseLabel,
        courseData,
        dataset: dataset.label,
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

// Format currency
const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
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
    type: props.chartType === 'mixed' ? 'bar' : props.chartType,
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
  <div class="course-revenue-chart-container">
    <!-- Summary Cards -->
    <div v-if="data.summary" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Total Students</p>
            <p class="text-2xl font-bold text-blue-600">
              {{ data.summary.total_students?.toLocaleString() || 0 }}
            </p>
          </div>
          <div class="text-blue-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Total Revenue</p>
            <p class="text-2xl font-bold text-green-600">
              {{ formatCurrency(data.summary.total_revenue || 0) }}
            </p>
          </div>
          <div class="text-green-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1l3 1m-3-4l-3 1" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Avg Revenue/Student</p>
            <p class="text-2xl font-bold text-purple-600">
              {{ formatCurrency(data.summary.average_revenue_per_student || 0) }}
            </p>
          </div>
          <div class="text-purple-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-600">Performance</p>
            <p class="text-2xl font-bold text-indigo-600">
              {{ data.data?.course_data?.length || 0 }}
            </p>
            <p class="text-xs text-gray-500">courses</p>
          </div>
          <div class="text-indigo-500">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Chart Controls -->
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
        <div class="flex items-center space-x-4">
          <div class="flex items-center space-x-2 text-sm">
            <span class="text-gray-600">Chart Type:</span>
            <select
              :value="chartType"
              @input="$emit('update:chartType', $event.target.value)"
              class="border border-gray-300 rounded px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="mixed">Mixed</option>
              <option value="bar">Bar</option>
              <option value="line">Line</option>
            </select>
          </div>
          <div class="text-sm text-gray-500">
            {{ data.school_year }} - {{ data.semester }} Semester
          </div>
        </div>
      </div>

      <!-- Chart -->
      <canvas
        ref="chartCanvas"
        :height="height"
        class="w-full"
      />

      <!-- Course Details Table -->
      <div v-if="data.course_data" class="mt-6">
        <h4 class="text-md font-semibold text-gray-900 mb-3">Course Breakdown</h4>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Students</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Avg/Student</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr
                v-for="(course, index) in data.course_data"
                :key="index"
                class="hover:bg-gray-50"
              >
                <td class="px-4 py-2 text-sm font-medium text-gray-900">
                  {{ course.course }}
                </td>
                <td class="px-4 py-2 text-sm text-gray-500 text-right">
                  {{ course.student_count.toLocaleString() }}
                </td>
                <td class="px-4 py-2 text-sm text-gray-500 text-right">
                  {{ formatCurrency(course.total_revenue) }}
                </td>
                <td class="px-4 py-2 text-sm text-gray-500 text-right">
                  {{ formatCurrency(course.revenue_per_student) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.course-revenue-chart-container {
  position: relative;
  width: 100%;
}

.course-revenue-chart-container canvas {
  max-width: 100%;
  height: auto;
}
</style>