<script setup>
import { computed } from 'vue'

const props = defineProps({
    payment: Object,
    step: {
        type: String,
        default: 'processing'
    },
    isProcessing: Boolean
})

const emit = defineEmits(['cancel', 'retry', 'close'])

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount)
}

const stepConfig = computed(() => {
    const configs = {
        processing: {
            icon: '⏳',
            title: 'Processing Payment',
            description: 'Your payment is being processed. Please wait...',
            color: 'blue'
        },
        confirm: {
            icon: '📱',
            title: 'Scan QR Code to Pay',
            description: 'Use your GCash app to scan the QR code below',
            color: 'green'
        },
        success: {
            icon: '✅',
            title: 'Payment Successful!',
            description: 'Your payment has been completed successfully.',
            color: 'green'
        },
        failed: {
            icon: '❌',
            title: 'Payment Failed',
            description: 'We couldn\'t process your payment. Please try again.',
            color: 'red'
        },
        timeout: {
            icon: '⏰',
            title: 'Payment Timeout',
            description: 'Payment processing timed out. Please check your payment status.',
            color: 'yellow'
        }
    }

    return configs[props.step] || configs.processing
})

const getColorClasses = (color) => {
    const classes = {
        blue: {
            bg: 'bg-blue-100',
            border: 'border-blue-200',
            text: 'text-blue-900',
            icon: 'text-blue-600',
            button: 'bg-blue-600 hover:bg-blue-700'
        },
        green: {
            bg: 'bg-green-100',
            border: 'border-green-200',
            text: 'text-green-900',
            icon: 'text-green-600',
            button: 'bg-green-600 hover:bg-green-700'
        },
        red: {
            bg: 'bg-red-100',
            border: 'border-red-200',
            text: 'text-red-900',
            icon: 'text-red-600',
            button: 'bg-red-600 hover:bg-red-700'
        },
        yellow: {
            bg: 'bg-yellow-100',
            border: 'border-yellow-200',
            text: 'text-yellow-900',
            icon: 'text-yellow-600',
            button: 'bg-yellow-600 hover:bg-yellow-700'
        }
    }

    return classes[color] || classes.blue
}

const colors = computed(() => getColorClasses(stepConfig.value.color))
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <!-- Header -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="text-2xl mr-3">{{ stepConfig.icon }}</div>
                        <div>
                            <h3 class="text-lg font-semibold" :class="colors.text">
                                {{ stepConfig.title }}
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">
                                {{ stepConfig.description }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="$emit('close')"
                        class="text-gray-400 hover:text-gray-600 transition"
                        v-if="!isProcessing"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="p-6">
                <!-- Payment Details -->
                <div v-if="payment" class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-medium text-gray-900 mb-2">Payment Details</h4>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Reference Number:</span>
                            <span class="font-medium">{{ payment.reference_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount:</span>
                            <span class="font-medium">{{ formatCurrency(payment.amount) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Payment Method:</span>
                            <span class="font-medium capitalize">{{ payment.gateway }}</span>
                        </div>
                    </div>
                </div>

                <!-- QR Code Display (for GCash) -->
                <div v-if="step === 'confirm' && payment?.qr_code"
                     class="mb-6 text-center">
                    <div class="inline-block p-6 bg-white border-2 border-gray-200 rounded-lg">
                        <img
                            :src="payment.qr_code"
                            alt="Payment QR Code"
                            class="w-48 h-48 mx-auto"
                        >
                        <p class="mt-3 text-sm text-gray-600">
                            Scan with your GCash app
                        </p>
                    </div>
                </div>

                <!-- Loading Animation -->
                <div v-if="step === 'processing'" class="mb-6 text-center">
                    <div class="inline-flex items-center justify-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                    </div>
                    <p class="mt-3 text-sm text-gray-600">
                        This usually takes a few seconds...
                    </p>
                </div>

                <!-- Success Animation -->
                <div v-if="step === 'success'" class="mb-6 text-center">
                    <div class="text-6xl mb-4">✅</div>
                    <p class="text-gray-600">
                        Redirecting to receipt page...
                    </p>
                </div>

                <!-- Failed State -->
                <div v-if="step === 'failed'" class="mb-6 text-center">
                    <div class="text-6xl mb-4">❌</div>
                    <p class="text-gray-600 text-sm">
                        You can try again or contact support if the problem persists.
                    </p>
                </div>

                <!-- Timeout State -->
                <div v-if="step === 'timeout'" class="mb-6 text-center">
                    <div class="text-6xl mb-4">⏰</div>
                    <p class="text-gray-600 text-sm">
                        Please check your payment history to confirm the status.
</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="p-6 border-t border-gray-200 bg-gray-50">
                <div class="flex space-x-3">
                    <!-- Cancel Button -->
                    <button
                        v-if="step === 'processing' || step === 'confirm'"
                        @click="$emit('cancel')"
                        class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-100 transition"
                    >
                        Cancel Payment
                    </button>

                    <!-- Retry Button -->
                    <button
                        v-if="step === 'failed'"
                        @click="$emit('retry')"
                        class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition"
                    >
                        Try Again
                    </button>

                    <!-- Close Button -->
                    <button
                        v-if="step === 'success' || step === 'timeout'"
                        @click="$emit('close')"
                        class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition"
                    >
                        Close
                    </button>

                    <!-- View Receipt Button -->
                    <button
                        v-if="step === 'success' && payment"
                        @click="window.location.href = `/student/payment/receipt/${payment.payment_id}`"
                        class="flex-1 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition"
                    >
                        View Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>