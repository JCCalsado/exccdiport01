<!DOCTYPE html>
<html>
<head>
    <title>Payment Receipt</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #3498db; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #2c3e50; margin: 0; }
        .header p { color: #7f8c8d; margin: 5px 0 0 0; }
        .payment-details { margin-bottom: 30px; }
        .payment-details h2 { color: #2c3e50; border-bottom: 1px solid #bdc3c7; padding-bottom: 10px; }
        .details-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .details-table td { padding: 8px; border: 1px solid #ddd; }
        .details-table td:first-child { font-weight: bold; background: #f8f9fa; width: 200px; }
        .amount { font-size: 18px; font-weight: bold; color: #27ae60; }
        .footer { margin-top: 40px; text-align: center; color: #7f8c8d; font-size: 12px; }
        .receipt-number { text-align: center; font-size: 16px; color: #e74c3c; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PAYMENT RECEIPT</h1>
        <p>{{ config('app.name') }} - Student Payment System</p>
    </div>

    <div class="receipt-number">
        Receipt #: {{ $payment->receipt_number }}
    </div>

    <div class="payment-details">
        <h2>Student Information</h2>
        <table class="details-table">
            <tr>
                <td>Student Name:</td>
                <td>{{ $payment->student->user->name }}</td>
            </tr>
            <tr>
                <td>Student ID:</td>
                <td>{{ $payment->student->student_id }}</td>
            </tr>
            <tr>
                <td>Course:</td>
                <td>{{ $payment->student->course }}</td>
            </tr>
            <tr>
                <td>Year Level:</td>
                <td>{{ $payment->student->year_level }}</td>
            </tr>
        </table>

        <h2>Payment Information</h2>
        <table class="details-table">
            <tr>
                <td>Receipt Number:</td>
                <td>{{ $payment->receipt_number }}</td>
            </tr>
            <tr>
                <td>Amount:</td>
                <td class="amount">₱{{ number_format($payment->amount, 2) }}</td>
            </tr>
            <tr>
                <td>Payment Method:</td>
                <td>{{ ucfirst($payment->payment_method) }}</td>
            </tr>
            <tr>
                <td>Description:</td>
                <td>{{ $payment->description }}</td>
            </tr>
            <tr>
                <td>Payment Date:</td>
                <td>{{ $payment->paid_at ? $payment->paid_at->format('F j, Y') : now()->format('F j, Y') }}</td>
            </tr>
            <tr>
                <td>Status:</td>
                <td style="color: #27ae60; font-weight: bold;">COMPLETED</td>
            </tr>
        </table>

        @if($payment->latestGatewayDetail)
        <h2>Gateway Information</h2>
        <table class="details-table">
            <tr>
                <td>Gateway:</td>
                <td>{{ strtoupper($payment->latestGatewayDetail->gateway) }}</td>
            </tr>
            <tr>
                <td>Transaction ID:</td>
                <td>{{ $payment->latestGatewayDetail->gateway_transaction_id }}</td>
            </tr>
            <tr>
                <td>Status:</td>
                <td>{{ $payment->latestGatewayDetail->gateway_status }}</td>
            </tr>
        </table>
        @endif
    </div>

    <div class="footer">
        <p>This is an electronically generated receipt. No signature is required.</p>
        <p>Generated on {{ now()->format('F j, Y, g:i A') }}</p>
    </div>
</body>
</html>