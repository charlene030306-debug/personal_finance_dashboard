<?php
$current_path = str_replace("\\", "/", $_SERVER["PHP_SELF"] ?? "");

$is_active = function (string $section) use ($current_path): bool {
    return match ($section) {
        "dashboard" => str_contains($current_path, "/dashboard.php"),
        "income" => str_contains($current_path, "/income/"),
        "expense" => str_contains($current_path, "/expense/"),
        "budget" => str_contains($current_path, "/budget/"),
        "reports" => str_contains($current_path, "/reports/"),
        default => false,
    };
};

$menu_items = [
    ["key" => "dashboard", "label" => "Dashboard", "icon" => "fa-solid fa-table-columns", "href" => APP_BASE_URL . "/dashboard.php"],
    ["key" => "income", "label" => "Income", "icon" => "fa-solid fa-arrow-trend-up", "href" => APP_BASE_URL . "/income/index.php"],
    ["key" => "expense", "label" => "Expense", "icon" => "fa-solid fa-wallet", "href" => APP_BASE_URL . "/expense/add_expense.php"],
    ["key" => "budget", "label" => "Budget", "icon" => "fa-solid fa-piggy-bank", "href" => APP_BASE_URL . "/budget/add_budget.php"],
    ["key" => "reports", "label" => "Reports", "icon" => "fa-solid fa-chart-column", "href" => APP_BASE_URL . "/reports/reports.php"],
];

$render_menu = function () use ($menu_items, $is_active): void {
    foreach ($menu_items as $item) {
        $active_class = $is_active($item["key"]) ? "active" : "";
        echo '<a href="' . htmlspecialchars($item["href"]) . '" class="nav-item ' . $active_class . '">';
        echo '<i class="' . htmlspecialchars($item["icon"]) . '"></i>' . htmlspecialchars($item["label"]);
        echo "</a>";
    }
};
?>

<aside class="app-sidebar d-none d-lg-flex">
    <a href="<?php echo APP_BASE_URL; ?>/dashboard.php" class="brand-link">
        <span class="brand-icon"><i class="fa-solid fa-chart-pie"></i></span>
        <span>Finance OS</span>
    </a>
    <nav class="sidebar-nav">
        <?php $render_menu(); ?>
    </nav>
    <a href="<?php echo APP_BASE_URL; ?>/auth/logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
</aside>

<div class="offcanvas offcanvas-start mobile-sidebar" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="mobileSidebarLabel">Finance OS</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="sidebar-nav">
            <?php $render_menu(); ?>
            <a href="<?php echo APP_BASE_URL; ?>/auth/logout.php" class="nav-item">
                <i class="fa-solid fa-right-from-bracket"></i>Logout
            </a>
        </nav>
    </div>
</div>
