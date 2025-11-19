<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { ArrowLeft, Plus, Download } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Props {
    student: any;
    assessment: any;
    transactions: any[];
    payments: any[];
    feeBreakdown: Array<{
        category: string;
        total: number;
        items: number;
    }>;
}

const props = defineProps<Props>();

// Calculate remaining balance
const remainingBalance = computed(() => {
    return Math.abs(props.student.account?.balance || 0);
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: props.student.name },
];

const showPaymentDialog = ref(false);

const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    description: '',
    payment_date: new Date().toISOString().split('T')[0],
});

const submitPayment = () => {
    paymentForm.post(route('student-fees.payments.store', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPaymentDialog.value = false;
            paymentForm.reset();
            paymentForm.clearErrors();
        },
        onError: () => {
            // Errors will be displayed in the form
        }
    });
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="`Fee Details - ${student.name}`" />

    <AppLayout>
        <div class="space-y-6 max-w-6xl mx-auto p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('student-fees.index')">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="w-4 h-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-3xl font-bold">Student Fee Details</h1>
                        <p class="text-gray-600 mt-2">
                            {{ student.name }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('student-fees.export-pdf', student.id)" target="_blank">
                        <Button variant="outline">
                            <Download class="w-4 h-4 mr-2" />
                            Export PDF
                        </Button>
                    </Link>
                    <Dialog v-model:open="showPaymentDialog">
                        <DialogTrigger as-child>
                            <Button>
                                <Plus class="w-4 h-4 mr-2" />
                                Record Payment
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Record New Payment</DialogTitle>
                                <DialogDescription>
                                    Add a payment for {{ student.name }}
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitPayment" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="amount">Amount *</Label>
                                    <Input
                                        id="amount"
                                        v-model="paymentForm.amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                        placeholder="0.00"
                                    />
                                    <p v-if="paymentForm.errors.amount" class="text-sm text-red-500">
                                        {{ paymentForm.errors.amount }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="payment_method">Payment Method *</Label>
                                    <select
                                        id="payment_method"
                                        v-model="paymentForm.payment_method"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                        <option value="cash">Cash</option>
                                        <option value="gcash">GCash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="debit_card">Debit Card</option>
                                    </select>
                                    <p v-if="paymentForm.errors.payment_method" class="text-sm text-red-500">
                                        {{ paymentForm.errors.payment_method }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="payment_date">Payment Date *</Label>
                                    <Input
                                        id="payment_date"
                                        v-model="paymentForm.payment_date"
                                        type="date"
                                        required
                                    />
                                    <p v-if="paymentForm.errors.payment_date" class="text-sm text-red-500">
                                        {{ paymentForm.errors.payment_date }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description</Label>
                                    <Input
                                        id="description"
                                        v-model="paymentForm.description"
                                        placeholder="e.g., Prelim, Midterm, Full Payment"
                                    />
                                    <p v-if="paymentForm.errors.description" class="text-sm text-red-500">
                                        {{ paymentForm.errors.description }}
                                    </p>
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="showPaymentDialog = false"
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" :disabled="paymentForm.processing">
                                        {{ paymentForm.processing ? 'Recording...' : 'Record Payment' }}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- Student Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Personal Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <Label class="text-sm text-gray-600">Full Name</Label>
                            <p class="font-medium">{{ student.name }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Email</Label>
                            <p class="font-medium">{{ student.email }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Birthday</Label>
                            <p class="font-medium">{{ student.birthday ? formatDate(student.birthday) : 'N/A' }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Phone</Label>
                            <p class="font-medium">{{ student.phone || 'N/A' }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Student ID</Label>
                            <p class="font-medium">{{ student.student_id }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Course</Label>
                            <p class="font-medium">{{ student.course }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Year Level</Label>
                            <p class="font-medium">{{ student.year_level }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Status</Label>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                {{ student.status }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Fee Breakdown -->
            <Card>
                <CardHeader>
                    <CardTitle>Fee Breakdown</CardTitle>
                    <CardDescription>Current assessment details</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <div
                            v-for="breakdown in feeBreakdown"
                            :key="breakdown.category"
                            class="flex justify-between items-center p-3 border rounded-lg"
                        >
                            <div>
                                <p class="font-medium">{{ breakdown.category }}</p>
                                <p class="text-sm text-gray-600">{{ breakdown.items }} items</p>
                            </div>
                            <span class="font-bold">{{ formatCurrency(breakdown.total) }}</span>
                        </div>
                    </div>

                    <div class="pt-4 border-t space-y-2">
                        <div class="flex justify-between items-center text-lg">
                            <span class="font-medium">Total Assessment</span>
                            <span class="font-bold">
                                {{ formatCurrency(assessment?.total_assessment || 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-lg">
                            <span class="font-medium">Current Balance</span>
                            <span
                                class="font-bold"
                                :class="(student.account?.balance || 0) > 0 ? 'text-red-500' : 'text-green-500'"
                            >
                                {{ formatCurrency(Math.abs(student.account?.balance || 0)) }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Payment History -->
            <Card>
                <CardHeader>
                    <CardTitle>Payment History</CardTitle>
                    <CardDescription>All recorded payments</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="payments.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        No payment history found
                                    </td>
                                </tr>
                                <tr v-for="payment in payments" :key="payment.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(payment.paid_at) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">{{ payment.reference_number }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                            {{ payment.payment_method }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">{{ payment.description }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                        {{ formatCurrency(payment.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Transaction History -->
            <Card>
                <CardHeader>
                    <CardTitle>Transaction History</CardTitle>
                    <CardDescription>All charges and payments</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="transactions.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        No transactions found
                                    </td>
                                </tr>
                                <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(transaction.created_at) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">{{ transaction.reference }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span 
                                            class="px-2 py-1 text-xs rounded-full"
                                            :class="transaction.kind === 'charge' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                                        >
                                            {{ transaction.kind }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ transaction.type }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                            {{ transaction.status }}
                                        </span>
                                    </td>
                                    <td 
                                        class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                        :class="transaction.kind === 'charge' ? 'text-red-600' : 'text-green-600'"
                                    >
                                        {{ transaction.kind === 'charge' ? '+' : '-' }}{{ formatCurrency(transaction.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
