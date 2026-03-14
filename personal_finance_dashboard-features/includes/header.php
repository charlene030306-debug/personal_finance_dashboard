<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined("APP_BASE_URL")) {
    define("APP_BASE_URL", "/personal_finance_dashboard-features/personal_finance_dashboard-features");
}

$current_path = str_replace("\\", "/", $_SERVER["PHP_SELF"] ?? "");
$is_auth_page = str_contains($current_path, "/auth/");
$page_title = $page_title ?? "Finance Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/personal_finance_dashboard-features/personal_finance_dashboard-features/assets/css/style.css">
    <link rel="stylesheet" href="/personal_finance_dashboard-features/personal_finance_dashboard-features/assets/css/styles.css">
</head>
<body class="<?php echo $is_auth_page ? "bg-light" : "app-body"; ?>">
<?php if ($is_auth_page): ?>
    <div class="container py-4">
<?php else: ?>
    <div class="app-shell">
        <?php include __DIR__ . "/sidebar.php"; ?>
        <main class="app-main">
            <header class="topbar">
                <div class="topbar-left">
                    <button
                        class="btn btn-outline-secondary d-lg-none"
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#mobileSidebar"
                        aria-controls="mobileSidebar"
                    >
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
                        <p class="page-subtitle mb-0">Welcome, <?php echo htmlspecialchars($_SESSION["user_name"] ?? "User"); ?></p>
                    </div>
                </div>
                <a href="<?php echo APP_BASE_URL; ?>/auth/logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
            </header>
            <div class="page-content">
<?php endif; ?>
