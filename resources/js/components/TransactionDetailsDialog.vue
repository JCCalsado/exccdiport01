<script setup lang="ts">

import { computed } from 'vue'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Badge } from '@/components/ui/badge'
import { useFormatters } from '@/composables/useFormatters'
import type { Transaction } from '@/types/transaction'
import { CheckCircle, XCircle, Clock, AlertCircle, Download, CreditCard } from 'lucide-vue-next'

interface Props {
  open: boolean
  transaction: Transaction | null
  showStudentInfo?: boolean // For admin/accounting views
  showPayNowButton?: boolean // For pending charges
  showDownloadButton?: boolean // For paid transactions
}

const props = withDefaults(defineProps<Props>(), {
  showStudentInfo: false,
  showPayNowButton: false,
  showDownloadButton: false,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  'pay-now': [transaction: Transaction]
  'download': [transaction: Transaction]
}>()

const { formatCurrency, formatDate } = useFormatters()

const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value),
})

// Status badge configuration
const statusConfig = computed(() => {
  if (!props.transaction) return null
  
  const configs = {
    paid: { icon: CheckCircle, class: 'bg-green-100 text-green-800', label: 'Paid' },
    pending: { icon: Clock, class: 'bg-yellow-100 text-yellow-800', label: 'Pending' },
    failed: { icon: XCircle, class: 'bg-red-100 text-red-800', label: 'Failed' },
    cancelled: { icon: AlertCircle, class: 'bg-gray-100 text-gray-800', label: 'Cancelled' },
  }
  
  return configs[props.transaction.status as keyof typeof configs]
})

// Kind badge configuration
const kindConfig = computed(() => {
  if (!props.transaction) return null
  
  return props.transaction.kind === 'charge'
    ? { class: 'bg-red-100 text-red-800', label: 'Charge' }
    : { class: 'bg-green-100 text-green-800', label: 'Payment' }
})

// Amount color based on kind
const amountColor = computed(() => {
  if (!props.transaction) return 'text-gray-900'
  return props.transaction.kind === 'charge' ? 'text-red-600' : 'text-green-600'
})

const handlePayNow = () => {
  if (props.transaction) {
    emit('pay-now', props.transaction)
  }
}

const handleDownload = () => {
  if (props.transaction) {
    emit('download', props.transaction)
  }
}
</script>

<template>
  <Dialog v-model:open="isOpen">
    <DialogContent class="max-w-2xl max-h-[80vh] overflow-y-auto">
      <DialogHeader>
        <DialogTitle>Transaction Details</DialogTitle>
        <DialogDescription>
          Complete information about this transaction
        </DialogDescription>
      </DialogHeader>

      <div v-if="transaction" class="space-y-6">
        <!-- Basic Information -->
        <div class="space-y-4">
          <h3 class="font-semibold text-lg border-b pb-2">Basic Information</h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Reference Number -->
            <div class="space-y-1">
              <p class="text-sm text-gray-600">Reference Number</p>
              <p class="font-mono font-medium">{{ transaction.reference }}</p>
            </div>

            <!-- Date -->
            <div class="space-y-1">
              <p class="text-sm text-gray-600">Date</p>
              <p class="font-medium">{{ formatDate(transaction.created_at) }}</p>
            </div>

            <!-- Transaction Type -->
            <div class="space-y-1">
              <p class="text-sm text-gray-600">Transaction Type</p>
              <Badge v-if="kindConfig" :class="kindConfig.class">
                {{ kindConfig.label }}
              </Badge>
            </div>

            <!-- Status -->
            <div class="space-y-1">
              <p class="text-sm text-gray-600">Status</p>
              <Badge v-if="statusConfig" :class="statusConfig.class">
                <component :is="statusConfig.icon" class="w-3 h-3 mr-1" />
                {{ statusConfig.label }}
              </Badge>
            </div>

            <!-- Category -->
            <div class="space-y-1">
              <p class="text-sm text-gray-600">Category</p>
              <p class="font-medium">{{ transaction.type }}</p>
            </div>

            <!-- Amount -->
            <div class="space-y-1">
              <p class="text-sm text-gray-600">Amount</p>
              <p class="text-xl font-bold" :class="amountColor">
                {{ transaction.kind === 'charge' ? '+' : '-' }}{{ formatCurrency(transaction.amount) }}
              </p>
            </div>
          </div>
        </div>

        <Separator />

        <!-- Student Information (for staff) -->
        <div v-if="showStudentInfo && transaction.user" class="space-y-4">
          <h3 class="font-semibold text-lg border-b pb-2">Student Information</h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
              <p class="text-sm text-gray-600">Student Name</p>
              <p class="font-medium">{{ transaction.user.name }}</p>
            </div>
            <div class="space-y-1">
              <p class="text-sm text-gray-600">Student ID</p>
              <p class="font-medium">{{ transaction.user.student_id }}</p>
            </div>
            <div class="space-y-1">
              <p class="text-sm text-gray-600">Email</p>
              <p class="font-medium">{{ transaction.user.email }}</p>
            </div>
          </div>
        </div>

        <!-- Payment Information (if payment) -->
        <div v-if="transaction.kind === 'payment'" class="space-y-4">
          <Separator />
          <h3 class="font-semibold text-lg border-b pb-2 flex items-center gap-2">
            <CreditCard class="w-5 h-5" />
            Payment Information
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
              <p class="text-sm text-gray-600">Payment Method</p>
              <p class="font-medium capitalize">
                {{ transaction.payment_channel || 'N/A' }}
              </p>
            </div>
            <div class="space-y-1">
              <p class="text-sm text-gray-600">Payment Date</p>
              <p class="font-medium">
                {{ transaction.paid_at ? formatDate(transaction.paid_at) : 'N/A' }}
              </p>
            </div>
            <div v-if="transaction.meta?.description" class="col-span-2 space-y-1">
              <p class="text-sm text-gray-600">Description</p>
              <p class="font-medium">{{ transaction.meta.description }}</p>
            </div>
          </div>
        </div>

        <!-- Charge Information (if charge) -->
        <div v-if="transaction.kind === 'charge'" class="space-y-4">
          <Separator />
          <h3 class="font-semibold text-lg border-b pb-2">Charge Details</h3>
          
          <div class="bg-gray-50 rounded-lg p-4 space-y-3">
            <!-- Fee/Subject Name -->
            <div class="flex justify-between items-center">
              <span class="text-gray-700 font-medium">
                {{ transaction.meta?.fee_name || transaction.meta?.subject_name || transaction.type }}
              </span>
              <span class="font-semibold text-lg">{{ formatCurrency(transaction.amount) }}</span>
            </div>

            <!-- Subject Code (if applicable) -->
            <div v-if="transaction.meta?.subject_code" class="text-sm text-gray-600">
              <span class="font-medium">Subject Code:</span> {{ transaction.meta.subject_code }}
            </div>

            <!-- Assessment ID (if applicable) -->
            <div v-if="transaction.meta?.assessment_id" class="text-sm text-gray-600">
              <span class="font-medium">Assessment Reference:</span> #{{ transaction.meta.assessment_id }}
            </div>

            <!-- Academic Information -->
            <div v-if="transaction.year && transaction.semester" class="pt-2 border-t text-sm text-gray-600">
              <p><span class="font-medium">Academic Year:</span> {{ transaction.year }}</p>
              <p><span class="font-medium">Semester:</span> {{ transaction.semester }}</p>
            </div>

            <!-- Fee Category -->
            <div v-if="transaction.fee?.category" class="text-sm text-gray-600">
              <span class="font-medium">Fee Category:</span> {{ transaction.fee.category }}
            </div>
          </div>
        </div>

        <Separator />

        <!-- Action Buttons -->
        <div class="flex justify-end gap-3 pt-4">
          <Button variant="outline" @click="isOpen = false">
            Close
          </Button>
          
          <Button 
            v-if="showDownloadButton && transaction.status === 'paid'"
            variant="secondary"
            @click="handleDownload"
          >
            <Download class="w-4 h-4 mr-2" />
            Download Receipt
          </Button>
          
          <Button 
            v-if="showPayNowButton && transaction.status === 'pending' && transaction.kind === 'charge'"
            variant="destructive"
            @click="handlePayNow"
          >
            Pay Now
          </Button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
