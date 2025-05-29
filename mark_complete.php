<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'student') {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['course_id']) || !isset($_GET['resource_id'])) {
    die("Error: Missing course_id or resource_id.");
}

$course_id = $_GET['course_id'];
$resource_id = $_GET['resource_id'];
$username = $_SESSION['user'];

// Load progress XML
$progress = simplexml_load_file($progress_file) or die("Error: Failed to load progress.xml.");

// Find or create student progress
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

// Find or create course progress
$course_node = null;
foreach ($student_node->course as $pc) {
    if ($pc->course_id == $course_id) {
        $course_node = $pc;
        break;
    }
}
if (!$course_node) {
    $course_node = $student_node->addChild('course');
    $course_node->addChild('course_id', $course_id);
    $course_node->addChild('completed', '');
}

// Update completed resources
$completed = array_filter(explode(',', (string)$course_node->completed));
if (!in_array($resource_id, $completed)) {
    $completed[] = $resource_id;
    $course_node->completed = implode(',', $completed);
}

// Save progress XML
if ($progress->asXML($progress_file)) {
    header("Location: course.php?course_id=" . urlencode($course_id));
} else {
    die("Error: Failed to save progress.");
}
?>