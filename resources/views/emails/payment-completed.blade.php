<!DOCTYPE html>
<html>
<head>
    <title>Payment Completed</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50; text-align: center;">Payment Confirmation</h1>

        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <p>Dear {{ $student->user->name }},</p>
            <p>We're pleased to confirm that your payment has been successfully processed.</p>
        </div>

        <div style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h2 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">Payment Details</h2>

            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; font-weight: bold; width: 150px;">Receipt Number:</td>
                    <td style="padding: 8px;">{{ $receiptNumber }}</td>
                </tr>
                <tr style="background: #f9f9f9;">
                    <td style="padding: 8px; font-weight: bold;">Amount:</td>
                    <td style="padding: 8px;">₱{{ number_format($amount, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: bold;">Payment Method:</td>
                    <td style="padding: 8px;">{{ ucfirst($paymentMethod) }}</td>
                </tr>
                <tr style="background: #f9f9f9;">
                    <td style="padding: 8px; font-weight: bold;">Payment Date:</td>
                    <td style="padding: 8px;">{{ $paidAt ? $paidAt->format('F j, Y, g:i A') : 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <p style="color: #7f8c8d; font-size: 14px;">Thank you for your payment!</p>
            <p style="color: #7f8c8d; font-size: 14px;">
                If you have any questions, please contact our support team.
            </p>
        </div>
    </div>
</body>
</html>