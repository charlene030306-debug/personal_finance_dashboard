<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once __DIR__ . "/report_helpers.php";

header("Content-Type: application/json; charset=utf-8");

$user_id = (int) $_SESSION["user_id"];
$filters = report_parse_filters($_GET);

// Summary totals
$summary = [
    "income" => 0.0,
    "expense" => 0.0,
    "balance" => 0.0,
];

if ($filters["transaction_type"] !== "expense") {
    $types = "";
    $params = [];
    $clause = report_build_date_clause(
        "income_date",
        $user_id,
        $filters["start_date"],
        $filters["end_date"],
        $filters["category"],
        $types,
        $params
    );
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount), 0) FROM income WHERE {$clause}");
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $income_total);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    $summary["income"] = (float) $income_total;
}

if ($filters["transaction_type"] !== "income") {
    $types = "";
    $params = [];
    $clause = report_build_date_clause(
        "expense_date",
        $user_id,
        $filters["start_date"],
        $filters["end_date"],
        $filters["category"],
        $types,
        $params
    );
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE {$clause}");
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $expense_total);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    $summary["expense"] = (float) $expense_total;
}

$summary["balance"] = $summary["income"] - $summary["expense"];

// Transactions list
$transactions = [];
[$union_sql, $union_types, $union_params] = report_build_union_query($filters, $user_id, true);
if ($union_sql !== "") {
    $stmt = mysqli_prepare($conn, $union_sql);
    mysqli_stmt_bind_param($stmt, $union_types, ...$union_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $transactions[] = [
            "type" => $row["type"],
            "date" => $row["txn_date"],
            "category" => $row["category"],
            "notes" => $row["notes"],
            "amount" => (float) $row["amount"],
        ];
    }
    mysqli_stmt_close($stmt);
}

// Expense pie chart
$expense_pie = ["labels" => [], "data" => []];
if ($filters["transaction_type"] !== "income") {
    $types = "";
    $params = [];
    $clause = report_build_date_clause(
        "expense_date",
        $user_id,
        $filters["start_date"],
        $filters["end_date"],
        $filters["category"],
        $types,
        $params
    );
    $stmt = mysqli_prepare(
        $conn,
        "SELECT category, SUM(amount) AS total_amount
         FROM expenses
         WHERE {$clause}
         GROUP BY category
         ORDER BY total_amount DESC"
    );
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $expense_pie["labels"][] = $row["category"];
        $expense_pie["data"][] = (float) $row["total_amount"];
    }
    mysqli_stmt_close($stmt);
}

// Income vs expense per month (bar chart)
$monthly_labels = [];
$monthly_income = [];
$monthly_expense = [];

$start_month = new DateTime($filters["start_date"]);
$start_month->modify("first day of this month");
$end_month = new DateTime($filters["end_date"]);
$end_month->modify("first day of this month");

$month_index = [];
for ($cursor = clone $start_month; $cursor <= $end_month; $cursor->modify("+1 month")) {
    $key = $cursor->format("Y-m");
    $month_index[$key] = count($monthly_labels);
    $monthly_labels[] = $cursor->format("M Y");
    $monthly_income[] = 0.0;
    $monthly_expense[] = 0.0;
}

if ($filters["transaction_type"] !== "expense") {
    $types = "";
    $params = [];
    $clause = report_build_date_clause(
        "income_date",
        $user_id,
        $filters["start_date"],
        $filters["end_date"],
        $filters["category"],
        $types,
        $params
    );
    $stmt = mysqli_prepare(
        $conn,
        "SELECT DATE_FORMAT(income_date, '%Y-%m') AS month_key, SUM(amount) AS total_amount
         FROM income
         WHERE {$clause}
         GROUP BY month_key"
    );
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row["month_key"];
        if (isset($month_index[$key])) {
            $monthly_income[$month_index[$key]] = (float) $row["total_amount"];
        }
    }
    mysqli_stmt_close($stmt);
}

if ($filters["transaction_type"] !== "income") {
    $types = "";
    $params = [];
    $clause = report_build_date_clause(
        "expense_date",
        $user_id,
        $filters["start_date"],
        $filters["end_date"],
        $filters["category"],
        $types,
        $params
    );
    $stmt = mysqli_prepare(
        $conn,
        "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month_key, SUM(amount) AS total_amount
         FROM expenses
         WHERE {$clause}
         GROUP BY month_key"
    );
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $key = $row["month_key"];
        if (isset($month_index[$key])) {
            $monthly_expense[$month_index[$key]] = (float) $row["total_amount"];
        }
    }
    mysqli_stmt_close($stmt);
}

// Spending trend (line chart)
$trend_labels = [];
$trend_data = [];
if ($filters["transaction_type"] !== "income") {
    $types = "";
    $params = [];
    $clause = report_build_date_clause(
        "expense_date",
        $user_id,
        $filters["start_date"],
        $filters["end_date"],
        $filters["category"],
        $types,
        $params
    );
    $stmt = mysqli_prepare(
        $conn,
        "SELECT expense_date AS txn_date, SUM(amount) AS total_amount
         FROM expenses
         WHERE {$clause}
         GROUP BY txn_date
         ORDER BY txn_date ASC"
    );
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $trend_labels[] = $row["txn_date"];
        $trend_data[] = (float) $row["total_amount"];
    }
    mysqli_stmt_close($stmt);
}

echo json_encode([
    "filters" => $filters,
    "summary" => $summary,
    "transactions" => $transactions,
    "charts" => [
        "expense_by_category" => $expense_pie,
        "monthly_totals" => [
            "labels" => $monthly_labels,
            "income" => $monthly_income,
            "expense" => $monthly_expense,
        ],
        "spending_trend" => [
            "labels" => $trend_labels,
            "data" => $trend_data,
        ],
    ],
]);
?>
