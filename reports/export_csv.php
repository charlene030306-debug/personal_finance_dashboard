<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once __DIR__ . "/report_helpers.php";

$user_id = (int) $_SESSION["user_id"];
$filters = report_parse_filters($_GET);

[$union_sql, $union_types, $union_params] = report_build_union_query($filters, $user_id);

$report_label = $filters["report_type"] . "_" . $filters["start_date"] . "_to_" . $filters["end_date"];
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"report_" . $report_label . ".csv\"");

$output = fopen("php://output", "w");
fputcsv($output, ["Type", "Date", "Category", "Notes", "Amount"]);

if ($union_sql !== "") {
    $stmt = mysqli_prepare($conn, $union_sql);
    mysqli_stmt_bind_param($stmt, $union_types, ...$union_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row["type"],
            $row["txn_date"],
            $row["category"],
            $row["notes"] ?: "-",
            number_format((float) $row["amount"], 2, ".", ""),
        ]);
    }
    mysqli_stmt_close($stmt);
}

fclose($output);
exit();
?>
