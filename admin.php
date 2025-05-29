<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit;
}

// Load XML files
$students = simplexml_load_file($students_file) or die("Error: Failed to load students.xml.");
$courses_file = 'data/courses.xml';
$courses = simplexml_load_file($courses_file) or die("Error: Failed to load $courses_file.");
$progress = simplexml_load_file($progress_file) or die("Error: Failed to load progress.xml.");

// Get current section
$section = $_GET['section'] ?? 'manage_students';
$search_query = $_POST['search'] ?? '';
$error = '';
$success = '';

// Handle student and course actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_student') {
        $student = $students->addChild('student');
        $student->addChild('username', $_POST['username']);
        $student->addChild('password', md5($_POST['password']));
        $student->addChild('email', $_POST['email']);
        $student->addChild('cpf', $_POST['cpf']);
        $student->addChild('phone', $_POST['phone']);
        $student->addChild('name', $_POST['name']);
        $student->addChild('status', 'inactive');
        $student->addChild('role', 'student');
        $student->addChild('courses', '');
        if ($students->asXML($students_file)) {
            $success = "Student added successfully.";
        } else {
            $error = "Failed to save student data.";
        }
    } elseif ($_POST['action'] == 'edit_student') {
        foreach ($students->student as $student) {
            if ($student->username == $_POST['username']) {
                $student->email = $_POST['email'];
                $student->cpf = $_POST['cpf'];
                $student->phone = $_POST['phone'];
                $student->name = $_POST['name'];
                $student->status = $_POST['status'];
                $student->courses = implode(',', $_POST['courses'] ?? []);
                if (!empty($_POST['password'])) {
                    $student->password = md5($_POST['password']);
                }
                break;
            }
        }
        if ($students->asXML($students_file)) {
            $success = "Student updated successfully.";
        } else {
            $error = "Failed to save student data.";
        }
    } elseif ($_POST['action'] == 'add_course') {
        $course_id = trim($_POST['id']);
        if (empty($course_id)) {
            $error = "Course ID is required.";
        } else {
            foreach ($courses->course as $c) {
                if ($c->id == $course_id) {
                    $error = "Course ID already exists.";
                    break;
                }
            }
        }
        if (!$error) {
            $course = $courses->addChild('course');
            $course->addChild('id', $course_id);
            $course->addChild('title', $_POST['title']);
            $course->addChild('description', $_POST['description']);
            $course->addChild('status', 'active');
            $resources = $course->addChild('resources');
            if (!empty($_POST['video_urls']) && is_array($_POST['video_urls'])) {
                foreach ($_POST['video_urls'] as $i => $url) {
                    if (!empty($url)) {
                        $video = $resources->addChild('video');
                        $video['url'] = $url;
                        $video['title'] = !empty($_POST['video_titles'][$i]) ? $_POST['video_titles'][$i] : 'Video ' . ($i + 1);
                    }
                }
            }
            if (!empty($_POST['pdf_urls']) && is_array($_POST['pdf_urls'])) {
                foreach ($_POST['pdf_urls'] as $i => $url) {
                    if (!empty($url)) {
                        $pdf = $resources->addChild('pdf');
                        $pdf['url'] = $url;
                        $pdf['title'] = !empty($_POST['pdf_titles'][$i]) ? $_POST['pdf_titles'][$i] : 'PDF ' . ($i + 1);
                    }
                }
            }
            $resources->addChild('quiz', $_POST['quiz'] ?? '');
            $resources->addChild('flashcard', $_POST['flashcard'] ?? '');

            // Criar DOMDocument para formatar o XML
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;
            $coursesElement = $dom->createElement('courses');
            $dom->appendChild($coursesElement);

            // Adicionar cada curso
            foreach ($courses->course as $c) {
                $courseElement = $dom->createElement('course');
                
                // Adicionar comentário
                $comment = $dom->createComment(' Course entry ');
                $coursesElement->appendChild($comment);
                
                // Adicionar elementos na ordem especificada
                $courseElement->appendChild($dom->createElement('id', htmlspecialchars($c->id)));
                $courseElement->appendChild($dom->createElement('title', htmlspecialchars($c->title)));
                $courseElement->appendChild($dom->createElement('description', htmlspecialchars($c->description)));
                $courseElement->appendChild($dom->createElement('status', htmlspecialchars($c->status)));
                
                $resourcesElement = $dom->createElement('resources');
                if ($c->resources->quiz) {
                    $resourcesElement->appendChild($dom->createElement('quiz', htmlspecialchars($c->resources->quiz)));
                }
                if ($c->resources->flashcard) {
                    $resourcesElement->appendChild($dom->createElement('flashcard', htmlspecialchars($c->resources->flashcard)));
                }
                foreach ($c->resources->video as $video) {
                    $videoElement = $dom->createElement('video');
                    $videoElement->setAttribute('url', htmlspecialchars($video['url']));
                    $videoElement->setAttribute('title', htmlspecialchars($video['title']));
                    $resourcesElement->appendChild($videoElement);
                }
                foreach ($c->resources->pdf as $pdf) {
                    $pdfElement = $dom->createElement('pdf');
                    $pdfElement->setAttribute('url', htmlspecialchars($pdf['url']));
                    $pdfElement->setAttribute('title', htmlspecialchars($pdf['title']));
                    $resourcesElement->appendChild($pdfElement);
                }
                $courseElement->appendChild($resourcesElement);
                $coursesElement->appendChild($courseElement);
            }

            if ($dom->save($courses_file)) {
                $success = "Course added successfully.";
            } else {
                $error = "Failed to save course data.";
            }
        }
    } elseif ($_POST['action'] == 'edit_course') {
        // Debug logging
        $debug_log = "Edit Course ID: {$_POST['course_id']}\n";
        $debug_log .= "POST Data: " . json_encode($_POST) . "\n";
        $debug_log .= "Before Update XML: " . $courses->asXML() . "\n";
        
        foreach ($courses->course as $course) {
            if ($course->id == $_POST['course_id']) {
                $course->title = $_POST['title'];
                $course->description = $_POST['description'];
                $course->status = $_POST['status'];
                
                // Verificar se há pelo menos um recurso
                $hasResources = !empty($_POST['quiz']) || !empty($_POST['flashcard']);
                if (!empty($_POST['video_urls']) && is_array($_POST['video_urls'])) {
                    foreach ($_POST['video_urls'] as $url) {
                        if (!empty($url)) {
                            $hasResources = true;
                            break;
                        }
                    }
                }
                if (!empty($_POST['pdf_urls']) && is_array($_POST['pdf_urls'])) {
                    foreach ($_POST['pdf_urls'] as $url) {
                        if (!empty($url)) {
                            $hasResources = true;
                            break;
                        }
                    }
                }
                
                if (!$hasResources) {
                    $error = "At least one resource (video, PDF, quiz, or flashcard) must be provided.";
                    file_put_contents('/tmp/debug_edit_course.log', $debug_log . "Error: No resources provided\n----\n", FILE_APPEND);
                    break;
                }
                
                // Clear existing videos and PDFs
                unset($course->resources->video);
                unset($course->resources->pdf);
                
                // Add videos
                if (!empty($_POST['video_urls']) && is_array($_POST['video_urls'])) {
                    foreach ($_POST['video_urls'] as $i => $url) {
                        if (!empty($url)) {
                            $video = $course->resources->addChild('video');
                            $video['url'] = $url;
                            $video['title'] = !empty($_POST['video_titles'][$i]) ? $_POST['video_titles'][$i] : 'Video ' . ($i + 1);
                            $debug_log .= "Added Video: URL=$url, Title=" . $video['title'] . "\n";
                        }
                    }
                }
                
                // Add PDFs
                if (!empty($_POST['pdf_urls']) && is_array($_POST['pdf_urls'])) {
                    foreach ($_POST['pdf_urls'] as $i => $url) {
                        if (!empty($url)) {
                            $pdf = $course->resources->addChild('pdf');
                            $pdf['url'] = $url;
                            $pdf['title'] = !empty($_POST['pdf_titles'][$i]) ? $_POST['pdf_titles'][$i] : 'PDF ' . ($i + 1);
                            $debug_log .= "Added PDF: URL=$url, Title=" . $pdf['title'] . "\n";
                        }
                    }
                }
                
                // Update quiz and flashcard
                $course->resources->quiz = $_POST['quiz'] ?? '';
                $course->resources->flashcard = $_POST['flashcard'] ?? '';
                
                // Check file permissions
                $is_writable = is_writable($courses_file);
                $debug_log .= "File Writable: " . ($is_writable ? 'Yes' : 'No') . "\n";
                $debug_log .= "File Owner: " . fileowner($courses_file) . ", Server User: " . posix_geteuid() . "\n";
                
                // Criar DOMDocument para formatar o XML
                $dom = new DOMDocument('1.0', 'UTF-8');
                $dom->formatOutput = true;
                $coursesElement = $dom->createElement('courses');
                $dom->appendChild($coursesElement);

                // Adicionar cada curso
                foreach ($courses->course as $c) {
                    $courseElement = $dom->createElement('course');
                    
                    // Adicionar comentário
                    $comment = $dom->createComment(' Course entry ');
                    $coursesElement->appendChild($comment);
                    
                    // Adicionar elementos na ordem especificada
                    $courseElement->appendChild($dom->createElement('id', htmlspecialchars($c->id)));
                    $courseElement->appendChild($dom->createElement('title', htmlspecialchars($c->title)));
                    $courseElement->appendChild($dom->createElement('description', htmlspecialchars($c->description)));
                    $courseElement->appendChild($dom->createElement('status', htmlspecialchars($c->status)));
                    
                    $resourcesElement = $dom->createElement('resources');
                    if ($c->resources->quiz) {
                        $resourcesElement->appendChild($dom->createElement('quiz', htmlspecialchars($c->resources->quiz)));
                    }
                    if ($c->resources->flashcard) {
                        $resourcesElement->appendChild($dom->createElement('flashcard', htmlspecialchars($c->resources->flashcard)));
                    }
                    foreach ($c->resources->video as $video) {
                        $videoElement = $dom->createElement('video');
                        $videoElement->setAttribute('url', htmlspecialchars($video['url']));
                        $videoElement->setAttribute('title', htmlspecialchars($video['title']));
                        $resourcesElement->appendChild($videoElement);
                    }
                    foreach ($c->resources->pdf as $pdf) {
                        $pdfElement = $dom->createElement('pdf');
                        $pdfElement->setAttribute('url', htmlspecialchars($pdf['url']));
                        $pdfElement->setAttribute('title', htmlspecialchars($pdf['title']));
                        $resourcesElement->appendChild($pdfElement);
                    }
                    $courseElement->appendChild($resourcesElement);
                    $coursesElement->appendChild($courseElement);
                }

                $debug_log .= "After Update XML: " . $dom->saveXML() . "\n";
                if ($is_writable && $dom->save($courses_file)) {
                    $success = "Course updated successfully.";
                } else {
                    $error = "Failed to save course data to XML. Check file permissions or path.";
                    error_log("Failed to write to $courses_file: " . print_r(error_get_last(), true));
                    $debug_log .= "Save Failed: " . print_r(error_get_last(), true) . "\n";
                }
                
                // Write debug log
                file_put_contents('/tmp/debug_edit_course.log', $debug_log . "----\n", FILE_APPEND);
                break;
            }
        }
    } elseif ($_POST['action'] == 'delete_course') {
        foreach ($courses->course as $course) {
            if ($course->id == $_POST['course_id']) {
                unset($course[0]);
                break;
            }
        }

        // Criar DOMDocument para formatar o XML
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $coursesElement = $dom->createElement('courses');
        $dom->appendChild($coursesElement);

        // Adicionar cada curso
        foreach ($courses->course as $c) {
            $courseElement = $dom->createElement('course');
            
            // Adicionar comentário
            $comment = $dom->createComment(' Course entry ');
            $coursesElement->appendChild($comment);
            
            // Adicionar elementos na ordem especificada
            $courseElement->appendChild($dom->createElement('id', htmlspecialchars($c->id)));
            $courseElement->appendChild($dom->createElement('title', htmlspecialchars($c->title)));
            $courseElement->appendChild($dom->createElement('description', htmlspecialchars($c->description)));
            $courseElement->appendChild($dom->createElement('status', htmlspecialchars($c->status)));
            
            $resourcesElement = $dom->createElement('resources');
            if ($c->resources->quiz) {
                $resourcesElement->appendChild($dom->createElement('quiz', htmlspecialchars($c->resources->quiz)));
            }
            if ($c->resources->flashcard) {
                $resourcesElement->appendChild($dom->createElement('flashcard', htmlspecialchars($c->resources->flashcard)));
            }
            foreach ($c->resources->video as $video) {
                $videoElement = $dom->createElement('video');
                $videoElement->setAttribute('url', htmlspecialchars($video['url']));
                $videoElement->setAttribute('title', htmlspecialchars($video['title']));
                $resourcesElement->appendChild($videoElement);
            }
            foreach ($c->resources->pdf as $pdf) {
                $pdfElement = $dom->createElement('pdf');
                $pdfElement->setAttribute('url', htmlspecialchars($pdf['url']));
                $pdfElement->setAttribute('title', htmlspecialchars($pdf['title']));
                $resourcesElement->appendChild($pdfElement);
            }
            $courseElement->appendChild($resourcesElement);
            $coursesElement->appendChild($courseElement);
        }

        if ($dom->save($courses_file)) {
            $success = "Course deleted successfully.";
        } else {
            $error = "Failed to delete course.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - AWS Training Portal</title>
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
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Student</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?section=add_student">Add Student</a></li>
                                <li><a class="dropdown-item" href="?section=manage_students">Manage Students</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Courses</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?section=add_course">Add Course</a></li>
                                <li><a class="dropdown-item" href="?section=manage_courses">Manage Courses</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Reports</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?section=report_students">Students</a></li>
                            </ul>
                        </li>
                    </ul>
                    <form class="d-flex ms-auto" method="POST" action="?section=<?php echo htmlspecialchars($section); ?>">
                        <input class="form-control me-2" type="search" name="search" placeholder="Search" value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-outline-light" type="submit">Search</button>
                    </form>
                    <ul class="navbar-nav ms-2">
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container mt-5">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($section == 'add_student'): ?>
                <h3>Add Student</h3>
                <form method="POST" class="mb-5">
                    <input type="hidden" name="action" value="add_student">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control" name="cpf" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Student</button>
                </form>
            <?php elseif ($section == 'manage_students'): ?>
                <h3>Manage Students</h3>
                <table class="table table-bordered mb-5">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>CPF</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Courses</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students->student as $student): ?>
                            <?php
                            if ($search_query && 
                                stripos($student->name, $search_query) === false &&
                                stripos($student->username, $search_query) === false &&
                                stripos($student->email, $search_query) === false &&
                                stripos($student->cpf, $search_query) === false &&
                                stripos($student->phone, $search_query) === false) {
                                continue;
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student->name); ?></td>
                                <td><?php echo htmlspecialchars($student->username); ?></td>
                                <td><?php echo htmlspecialchars($student->email); ?></td>
                                <td><?php echo htmlspecialchars($student->cpf); ?></td>
                                <td><?php echo htmlspecialchars($student->phone); ?></td>
                                <td><?php echo htmlspecialchars($student->status); ?></td>
                                <td><?php echo htmlspecialchars($student->courses); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editStudentModal<?php echo htmlspecialchars($student->username); ?>">Edit</button>
                                </td>
                            </tr>
                            <!-- Edit Student Modal -->
                            <div class="modal fade" id="editStudentModal<?php echo htmlspecialchars($student->username); ?>" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Student: <?php echo htmlspecialchars($student->name); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit_student">
                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($student->username); ?>">
                                                <div class="mb-3">
                                                    <label for="name" class="form-label">Name</label>
                                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($student->name); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email</label>
                                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($student->email); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="cpf" class="form-label">CPF</label>
                                                    <input type="text" class="form-control" name="cpf" value="<?php echo htmlspecialchars($student->cpf); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="phone" class="form-label">Phone</label>
                                                    <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($student->phone); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                                                    <input type="password" class="form-control" name="password" placeholder="Enter new password">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select name="status" class="form-control">
                                                        <option value="active" <?php echo $student->status == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $student->status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Enrolled Courses</label>
                                                    <?php foreach ($courses->course as $course): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input" name="courses[]" type="checkbox" value="<?php echo htmlspecialchars($course->id); ?>"
                                                                <?php echo in_array((string)$course->id, explode(',', (string)$student->courses)) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label"><?php echo htmlspecialchars($course->title); ?> (ID: <?php echo htmlspecialchars($course->id); ?>)</label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-primary">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($section == 'add_course'): ?>
                <h3>Add Course</h3>
                <form method="POST" class="mb-5" id="addCourseForm">
                    <input type="hidden" name="action" value="add_course">
                    <div class="mb-3">
                        <label for="id" class="form-label">Course ID</label>
                        <input type="text" class="form-control" name="id" required placeholder="e.g., AWS101">
                    </div>
                    <div class="mb-3">
                        <label for="title" class="form-label">Course Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Video Links</label>
                        <div id="videoFields">
                            <div class="input-group mb-2">
                                <input type="url" class="form-control" name="video_urls[]" placeholder="Video URL">
                                <input type="text" class="form-control" name="video_titles[]" placeholder="Video Title">
                                <button type="button" class="btn btn-danger remove-video">Clear</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" id="addVideo">Add Video</button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">PDF Links</label>
                        <div id="pdfFields">
                            <div class="input-group mb-2">
                                <input type="url" class="form-control" name="pdf_urls[]" placeholder="PDF URL">
                                <input type="text" class="form-control" name="pdf_titles[]" placeholder="PDF Title">
                                <button type="button" class="btn btn-danger remove-pdf">Clear</button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" id="addPdf">Add PDF</button>
                    </div>
                    <div class="mb-3">
                        <label for="quiz" class="form-label">Quiz URL</label>
                        <input type="url" class="form-control" name="quiz">
                    </div>
                    <div class="mb-3">
                        <label for="flashcard" class="form-label">Flashcard URL</label>
                        <input type="url" class="form-control" name="flashcard">
                    </div>
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </form>
            <?php elseif ($section == 'manage_courses'): ?>
                <h3>Manage Courses</h3>
                <a href="?section=add_course" class="btn btn-primary mb-3">Novo Curso</a>
                <table class="table table-bordered mb-5">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses->course as $course): ?>
                            <?php
                            if ($search_query && stripos($course->title, $search_query) === false && stripos($course->id, $search_query) === false) {
                                continue;
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course->id); ?></td>
                                <td><?php echo htmlspecialchars($course->title); ?></td>
                                <td><?php echo htmlspecialchars($course->status); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editCourseModal<?php echo htmlspecialchars($course->id); ?>">Edit</button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_course">
                                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course->id); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <!-- Edit Course Modal -->
                            <div class="modal fade" id="editCourseModal<?php echo htmlspecialchars($course->id); ?>" tabindex="-1" aria-labelledby="editCourseModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <form method="POST" id="editCourseForm<?php echo htmlspecialchars($course->id); ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Course: <?php echo htmlspecialchars($course->title); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit_course">
                                                <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course->id); ?>">
                                                <div class="mb-3">
                                                    <label for="id" class="form-label">Course ID (read-only)</label>
                                                    <input type="text" class="form-control" name="id" value="<?php echo htmlspecialchars($course->id); ?>" disabled>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="title" class="form-label">Title</label>
                                                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($course->title); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="description" class="form-label">Description</label>
                                                    <textarea class="form-control" name="description" required><?php echo htmlspecialchars($course->description); ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select name="status" class="form-control">
                                                        <option value="active" <?php echo $course->status == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $course->status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Video Links</label>
                                                    <div id="editVideoFields<?php echo htmlspecialchars($course->id); ?>">
                                                        <?php foreach ($course->resources->video as $video): ?>
                                                            <div class="input-group mb-2">
                                                                <input type="url" class="form-control" name="video_urls[]" value="<?php echo htmlspecialchars($video['url']); ?>" placeholder="Video URL">
                                                                <input type="text" class="form-control" name="video_titles[]" value="<?php echo htmlspecialchars($video['title']); ?>" placeholder="Video Title">
                                                                <button type="button" class="btn btn-danger remove-video">Clear</button>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <button type="button" class="btn btn-secondary add-video-btn" data-course-id="<?php echo htmlspecialchars($course->id); ?>">Add Video</button>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">PDF Links</label>
                                                    <div id="editPdfFields<?php echo htmlspecialchars($course->id); ?>">
                                                        <?php foreach ($course->resources->pdf as $pdf): ?>
                                                            <div class="input-group mb-2">
                                                                <input type="url" class="form-control" name="pdf_urls[]" value="<?php echo htmlspecialchars($pdf['url']); ?>" placeholder="PDF URL">
                                                                <input type="text" class="form-control" name="pdf_titles[]" value="<?php echo htmlspecialchars($pdf['title']); ?>" placeholder="PDF Title">
                                                                <button type="button" class="btn btn-danger remove-pdf">Clear</button>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <button type="button" class="btn btn-secondary add-pdf-btn" data-course-id="<?php echo htmlspecialchars($course->id); ?>">Add PDF</button>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="quiz" class="form-label">Quiz URL</label>
                                                    <input type="url" class="form-control" name="quiz" value="<?php echo htmlspecialchars($course->resources->quiz); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="flashcard" class="form-label">Flashcard URL</label>
                                                    <input type="url" class="form-control" name="flashcard" value="<?php echo htmlspecialchars($course->resources->flashcard); ?>">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($section == 'report_students'): ?>
                <h3>Student Report</h3>
                <table class="table table-bordered mb-5">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>CPF</th>
                            <th>Phone</th>
                            <th>Enrolled Courses</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students->student as $student): ?>
                            <?php
                            if ($search_query && 
                                stripos($student->name, $search_query) === false &&
                                stripos($student->username, $search_query) === false &&
                                stripos($student->email, $search_query) === false &&
                                stripos($student->cpf, $search_query) === false &&
                                stripos($student->phone, $search_query) === false) {
                                continue;
                            }
                            $enrolled_courses = array_filter(explode(',', (string)$student->courses));
                            $progress_data = [];
                            foreach ($progress->student as $p) {
                                if ($p->username == $student->username) {
                                    foreach ($p->course as $pc) {
                                        $progress_data[(string)$pc->course_id] = array_filter(explode(',', (string)$pc->completed));
                                    }
                                }
                            }
                            $course_progress = [];
                            foreach ($courses->course as $course) {
                                if (in_array((string)$course->id, $enrolled_courses)) {
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
                                    $total = count($resources);
                                    $completed = isset($progress_data[(string)$course->id]) ? $progress_data[(string)$course->id] : [];
                                    $completed_count = count(array_intersect($resources, $completed));
                                    $percentage = ($total > 0) ? ($completed_count / $total) * 100 : 0;
                                    $course_progress[] = [
                                        'title' => (string)$course->title,
                                        'percentage' => round($percentage, 2)
                                    ];
                                }
                            }
                            $course_titles = array_map(function($c) { return htmlspecialchars($c['title']); }, $course_progress);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student->name); ?></td>
                                <td><?php echo htmlspecialchars($student->username); ?></td>
                                <td><?php echo htmlspecialchars($student->email); ?></td>
                                <td><?php echo htmlspecialchars($student->cpf); ?></td>
                                <td><?php echo htmlspecialchars($student->phone); ?></td>
                                <td><?php echo implode(', ', $course_titles); ?></td>
                                <td>
                                    <?php if (empty($course_progress)): ?>
                                        No courses enrolled
                                    <?php else: ?>
                                        <ul class="list-unstyled">
                                            <?php foreach ($course_progress as $cp): ?>
                                                <li><?php echo htmlspecialchars($cp['title']); ?>: <?php echo $cp['percentage']; ?>%</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3">
        <p>© <?php echo date('Y'); ?> AWS Training Portal. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Adicionar campo de vídeo para o formulário de adição de curso
        function addVideoField() {
            const videoFields = document.getElementById('videoFields');
            const newField = document.createElement('div');
            newField.className = 'input-group mb-2';
            newField.innerHTML = `
                <input type="url" class="form-control" name="video_urls[]" placeholder="Video URL">
                <input type="text" class="form-control" name="video_titles[]" placeholder="Video Title">
                <button type="button" class="btn btn-danger remove-video">Clear</button>
            `;
            videoFields.appendChild(newField);
        }

        document.getElementById('addVideo')?.addEventListener('click', addVideoField);

        // Adicionar campo de PDF para o formulário de adição de curso
        document.getElementById('addPdf')?.addEventListener('click', function() {
            const pdfFields = document.getElementById('pdfFields');
            const newField = document.createElement('div');
            newField.className = 'input-group mb-2';
            newField.innerHTML = `
                <input type="url" class="form-control" name="pdf_urls[]" placeholder="PDF URL">
                <input type="text" class="form-control" name="pdf_titles[]" placeholder="PDF Title">
                <button type="button" class="btn btn-danger remove-pdf">Clear</button>
            `;
            pdfFields.appendChild(newField);
        });

        // Adicionar campo de vídeo para os modais de edição
        document.querySelectorAll('.add-video-btn').forEach(button => {
            button.addEventListener('click', function() {
                const courseId = this.getAttribute('data-course-id');
                const videoFields = document.getElementById('editVideoFields' + courseId);
                const newField = document.createElement('div');
                newField.className = 'input-group mb-2';
                newField.innerHTML = `
                    <input type="url" class="form-control" name="video_urls[]" placeholder="Video URL">
                    <input type="text" class="form-control" name="video_titles[]" placeholder="Video Title">
                    <button type="button" class="btn btn-danger remove-video">Clear</button>
                `;
                videoFields.appendChild(newField);
            });
        });

        // Adicionar campo de PDF para os modais de edição
        document.querySelectorAll('.add-pdf-btn').forEach(button => {
            button.addEventListener('click', function() {
                const courseId = this.getAttribute('data-course-id');
                const pdfFields = document.getElementById('editPdfFields' + courseId);
                const newField = document.createElement('div');
                newField.className = 'input-group mb-2';
                newField.innerHTML = `
                    <input type="url" class="form-control" name="pdf_urls[]" placeholder="PDF URL">
                    <input type="text" class="form-control" name="pdf_titles[]" placeholder="PDF Title">
                    <button type="button" class="btn btn-danger remove-pdf">Clear</button>
                `;
                pdfFields.appendChild(newField);
            });
        });

        // Limpar campos de vídeo
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-video')) {
                const inputs = event.target.parentElement.querySelectorAll('input');
                inputs.forEach(input => input.value = '');
            }
        });

        // Limpar campos de PDF
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-pdf')) {
                const inputs = event.target.parentElement.querySelectorAll('input');
                inputs.forEach(input => input.value = '');
            }
        });

        // Validar formulário antes do envio
        document.querySelectorAll('form[id^="editCourseForm"]').forEach(form => {
            form.addEventListener('submit', function(event) {
                const videoUrls = form.querySelectorAll('input[name="video_urls[]"]');
                const pdfUrls = form.querySelectorAll('input[name="pdf_urls[]"]');
                let hasValidInput = false;
                videoUrls.forEach(input => {
                    if (input.value.trim() !== '') hasValidInput = true;
                });
                pdfUrls.forEach(input => {
                    if (input.value.trim() !== '') hasValidInput = true;
                });
                if (!hasValidInput && !form.querySelector('input[name="quiz"]').value && !form.querySelector('input[name="flashcard"]').value) {
                    alert('Please provide at least one resource (video, PDF, quiz, or flashcard).');
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>