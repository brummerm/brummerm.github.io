<?php
/**
 * Submissions Admin Panel
 * 
 * This script provides an interface for instructors to view, grade, and manage student submissions.
 * 
 * Security Note: In a production environment, you should implement proper authentication
 * for this admin panel before deployment.
 */

// Database connection settings
$db_host = 'localhost';      // Change to your database host
$db_name = 'cs_course_submissions';
$db_user = 'mbrummer';    // Change to your database username
$db_pass = 'F22plane!';    // Change to your database password

// Initialize variables
$submissions = [];
$message = '';
$messageType = '';
$viewSubmission = null;
$selectedAssignment = 0;
$selectedStatus = '';

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Handle grading submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade') {
        $submissionId = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;
        $grade = isset($_POST['grade']) ? (float)$_POST['grade'] : 0;
        $feedback = isset($_POST['feedback']) ? $_POST['feedback'] : '';
        
        if ($submissionId > 0) {
            // Update the submission grade and status
            $stmt = $pdo->prepare("UPDATE submissions SET grade = ?, status = 'graded', feedback = ?, graded_by = ?, graded_at = NOW() WHERE submission_id = ?");
            $stmt->execute([$grade, $feedback, 'admin', $submissionId]);
            
            // Add entry to submission history
            $stmt = $pdo->prepare("INSERT INTO submission_history (submission_id, action_type, details) VALUES (?, 'graded', ?)");
            $stmt->execute([$submissionId, "Graded with score: $grade"]);
            
            $message = "Submission has been graded successfully!";
            $messageType = "success";
        }
    }
    
    // View specific submission
    if (isset($_GET['view']) && is_numeric($_GET['view'])) {
        $submissionId = (int)$_GET['view'];
        
        // Get submission details
        $stmt = $pdo->prepare("
            SELECT s.*, a.title as assignment_title, a.total_points, 
                   CONCAT(st.first_name, ' ', st.last_name) as student_name,
                   st.email as student_email
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.assignment_id
            JOIN students st ON s.student_id = st.student_id
            WHERE s.submission_id = ?
        ");
        $stmt->execute([$submissionId]);
        $viewSubmission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($viewSubmission) {
            // Get files for this submission
            $stmt = $pdo->prepare("SELECT * FROM submission_files WHERE submission_id = ?");
            $stmt->execute([$submissionId]);
            $viewSubmission['files'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get submission history
            $stmt = $pdo->prepare("SELECT * FROM submission_history WHERE submission_id = ? ORDER BY action_date DESC");
            $stmt->execute([$submissionId]);
            $viewSubmission['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Filter by assignment and status if provided
        $selectedAssignment = isset($_GET['assignment']) ? (int)$_GET['assignment'] : 0;
        $selectedStatus = isset($_GET['status']) ? $_GET['status'] : '';
        
        // Build query conditions based on filters
        $conditions = [];
        $params = [];
        
        if ($selectedAssignment > 0) {
            $conditions[] = "s.assignment_id = ?";
            $params[] = $selectedAssignment;
        }
        
        if (!empty($selectedStatus)) {
            $conditions[] = "s.status = ?";
            $params[] = $selectedStatus;
        }
        
        $whereClause = "";
        if (!empty($conditions)) {
            $whereClause = "WHERE " . implode(" AND ", $conditions);
        }
        
        // Get list of submissions
        $query = "
            SELECT s.submission_id, s.student_id, s.submission_date, s.status, s.grade,
                   a.title as assignment_title, a.total_points,
                   CONCAT(st.first_name, ' ', st.last_name) as student_name
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.assignment_id
            JOIN students st ON s.student_id = st.student_id
            $whereClause
            ORDER BY s.submission_date DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get assignments for filter dropdown
        $stmt = $pdo->query("SELECT assignment_id, title FROM assignments ORDER BY assignment_id");
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "error";
}

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Helper function to format date
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('F j, Y g:i A');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Submission Admin Panel</title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: var(--light-color);
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        h1, h2, h3 {
            color: var(--secondary-color);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .filters {
            background-color: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .filters form {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .filters select, .filters button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .filters button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .filters button:hover {
            background-color: #2980b9;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: var(--primary-color);
            color: white;
        }
        
        tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .btn {
            display: inline-block;
            padding: 8px 12px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 0.9em;
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .status-submitted {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-graded {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-late {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-resubmitted {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .submission-details {
            background-color: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .submission-meta {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .submission-files {
            margin-bottom: 20px;
        }
        
        .file-item {
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .file-info {
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .grading-form {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-group textarea {
            height: 100px;
        }
        
        .history-item {
            padding: 10px;
            margin-bottom: 10px;
            border-left: 3px solid var(--primary-color);
            background-color: #f8f9fa;
        }
        
        .history-date {
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Course Submission Admin Panel</h1>
            <a href="../index.html" class="btn">Back to Course Website</a>
        </header>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($viewSubmission): ?>
            <!-- Single Submission View -->
            <a href="admin.php" class="back-link">&larr; Back to All Submissions</a>
            
            <div class="submission-details">
                <div class="submission-meta">
                    <h2>Submission Details</h2>
                    <p><strong>Assignment:</strong> <?php echo htmlspecialchars($viewSubmission['assignment_title']); ?></p>
                    <p><strong>Student:</strong> <?php echo htmlspecialchars($viewSubmission['student_name']); ?> (<?php echo htmlspecialchars($viewSubmission['student_id']); ?>)</p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($viewSubmission['student_email']); ?></p>
                    <p><strong>Submitted:</strong> <?php echo formatDate($viewSubmission['submission_date']); ?></p>
                    <p><strong>Status:</strong> <span class="status status-<?php echo $viewSubmission['status']; ?>"><?php echo ucfirst($viewSubmission['status']); ?></span></p>
                    
                    <?php if (!empty($viewSubmission['comments'])): ?>
                        <h3>Student Comments</h3>
                        <div class="comments">
                            <?php echo nl2br(htmlspecialchars($viewSubmission['comments'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="submission-files">
                    <h3>Submitted Files</h3>
                    <?php if (!empty($viewSubmission['files'])): ?>
                        <?php foreach ($viewSubmission['files'] as $file): ?>
                            <div class="file-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($file['file_name']); ?></strong>
                                    <div class="file-info">
                                        <?php echo formatFileSize($file['file_size']); ?> | 
                                        <?php echo htmlspecialchars($file['file_type']); ?> | 
                                        Uploaded: <?php echo formatDate($file['upload_date']); ?>
                                    </div>
                                </div>
                                <a href="<?php echo htmlspecialchars($file['file_path']); ?>" class="btn btn-small" target="_blank">Download</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No files submitted.</p>
                    <?php endif; ?>
                </div>
                
                <?php if ($viewSubmission['status'] === 'graded'): ?>
                    <div class="grading-result">
                        <h3>Grading Information</h3>
                        <p><strong>Grade:</strong> <?php echo $viewSubmission['grade']; ?> / <?php echo $viewSubmission['total_points']; ?></p>
                        <p><strong>Graded by:</strong> <?php echo htmlspecialchars($viewSubmission['graded_by']); ?></p>
                        <p><strong>Graded on:</strong> <?php echo formatDate($viewSubmission['graded_at']); ?></p>
                        
                        <?php if (!empty($viewSubmission['feedback'])): ?>
                            <h4>Feedback</h4>
                            <div class="feedback">
                                <?php echo nl2br(htmlspecialchars($viewSubmission['feedback'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grading-form">
                        <h3>Grade Submission</h3>
                        <form method="POST" action="admin.php">
                            <input type="hidden" name="action" value="grade">
                            <input type="hidden" name="submission_id" value="<?php echo $viewSubmission['submission_id']; ?>">
                            
                            <div class="form-group">
                                <label for="grade">Grade (out of <?php echo $viewSubmission['total_points']; ?>):</label>
                                <input type="number" id="grade" name="grade" min="0" max="<?php echo $viewSubmission['total_points']; ?>" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="feedback">Feedback:</label>
                                <textarea id="feedback" name="feedback" placeholder="Provide feedback for the student..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success">Submit Grade</button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($viewSubmission['history'])): ?>
                    <div class="submission-history">
                        <h3>Submission History</h3>
                        <?php foreach ($viewSubmission['history'] as $history): ?>
                            <div class="history-item">
                                <div class="history-date"><?php echo formatDate($history['action_date']); ?></div>
                                <strong><?php echo ucfirst($history['action_type']); ?></strong>: 
                                <?php echo htmlspecialchars($history['details']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Submissions List View -->
            <div class="filters">
                <form method="GET" action="admin.php">
                    <div>
                        <label for="assignment">Assignment:</label>
                        <select name="assignment" id="assignment">
                            <option value="0">All Assignments</option>
                            <?php foreach ($assignments as $assignment): ?>
                                <option value="<?php echo $assignment['assignment_id']; ?>" <?php echo ($selectedAssignment == $assignment['assignment_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($assignment['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status">Status:</label>
                        <select name="status" id="status">
                            <option value="">All Statuses</option>
                            <option value="submitted" <?php echo ($selectedStatus === 'submitted') ? 'selected' : ''; ?>>Submitted</option>
                            <option value="graded" <?php echo ($selectedStatus === 'graded') ? 'selected' : ''; ?>>Graded</option>
                            <option value="late" <?php echo ($selectedStatus === 'late') ? 'selected' : ''; ?>>Late</option>
                            <option value="resubmitted" <?php echo ($selectedStatus === 'resubmitted') ? 'selected' : ''; ?>>Resubmitted</option>
                        </select>
                    </div>
                    
                    <button type="submit">Apply Filters</button>
                </form>
            </div>
            
            <?php if (empty($submissions)): ?>
                <div class="alert alert-info">No submissions found matching the current filters.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Assignment</th>
                            <th>Submission Date</th>
                            <th>Status</th>
                            <th>Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $submission): ?>
                            <tr>
                                <td><?php echo $submission['submission_id']; ?></td>
                                <td><?php echo htmlspecialchars($submission['student_name']) . ' (' . $submission['student_id'] . ')'; ?></td>
                                <td><?php echo htmlspecialchars($submission['assignment_title']); ?></td>
                                <td><?php echo formatDate($submission['submission_date']); ?></td>
                                <td>
                                    <span class="status status-<?php echo $submission['status']; ?>">
                                        <?php echo ucfirst($submission['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($submission['status'] === 'graded'): ?>
                                        <?php echo $submission['grade']; ?> / <?php echo $submission['total_points']; ?>
                                    <?php else: ?>
                                        Not graded
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="admin.php?view=<?php echo $submission['submission_id']; ?>" class="btn btn-small">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>