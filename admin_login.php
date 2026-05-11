<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/classes/Repositories.php';
require_once __DIR__ . '/classes/Services.php';

$database = new db();
$pdo = $database->connect();
$organisationRepository = new OrganisationRepository($pdo);
$authService = new AuthService($organisationRepository);
$view = new ViewService();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif ($authService->login($username, $password)) {
        header('Location: admin_dash.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organisation login | Photo Hub</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>

<body>
    <main class="admin-login-shell">
        <section class="form-card admin-login-card">
            <p class="eyebrow">Photo Hub</p>
            <h1 class="admin-title">Organisation login</h1>
            <?php if ($error !== null): ?>
                <p class="notice notice-danger"><?= $view->escape($error) ?></p><?php endif; ?>
            <form method="POST" action="admin_login.php">
                <div class="form-group"><label for="username">Username</label><input type="text" id="username"
                        name="username" autocomplete="username" required></div>
                <div class="form-group"><label for="password">Password</label><input type="password" id="password"
                        name="password" autocomplete="current-password" required></div>
                <button type="submit">Log in</button>
            </form>
            <a class="admin-link" href="main.php">Back to upload page</a>
        </section>
    </main>
</body>

</html>