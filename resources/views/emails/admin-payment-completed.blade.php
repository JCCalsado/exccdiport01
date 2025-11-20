<!DOCTYPE html>
<html>
<head>
    <title>Payment Completed - {{ $studentName }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #27ae60; text-align: center;">Payment Notification</h1>

        <div style="background: #d4edda; padding: 20px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #27ae60;">
            <p>A payment has been successfully completed:</p>
        </div>

        <div style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h2 style="color: #2c3e50; border-bottom: 2px solid #27ae60; padding-bottom: 10px;">Payment Details</h2>

            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; font-weight: bold; width: 150px;">Student:</td>
                    <td style="padding: 8px;">{{ $studentName }}</td>
                </tr>
                <tr style="background: #f9f9f9;">
                    <td style="padding: 8px; font-weight: bold;">Receipt Number:</td>
                    <td style="padding: 8px;">{{ $receiptNumber }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: bold;">Amount:</td>
                    <td style="padding: 8px; font-weight: bold; color: #27ae60;">₱{{ number_format($amount, 2) }}</td>
                </tr>
                <tr style="background: #f9f9f9;">
                    <td style="padding: 8px; font-weight: bold;">Payment Method:</td>
                    <td style="padding: 8px;">{{ ucfirst($paymentMethod) }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: bold;">Payment Date:</td>
                    <td style="padding: 8px;">{{ $paidAt ? $paidAt->format('F j, Y, g:i A') : 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ url('/students/' . $student->id) }}" style="background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">View Student Details</a>
        </div>
    </div>
</body>
</html>