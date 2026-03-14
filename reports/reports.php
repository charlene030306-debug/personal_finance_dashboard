<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";
require_once __DIR__ . "/report_helpers.php";

$page_title = "Reports";

$user_id = (int) $_SESSION["user_id"];
$filters = report_parse_filters($_GET);

$category_set = [];
$stmt = mysqli_prepare($conn, "SELECT DISTINCT category FROM income WHERE user_id = ? ORDER BY category ASC");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $category_set[$row["category"]] = true;
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT DISTINCT category FROM expenses WHERE user_id = ? ORDER BY category ASC");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $category_set[$row["category"]] = true;
}
mysqli_stmt_close($stmt);

$categories = array_keys($category_set);
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

include "../includes/header.php";
?>

<div class="reports-page reports-container">

<!-- Summary Cards -->
<section class="stats-grid reports-summary dashboard-card-grid">
    <div class="stat-card income">
        <div>
            <p class="stat-title">Total Income</p>
            <h3 class="stat-value" id="summaryIncome">Rs 0.00</h3>
            <small id="summaryRange" class="stat-sub">Loading range...</small>
        </div>
        <span class="stat-icon"><i class="fa-solid fa-arrow-trend-up"></i></span>
    </div>
    <div class="stat-card expense">
        <div>
            <p class="stat-title">Total Expenses</p>
            <h3 class="stat-value" id="summaryExpense">Rs 0.00</h3>
            <small class="stat-sub">Filtered totals</small>
        </div>
        <span class="stat-icon"><i class="fa-solid fa-wallet"></i></span>
    </div>
    <div class="stat-card balance">
        <div>
            <p class="stat-title">Net Balance</p>
            <h3 class="stat-value" id="summaryBalance">Rs 0.00</h3>
            <small class="stat-sub">Income - Expenses</small>
        </div>
        <span class="stat-icon"><i class="fa-solid fa-scale-balanced"></i></span>
    </div>
</section>

<!-- Filters Section -->
<section class="dashboard-card reports-filter-card">
    <div class="card-heading">
        <h5>Report Filters</h5>
        <span class="heading-meta">Customize time range and focus</span>
    </div>

    <form id="reportFilters" class="reports-filter-grid reports-filter-bar" method="GET">
        <div>
            <label class="form-label">Report Type</label>
            <select name="report_type" class="form-select">
                <option value="all" <?php echo $filters["report_type"] === "all" ? "selected" : ""; ?>>All Time</option>
                <option value="daily" <?php echo $filters["report_type"] === "daily" ? "selected" : ""; ?>>Daily</option>
                <option value="weekly" <?php echo $filters["report_type"] === "weekly" ? "selected" : ""; ?>>Weekly</option>
                <option value="monthly" <?php echo $filters["report_type"] === "monthly" ? "selected" : ""; ?>>Monthly</option>
                <option value="custom" <?php echo $filters["report_type"] === "custom" ? "selected" : ""; ?>>Custom Range</option>
            </select>
        </div>
        <div>
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($filters["start_date"]); ?>">
        </div>
        <div>
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($filters["end_date"]); ?>">
        </div>
        <div>
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
                <option value="all" <?php echo $filters["category"] === "" ? "selected" : ""; ?>>All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filters["category"] === $category ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($category); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Transaction Type</label>
            <select name="transaction_type" class="form-select">
                <option value="all" <?php echo $filters["transaction_type"] === "all" ? "selected" : ""; ?>>All</option>
                <option value="income" <?php echo $filters["transaction_type"] === "income" ? "selected" : ""; ?>>Income</option>
                <option value="expense" <?php echo $filters["transaction_type"] === "expense" ? "selected" : ""; ?>>Expense</option>
            </select>
        </div>
        <div class="reports-filter-actions">
            <button class="btn btn-primary" type="submit">Apply Filters</button>
            <a class="btn btn-outline-secondary" href="reports.php">Reset</a>
        </div>
    </form>
</section>

<!-- Transactions Table -->
<section class="dashboard-card">
    <div class="card-heading">
        <h5>Transactions</h5>
        <div class="report-export-actions">
            <a class="btn btn-outline-secondary btn-sm" id="exportCsvBtn" href="#">Export CSV</a>
            <a class="btn btn-outline-secondary btn-sm" id="exportXlsxBtn" href="#">Export XLSX</a>
            <a class="btn btn-outline-secondary btn-sm" id="exportPdfBtn" href="#">Export PDF</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table finance-table dashboard-table align-middle mb-0" id="transactionsTable">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Notes</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody id="transactionsBody">
                <tr>
                    <td colspan="5">Loading transactions...</td>
                </tr>
            </tbody>
        </table>
        <p class="empty-state d-none" id="transactionsEmpty">No transactions match the selected filters.</p>
    </div>
</section>

<!-- Charts Section -->
<section class="charts-grid">
    <div class="dashboard-card">
        <div class="card-heading">
            <h5>Expenses by Category</h5>
            <span class="heading-meta">Pie chart</span>
        </div>
        <div class="chart-wrap">
            <canvas id="expensePieChart"></canvas>
            <p class="empty-state d-none" id="expensePieEmpty">No expense data available.</p>
        </div>
    </div>
    <div class="dashboard-card">
        <div class="card-heading">
            <h5>Income vs Expenses</h5>
            <span class="heading-meta">Monthly comparison</span>
        </div>
        <div class="chart-wrap">
            <canvas id="incomeExpenseBar"></canvas>
            <p class="empty-state d-none" id="incomeExpenseEmpty">No monthly data available.</p>
        </div>
    </div>
    <div class="dashboard-card chart-wide">
        <div class="card-heading">
            <h5>Spending Trend</h5>
            <span class="heading-meta">Daily expenses over time</span>
        </div>
        <div class="chart-wrap">
            <canvas id="spendingTrendLine"></canvas>
            <p class="empty-state d-none" id="spendingTrendEmpty">No spending data available.</p>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const reportFilters = document.getElementById("reportFilters");
const summaryIncome = document.getElementById("summaryIncome");
const summaryExpense = document.getElementById("summaryExpense");
const summaryBalance = document.getElementById("summaryBalance");
const summaryRange = document.getElementById("summaryRange");
const transactionsBody = document.getElementById("transactionsBody");
const transactionsEmpty = document.getElementById("transactionsEmpty");

const exportCsvBtn = document.getElementById("exportCsvBtn");
const exportXlsxBtn = document.getElementById("exportXlsxBtn");
const exportPdfBtn = document.getElementById("exportPdfBtn");

const expensePieEmpty = document.getElementById("expensePieEmpty");
const incomeExpenseEmpty = document.getElementById("incomeExpenseEmpty");
const spendingTrendEmpty = document.getElementById("spendingTrendEmpty");

const currencyFormatter = new Intl.NumberFormat("en-IN", {
    style: "currency",
    currency: "INR",
    minimumFractionDigits: 2,
});

let expensePieChart;
let incomeExpenseBarChart;
let spendingTrendChart;

function buildQueryString(form) {
    const data = new FormData(form);
    const params = new URLSearchParams();
    for (const [key, value] of data.entries()) {
        if (value !== "") {
            params.append(key, value);
        }
    }
    return params.toString();
}

function updateExportLinks(queryString) {
    exportCsvBtn.href = `export_csv.php?${queryString}`;
    exportXlsxBtn.href = `export_xlsx.php?${queryString}`;
    exportPdfBtn.href = `export_pdf.php?${queryString}`;
}

function renderTransactions(rows) {
    transactionsBody.innerHTML = "";
    if (!rows.length) {
        transactionsEmpty.classList.remove("d-none");
        transactionsBody.innerHTML = "<tr><td colspan='5'>No transactions found.</td></tr>";
        return;
    }
    transactionsEmpty.classList.add("d-none");
    rows.forEach(row => {
        const badgeClass = row.type === "Income" ? "income-badge" : "expense-badge";
        const notes = row.notes && row.notes.trim() ? row.notes : "-";
        const amountClass = row.type === "Income" ? "amount-income" : "amount-expense";
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td><span class="badge-type ${badgeClass}">${row.type}</span></td>
            <td>${row.date}</td>
            <td>${row.category}</td>
            <td>${notes}</td>
            <td class="amount-column ${amountClass}">${currencyFormatter.format(row.amount)}</td>
        `;
        transactionsBody.appendChild(tr);
    });
}


function renderCharts(charts) {
    if (expensePieChart) {
        expensePieChart.destroy();
    }
    if (incomeExpenseBarChart) {
        incomeExpenseBarChart.destroy();
    }
    if (spendingTrendChart) {
        spendingTrendChart.destroy();
    }

    expensePieEmpty.classList.toggle("d-none", charts.expense_by_category.labels.length > 0);
    incomeExpenseEmpty.classList.toggle("d-none", charts.monthly_totals.labels.length > 0);
    spendingTrendEmpty.classList.toggle("d-none", charts.spending_trend.labels.length > 0);

    expensePieChart = new Chart(document.getElementById("expensePieChart"), {
        type: "pie",
        data: {
            labels: charts.expense_by_category.labels,
            datasets: [{
                data: charts.expense_by_category.data,
                backgroundColor: [
                    "#ef5350", "#f59e0b", "#10b981", "#3b82f6", "#8b5cf6", "#14b8a6", "#f97316"
                ],
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: "bottom" },
            },
        },
    });

    incomeExpenseBarChart = new Chart(document.getElementById("incomeExpenseBar"), {
        type: "bar",
        data: {
            labels: charts.monthly_totals.labels,
            datasets: [
                {
                    label: "Income",
                    data: charts.monthly_totals.income,
                    backgroundColor: "#20b36f",
                },
                {
                    label: "Expenses",
                    data: charts.monthly_totals.expense,
                    backgroundColor: "#ef5350",
                }
            ],
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    ticks: {
                        callback: value => currencyFormatter.format(value),
                    },
                },
            },
        },
    });

    spendingTrendChart = new Chart(document.getElementById("spendingTrendLine"), {
        type: "line",
        data: {
            labels: charts.spending_trend.labels,
            datasets: [{
                label: "Daily Expenses",
                data: charts.spending_trend.data,
                borderColor: "#3265ea",
                backgroundColor: "rgba(50, 101, 234, 0.2)",
                tension: 0.3,
                fill: true,
            }],
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    ticks: {
                        callback: value => currencyFormatter.format(value),
                    },
                },
            },
        },
    });
}

async function loadReportData() {
    const queryString = buildQueryString(reportFilters);
    updateExportLinks(queryString);

    const response = await fetch(`report_data.php?${queryString}`);
    const data = await response.json();

    summaryIncome.textContent = currencyFormatter.format(data.summary.income);
    summaryExpense.textContent = currencyFormatter.format(data.summary.expense);
    summaryBalance.textContent = currencyFormatter.format(data.summary.balance);
    summaryRange.textContent = `${data.filters.start_date} to ${data.filters.end_date}`;

    renderTransactions(data.transactions);
    renderCharts(data.charts);
}

reportFilters.addEventListener("submit", event => {
    event.preventDefault();
    loadReportData();
});

reportFilters.addEventListener("change", () => {
    loadReportData();
});

loadReportData();
</script>

</div>

<?php include "../includes/footer.php"; ?>
