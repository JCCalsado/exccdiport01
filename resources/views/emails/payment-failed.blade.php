<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #e74c3c; text-align: center;">Payment Failed</h1>

        <div style="background: #fdf2f2; padding: 20px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #e74c3c;">
            <p>Dear {{ $student->user->name }},</p>
            <p>We encountered an issue processing your payment. The payment has been cancelled and no charges were made to your account.</p>
        </div>

        <div style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h2 style="color: #e74c3c; border-bottom: 2px solid #e74c3c; padding-bottom: 10px;">Payment Details</h2>

            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; font-weight: bold; width: 150px;">Reference Number:</td>
                    <td style="padding: 8px;">{{ $referenceNumber }}</td>
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
                    <td style="padding: 8px; font-weight: bold;">Reason for Failure:</td>
                    <td style="padding: 8px;">{{ $reason }}</td>
                </tr>
            </table>
        </div>

        <div style="background: #fff3cd; padding: 20px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
            <h3 style="color: #856404; margin-top: 0;">What to do next?</h3>
            <ul style="color: #856404;">
                <li>Check your payment method for sufficient funds</li>
                <li>Verify your payment details are correct</li>
                <li>Try the payment again using the same or a different method</li>
                <li>Contact your bank if you suspect an issue with your card</li>
                <li>Reach out to our support team if the problem persists</li>
            </ul>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ url('/payment/create') }}" style="background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">Try Again</a>
            <p style="color: #7f8c8d; font-size: 14px; margin-top: 15px;">
                If you continue to experience issues, please contact our support team.
            </p>
        </div>
    </div>
</body>
</html>