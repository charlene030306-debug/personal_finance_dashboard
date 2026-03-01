<?php
require_once "../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $errors[] = "All fields are required.";
    }

    if (empty($errors)) {

        $stmt = mysqli_prepare($conn, "SELECT id, name, password FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 1) {

            mysqli_stmt_bind_result($stmt, $id, $name, $hashed_password);
            mysqli_stmt_fetch($stmt);

            if (password_verify($password, $hashed_password)) {

                $_SESSION["user_id"] = $id;
                $_SESSION["user_name"] = $name;

                header("Location: ../dashboard.php");
                exit();
            } else {
                $errors[] = "Invalid credentials.";
            }

        } else {
            $errors[] = "Invalid credentials.";
        }
    }
}

include "../includes/header.php";
?>

<div class="card shadow card-auth">
    <div class="card-body">
        <h4 class="text-center mb-4">Login</h4>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary">Login</button>

            <p class="mt-3 text-center">
                Don't have an account?
                <a href="register.php">Register</a>
            </p>
        </form>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
