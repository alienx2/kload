<?php
$dataFile = 'data.json';

// Initialize data file if it doesn't exist
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([]));
}

$message = '';
$error = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_bill'])) {
        $date = $_POST['date'] ?? '';
        $kwh_total = $_POST['kwh_total'] ?? 0;
        $cost_per_kwh = $_POST['cost_per_kwh'] ?? 0;
        $balance = $_POST['balance'] ?? 0;
        $comments = $_POST['comments'] ?? '';

        if ($date) {
            $data = json_decode(file_get_contents($dataFile), true);
            
            // Overwrite logic
            $indexedData = [];
            foreach ($data as $entry) {
                $indexedData[$entry['date']] = $entry;
            }
            
            $indexedData[$date] = [
                'date' => $date,
                'kwh_total' => (float)$kwh_total,
                'cost_per_kwh' => (float)$cost_per_kwh,
                'balance' => (float)$balance,
                'comments' => $comments
            ];

            $data = array_values($indexedData);
            usort($data, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
            
            file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
            $message = "Entry saved successfully!";
        } else {
            $error = "Date is required.";
        }
    } elseif (isset($_POST['import_json'])) {
        $jsonInput = $_POST['json_data'] ?? '';
        $decoded = json_decode($jsonInput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $sanitized = [];
            foreach ($decoded as $entry) {
                if (empty($entry['date'])) continue;
                $sanitized[] = [
                    'date' => $entry['date'],
                    'kwh_total' => (float)($entry['kwh_total'] ?? $entry['kwh_cost'] ?? 0),
                    'cost_per_kwh' => (float)($entry['cost_per_kwh'] ?? 0),
                    'balance' => (float)($entry['balance'] ?? 0),
                    'comments' => (string)($entry['comments'] ?? '')
                ];
            }
            usort($sanitized, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
            file_put_contents($dataFile, json_encode($sanitized, JSON_PRETTY_PRINT));
            $message = "Data imported successfully!";
        } else {
            $error = "Invalid JSON format.";
        }
    }
}

$bills = json_decode(file_get_contents($dataFile), true);

// Pre-calculate computed daily costs
$totalCosts = 0;
$count = count($bills);
foreach ($bills as &$bill) {
    $bill['daily_cost'] = ($bill['kwh_total'] ?? 0) * ($bill['cost_per_kwh'] ?? 0);
    $totalCosts += $bill['daily_cost'];
}
unset($bill);
$averageCost = $count > 0 ? $totalCosts / $count : 0;

// Group bills by month
$groupedBills = [];
foreach ($bills as $bill) {
    $monthKey = date('Y-m', strtotime($bill['date']));
    $monthName = date('F Y', strtotime($bill['date']));
    if (!isset($groupedBills[$monthKey])) {
        $groupedBills[$monthKey] = [
            'name' => $monthName,
            'entries' => [],
            'total_cost' => 0
        ];
    }
    $groupedBills[$monthKey]['entries'][] = $bill;
    $groupedBills[$monthKey]['total_cost'] += $bill['daily_cost'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meralco Kuryente Load Tracker</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 900px; margin: 20px auto; padding: 0 20px; line-height: 1.6; color: #333; }
        .container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border: 1px solid #ddd; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="date"], input[type="number"], textarea { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #f36f21; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #d35400; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: top; }
        th { background-color: #f8f9fa; color: #555; }
        .message { color: #27ae60; font-weight: bold; margin-bottom: 15px; }
        .error { color: #e74c3c; font-weight: bold; margin-bottom: 15px; }
        .section { margin-top: 40px; border-top: 2px solid #eee; padding-top: 20px; }
        .meralco-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .meralco-logo { color: #f36f21; font-weight: 800; font-size: 1.5rem; }
        .month-header { background-color: #f0f4f8; border-bottom: 2px solid #d1d8e0; }
        .month-header td { padding: 8px 12px; }
        .high-usage { color: #e74c3c; font-weight: bold; }
        .high-usage::after { content: " 🚩"; }
        .avg-info { background: #e9f7ef; padding: 10px; border-radius: 4px; margin-bottom: 20px; display: inline-block; border-left: 4px solid #27ae60; }
        .comments-cell { font-size: 0.9em; color: #666; font-style: italic; }
        .edit-btn { background: #3498db; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; cursor: pointer; border: none; margin-left: 10px; }
        .edit-btn:hover { background: #2980b9; }
        .form-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .reset-link { font-size: 0.8em; color: #7f8c8d; text-decoration: underline; cursor: pointer; }
    </style>
</head>
<body>
    <div class="meralco-header">
        <div class="meralco-logo">Meralco Kuryente Load</div>
        <h1>Daily Tracker</h1>
    </div>

    <?php if ($message): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <div class="avg-info">
        Current Average Daily Cost: <strong>₱<?php echo number_format($averageCost, 2); ?></strong>
    </div>

    <div class="container">
        <div class="form-header">
            <h2 id="form-title">Add / Edit Entry</h2>
            <span class="reset-link" onclick="resetForm()">Clear / New Entry</span>
        </div>
        <form method="POST" id="bill-form">
            <div class="form-group">
                <label for="date">Date:</label>
                <input type="date" id="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label for="kwh_total">kWh Consumed:</label>
                <input type="number" id="kwh_total" name="kwh_total" step="0.01" required placeholder="e.g. 4.5">
            </div>
            <div class="form-group">
                <label for="cost_per_kwh">Rate per kWh (₱):</label>
                <input type="number" id="cost_per_kwh" name="cost_per_kwh" step="0.01" required placeholder="e.g. 12.50">
            </div>
            <div class="form-group">
                <label for="balance">Remaining Balance (₱):</label>
                <input type="number" id="balance" name="balance" step="0.01" placeholder="e.g. 450.75">
            </div>
            <div class="form-group">
                <label for="comments">Comments / Notes:</label>
                <textarea id="comments" name="comments" rows="2" placeholder="e.g. Used AC all day..."></textarea>
            </div>
            <button type="submit" name="add_bill">Save Entry</button>
        </form>
    </div>

    <div class="section">
        <h2>Consumption History</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>kWh Used</th>
                    <th>Rate/kWh</th>
                    <th>Daily Cost</th>
                    <th>Balance</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groupedBills)): ?>
                    <tr><td colspan="6">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($groupedBills as $month): ?>
                        <tr class="month-header">
                            <td colspan="6">
                                <strong><?php echo htmlspecialchars($month['name']); ?></strong>
                                <span style="float: right; font-size: 0.85em; font-weight: normal;">
                                    Monthly Total Cost: ₱<?php echo number_format($month['total_cost'], 2); ?>
                                </span>
                            </td>
                        </tr>
                        <?php foreach ($month['entries'] as $bill): ?>
                            <?php $isHigh = ($bill['daily_cost'] > $averageCost); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bill['date']); ?></td>
                                <td><?php echo number_format($bill['kwh_total'] ?? 0, 2); ?> kWh</td>
                                <td>₱<?php echo number_format($bill['cost_per_kwh'] ?? 0, 2); ?></td>
                                <td class="<?php echo $isHigh ? 'high-usage' : ''; ?>">
                                    ₱<?php echo number_format($bill['daily_cost'], 2); ?>
                                </td>
                                <td>₱<?php echo number_format($bill['balance'] ?? 0, 2); ?></td>
                                <td class="comments-cell">
                                    <?php echo htmlspecialchars($bill['comments'] ?? ''); ?>
                                    <button class="edit-btn" onclick="editEntry('<?php echo $bill['date']; ?>', <?php echo $bill['kwh_total']; ?>, <?php echo $bill['cost_per_kwh']; ?>, <?php echo $bill['balance'] ?? 0; ?>, <?php echo htmlspecialchars(json_encode($bill['comments'] ?? '')); ?>)">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Import Data</h2>
        <form method="POST">
            <div class="form-group">
                <label for="json_data">Paste your backup JSON here:</label>
                <textarea id="json_data" name="json_data" rows="8" placeholder='[{"date": "2024-05-01", "kwh_total": 4.5, "cost_per_kwh": 12.5, "balance": 450.75, "comments": "Heavy usage"}]'></textarea>
            </div>





            <button type="submit" name="import_json" style="background: #34495e;">Import Backup</button>
        </form>
        <p><small>Note: This will replace all current data with the imported data.</small></p>
    </div>

    <div class="section">
        <h2>Current Data (JSON Export)</h2>
        <pre style="background: #eee; padding: 15px; border-radius: 4px; overflow-x: auto;"><?php echo htmlspecialchars(json_encode($bills, JSON_PRETTY_PRINT)); ?></pre>
    </div>

    <script>
        function editEntry(date, kwh, rate, balance, comments) {
            document.getElementById('date').value = date;
            document.getElementById('kwh_total').value = kwh;
            document.getElementById('cost_per_kwh').value = rate;
            document.getElementById('balance').value = balance;
            document.getElementById('comments').value = comments;
            
            // Highlight the form
            document.getElementById('form-title').innerText = "Editing Entry: " + date;
            document.getElementById('bill-form').scrollIntoView({ behavior: 'smooth' });
            document.getElementById('comments').focus();
        }

        function resetForm() {
            document.getElementById('bill-form').reset();
            document.getElementById('form-title').innerText = "Add / Edit Entry";
            // Restore current date as default
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date').value = today;
        }
    </script>

</body>
</html>
