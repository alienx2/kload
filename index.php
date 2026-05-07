<?php
// Determine selected year for viewing
$viewYear = $_GET['year'] ?? date('Y');
$dataFile = $viewYear . '.json';

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
            $yearOfEntry = date('Y', strtotime($date));
            $targetFile = $yearOfEntry . '.json';
            
            if (!file_exists($targetFile)) {
                file_put_contents($targetFile, json_encode([]));
            }

            $data = json_decode(file_get_contents($targetFile), true);
            
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
            
            file_put_contents($targetFile, json_encode($data, JSON_PRETTY_PRINT));
            
            if ($yearOfEntry != $viewYear) {
                header("Location: ?year=$yearOfEntry&msg=Entry saved to $yearOfEntry records.");
                exit;
            }
            $message = "Entry saved successfully!";
        } else {
            $error = "Date is required.";
        }
    } elseif (isset($_POST['import_json'])) {
        $jsonInput = $_POST['json_data'] ?? '';
        $decoded = json_decode($jsonInput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Group incoming data by year
            $incomingByYear = [];
            foreach ($decoded as $entry) {
                if (empty($entry['date'])) continue;
                $year = date('Y', strtotime($entry['date']));
                $incomingByYear[$year][] = [
                    'date' => $entry['date'],
                    'kwh_total' => (float)($entry['kwh_total'] ?? $entry['kwh_cost'] ?? $entry['kwh_left'] ?? 0),
                    'cost_per_kwh' => (float)($entry['cost_per_kwh'] ?? 0),
                    'balance' => (float)($entry['balance'] ?? 0),
                    'comments' => (string)($entry['comments'] ?? '')
                ];
            }
            
            // Merge into existing files
            foreach ($incomingByYear as $year => $incomingItems) {
                $targetFile = $year . '.json';
                $existingData = [];
                if (file_exists($targetFile)) {
                    $existingData = json_decode(file_get_contents($targetFile), true) ?: [];
                }
                
                // Index existing data by date for merging
                $mergedData = [];
                foreach ($existingData as $item) {
                    $mergedData[$item['date']] = $item;
                }
                
                // Add/Overwrite with incoming items
                foreach ($incomingItems as $item) {
                    $mergedData[$item['date']] = $item;
                }
                
                // Convert back to indexed array and sort
                $finalData = array_values($mergedData);
                usort($finalData, function($a, $b) {
                    return strcmp($b['date'], $a['date']);
                });
                
                file_put_contents($targetFile, json_encode($finalData, JSON_PRETTY_PRINT));
            }
            $message = "Data merged successfully!";
        } else {
            $error = "Invalid JSON format.";
        }
    }
}

if (isset($_GET['msg'])) $message = $_GET['msg'];

$bills = json_decode(file_get_contents($dataFile), true) ?: [];

// Get list of available years (json files)
$availableYears = [];
foreach (glob("*.json") as $filename) {
    $y = str_replace('.json', '', basename($filename));
    if (is_numeric($y)) {
        $availableYears[] = $y;
    }
}
rsort($availableYears);

// Get available months in the selected year
$availableMonths = [];
foreach ($bills as $bill) {
    $m = date('m', strtotime($bill['date']));
    $mName = date('F', strtotime($bill['date']));
    $availableMonths[$m] = $mName;
}
krsort($availableMonths);

// Determine selected month for viewing (default to latest month with data or current month)
$viewMonth = $_GET['month'] ?? (count($availableMonths) > 0 ? array_key_first($availableMonths) : date('m'));

// Pre-calculate computed daily costs based on balance difference for ALL bills in the year
// to ensure consistency even when paginated.
for ($i = 0; $i < count($bills); $i++) {
    $currentBalance = $bills[$i]['balance'] ?? 0;
    $nextDayBalance = (isset($bills[$i-1])) ? ($bills[$i-1]['balance'] ?? 0) : null;
    
    if ($nextDayBalance !== null) {
        $bills[$i]['daily_cost'] = $currentBalance - $nextDayBalance;
    } else {
        $bills[$i]['daily_cost'] = null;
    }
}

// Filter bills for the selected month
$filteredBills = [];
$totalCosts = 0;
$validCostCount = 0;
$monthlyTotal = 0;

foreach ($bills as $bill) {
    if (date('m', strtotime($bill['date'])) == $viewMonth) {
        $filteredBills[] = $bill;
        if ($bill['daily_cost'] !== null && $bill['daily_cost'] >= 0) {
            $monthlyTotal += $bill['daily_cost'];
            $totalCosts += $bill['daily_cost'];
            $validCostCount++;
        }
    }
}

$averageCost = $validCostCount > 0 ? $totalCosts / $validCostCount : 0;
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
        .high-usage { color: #e74c3c; font-weight: bold; }
        .high-usage::after { content: " 🚩"; }
        .avg-info { background: #e9f7ef; padding: 10px; border-radius: 4px; display: inline-block; border-left: 4px solid #27ae60; }
        .comments-cell { font-size: 0.9em; color: #666; font-style: italic; }
        .edit-btn { background: #3498db; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; cursor: pointer; border: none; margin-left: 10px; }
        .edit-btn:hover { background: #2980b9; }
        .form-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .reset-link { font-size: 0.8em; color: #7f8c8d; text-decoration: underline; cursor: pointer; }
        .nav-pills { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px; }
        .nav-pill { text-decoration: none; padding: 5px 12px; border-radius: 20px; background: #eee; color: #333; font-weight: bold; font-size: 0.85em; }
        .nav-pill.active { background: #f36f21; color: #fff; }
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

    <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px;">
        <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
            <div class="avg-info">
                Average Daily Cost: <strong>₱<?php echo number_format($averageCost, 2); ?></strong>
            </div>
            <div style="font-weight: bold;">
                Monthly Total: ₱<?php echo number_format($monthlyTotal, 2); ?>
            </div>
        </div>

        <div class="container" style="padding: 15px 20px;">
            <div style="margin-bottom: 10px;">
                <label>Year:</label>
                <div class="nav-pills">
                    <?php foreach ($availableYears as $year): ?>
                        <a href="?year=<?php echo $year; ?>" class="nav-pill <?php echo ($year == $viewYear) ? 'active' : ''; ?>">
                            <?php echo $year; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if (!empty($availableMonths)): ?>
            <div>
                <label>Month:</label>
                <div class="nav-pills">
                    <?php foreach ($availableMonths as $mCode => $mName): ?>
                        <a href="?year=<?php echo $viewYear; ?>&month=<?php echo $mCode; ?>" class="nav-pill <?php echo ($mCode == $viewMonth) ? 'active' : ''; ?>">
                            <?php echo $mName; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
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
                <label for="kwh_total">kWh Available Left:</label>
                <input type="number" id="kwh_total" name="kwh_total" step="0.01" required placeholder="e.g. 824.55">
            </div>
            <div class="form-group">
                <label for="cost_per_kwh">Rate per kWh (₱):</label>
                <input type="number" id="cost_per_kwh" name="cost_per_kwh" step="0.01" required placeholder="e.g. 14.17">
            </div>
            <div class="form-group">
                <label for="balance">Remaining Balance (₱):</label>
                <input type="number" id="balance" name="balance" step="0.01" placeholder="e.g. 11679.82">
            </div>
            <div class="form-group">
                <label for="comments">Comments / Notes:</label>
                <textarea id="comments" name="comments" rows="2" placeholder="e.g. Used AC all day..."></textarea>
            </div>
            <button type="submit" name="add_bill">Save Entry</button>
        </form>
    </div>

    <div class="section">
        <h2>Consumption History (<?php echo ($availableMonths[$viewMonth] ?? date('F')) . ' ' . $viewYear; ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>kWh Left</th>
                    <th>Rate/kWh</th>
                    <th>Balance</th>
                    <th>Daily Cost</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($filteredBills)): ?>
                    <tr><td colspan="6">No records found for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($filteredBills as $bill): ?>
                        <?php $isHigh = ($bill['daily_cost'] !== null && $bill['daily_cost'] > 300); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($bill['date']); ?></td>
                            <td><?php echo number_format($bill['kwh_total'] ?? 0, 2); ?> kWh</td>
                            <td>₱<?php echo number_format($bill['cost_per_kwh'] ?? 0, 2); ?></td>
                            <td>₱<?php echo number_format($bill['balance'] ?? 0, 2); ?></td>
                            <td class="<?php echo $isHigh ? 'high-usage' : ''; ?>">
                                <?php if ($bill['daily_cost'] === null): ?>
                                    <span style="color: #999; font-style: italic;">Wait for next day...</span>
                                <?php else: ?>
                                    ₱<?php echo number_format($bill['daily_cost'], 2); ?>
                                <?php endif; ?>
                            </td>
                            <td class="comments-cell">
                                <?php echo htmlspecialchars($bill['comments'] ?? ''); ?>
                                <button class="edit-btn" onclick="editEntry('<?php echo $bill['date']; ?>', <?php echo $bill['kwh_total'] ?? 0; ?>, <?php echo $bill['cost_per_kwh'] ?? 0; ?>, <?php echo $bill['balance'] ?? 0; ?>, <?php echo htmlspecialchars(json_encode($bill['comments'] ?? '')); ?>)">Edit</button>
                            </td>
                        </tr>
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
                <textarea id="json_data" name="json_data" rows="8" placeholder='[{"date": "2024-05-01", "kwh_total": 824.55, "cost_per_kwh": 14.17, "balance": 11679.82, "comments": "Normal usage"}]'></textarea>
            </div>
            <button type="submit" name="import_json" style="background: #34495e;">Import Backup</button>
        </form>
        <p><small>Note: This will merge new data with your current records. Matching dates will be updated.</small></p>
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
