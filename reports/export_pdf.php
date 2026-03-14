<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once __DIR__ . "/report_helpers.php";

$user_id = (int) $_SESSION["user_id"];
$filters = report_parse_filters($_GET);

[$union_sql, $union_types, $union_params] = report_build_union_query($filters, $user_id, true);

$transactions = [];
$total_income = 0.0;
$total_expense = 0.0;

if ($union_sql !== "") {
    $stmt = mysqli_prepare($conn, $union_sql);
    mysqli_stmt_bind_param($stmt, $union_types, ...$union_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $transactions[] = $row;
        if ($row["type"] === "Income") {
            $total_income += (float) $row["amount"];
        } else {
            $total_expense += (float) $row["amount"];
        }
    }
    mysqli_stmt_close($stmt);
}

$balance = $total_income - $total_expense;

$title = ucfirst($filters["report_type"]) . " Report";
$date_label = $filters["start_date"] . " to " . $filters["end_date"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title . " " . $date_label); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #222; }
        h1 { margin: 0 0 6px; font-size: 24px; }
        .muted { color: #666; margin-bottom: 18px; }
        .summary { display: flex; gap: 12px; margin-bottom: 18px; }
        .card { border: 1px solid #ddd; border-radius: 6px; padding: 12px; flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; font-size: 12px; text-align: left; }
        th { background: #f3f3f3; }
        .right { text-align: right; }
        .section-title { margin-top: 22px; font-size: 16px; }
        @media print {
            .no-print { display: none; }
            body { margin: 10mm; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print / Save as PDF</button>
    <h1><?php echo htmlspecialchars($title); ?></h1>
    <div class="muted">Date Range: <?php echo htmlspecialchars($date_label); ?></div>

    <div class="summary">
        <div class="card"><strong>Total Income:</strong><br>Rs <?php echo number_format($total_income, 2); ?></div>
        <div class="card"><strong>Total Expenses:</strong><br>Rs <?php echo number_format($total_expense, 2); ?></div>
        <div class="card"><strong>Net Balance:</strong><br>Rs <?php echo number_format($balance, 2); ?></div>
    </div>

    <h2 class="section-title">Transactions</h2>
    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Date</th>
                <th>Category</th>
                <th>Notes</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="6">No transactions found for this report.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($transactions as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row["type"]); ?></td>
                        <td><?php echo htmlspecialchars($row["txn_date"]); ?></td>
                        <td><?php echo htmlspecialchars($row["category"]); ?></td>
                        <td><?php echo htmlspecialchars($row["notes"] ?: "-"); ?></td>
                        <td class="right">Rs <?php echo number_format((float) $row["amount"], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
