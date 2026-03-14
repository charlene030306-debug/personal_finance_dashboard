<?php
require_once "../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION["user_id"])) {
    header("Location: /personal_finance_dashboard-features/personal_finance_dashboard-features/dashboard.php");
    exit();
}

$errors = [];
$email = "";
$show_registered_success = isset($_GET["registered"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $errors[] = "Email and password are required.";
    }

    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id, name, password FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) === 1) {
            mysqli_stmt_bind_result($stmt, $id, $name, $hashed_password);
            mysqli_stmt_fetch($stmt);

            if (password_verify($password, $hashed_password)) {
                session_regenerate_id(true);
                $_SESSION["user_id"] = (int) $id;
                $_SESSION["user_name"] = $name;

                mysqli_stmt_close($stmt);
                header("Location: /personal_finance_dashboard-features/personal_finance_dashboard-features/dashboard.php");
                exit();
            }
        }

        mysqli_stmt_close($stmt);
        $errors[] = "Invalid email or password.";
    }
}

$page_title = "Login";
include "../includes/header.php";
?>

<div class="card shadow card-auth">
    <div class="card-body">
        <h4 class="text-center mb-4">Login</h4>

        <?php if ($show_registered_success): ?>
            <div class="alert alert-success">Registration successful. Please log in.</div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <form method="POST" novalidate>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary">Login</button>

            <p class="mt-3 text-center mb-0">
                Don't have an account?
                <a href="register.php">Register</a>
            </p>
        </form>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
