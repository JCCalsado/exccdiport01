<script setup>
import { computed } from 'vue'

const props = defineProps({
    fees: Array,
    total: Number
})

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount)
}

const groupedFees = computed(() => {
    const grouped = {}

    props.fees.forEach(fee => {
        const category = fee.fee_category?.name || 'General'
        if (!grouped[category]) {
            grouped[category] = {
                name: category,
                fees: [],
                subtotal: 0
            }
        }
        grouped[category].fees.push(fee)
        grouped[category].subtotal += fee.balance
    })

    return Object.values(grouped)
})

const getCategoryIcon = (category) => {
    const icons = {
        'Tuition': '📚',
        'Laboratory': '🔬',
        'Library': '📖',
        'Athletic': '⚽',
        'Miscellaneous': '📋',
        'General': '💰'
    }
    return icons[category] || '📋'
}
</script>

<template>
    <div class="bg-white shadow-sm rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Selected Fees</h3>

        <!-- Fee Categories -->
        <div class="space-y-4">
            <div
                v-for="category in groupedFees"
                :key="category.name"
                class="border border-gray-200 rounded-lg p-4"
            >
                <div class="flex items-center mb-3">
                    <div class="text-xl mr-2">
                        {{ getCategoryIcon(category.name) }}
                    </div>
                    <h4 class="font-medium text-gray-900">{{ category.name }}</h4>
                    <div class="ml-auto text-right">
                        <p class="font-semibold">{{ formatCurrency(category.subtotal) }}</p>
                        <p class="text-xs text-gray-500">{{ category.fees.length }} fee(s)</p>
                    </div>
                </div>

                <!-- Individual fees in this category -->
                <div class="space-y-2 ml-9">
                    <div
                        v-for="fee in category.fees"
                        :key="fee.id"
                        class="flex items-center justify-between text-sm"
                    >
                        <div class="flex-1">
                            <p class="text-gray-700">{{ fee.fee?.name || fee.description }}</p>
                            <p class="text-xs text-gray-500">
                                Original: {{ formatCurrency(fee.amount) }} |
                                Paid: {{ formatCurrency(fee.amount_paid || 0) }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium text-gray-900">{{ formatCurrency(fee.balance) }}</p>
                            <p class="text-xs text-orange-600" v-if="fee.balance > 0">Outstanding</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Summary -->
        <div class="mt-6 pt-4 border-t border-gray-200">
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Selected Items:</span>
                    <span>{{ fees.length }} fees</span>
                </div>
                <div class="flex justify-between font-semibold text-lg">
                    <span>Total Payment:</span>
                    <span class="text-blue-600">{{ formatCurrency(total) }}</span>
                </div>
            </div>
        </div>

        <!-- Progress indicator -->
        <div class="mt-4">
            <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
                <span>Payment Progress</span>
                <span>{{ fees.filter(f => f.balance === 0).length }}/{{ fees.length }} completed</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div
                    class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                    :style="{ width: `${(fees.filter(f => f.balance === 0).length / fees.length) * 100}%` }"
                ></div>
            </div>
        </div>
    </div>
</template>