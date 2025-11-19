export type TransactionStatus = 'pending' | 'paid' | 'failed' | 'cancelled'
export type TransactionKind = 'charge' | 'payment'

export type TransactionCategory =
  | 'Tuition'
  | 'Laboratory'
  | 'Library'
  | 'Athletic'
  | 'Miscellaneous'
  | 'Payment'
  | string

export type PaymentMethod =
  | 'cash'
  | 'gcash'
  | 'bank_transfer'
  | 'credit_card'
  | 'debit_card'

export interface TransactionMeta {
  fee_name?: string
  description?: string
  assessment_id?: number
  subject_code?: string
  subject_name?: string
  units?: number
  has_lab?: boolean
  fee_code?: string
  reference_number?: string
  payment_id?: number
  [key: string]: any
}

export interface Fee {
  id: number
  name: string
  category: string
  amount: number
  code?: string
}

export interface TransactionUser {
  id: number
  name: string
  student_id: string
  email: string
}

export interface Transaction {
  id: number
  reference: string
  user?: TransactionUser
  kind: TransactionKind
  type: TransactionCategory
  year?: string
  semester?: string
  amount: number
  status: TransactionStatus
  payment_channel?: PaymentMethod
  paid_at?: string
  created_at: string
  updated_at?: string
  fee?: Fee
  meta?: TransactionMeta
}

export interface Account {
  id: number
  user_id: number
  balance: number
  created_at?: string
  updated_at?: string
}

// NEW: Added missing types
export interface Notification {
  id: number
  title: string
  message: string
  start_date: string
  end_date?: string
  target_role: string
  created_at: string
  updated_at: string
}

export interface TransactionStats {
  total_fees: number
  total_paid: number
  remaining_balance: number
  pending_charges_count: number
}

export interface Assessment {
  id: number
  user_id: number
  assessment_number: string
  year_level: string
  semester: string
  school_year: string
  tuition_fee: number
  other_fees: number
  total_assessment: number
  subjects?: AssessmentSubject[]
  fee_breakdown?: FeeBreakdownItem[]
  status: 'draft' | 'active' | 'completed' | 'cancelled'
  created_by?: number
  created_at: string
  updated_at: string
}

export interface AssessmentSubject {
  id: number
  code?: string
  name?: string
  units: number
  amount: number
}

export interface FeeBreakdownItem {
  id: number
  name?: string
  category?: string
  amount: number
}

export interface TransactionsByTerm {
  [term: string]: Transaction[]
}

export interface CurrentTerm {
  year: number
  semester: string
}

export interface TermSummary {
  total_assessment: number
  total_paid: number
  current_balance: number
  transaction_count: number
}

// Helper functions
export const isChargeTransaction = (t: Transaction): boolean => t.kind === 'charge'
export const isPaymentTransaction = (t: Transaction): boolean => t.kind === 'payment'
export const isPendingTransaction = (t: Transaction): boolean => t.status === 'pending'
export const isPaidTransaction = (t: Transaction): boolean => t.status === 'paid'

// Payment method labels
export const getPaymentMethodLabel = (method: PaymentMethod): string => {
  const labels: Record<PaymentMethod, string> = {
    cash: 'Cash',
    gcash: 'GCash',
    bank_transfer: 'Bank Transfer',
    credit_card: 'Credit Card',
    debit_card: 'Debit Card',
  }
  return labels[method] || method
}

// Status labels
export const getStatusLabel = (status: TransactionStatus): string => {
  const labels: Record<TransactionStatus, string> = {
    pending: 'Pending',
    paid: 'Paid',
    failed: 'Failed',
    cancelled: 'Cancelled',
  }
  return labels[status] || status
}
