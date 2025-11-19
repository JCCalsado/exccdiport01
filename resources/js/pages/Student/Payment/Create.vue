<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue'
import PaymentMethodSelector from '@/components/PaymentMethodSelector.vue'
import FeeBreakdown from '@/components/FeeBreakdown.vue'
import PaymentStatus from '@/components/PaymentStatus.vue'

const props = defineProps({
    student: Object,
    outstandingFees: Array,
    paymentMethods: Array,
    recentPayments: Array,
    defaultAmount: [String, Number],
    selectedFees: Array,
})

const form = useForm({
    amount: props.defaultAmount || '',
    payment_method: '',
    description: '',
    fee_items: props.selectedFees || [],
})

const selectedFees = ref([])
const paymentMethods = ref([])
const showPaymentStatus = ref(false)
const currentPayment = ref(null)
const statusCheckInterval = ref(null)

const totalSelectedFees = computed(() => {
    return selectedFees.value.reduce((sum, fee) => sum + fee.balance, 0)
})

const gatewayFees = computed(() => {
    if (!form.payment_method || !form.amount) return 0
    const method = paymentMethods.value[form.payment_method]
    return method?.fees || 0
})

const totalAmount = computed(() => {
    return (parseFloat(form.amount) || 0) + gatewayFees.value
})

const isProcessing = ref(false)
const paymentStep = ref('select') // select, confirm, processing, success, failed

onMounted(() => {
    // Pre-select fees if provided
    if (props.selectedFees?.length > 0) {
        const feeIds = props.selectedFees.map(id => id.toString())
        selectedFees.value = props.outstandingFees.filter(fee =>
            feeIds.includes(fee.id.toString())
        )
        form.amount = totalSelectedFees.value
        form.description = 'Payment for: ' + selectedFees.value
            .map(fee => fee.fee?.name || fee.description)
            .join(', ')
    }

    // Filter available payment methods
    paymentMethods.value = Object.fromEntries(
        Object.entries(props.paymentMethods).filter(([key, method]) => method.available)
    )
})

onUnmounted(() => {
    if (statusCheckInterval.value) {
        clearInterval(statusCheckInterval.value)
    }
})

const selectFee = (fee) => {
    const index = selectedFees.value.findIndex(f => f.id === fee.id)
    if (index > -1) {
        selectedFees.value.splice(index, 1)
    } else {
        selectedFees.value.push(fee)
    }
    updateAmountAndDescription()
}

const selectAllFees = () => {
    selectedFees.value = [...props.outstandingFees]
    updateAmountAndDescription()
}

const clearSelection = () => {
    selectedFees.value = []
    form.amount = ''
    form.description = ''
}

const updateAmountAndDescription = () => {
    form.amount = totalSelectedFees.value
    if (selectedFees.value.length > 0) {
        form.description = 'Payment for: ' + selectedFees.value
            .map(fee => fee.fee?.name || fee.description)
            .join(', ')
    } else {
        form.description = ''
    }
}

const initiatePayment = async () => {
    if (!form.amount || !form.payment_method) {
        return
    }

    isProcessing.value = true
    paymentStep.value = 'processing'

    try {
        const response = await axios.post('/student/payment/initiate', {
            amount: form.amount,
            payment_method: form.payment_method,
            description: form.description || 'Online Payment',
            fee_items: selectedFees.value.map(f => f.id),
        })

        if (response.data.success) {
            currentPayment.value = response.data

            // Handle different payment methods
            if (response.data.qr_code) {
                // GCash - show QR code
                await showQRCode(response.data)
            } else if (response.data.redirect_url) {
                // PayPal/Stripe - redirect
                window.location.href = response.data.redirect_url
            } else {
                // Other - show processing status
                startStatusChecking(response.data.payment_id)
            }
        } else {
            throw new Error(response.data.message || 'Payment initiation failed')
        }
    } catch (error) {
        console.error('Payment initiation error:', error)
        paymentStep.value = 'failed'
        isProcessing.value = false
    }
}

const showQRCode = async (paymentData) => {
    showPaymentStatus.value = true
    paymentStep.value = 'confirm'

    // Start checking status for QR code payments
    startStatusChecking(paymentData.payment_id)
}

const startStatusChecking = (paymentId) => {
    let attempts = 0
    const maxAttempts = 60 // Check for 5 minutes (every 5 seconds)

    statusCheckInterval.value = setInterval(async () => {
        attempts++

        try {
            const response = await axios.get(`/student/payment/check-status/${paymentId}`)

            if (response.data.status === 'completed') {
                clearInterval(statusCheckInterval.value)
                paymentStep.value = 'success'
                isProcessing.value = false

                // Show success message and redirect to receipt
                setTimeout(() => {
                    window.location.href = `/student/payment/receipt/${paymentId}`
                }, 3000)
            } else if (response.data.status === 'failed' || response.data.status === 'cancelled') {
                clearInterval(statusCheckInterval.value)
                paymentStep.value = 'failed'
                isProcessing.value = false
            }
        } catch (error) {
            console.error('Status check error:', error)
        }

        if (attempts >= maxAttempts) {
            clearInterval(statusCheckInterval.value)
            paymentStep.value = 'timeout'
            isProcessing.value = false
        }
    }, 5000) // Check every 5 seconds
}

const cancelPayment = () => {
    if (confirm('Are you sure you want to cancel this payment?')) {
        isProcessing.value = false
        paymentStep.value = 'select'
        showPaymentStatus.value = false

        if (statusCheckInterval.value) {
            clearInterval(statusCheckInterval.value)
        }
    }
}

const retryPayment = () => {
    paymentStep.value = 'select'
    isProcessing.value = false
}

const viewReceipt = (payment) => {
    window.location.href = `/student/payment/receipt/${payment.id}`
}

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount)
}
</script>

<template>
    <Head title="Make Payment" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">Make a Payment</h1>
                    <p class="mt-2 text-gray-600">Select fees to pay or enter a custom amount</p>
                </div>

                <!-- Student Info Card -->
                <div class="bg-white shadow-sm rounded-lg mb-6 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ student.user.name }}</h3>
                            <p class="text-gray-600">Student ID: {{ student.student_id }}</p>
                            <p class="text-gray-600">Course: {{ student.course }} - Year {{ student.year_level }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">Current Balance</p>
                            <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(student.user.account?.balance || 0) }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Outstanding Fees Section -->
                    <div class="lg:col-span-2">
                        <div class="bg-white shadow-sm rounded-lg">
                            <div class="p-6 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900">Outstanding Fees</h3>
                                    <div class="space-x-2">
                                        <button
                                            @click="selectAllFees"
                                            class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition"
                                        >
                                            Select All
                                        </button>
                                        <button
                                            @click="clearSelection"
                                            class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition"
                                        >
                                            Clear
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6">
                                <div v-if="outstandingFees.length === 0" class="text-center py-8 text-gray-500">
                                    No outstanding fees found
                                </div>

                                <div v-else class="space-y-3">
                                    <div
                                        v-for="fee in outstandingFees"
                                        :key="fee.id"
                                        class="flex items-center p-4 border rounded-lg hover:bg-gray-50 transition cursor-pointer"
                                        :class="{
                                            'border-blue-500 bg-blue-50': selectedFees.some(f => f.id === fee.id),
                                            'border-gray-200': !selectedFees.some(f => f.id === fee.id)
                                        }"
                                        @click="selectFee(fee)"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="selectedFees.some(f => f.id === fee.id)"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-3"
                                            @click.stop
                                        >

                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="font-medium text-gray-900">
                                                        {{ fee.fee?.name || fee.description }}
                                                    </p>
                                                    <p class="text-sm text-gray-600">
                                                        {{ fee.fee_category?.name || 'General' }}
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="font-semibold text-gray-900">
                                                        {{ formatCurrency(fee.balance) }}
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        Original: {{ formatCurrency(fee.amount) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Form -->
                        <div class="bg-white shadow-sm rounded-lg mt-6 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Details</h3>

                            <form @submit.prevent="initiatePayment">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Payment Amount
                                        </label>
                                        <input
                                            v-model="form.amount"
                                            type="number"
                                            step="0.01"
                                            min="1"
                                            max="100000"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            :disabled="selectedFees.length > 0"
                                            required
                                        >
                                        <p class="mt-1 text-xs text-gray-500">
                                            Enter amount or select fees above
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Description
                                        </label>
                                        <input
                                            v-model="form.description"
                                            type="text"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="Payment description"
                                            required
                                        >
                                    </div>
                                </div>

                                <!-- Payment Methods -->
                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">
                                        Payment Method
                                    </label>
                                    <PaymentMethodSelector
                                        v-model="form.payment_method"
                                        :methods="paymentMethods"
                                        :amount="parseFloat(form.amount) || 0"
                                    />
                                </div>

                                <!-- Payment Summary -->
                                <div v-if="form.amount && form.payment_method" class="mt-6 p-4 bg-gray-50 rounded-lg">
                                    <h4 class="font-medium text-gray-900 mb-3">Payment Summary</h4>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Payment Amount:</span>
                                            <span class="font-medium">{{ formatCurrency(form.amount) }}</span>
                                        </div>
                                        <div v-if="gatewayFees > 0" class="flex justify-between">
                                            <span class="text-gray-600">Processing Fee:</span>
                                            <span class="font-medium">{{ formatCurrency(gatewayFees) }}</span>
                                        </div>
                                        <div class="flex justify-between pt-2 border-t border-gray-200">
                                            <span class="font-semibold text-gray-900">Total Amount:</span>
                                            <span class="font-bold text-lg text-blue-600">{{ formatCurrency(totalAmount) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="mt-6 flex justify-end space-x-3">
                                    <Link
                                        href="/student/dashboard"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition"
                                    >
                                        Cancel
                                    </Link>
                                    <button
                                        type="submit"
                                        :disabled="!form.amount || !form.payment_method || isProcessing"
                                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                                    >
                                        <span v-if="isProcessing">Processing...</span>
                                        <span v-else>Proceed to Payment</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Selected Fees Summary -->
                        <FeeBreakdown
                            v-if="selectedFees.length > 0"
                            :fees="selectedFees"
                            :total="totalSelectedFees"
                        />

                        <!-- Recent Payments -->
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Payments</h3>

                            <div v-if="recentPayments.length === 0" class="text-center py-4 text-gray-500">
                                No recent payments
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="payment in recentPayments"
                                    :key="payment.id"
                                    class="flex items-center justify-between p-3 border border-gray-200 rounded-lg"
                                >
                                    <div>
                                        <p class="font-medium text-gray-900">{{ formatCurrency(payment.amount) }}</p>
                                        <p class="text-xs text-gray-600">{{ payment.created_at }}</p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span
                                            class="px-2 py-1 text-xs rounded-full"
                                            :class="{
                                                'bg-green-100 text-green-800': payment.status === 'completed',
                                                'bg-yellow-100 text-yellow-800': payment.status === 'pending',
                                                'bg-red-100 text-red-800': payment.status === 'failed'
                                            }"
                                        >
                                            {{ payment.status }}
                                        </span>
                                        <button
                                            v-if="payment.status === 'completed'"
                                            @click="viewReceipt(payment)"
                                            class="text-blue-600 hover:text-blue-800 text-sm"
                                        >
                                            View
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <Link
                                href="/student/payment/history"
                                class="mt-4 block text-center text-blue-600 hover:text-blue-800 text-sm"
                            >
                                View Payment History →
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Payment Status Modal -->
                <PaymentStatus
                    v-if="showPaymentStatus"
                    :payment="currentPayment"
                    :step="paymentStep"
                    :is-processing="isProcessing"
                    @cancel="cancelPayment"
                    @retry="retryPayment"
                    @close="showPaymentStatus = false"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>