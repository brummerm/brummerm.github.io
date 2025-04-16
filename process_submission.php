<?php
/**
 * Assignment Submission Handler
 * 
 * This script processes file uploads from the assignment submission form,
 * stores files on the server, and records submission data in the database.
 */

// Database connection settings
$db_host = 'localhost';      // Change to your database host
$db_name = 'cs_course_submissions';
$db_user = 'mbrummer';    // Change to your database username
$db_pass = 'F22plane!';    // Change to your database password

// File upload settings
$upload_dir = 'uploads/';    // Directory to store uploaded files
$max_file_size = 20 * 1024 * 1024; // 20MB limit
$allowed_extensions = array('py', 'zip', 'pdf', 'docx', 'txt');

// Create uploads directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Function to sanitize input data
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to get file extension
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Function to generate a unique file path
function generateUniqueFilePath($directory, $filename) {
    $pathInfo = pathinfo($filename);
    $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
    $basename = $pathInfo['filename'];
    
    // Replace spaces with underscores
    $basename = str_replace(' ', '_', $basename);
    
    // Start with the original filename
    $newFilename = $basename . $extension;
    $counter = 1;
    
    // If file exists, append a number
    while (file_exists($directory . $newFilename)) {
        $newFilename = $basename . '_' . $counter . $extension;
        $counter++;
    }
    
    return $directory . $newFilename;
}

// Initialize response array
$response = array(
    'success' => false,
    'message' => '',
    'redirect' => ''
);

// Process only POST requests with the correct form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Connect to database
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get form data
        $studentName = isset($_POST['student-name']) ? sanitize($_POST['student-name']) : '';
        $studentId = isset($_POST['student-id']) ? sanitize($_POST['student-id']) : '';
        $assignmentId = isset($_POST['assignment-number']) ? (int)$_POST['assignment-number'] : 0;
        $submissionDate = isset($_POST['submission-date']) ? sanitize($_POST['submission-date']) : date('Y-m-d');
        $comments = isset($_POST['comments']) ? sanitize($_POST['comments']) : '';
        
        // Validate required fields
        if (empty($studentName) || empty($studentId) || empty($assignmentId)) {
            throw new Exception("Missing required fields. Please fill in all required fields.");
        }
        
        // Check if student exists, if not create a new student record
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        
        if ($stmt->rowCount() == 0) {
            // Extract first and last name
            $nameParts = explode(' ', $studentName, 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
            
            // Generate a default email (this should be replaced with actual student email)
            $email = strtolower(str_replace(' ', '.', $studentName)) . '@student.example.com';
            
            // Insert new student
            $stmt = $pdo->prepare("INSERT INTO students (student_id, first_name, last_name, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$studentId, $firstName, $lastName, $email]);
        }
        
        // Create a submission record
        $stmt = $pdo->prepare("INSERT INTO submissions (student_id, assignment_id, submission_date, comments) 
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([$studentId, $assignmentId, $submissionDate, $comments]);
        $submissionId = $pdo->lastInsertId();
        
        // Create a submission directory
        $submissionDir = $upload_dir . $studentId . '_assignment' . $assignmentId . '_' . date('Ymd_His') . '/';
        if (!file_exists($submissionDir)) {
            mkdir($submissionDir, 0755, true);
        }
        
        // Process file uploads
        if (isset($_FILES['assignment-files']) && !empty($_FILES['assignment-files']['name'][0])) {
            // Handle multiple file uploads
            $files = $_FILES['assignment-files'];
            $fileCount = count($files['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                // Skip if no file was uploaded
                if ($files['error'][$i] == UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                
                // Check for errors
                if ($files['error'][$i] != UPLOAD_ERR_OK) {
                    throw new Exception("Error uploading file: " . $files['name'][$i]);
                }
                
                // Check file size
                if ($files['size'][$i] > $max_file_size) {
                    throw new Exception("File too large: " . $files['name'][$i]);
                }
                
                // Check file extension
                $fileExtension = getFileExtension($files['name'][$i]);
                if (!in_array($fileExtension, $allowed_extensions)) {
                    throw new Exception("Invalid file type: " . $files['name'][$i]);
                }
                
                // Generate a unique filename
                $fileName = basename($files['name'][$i]);
                $filePath = generateUniqueFilePath($submissionDir, $fileName);
                
                // Move the file
                if (!move_uploaded_file($files['tmp_name'][$i], $filePath)) {
                    throw new Exception("Failed to save file: " . $files['name'][$i]);
                }
                
                // Get the relative path for database storage
                $relativeFilePath = substr($filePath, strlen($_SERVER['DOCUMENT_ROOT']));
                
                // Record file information in database
                $stmt = $pdo->prepare("INSERT INTO submission_files 
                                      (submission_id, file_name, file_path, file_type, file_size) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $submissionId,
                    $fileName,
                    $relativeFilePath,
                    $files['type'][$i],
                    $files['size'][$i]
                ]);
            }
        } else {
            throw new Exception("No files were uploaded. Please select at least one file to upload.");
        }
        
        // Record submission history
        $stmt = $pdo->prepare("INSERT INTO submission_history 
                              (submission_id, action_type, details) 
                              VALUES (?, 'created', ?)");
        $stmt->execute([
            $submissionId,
            "Initial submission with " . $fileCount . " file(s) uploaded."
        ]);
        
        // Success
        $response['success'] = true;
        $response['message'] = "Assignment successfully submitted!";
        $response['redirect'] = "index.html?section=submit-assignment&status=success";
        
    } catch (Exception $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }
} else {
    $response['message'] = "Invalid request method.";
}

// If this is an AJAX request, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Otherwise, redirect with status
if ($response['success'] && !empty($response['redirect'])) {
    header("Location: " . $response['redirect']);
} else {
    // Redirect with error message
    header("Location: index.html?section=submit-assignment&status=error&message=" . urlencode($response['message']));
}
exit;