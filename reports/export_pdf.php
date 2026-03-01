<?php
require_once "../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$month = date("Y-m");
if (isset($_GET["month"]) && preg_match("/^\d{4}-\d{2}$/", $_GET["month"])) {
    $month = $_GET["month"];
}

$start = $month . "-01";
$end = date("Y-m-t", strtotime($start));

$stmt = mysqli_prepare(
    $conn,
    "(SELECT 'Income' AS type, income_date AS txn_date, source, category, notes, amount
      FROM income
      WHERE user_id = ? AND income_date BETWEEN ? AND ?)
     UNION ALL
     (SELECT 'Expense' AS type, expense_date AS txn_date, '' AS source, category, notes, amount
      FROM expenses
      WHERE user_id = ? AND expense_date BETWEEN ? AND ?)
     ORDER BY txn_date DESC, type ASC"
);
mysqli_stmt_bind_param($stmt, "ississ", $user_id, $start, $end, $user_id, $start, $end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$total_income = 0.0;
$total_expense = 0.0;
$rows = [];

while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
    if ($row["type"] === "Income") {
        $total_income += (float) $row["amount"];
    } else {
        $total_expense += (float) $row["amount"];
    }
}

mysqli_stmt_close($stmt);
$balance = $total_income - $total_expense;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report <?php echo htmlspecialchars($month); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #222; }
        h1 { margin: 0 0 6px; font-size: 24px; }
        .muted { color: #666; margin-bottom: 18px; }
        .summary { display: flex; gap: 12px; margin-bottom: 18px; }
        .card { border: 1px solid #ddd; border-radius: 6px; padding: 12px; flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; font-size: 13px; text-align: left; }
        th { background: #f3f3f3; }
        .right { text-align: right; }
        @media print {
            .no-print { display: none; }
            body { margin: 10mm; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print / Save as PDF</button>
    <h1>Monthly Finance Report</h1>
    <div class="muted">Month: <?php echo htmlspecialchars($month); ?></div>

    <div class="summary">
        <div class="card"><strong>Total Income:</strong><br>Rs <?php echo number_format($total_income, 2); ?></div>
        <div class="card"><strong>Total Expense:</strong><br>Rs <?php echo number_format($total_expense, 2); ?></div>
        <div class="card"><strong>Net Balance:</strong><br>Rs <?php echo number_format($balance, 2); ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Date</th>
                <th>Source</th>
                <th>Category</th>
                <th>Notes</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6">No transactions found for this month.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row["type"]); ?></td>
                        <td><?php echo htmlspecialchars($row["txn_date"]); ?></td>
                        <td><?php echo htmlspecialchars($row["source"] ?: "-"); ?></td>
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
