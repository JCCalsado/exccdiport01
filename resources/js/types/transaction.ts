export interface Transaction {
  id: number
  type: string
  kind: 'charge' | 'payment' | 'adjustment' | 'waiver'
  amount: number
  status: 'pending' | 'paid' | 'completed' | 'failed' | 'cancelled'
  reference: string
  created_at: string
  updated_at: string
  year?: number
  semester?: string
  payment_channel?: string
  description?: string
}

export interface Account {
  id: number
  user_id: number
  balance: number
  total_charges: number
  total_payments: number
  created_at: string
  updated_at: string
}

export interface Notification {
  id: number
  title: string
  message: string
  type: string
  created_at: string
  start_date?: string
  end_date?: string
  read_at?: string | null
}

export interface TransactionStats {
  total_fees: number
  total_paid: number
  remaining_balance: number
  pending_charges_count: number
}