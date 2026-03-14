<?php
declare(strict_types=1);

function report_validate_date(?string $value): ?string
{
    if ($value === null || $value === "") {
        return null;
    }

    $date = DateTime::createFromFormat("Y-m-d", $value);
    if ($date === false) {
        return null;
    }

    if ($date->format("Y-m-d") !== $value) {
        return null;
    }

    return $value;
}

function report_parse_filters(array $input): array
{
    $allowed_types = ["daily", "weekly", "monthly", "custom"];
    $allowed_txn_types = ["all", "income", "expense"];

    $report_type = $input["report_type"] ?? "monthly";
    if (!in_array($report_type, $allowed_types, true)) {
        $report_type = "monthly";
    }

    $transaction_type = $input["transaction_type"] ?? "all";
    if (!in_array($transaction_type, $allowed_txn_types, true)) {
        $transaction_type = "all";
    }

    $category = trim((string) ($input["category"] ?? ""));
    if ($category === "all") {
        $category = "";
    }

    $start_input = report_validate_date($input["start_date"] ?? null);
    $end_input = report_validate_date($input["end_date"] ?? null);

    $today = new DateTime("today");

    if ($report_type === "daily") {
        $base = $start_input ? new DateTime($start_input) : clone $today;
        $start_date = $base->format("Y-m-d");
        $end_date = $base->format("Y-m-d");
    } elseif ($report_type === "weekly") {
        $base = $start_input ? new DateTime($start_input) : clone $today;
        $start = clone $base;
        $start->modify("monday this week");
        $end = clone $start;
        $end->modify("+6 days");
        $start_date = $start->format("Y-m-d");
        $end_date = $end->format("Y-m-d");
    } elseif ($report_type === "monthly") {
        $base = $start_input ? new DateTime($start_input) : clone $today;
        $start = new DateTime($base->format("Y-m-01"));
        $end = clone $start;
        $end->modify("last day of this month");
        $start_date = $start->format("Y-m-d");
        $end_date = $end->format("Y-m-d");
    } else {
        $start = $start_input ? new DateTime($start_input) : (clone $today)->modify("-29 days");
        $end = $end_input ? new DateTime($end_input) : clone $today;

        if ($start > $end) {
            $temp = $start;
            $start = $end;
            $end = $temp;
        }

        $start_date = $start->format("Y-m-d");
        $end_date = $end->format("Y-m-d");
    }

    return [
        "report_type" => $report_type,
        "transaction_type" => $transaction_type,
        "category" => $category,
        "start_date" => $start_date,
        "end_date" => $end_date,
    ];
}

function report_build_date_clause(
    string $date_column,
    int $user_id,
    string $start_date,
    string $end_date,
    string $category,
    string &$types,
    array &$params
): string {
    $types = "iss";
    $params = [$user_id, $start_date, $end_date];
    $clause = "user_id = ? AND {$date_column} BETWEEN ? AND ?";

    if ($category !== "") {
        $types .= "s";
        $params[] = $category;
        $clause .= " AND category = ?";
    }

    return $clause;
}

function report_build_union_query(array $filters, int $user_id, bool $include_order = true): array
{
    $parts = [];
    $types = "";
    $params = [];

    $include_income = $filters["transaction_type"] !== "expense";
    $include_expense = $filters["transaction_type"] !== "income";

    if ($include_income) {
        $income_types = "";
        $income_params = [];
        $income_clause = report_build_date_clause(
            "income_date",
            $user_id,
            $filters["start_date"],
            $filters["end_date"],
            $filters["category"],
            $income_types,
            $income_params
        );
        $parts[] = "SELECT 'Income' AS type, income_date AS txn_date, category, notes, amount
                    FROM income
                    WHERE {$income_clause}";
        $types .= $income_types;
        $params = array_merge($params, $income_params);
    }

    if ($include_expense) {
        $expense_types = "";
        $expense_params = [];
        $expense_clause = report_build_date_clause(
            "expense_date",
            $user_id,
            $filters["start_date"],
            $filters["end_date"],
            $filters["category"],
            $expense_types,
            $expense_params
        );
        $parts[] = "SELECT 'Expense' AS type, expense_date AS txn_date, category, notes, amount
                    FROM expenses
                    WHERE {$expense_clause}";
        $types .= $expense_types;
        $params = array_merge($params, $expense_params);
    }

    if (empty($parts)) {
        return ["", "", []];
    }

    $sql = implode(" UNION ALL ", $parts);
    if ($include_order) {
        $sql .= " ORDER BY txn_date DESC, type ASC";
    }

    return [$sql, $types, $params];
}
?>
