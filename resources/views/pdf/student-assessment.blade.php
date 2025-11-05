<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Student Assessment - {{ $student->student_id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
        }
        .header p {
            margin: 5px 0;
            font-size: 10px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            width: 30%;
            font-weight: bold;
            padding: 5px;
        }
        .info-value {
            display: table-cell;
            width: 70%;
            padding: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th,
        table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CAMARINES NORTE COLLEGE COMPUTER DEPT. INC.</h1>
        <p>Certificate of Matriculation / Assessment Form</p>
        <p>Assessment No: {{ $assessment->assessment_number }}</p>
    </div>

    <div class="section">
        <div class="section-title">Student Information</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Student ID:</div>
                <div class="info-value">{{ $student->student_id }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Name:</div>
                <div class="info-value">{{ $student->name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Course:</div>
                <div class="info-value">{{ $student->course }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Year Level:</div>
                <div class="info-value">{{ $student->year_level }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">School Year:</div>
                <div class="info-value">{{ $assessment->school_year }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Semester:</div>
                <div class="info-value">{{ $assessment->semester }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Fee Assessment</div>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $charges = $transactions->where('kind', 'charge');
                    $grouped = $charges->groupBy('type');
                @endphp
                
                @foreach($grouped as $category => $items)
                    @foreach($items as $item)
                        <tr>
                            <td>{{ $category }}</td>
                            <td>{{ $item->meta['description'] ?? $item->type }}</td>
                            <td class="text-right">₱{{ number_format($item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach

                <tr class="total-row">
                    <td colspan="2" class="text-right">Tuition Fee Total:</td>
                    <td class="text-right">₱{{ number_format($assessment->tuition_fee, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="2" class="text-right">Other Fees Total:</td>
                    <td class="text-right">₱{{ number_format($assessment->other_fees, 2) }}</td>
                </tr>
                <tr class="total-row" style="background-color: #e0e0e0;">
                    <td colspan="2" class="text-right">TOTAL ASSESSMENT:</td>
                    <td class="text-right">₱{{ number_format($assessment->total_assessment, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if($payments->count() > 0)
    <div class="section">
        <div class="section-title">Payment History</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Method</th>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment->paid_at->format('M d, Y') }}</td>
                    <td>{{ $payment->reference_number }}</td>
                    <td>{{ strtoupper($payment->payment_method) }}</td>
                    <td>{{ $payment->description }}</td>
                    <td class="text-right">₱{{ number_format($payment->amount, 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4" class="text-right">Total Paid:</td>
                    <td class="text-right">₱{{ number_format($payments->sum('amount'), 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <div class="section-title">Balance Summary</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Total Assessment:</div>
                <div class="info-value">₱{{ number_format($assessment->total_assessment, 2) }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Total Paid:</div>
                <div class="info-value">₱{{ number_format($payments->sum('amount'), 2) }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Current Balance:</div>
                <div class="info-value" style="font-size: 16px; font-weight: bold;">
                    ₱{{ number_format(abs($student->account->balance ?? 0), 2) }}
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Generated on {{ now()->format('F d, Y h:i A') }}</p>
        <p>This is a computer-generated document. No signature required.</p>
    </div>
</body>
</html>