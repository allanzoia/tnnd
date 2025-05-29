<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['course_id'])) {
    die("Error: Course ID not provided.");
}

$course_id = $_GET['course_id'];
$username = $_SESSION['user'];

// Load XML files
$courses = simplexml_load_file($courses_file) or die("Error: Failed to load courses.xml.");
$progress = simplexml_load_file($progress_file) or die("Error: Failed to load progress.xml.");

// Find the course
$course = null;
foreach ($courses->course as $c) {
    if ($c->id == $course_id) {
        $course = $c;
        break;
    }
}
if (!$course) {
    die("Error: Course not found.");
}

// Debug: Log course resources
$debug_log = "Course ID: $course_id\n";
$debug_log .= "Resources XML: " . $course->resources->asXML() . "\n";
$debug_log .= "Video Count: " . count($course->resources->video) . "\n";
$debug_log .= "PDF Count: " . count($course->resources->pdf) . "\n";
$debug_log .= "Quiz Exists: " . ($course->resources->quiz ? 'Yes' : 'No') . "\n";
$debug_log .= "Flashcard Exists: " . ($course->resources->flashcard ? 'Yes' : 'No') . "\n";

// Calculate progress
$resources = [];
foreach ($course->resources->video as $video) {
    $resources[] = 'video_' . md5($video['url']);
}
foreach ($course->resources->pdf as $pdf) {
    $resources[] = 'pdf_' . md5($pdf['url']);
}
if ($course->resources->quiz) {
    $resources[] = 'quiz';
}
if ($course->resources->flashcard) {
    $resources[] = 'flashcard';
}
$total_resources = count($resources);

$completed = [];
foreach ($progress->student as $p) {
    if ($p->username == $username) {
        foreach ($p->course as $pc) {
            if ($pc->course_id == $course_id) {
                $completed = array_filter(explode(',', (string)$pc->completed));
                break;
            }
        }
        break;
    }
}
$completed_count = count(array_intersect($resources, $completed));
$percentage = ($total_resources > 0) ? round(($completed_count / $total_resources) * 100, 2) : 0;

// Debug: Log progress calculation
$debug_log .= "Resources Array: " . json_encode($resources) . "\n";
$debug_log .= "Completed Array: " . json_encode($completed) . "\n";
$debug_log .= "Completed Count: $completed_count\n";
$debug_log .= "Total Resources: $total_resources\n";
$debug_log .= "Percentage: $percentage%\n";
file_put_contents('/tmp/course_debug.log', $debug_log . "----\n", FILE_APPEND);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course->title); ?> - AWS Training Portal</title>
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
        .resource-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            <h2><?php echo htmlspecialchars($course->title); ?></h2>
            <p><?php echo htmlspecialchars($course->description); ?></p>
            <p><strong>Progress: <?php echo $percentage; ?>%</strong></p>

            <h4>Resources</h4>
            <div class="accordion mb-5" id="resourcesAccordion">
                <?php if (count($course->resources->video) > 0): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="videosHeading">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#videosCollapse" aria-expanded="true" aria-controls="videosCollapse">
                                Videos
                            </button>
                        </h2>
                        <div id="videosCollapse" class="accordion-collapse collapse show" aria-labelledby="videosHeading" data-bs-parent="#resourcesAccordion">
                            <div class="accordion-body">
                                <ul class="list-group">
                                    <?php foreach ($course->resources->video as $video): ?>
                                        <li class="list-group-item resource-item">
                                            <a href="video.php?course_id=<?php echo urlencode($course_id); ?>&username=<?php echo urlencode($username); ?>&url=<?php echo urlencode($video['url']); ?>&title=<?php echo urlencode($video['title']); ?>">
                                                <?php echo htmlspecialchars($video['title']); ?>
                                            </a>
                                            <?php if (in_array('video_' . md5($video['url']), $completed)): ?>
                                                <span class="badge bg-success ms-2">Completed</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (count($course->resources->pdf) > 0): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="pdfsHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pdfsCollapse" aria-expanded="false" aria-controls="pdfsCollapse">
                                Slides e PDFs
                            </button>
                        </h2>
                        <div id="pdfsCollapse" class="accordion-collapse collapse" aria-labelledby="pdfsHeading" data-bs-parent="#resourcesAccordion">
                            <div class="accordion-body">
                                <ul class="list-group">
                                    <?php foreach ($course->resources->pdf as $pdf): ?>
                                        <li class="list-group-item resource-item">
                                            <a href="<?php echo htmlspecialchars($pdf['url']); ?>" target="_blank">
                                                <?php echo htmlspecialchars($pdf['title']); ?>
                                            </a>
                                            <div>
                                                <?php if (in_array('pdf_' . md5($pdf['url']), $completed)): ?>
                                                    <span class="badge bg-success ms-2">Completed</span>
                                                <?php else: ?>
                                                    <a href="mark_complete.php?course_id=<?php echo urlencode($course_id); ?>&resource_id=<?php echo urlencode('pdf_' . md5($pdf['url'])); ?>" class="btn btn-sm btn-primary ms-2">Mark as Completed</a>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($course->resources->quiz): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="quizHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#quizCollapse" aria-expanded="false" aria-controls="quizCollapse">
                                Simulados
                            </button>
                        </h2>
                        <div id="quizCollapse" class="accordion-collapse collapse" aria-labelledby="quizHeading" data-bs-parent="#resourcesAccordion">
                            <div class="accordion-body">
                                <ul class="list-group">
                                    <li class="list-group-item resource-item">
                                        <a href="<?php echo htmlspecialchars($course->resources->quiz); ?>" target="_blank">Quiz</a>
                                        <div>
                                            <?php if (in_array('quiz', $completed)): ?>
                                                <span class="badge bg-success ms-2">Completed</span>
                                            <?php else: ?>
                                                <a href="mark_complete.php?course_id=<?php echo urlencode($course_id); ?>&resource_id=quiz" class="btn btn-sm btn-primary ms-2">Mark as Completed</a>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($course->resources->flashcard): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="flashcardHeading">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flashcardCollapse" aria-expanded="false" aria-controls="flashcardCollapse">
                                Flashcards
                            </button>
                        </h2>
                        <div id="flashcardCollapse" class="accordion-collapse collapse" aria-labelledby="flashcardHeading" data-bs-parent="#resourcesAccordion">
                            <div class="accordion-body">
                                <ul class="list-group">
                                    <li class="list-group-item resource-item">
                                        <a href="<?php echo htmlspecialchars($course->resources->flashcard); ?>" target="_blank">
                                            Flashcard
                                        </a>
                                        <div>
                                            <?php if (in_array('flashcard', $completed)): ?>
                                                <span class="badge bg-success ms-2">Completed</span>
                                            <?php else: ?>
                                                <a href="mark_complete.php?course_id=<?php echo urlencode($course_id); ?>&amp;resource_id=flashcard" class="btn btn-sm btn-primary ms-2">Mark as Completed</a>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3">
        <p>© <?php echo htmlspecialchars(date('Y')); ?> AWS Training Portal. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>