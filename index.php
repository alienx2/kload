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

        if ($date && $units !== '') {
            $data = json_decode(file_get_contents($dataFile), true);
            $data[] = [
                'date' => $date,
                'units' => (float)$units,
                'cost' => (float)$cost
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Electricity Daily Bill Tracker</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 20px auto; padding: 0 20px; line-height: 1.6; }
        .container { background: #f4f4f4; padding: 20px; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="date"], input[type="number"], textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        button { background: #007bff; color: #fff; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .message { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .section { margin-top: 30px; border-top: 1px solid #ccc; padding-top: 20px; }
    </style>
</head>
<body>
    <h1>Electricity Daily Bill Tracker</h1>

    <?php if ($message): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <div class="container">
        <h2>Add Daily Bill</h2>
        <form method="POST">
            <div class="form-group">
                <label for="date">Date:</label>
                <input type="date" id="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label for="units">Units (kWh):</label>
                <input type="number" id="units" name="units" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="cost">Cost ($):</label>
                <input type="number" id="cost" name="cost" step="0.01">
            </div>
            <button type="submit" name="add_bill">Add Bill</button>
        </form>
    </div>

    <div class="section">
        <h2>History</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Units (kWh)</th>
                    <th>Cost ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bills)): ?>
                    <tr><td colspan="3">No data recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($bills as $bill): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($bill['date']); ?></td>
                            <td><?php echo htmlspecialchars($bill['units']); ?></td>
                            <td><?php echo htmlspecialchars($bill['cost'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Import JSON Data</h2>
        <form method="POST">
            <div class="form-group">
                <label for="json_data">Paste JSON here (must be an array of objects):</label>
                <textarea id="json_data" name="json_data" rows="10" placeholder='[{"date": "2024-05-01", "units": 10, "cost": 5}]'></textarea>
            </div>
            <button type="submit" name="import_json">Import Data</button>
        </form>
        <p><small>Warning: Importing will overwrite existing data.</small></p>
    </div>

    <div class="section">
        <h2>Export Current Data</h2>
        <pre><?php echo htmlspecialchars(json_encode($bills, JSON_PRETTY_PRINT)); ?></pre>
    </div>

</body>
</html>
