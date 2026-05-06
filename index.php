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
        $units = $_POST['units'] ?? 0;
        $cost = $_POST['cost'] ?? 0;
        $kwh_cost = $_POST['kwh_cost'] ?? 0;
        $balance = $_POST['balance'] ?? 0;

        if ($date && $units !== '') {
            $data = json_decode(file_get_contents($dataFile), true);
            $data[] = [
                'date' => $date,
                'units' => (float)$units,
                'cost' => (float)$cost,
                'kwh_cost' => (float)$kwh_cost,
                'balance' => (float)$balance
            ];
            // Sort by date descending
            usort($data, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });
            file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
            $message = "Bill added successfully!";
        } else {
            $error = "Date and units are required.";
        }
    } elseif (isset($_POST['import_json'])) {
        $jsonInput = $_POST['json_data'] ?? '';
        $decoded = json_decode($jsonInput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            file_put_contents($dataFile, json_encode($decoded, JSON_PRETTY_PRINT));
            $message = "Data imported successfully!";
        } else {
            $error = "Invalid JSON format.";
        }
    }
}

$bills = json_decode(file_get_contents($dataFile), true);

// Group bills by month
$groupedBills = [];
foreach ($bills as $bill) {
    $monthKey = date('Y-m', strtotime($bill['date']));
    $monthName = date('F Y', strtotime($bill['date']));
    if (!isset($groupedBills[$monthKey])) {
        $groupedBills[$monthKey] = [
            'name' => $monthName,
            'entries' => [],
            'total_units' => 0,
            'total_cost' => 0
        ];
    }
    $groupedBills[$monthKey]['entries'][] = $bill;
    $groupedBills[$monthKey]['total_units'] += $bill['units'];
    $groupedBills[$monthKey]['total_cost'] += $bill['cost'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meralco Kuryente Load Tracker</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 800px; margin: 20px auto; padding: 0 20px; line-height: 1.6; color: #333; }
        .container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border: 1px solid #ddd; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="date"], input[type="number"], textarea { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #f36f21; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #d35400; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f8f9fa; color: #555; }
        .message { color: #27ae60; font-weight: bold; margin-bottom: 15px; }
        .error { color: #e74c3c; font-weight: bold; margin-bottom: 15px; }
        .section { margin-top: 40px; border-top: 2px solid #eee; padding-top: 20px; }
        .meralco-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .meralco-logo { color: #f36f21; font-weight: 800; font-size: 1.5rem; }
        .month-header { background-color: #f0f4f8; border-bottom: 2px solid #d1d8e0; }
        .month-header td { padding: 8px 12px; }
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

    <div class="container">
        <h2>Add Daily Usage / Load</h2>
        <form method="POST">
            <div class="form-group">
                <label for="date">Date:</label>
                <input type="date" id="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label for="units">Units Consumed (kWh):</label>
                <input type="number" id="units" name="units" step="0.01" required placeholder="e.g. 5.5">
            </div>
            <div class="form-group">
                <label for="kwh_cost">Cost per kWh (₱):</label>
                <input type="number" id="kwh_cost" name="kwh_cost" step="0.01" placeholder="e.g. 12.50">
            </div>
            <div class="form-group">
                <label for="cost">Amount Spent / Load Top-up (₱):</label>
                <input type="number" id="cost" name="cost" step="0.01" placeholder="e.g. 100.00">
            </div>
            <div class="form-group">
                <label for="balance">Remaining Balance (₱):</label>
                <input type="number" id="balance" name="balance" step="0.01" placeholder="e.g. 450.75">
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
                    <th>Consumption</th>
                    <th>Cost/kWh</th>
                    <th>Top-up</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groupedBills)): ?>
                    <tr><td colspan="5">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($groupedBills as $month): ?>
                        <tr class="month-header">
                            <td colspan="5">
                                <strong><?php echo htmlspecialchars($month['name']); ?></strong>
                                <span style="float: right; font-size: 0.85em; font-weight: normal;">
                                    Total: <?php echo number_format($month['total_units'], 2); ?> kWh | 
                                    Top-ups: ₱<?php echo number_format($month['total_cost'], 2); ?>
                                </span>
                            </td>
                        </tr>
                        <?php foreach ($month['entries'] as $bill): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bill['date']); ?></td>
                                <td><?php echo htmlspecialchars($bill['units']); ?> kWh</td>
                                <td>₱<?php echo number_format($bill['kwh_cost'] ?? 0, 2); ?></td>
                                <td>₱<?php echo number_format($bill['cost'] ?? 0, 2); ?></td>
                                <td>₱<?php echo number_format($bill['balance'] ?? 0, 2); ?></td>
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
                <textarea id="json_data" name="json_data" rows="8" placeholder='[{"date": "2024-05-01", "units": 5.2, "kwh_cost": 12.5, "cost": 0, "balance": 450.75}]'></textarea>
            </div>

            <button type="submit" name="import_json" style="background: #34495e;">Import Backup</button>
        </form>
        <p><small>Note: This will replace all current data with the imported data.</small></p>
    </div>

    <div class="section">
        <h2>Current Data (JSON Export)</h2>
        <pre style="background: #eee; padding: 15px; border-radius: 4px; overflow-x: auto;"><?php echo htmlspecialchars(json_encode($bills, JSON_PRETTY_PRINT)); ?></pre>
    </div>


</body>
</html>
