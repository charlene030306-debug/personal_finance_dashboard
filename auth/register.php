<?php
require_once "../config/db.php";

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if (empty($name) || empty($email) || empty($password)) {
        $errors[] = "All fields are required.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {

        // Check if email exists
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Email already registered.";
        } else {

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sss", $name, $email, $hashed_password);

            if (mysqli_stmt_execute($stmt)) {
                header("Location: login.php");
                exit();
            } else {
                $errors[] = "Something went wrong.";
            }
        }
    }
}

include "../includes/header.php";
?>

<div class="card shadow card-auth">
    <div class="card-body">
        <h4 class="text-center mb-4">Register</h4>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <div class="mb-3">
                <label>Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary">Register</button>

            <p class="mt-3 text-center">
                Already have an account?
                <a href="login.php">Login</a>
            </p>
        </form>
    </div>
</div>

<?php include "../includes/footer.php"; ?>