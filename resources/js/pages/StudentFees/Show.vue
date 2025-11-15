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
import { ArrowLeft, Plus, Download, Calendar, CreditCard, FileText, DollarSign, Clock, CheckCircle } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Payment {
    id: number;
    reference_number: string;
    amount: number;
    description: string;
    payment_method: string;
    status: string;
    paid_at: string;
    created_at: string;
}

interface Transaction {
    id: number;
    reference: string;
    type: string;
    kind: string;
    amount: number;
    status: string;
    created_at: string;
}

interface FeeBreakdown {
    category: string;
    total: number;
    items: number;
}

interface Student {
    id: number;
    student_id: string;
    name: string;
    email: string;
    birthday: string | null;
    phone: string | null;
    address: string | null;
    course: string;
    year_level: string;
    status: string;
    account: {
        balance: number;
    } | null;
}

interface Assessment {
    id: number;
    assessment_number: string;
    total_assessment: number;
    tuition_fee: number;
    other_fees: number;
    school_year: string;
    semester: string;
    status: string;
}

interface Props {
    student: Student;
    assessment: Assessment | null;
    transactions: Transaction[];
    payments: Payment[];
    feeBreakdown: FeeBreakdown[];
}

const props = defineProps<Props>();

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

const remainingBalance = computed(() => {
    return Math.abs(props.student.account?.balance ?? 0);
});

const totalPaid = computed(() => {
    return props.payments.reduce((sum, payment) => sum + parseFloat(String(payment.amount)), 0);
});

const hasOutstandingBalance = computed(() => {
    return (props.student.account?.balance ?? 0) < 0;
});

const submitPayment = () => {
    paymentForm.post(route('student-fees.payments.store', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPaymentDialog.value = false;
            paymentForm.reset();
            paymentForm.payment_date = new Date().toISOString().split('T')[0];
        },
    });
};

const formatCurrency = (amount: number | string) => {
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(numAmount || 0);
};

const formatDate = (date: string) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDateTime = (date: string) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusColor = (status: string) => {
    const statusMap: Record<string, string> = {
        'paid': 'bg-green-100 text-green-800',
        'completed': 'bg-green-100 text-green-800',
        'pending': 'bg-yellow-100 text-yellow-800',
        'failed': 'bg-red-100 text-red-800',
        'cancelled': 'bg-gray-100 text-gray-800',
    };
    return statusMap[status.toLowerCase()] || 'bg-gray-100 text-gray-800';
};

const getPaymentMethodDisplay = (method: string) => {
    const methodMap: Record<string, string> = {
        'cash': 'Cash',
        'gcash': 'GCash',
        'bank_transfer': 'Bank Transfer',
        'credit_card': 'Credit Card',
        'debit_card': 'Debit Card',
    };
    return methodMap[method] || method;
};

const exportPDF = () => {
    window.open(route('student-fees.export-pdf', props.student.id), '_blank');
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
                    <Button variant="outline" @click="exportPDF">
                        <Download class="w-4 h-4 mr-2" />
                        Export PDF
                    </Button>
                    <Dialog v-model:open="showPaymentDialog">
                        <DialogTrigger as-child>
                            <Button>
                                <Plus class="w-4 h-4 mr-2" />
                                Record Payment
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-[500px]">
                            <DialogHeader>
                                <DialogTitle>Record New Payment</DialogTitle>
                                <DialogDescription>
                                    Add a payment record for {{ student.name }}
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitPayment" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="amount">
                                        Amount <span class="text-red-500">*</span>
                                    </Label>
                                    <div class="relative">
                                        <DollarSign class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                                        <Input
                                            id="amount"
                                            v-model="paymentForm.amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            required
                                            placeholder="0.00"
                                            class="pl-10"
                                        />
                                    </div>
                                    <p v-if="paymentForm.errors.amount" class="text-sm text-red-500">
                                        {{ paymentForm.errors.amount }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Outstanding balance: {{ formatCurrency(remainingBalance) }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="payment_method">
                                        Payment Method <span class="text-red-500">*</span>
                                    </Label>
                                    <div class="relative">
                                        <CreditCard class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                                        <select
                                            id="payment_method"
                                            v-model="paymentForm.payment_method"
                                            required
                                            class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        >
                                            <option value="cash">Cash</option>
                                            <option value="gcash">GCash</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="credit_card">Credit Card</option>
                                            <option value="debit_card">Debit Card</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="payment_date">
                                        Payment Date <span class="text-red-500">*</span>
                                    </Label>
                                    <div class="relative">
                                        <Calendar class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                                        <Input
                                            id="payment_date"
                                            v-model="paymentForm.payment_date"
                                            type="date"
                                            required
                                            :max="new Date().toISOString().split('T')[0]"
                                            class="pl-10"
                                        />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description</Label>
                                    <Input
                                        id="description"
                                        v-model="paymentForm.description"
                                        placeholder="e.g., Prelim Payment, Midterm Payment"
                                    />
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="showPaymentDialog = false"
                                        :disabled="paymentForm.processing"
                                    >
                                        Cancel
                                    </Button>
                                    <Button 
                                        type="submit" 
                                        :disabled="paymentForm.processing"
                                    >
                                        {{ paymentForm.processing ? 'Recording...' : 'Record Payment' }}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- Balance Summary -->
            <div :class="[
                'rounded-lg shadow-md p-6 border-2',
                hasOutstandingBalance 
                    ? 'bg-gradient-to-r from-red-50 to-orange-50 border-red-200' 
                    : 'bg-gradient-to-r from-green-50 to-emerald-50 border-green-200'
            ]">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Current Balance</p>
                        <p :class="[
                            'text-4xl font-bold',
                            hasOutstandingBalance ? 'text-red-600' : 'text-green-600'
                        ]">
                            {{ formatCurrency(remainingBalance) }}
                        </p>
                        <p class="text-sm text-gray-600 mt-2 flex items-center gap-2">
                            <CheckCircle v-if="!hasOutstandingBalance" class="w-4 h-4" />
                            {{ hasOutstandingBalance ? 'Outstanding' : 'Fully Paid' }}
                        </p>
                    </div>
                    <div class="text-right space-y-2">
                        <div>
                            <p class="text-xs text-gray-600">Total Assessment</p>
                            <p class="text-lg font-semibold text-gray-900">
                                {{ formatCurrency(assessment?.total_assessment || 0) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600">Total Paid</p>
                            <p class="text-lg font-semibold text-green-600">
                                {{ formatCurrency(totalPaid) }}
                            </p>
                        </div>
                    </div>
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
            <Card v-if="feeBreakdown.length > 0">
                <CardHeader>
                    <CardTitle>Fee Breakdown</CardTitle>
                    <CardDescription>Current assessment details</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <div
                            v-for="breakdown in feeBreakdown"
                            :key="breakdown.category"
                            class="flex justify-between items-center p-3 border rounded-lg hover:bg-gray-50"
                        >
                            <div>
                                <p class="font-medium">{{ breakdown.category }}</p>
                                <p class="text-sm text-gray-600">{{ breakdown.items }} {{ breakdown.items === 1 ? 'item' : 'items' }}</p>
                            </div>
                            <span class="font-bold text-lg">{{ formatCurrency(breakdown.total) }}</span>
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
                                :class="hasOutstandingBalance ? 'text-red-500' : 'text-green-500'"
                            >
                                {{ formatCurrency(remainingBalance) }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Payment History -->
            <Card>
                <CardHeader>
                    <CardTitle>Payment History</CardTitle>
                    <CardDescription>All recorded payments ({{ payments.length }})</CardDescription>
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
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <Clock class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                        <p class="text-gray-500 font-medium">No payment history found</p>
                                        <p class="text-sm text-gray-400 mt-1">Payments will appear here once recorded</p>
                                    </td>
                                </tr>
                                <tr v-for="payment in payments" :key="payment.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        {{ formatDate(payment.paid_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-blue-600">
                                        {{ payment.reference_number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                            {{ getPaymentMethodDisplay(payment.payment_method) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ payment.description || 'Payment' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                        {{ formatCurrency(payment.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="payments.length > 0" class="bg-gray-50 font-semibold">
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-right text-sm">Total Paid:</td>
                                    <td class="px-6 py-4 text-right text-green-600">
                                        {{ formatCurrency(totalPaid) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Transaction History -->
            <Card>
                <CardHeader>
                    <CardTitle>Transaction History</CardTitle>
                    <CardDescription>All charges and payments ({{ transactions.length }})</CardDescription>
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
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <FileText class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                        <p class="text-gray-500 font-medium">No transactions found</p>
                                    </td>
                                </tr>
                                <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDateTime(transaction.created_at) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">{{ transaction.reference }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span 
                                            class="px-2 py-1 text-xs rounded-full font-semibold"
                                            :class="transaction.kind === 'charge' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                                        >
                                            {{ transaction.kind }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ transaction.type || 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span 
                                            class="px-2 py-1 text-xs rounded-full"
                                            :class="getStatusColor(transaction.status)"
                                        >
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