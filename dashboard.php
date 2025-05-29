<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['user'];
$students = simplexml_load_file($students_file) or die("Error: Failed to load students.xml.");
$courses = simplexml_load_file($courses_file) or die("Error: Failed to load courses.xml.");
$progress = simplexml_load_file($progress_file) or die("Error: Failed to load progress.xml.");

// Get student details
$student = null;
foreach ($students->student as $s) {
    if ($s->username == $username) {
        $student = $s;
        break;
    }
}
if (!$student) {
    header('Location: login.php');
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']);
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
    $password = trim($_POST['password']);

    $error = '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($cpf) != 11) {
        $error = "CPF must be 11 digits.";
    } elseif (strlen($phone) < 10 || strlen($phone) > 11) {
        $error = "Phone must be 10 or 11 digits.";
    } else {
        // Check for duplicate email (excluding current user)
        foreach ($students->student as $s) {
            if ($s->email == $email && $s->username != $username) {
                $error = "Email already in use.";
                break;
            }
        }
    }

    if (!$error) {
        $student->name = $name;
        $student->email = $email;
        $student->cpf = $cpf;
        $student->phone = $phone;
        if ($password) {
            $student->password = md5($password);
        }
        $students->asXML($students_file);
        $success = "Profile updated successfully.";
    }
}

// Get enrolled courses
$enrolled_courses = explode(',', (string)$student->courses);
$progress_data = [];
foreach ($progress->student as $p) {
    if ($p->username == $username) {
        foreach ($p->course as $pc) {
            $progress_data[(string)$pc->course_id] = explode(',', (string)$pc->completed);
        }
    }
}

$section = $_GET['section'] ?? 'courses';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - AWS Training Portal</title>
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
        }
        footer {
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <div class="content">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="index.php">AWS Training Portal</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $section == 'courses' ? 'active' : ''; ?>" href="?section=courses">Courses</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $section == 'profile' ? 'active' : ''; ?>" href="?section=profile">Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container mt-5">
            <?php if ($section == 'courses'): ?>
                <h2>Welcome, <?php echo htmlspecialchars($student->name); ?>!</h2>
                <h3>Your Courses</h3>
                <div class="row">
                    <?php foreach ($courses->course as $course): ?>
                        <?php if (in_array((string)$course->id, $enrolled_courses) && $course->status == 'active'): ?>
                            <?php
                            $completed = isset($progress_data[(string)$course->id]) ? $progress_data[(string)$course->id] : [];
                            $resources = [];
                            foreach ($course->resources->video as $video) {
                                $resources[] = 'video_' . md5($video['url']);
                            }
                            foreach ($course->resources->pdf as $pdf) {
                                $resources[] = 'pdf_' . md5($pdf['url']);
                            }
                            $resources[] = 'quiz';
                            $resources[] = 'flashcard';
                            $total = count($resources);
                            $completed_count = count(array_intersect($resources, $completed));
                            $percentage = ($total > 0) ? ($completed_count / $total) * 100 : 0;
                            ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?php echo htmlspecialchars($course->title); ?></h5>
                                        <p class="card-text flex-grow-1"><?php echo htmlspecialchars($course->description); ?></p>
                                        <div class="progress mb-3">
                                            <div class="progress-bar" style="width: <?php echo $percentage; ?>%" role="progressbar" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo round($percentage, 2); ?>%</div>
                                        </div>
                                        <a href="course.php?course_id=<?php echo htmlspecialchars($course->id); ?>" class="btn btn-primary mt-auto">View Course</a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($section == 'profile'): ?>
                <h2>Profile</h2>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger w-50"><?php echo htmlspecialchars($error); ?></div>
                <?php elseif (isset($success)): ?>
                    <div class="alert alert-success w-50"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <form method="POST" class="w-50">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username (read-only)</label>
                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($student->username); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($student->name); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student->email); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control" id="cpf" name="cpf" value="<?php echo htmlspecialchars($student->cpf); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($student->phone); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3">
        <p>© <?php echo date('Y'); ?> AWS Training Portal. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>