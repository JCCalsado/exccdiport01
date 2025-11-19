<script setup>
import { computed, watch } from 'vue'

const props = defineProps({
    modelValue: String,
    methods: Object,
    amount: {
        type: Number,
        default: 0
    }
})

const emit = defineEmits(['update:modelValue'])

const selectedMethod = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount)
}

const getMethodIcon = (method) => {
    const icons = {
        gcash: '💵',
        paypal: '💳',
        stripe: '🔵'
    }
    return icons[method] || '💳'
}

const getMethodColor = (method) => {
    const colors = {
        gcash: 'bg-blue-50 border-blue-200 text-blue-900',
        paypal: 'bg-yellow-50 border-yellow-200 text-yellow-900',
        stripe: 'bg-purple-50 border-purple-200 text-purple-900'
    }
    return colors[method] || 'bg-gray-50 border-gray-200 text-gray-900'
}

const getMethodDescription = (method) => {
    const descriptions = {
        gcash: 'Pay using GCash QR code',
        paypal: 'Pay with PayPal, Credit Card, or Debit Card',
        stripe: 'Pay with Credit Card, Debit Card, or Apple Pay'
    }
    return descriptions[method] || 'Pay with this method'
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="(method, key) in methods"
            :key="key"
            class="relative flex items-center p-4 border-2 rounded-lg cursor-pointer transition-all hover:shadow-md"
            :class="[
                getMethodColor(key),
                {
                    'ring-2 ring-blue-500 ring-offset-2': selectedMethod === key,
                    'opacity-50 cursor-not-allowed': !method.available
                }
            ]"
            @click="method.available ? selectedMethod = key : null"
        >
            <!-- Radio Button -->
            <input
                type="radio"
                :value="key"
                v-model="selectedMethod"
                :disabled="!method.available"
                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 mr-3"
            >

            <!-- Method Icon and Info -->
            <div class="flex-1">
                <div class="flex items-center">
                    <div class="text-2xl mr-3">
                        {{ getMethodIcon(key) }}
                    </div>
                    <div>
                        <h4 class="font-medium">{{ method.name }}</h4>
                        <p class="text-sm opacity-75">{{ getMethodDescription(key) }}</p>
                    </div>
                </div>

                <!-- Fee Information -->
                <div v-if="amount > 0 && method.fees > 0" class="mt-2 text-sm">
                    <span class="font-medium">Processing fee:</span>
                    {{ formatCurrency(method.fees) }}
                    <span class="opacity-75">({{ formatCurrency(amount + method.fees) }} total)</span>
                </div>
            </div>

            <!-- Availability Badge -->
            <div>
                <span
                    class="px-2 py-1 text-xs rounded-full"
                    :class="{
                        'bg-green-100 text-green-800': method.available,
                        'bg-red-100 text-red-800': !method.available
                    }"
                >
                    {{ method.available ? 'Available' : 'Unavailable' }}
                </span>
            </div>
        </div>

        <!-- No available methods message -->
        <div v-if="Object.keys(methods).length === 0 || !Object.values(methods).some(m => m.available)"
             class="p-6 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center">
                <div class="text-red-600 mr-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="font-medium text-red-900">No Payment Methods Available</h4>
                    <p class="text-sm text-red-700 mt-1">
                        Payment methods are currently unavailable. Please try again later or contact support.
                    </p>
                </div>
            </div>
        </div>

        <!-- Selected method details -->
        <div v-if="selectedMethod && methods[selectedMethod]"
             class="p-4 bg-gray-50 rounded-lg border">
            <div class="flex items-start">
                <div class="text-xl mr-3">
                    {{ getMethodIcon(selectedMethod) }}
                </div>
                <div class="flex-1">
                    <h5 class="font-medium text-gray-900">
                        Paying with {{ methods[selectedMethod].name }}
                    </h5>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ getMethodDescription(selectedMethod) }}
                    </p>

                    <!-- Method-specific details -->
                    <div v-if="selectedMethod === 'gcash'" class="mt-3 p-3 bg-blue-50 rounded border border-blue-200">
                        <p class="text-sm text-blue-800">
                            <strong>How to pay with GCash:</strong>
                        </p>
                        <ol class="text-sm text-blue-700 mt-1 space-y-1 list-decimal list-inside">
                            <li>Click "Proceed to Payment" to generate a QR code</li>
                            <li>Open your GCash app and scan the QR code</li>
                            <li>Confirm payment details and complete payment</li>
                            <li>Wait for payment confirmation (usually within minutes)</li>
                        </ol>
                    </div>

                    <div v-else-if="selectedMethod === 'paypal'" class="mt-3 p-3 bg-yellow-50 rounded border border-yellow-200">
                        <p class="text-sm text-yellow-800">
                            <strong>How to pay with PayPal:</strong>
                        </p>
                        <ol class="text-sm text-yellow-700 mt-1 space-y-1 list-decimal list-inside">
                            <li>Click "Proceed to Payment" to be redirected to PayPal</li>
                            <li>Log in to your PayPal account or pay as a guest</li>
                            <li>Confirm payment details and complete payment</li>
                            <li>You'll be redirected back after payment</li>
                        </ol>
                    </div>

                    <div v-else-if="selectedMethod === 'stripe'" class="mt-3 p-3 bg-purple-50 rounded border border-purple-200">
                        <p class="text-sm text-purple-800">
                            <strong>How to pay with Stripe:</strong>
                        </p>
                        <ol class="text-sm text-purple-700 mt-1 space-y-1 list-decimal list-inside">
                            <li>Click "Proceed to Payment" to open secure checkout</li>
                            <li>Enter your card details or use Apple Pay/Google Pay</li>
                            <li>Confirm payment details and complete payment</li>
                            <li>Payment will be processed securely and instantly</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>