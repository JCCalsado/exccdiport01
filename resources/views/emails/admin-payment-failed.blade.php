<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed - {{ $studentName }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #e74c3c; text-align: center;">Payment Failed Alert</h1>

        <div style="background: #fdf2f2; padding: 20px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #e74c3c;">
            <p>A payment attempt has failed for the following student:</p>
        </div>

        <div style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <h2 style="color: #e74c3c; border-bottom: 2px solid #e74c3c; padding-bottom: 10px;">Failed Payment Details</h2>

            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; font-weight: bold; width: 150px;">Student:</td>
                    <td style="padding: 8px;">{{ $studentName }}</td>
                </tr>
                <tr style="background: #f9f9f9;">
                    <td style="padding: 8px; font-weight: bold;">Reference Number:</td>
                    <td style="padding: 8px;">{{ $referenceNumber }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: bold;">Amount:</td>
                    <td style="padding: 8px; color: #e74c3c;">₱{{ number_format($amount, 2) }}</td>
                </tr>
                <tr style="background: #f9f9f9;">
                    <td style="padding: 8px; font-weight: bold;">Payment Method:</td>
                    <td style="padding: 8px;">{{ ucfirst($paymentMethod) }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; font-weight: bold;">Reason for Failure:</td>
                    <td style="padding: 8px; color: #e74c3c;">{{ $reason }}</td>
                </tr>
            </table>
        </div>

        <div style="background: #fff3cd; padding: 20px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
            <h3 style="color: #856404; margin-top: 0;">Recommended Action</h3>
            <p style="color: #856404;">You may want to contact the student to resolve any payment issues or provide alternative payment options.</p>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ url('/students/' . $student->id) }}" style="background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">Contact Student</a>
        </div>
    </div>
</body>
</html>