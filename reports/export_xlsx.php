<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once __DIR__ . "/report_helpers.php";

$user_id = (int) $_SESSION["user_id"];
$filters = report_parse_filters($_GET);

[$union_sql, $union_types, $union_params] = report_build_union_query($filters, $user_id);

$report_label = $filters["report_type"] . "_" . $filters["start_date"] . "_to_" . $filters["end_date"];

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"report_" . $report_label . ".xlsx\"");

echo "<?xml version=\"1.0\"?>\n";
echo "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n";
echo " xmlns:o=\"urn:schemas-microsoft-com:office:office\"\n";
echo " xmlns:x=\"urn:schemas-microsoft-com:office:excel\"\n";
echo " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\">\n";
echo "<Worksheet ss:Name=\"Report\">\n";
echo "<Table>\n";

echo "<Row>";
echo "<Cell><Data ss:Type=\"String\">Type</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Date</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Category</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Notes</Data></Cell>";
echo "<Cell><Data ss:Type=\"String\">Amount</Data></Cell>";
echo "</Row>\n";

if ($union_sql !== "") {
    $stmt = mysqli_prepare($conn, $union_sql);
    mysqli_stmt_bind_param($stmt, $union_types, ...$union_params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $amount = number_format((float) $row["amount"], 2, ".", "");
        echo "<Row>";
        echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($row["type"]) . "</Data></Cell>";
        echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($row["txn_date"]) . "</Data></Cell>";
        echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($row["category"]) . "</Data></Cell>";
        echo "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($row["notes"] ?: "-") . "</Data></Cell>";
        echo "<Cell><Data ss:Type=\"Number\">" . $amount . "</Data></Cell>";
        echo "</Row>\n";
    }
    mysqli_stmt_close($stmt);
}

echo "</Table>\n";
echo "</Worksheet>\n";
echo "</Workbook>";
exit();
?>
