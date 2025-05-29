<?php
$students_file = 'data/students.xml';
$courses_file = 'data/courses.xml';
$progress_file = 'data/progress.xml';

// Function to initialize or fix XML file
function initialize_xml($file, $root) {
    if (!file_exists($file) || !simplexml_load_file($file)) {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><$root></$root>");
        if ($root === 'students') {
            $admin = $xml->addChild('student');
            $admin->addChild('username', 'admin');
            $admin->addChild('password', md5('admin123'));
            $admin->addChild('email', 'admin@example.com');
            $admin->addChild('cpf', '12345678900');
            $admin->addChild('phone', '11999999999');
            $admin->addChild('name', 'Admin');
            $admin->addChild('status', 'active');
            $admin->addChild('role', 'admin');
            $admin->addChild('courses', '');
        }
        file_put_contents($file, $xml->asXML());
        chmod($file, 0644);
    } elseif ($root === 'students') {
        // Migrate existing students
        $xml = simplexml_load_file($file);
        foreach ($xml->student as $student) {
            if (!isset($student->email)) $student->addChild('email', '');
            if (!isset($student->cpf)) $student->addChild('cpf', '');
            if (!isset($student->phone)) $student->addChild('phone', '');
            if (!isset($student->name)) $student->addChild('name', $student->username);
        }
        $xml->asXML($file);
    }
}

// Initialize XML files
initialize_xml($students_file, 'students');
initialize_xml($courses_file, 'courses');
initialize_xml($progress_file, 'progress');
?>