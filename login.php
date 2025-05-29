<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = $_POST['identifier']; // Username or Email
    $password = md5($_POST['password']);
    $xml = simplexml_load_file($students_file);

    foreach ($xml->student as $student) {
        if (($student->username == $identifier || $student->email == $identifier) && $student->password == $password) {
            if ($student->status == 'active' || $student->role == 'admin') {
                $_SESSION['user'] = (string)$student->username;
                $_SESSION['role'] = (string)$student->role;
                header('Location: ' . ($student->role == 'admin' ? 'admin.php' : 'dashboard.php'));
                exit;
            } else {
                $error = "Account is inactive.";
            }
        }
    }
    $error = isset($error) ? $error : "Invalid credentials.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AWS Training Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            display: flex;
            flex-direction: column;
        }
        .content {
            flex: 1 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        footer {
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="container">
            <h2 class="text-center mb-4">Login</h2>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger w-50 mx-auto"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" class="w-50 mx-auto">
                <div class="mb-3">
                    <label for="identifier" class="form-label">Username or Email</label>
                    <input type="text" class="form-control" id="identifier" name="identifier" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3">
        <p>© <?php echo date('Y'); ?> AWS Training Portal. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>