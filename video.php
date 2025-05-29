<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['course_id'], $_GET['username'], $_GET['url'], $_GET['title']) || $_GET['username'] != $_SESSION['user']) {
    die("Error: Invalid parameters or unauthorized access.");
}

$course_id = $_GET['course_id'];
$username = $_GET['username'];
$video_url = urldecode($_GET['url']);
$video_title = urldecode($_GET['title']);

// Validate YouTube URL and convert to embed format
$embed_url = $video_url;
if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $video_url, $matches)) {
    $embed_url = "https://www.youtube.com/embed/" . $matches[1];
} elseif (preg_match('/youtu\.be\/([^?]+)/', $video_url, $matches)) {
    $embed_url = "https://www.youtube.com/embed/" . $matches[1];
}

// Update progress in progress.xml
$progress = simplexml_load_file($progress_file) or die("Error: Failed to load progress.xml.");
$student_node = null;
foreach ($progress->student as $p) {
    if ($p->username == $username) {
        $student_node = $p;
        break;
    }
}
if (!$student_node) {
    $student_node = $progress->addChild('student');
    $student_node->addChild('username', $username);
}
$course_node = null;
foreach ($student_node->course as $c) {
    if ($c->course_id == $course_id) {
        $course_node = $c;
        break;
    }
}
if (!$course_node) {
    $course_node = $student_node->addChild('course');
    $course_node->addChild('course_id', $course_id);
    $course_node->addChild('completed', '');
}
$completed = array_filter(explode(',', (string)$course_node->completed));
$video_id = 'video_' . md5($video_url);
if (!in_array($video_id, $completed)) {
    $completed[] = $video_id;
    $course_node->completed = implode(',', $completed);
    $progress->asXML($progress_file);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($video_title); ?> - AWS Training Portal</title>
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
        .video-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            height: 0;
            overflow: hidden;
            max-width: 100%;
            background: #000;
            margin-bottom: 1rem;
        }
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
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
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container mt-5">
            <h3><?php echo htmlspecialchars($video_title); ?></h3>
            <div class="video-container">
                <iframe src="<?php echo htmlspecialchars($embed_url); ?>" frameborder="0" allowfullscreen></iframe>
            </div>
            <a href="course.php?course_id=<?php echo urlencode($course_id); ?>" class="btn btn-primary">Return to Course</a>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3">
        <p>© <?php echo date('Y'); ?> AWS Training Portal. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>