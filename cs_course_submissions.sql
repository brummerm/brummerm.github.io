-- Create database for course submissions
CREATE DATABASE IF NOT EXISTS cs_course_submissions;
USE cs_course_submissions;

-- Students table
CREATE TABLE students (
    student_id VARCHAR(20) PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Assignments table
CREATE TABLE assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    due_date DATE NOT NULL,
    total_points INT DEFAULT 100,
    weight_percentage DECIMAL(5,2) DEFAULT 10.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default assignments based on syllabus
INSERT INTO assignments (title, description, due_date, total_points, weight_percentage) VALUES
('Assignment 1: Introduction to Python', 'Basic programming concepts, Python syntax, variables, data types, and simple input/output operations.', '2025-02-15', 100, 5.00),
('Assignment 2: Control Structures', 'Conditional statements, loops, and program flow control. Introduction to algorithm design.', '2025-03-01', 100, 5.00),
('Assignment 3: Functions and Modularity', 'Function definition, parameters, return values, scope, and built-in functions.', '2025-03-15', 100, 5.00),
('Assignment 4: Data Structures', 'Lists, dictionaries, sets, and tuples. Working with complex data.', '2025-04-01', 100, 5.00),
('Assignment 5: File Operations', 'Reading from and writing to files. Data processing techniques.', '2025-04-15', 100, 5.00),
('Assignment 6: Object-Oriented Programming', 'Classes, objects, inheritance, polymorphism, and encapsulation.', '2025-05-01', 100, 5.00),
('Final Project', 'Apply learned concepts to develop a comprehensive programming solution to a real-world problem.', '2025-05-15', 200, 30.00);

-- Submissions table
CREATE TABLE submissions (
    submission_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    assignment_id INT NOT NULL,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    comments TEXT,
    status ENUM('submitted', 'graded', 'late', 'resubmitted') DEFAULT 'submitted',
    grade DECIMAL(5,2) DEFAULT NULL,
    feedback TEXT,
    graded_by VARCHAR(50) DEFAULT NULL,
    graded_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id)
);

-- Submission files table (for storing file metadata)
CREATE TABLE submission_files (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(submission_id) ON DELETE CASCADE
);

-- Table for tracking submission history
CREATE TABLE submission_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    action_type ENUM('created', 'updated', 'graded', 'deleted') NOT NULL,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    action_by VARCHAR(50) DEFAULT NULL,
    details TEXT,
    FOREIGN KEY (submission_id) REFERENCES submissions(submission_id) ON DELETE CASCADE
);

-- Create indices for better performance
CREATE INDEX idx_submissions_student ON submissions(student_id);
CREATE INDEX idx_submissions_assignment ON submissions(assignment_id);
CREATE INDEX idx_submissions_status ON submissions(status);
CREATE INDEX idx_submission_files_submission ON submission_files(submission_id);