<?php
session_start();
require_once 'logic.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] != 'ADMIN' && $_SESSION['role'] != 'SADMIN')) {
    header("Location: index.php");
    exit();
}

$conn = get_db_connection();

// Handle book addition
if (isset($_POST['add_book'])) {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $category = $_POST['category'];
    $copies = $_POST['copies'];
    $isbn = $_POST['isbn'];
    $published_year = $_POST['published_year'];
    
    $add_sql = "INSERT INTO library (title, author, category, total_copies, available_copies, isbn, publication_year) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
    $add_stmt = mysqli_prepare($conn, $add_sql);
    mysqli_stmt_bind_param($add_stmt, "sssiisi", $title, $author, $category, $copies, $copies, $isbn, $published_year);
    
    if (mysqli_stmt_execute($add_stmt)) {
        echo "<script>alert('Book added successfully!');</script>";
    } else {
        echo "<script>alert('Error adding book: " . mysqli_error($conn) . "');</script>";
    }
}

// Handle book deletion
if (isset($_POST['delete_book'])) {
    $book_id = $_POST['book_id'];
    
    // Check if book is borrowed
    $check_sql = "SELECT COUNT(*) as count FROM borrowings WHERE book_id = ? AND status = 'borrowed'";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $book_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $row = mysqli_fetch_assoc($result);
    
    if ($row['count'] > 0) {
        echo "<script>alert('Cannot delete: Book is currently borrowed by users.');</script>";
    } else {
        $delete_sql = "DELETE FROM library WHERE book_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $book_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            echo "<script>alert('Book deleted successfully!');</script>";
        } else {
            echo "<script>alert('Error deleting book: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// Handle update book copies
if (isset($_POST['update_copies'])) {
    $book_id = $_POST['book_id'];
    $additional_copies = $_POST['additional_copies'];
    
    $update_sql = "UPDATE library SET 
                   total_copies = total_copies + ?, 
                   available_copies = available_copies + ? 
                   WHERE book_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "iii", $additional_copies, $additional_copies, $book_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        echo "<script>alert('Book copies updated successfully!');</script>";
    } else {
        echo "<script>alert('Error updating book copies: " . mysqli_error($conn) . "');</script>";
    }
}

// Handle user borrowing limit update
if (isset($_POST['update_user_limit'])) {
    $user_id = $_POST['user_id'];
    $new_limit = $_POST['new_limit'];
    
    $update_sql = "UPDATE users SET allowed = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ii", $new_limit, $user_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        echo "<script>alert('User borrowing limit updated successfully!');</script>";
    } else {
        echo "<script>alert('Error updating user limit: " . mysqli_error($conn) . "');</script>";
    }
}

// Send overdue notice
if (isset($_POST['send_notice'])) {
    $borrow_id = $_POST['borrow_id'];
    $user_id = $_POST['user_id'];
    
    // In a real application, you would send an email here
    // For now, just update the database to mark that a notice was sent
    $notice_sql = "UPDATE borrowings SET notice_sent = 1 WHERE borrow_id = ?";
    $notice_stmt = mysqli_prepare($conn, $notice_sql);
    mysqli_stmt_bind_param($notice_stmt, "i", $borrow_id);
    
    if (mysqli_stmt_execute($notice_stmt)) {
        echo "<script>alert('Overdue notice sent successfully!');</script>";
    } else {
        echo "<script>alert('Error sending notice: " . mysqli_error($conn) . "');</script>";
    }
}

// Get all books
$books_sql = "SELECT * FROM library ORDER BY title";
$books_result = mysqli_query($conn, $books_sql);

// Get all active borrowings
$borrowings_sql = "SELECT b.*, l.title, l.author, u.username, u.email 
                  FROM borrowings b 
                  JOIN library l ON b.book_id = l.book_id 
                  JOIN users u ON b.user_id = u.id 
                  WHERE b.status = 'borrowed'
                  ORDER BY b.due_date ASC";
$borrowings_result = mysqli_query($conn, $borrowings_sql);

// Get overdue books
$overdue_sql = "SELECT b.*, l.title, l.author, u.username, u.email 
               FROM borrowings b 
               JOIN library l ON b.book_id = l.book_id 
               JOIN users u ON b.user_id = u.id 
               WHERE b.status = 'borrowed' AND b.due_date < NOW()
               ORDER BY b.due_date ASC";
$overdue_result = mysqli_query($conn, $overdue_sql);

// Get borrowing statistics
$stats_sql = "SELECT 
              (SELECT COUNT(*) FROM library) as total_books,
              (SELECT SUM(total_copies) FROM library) as total_copies,
              (SELECT SUM(total_copies - available_copies) FROM library) as borrowed_copies,
              (SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed') as active_borrowings,
              (SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed' AND due_date < NOW()) as overdue_borrowings,
              (SELECT COUNT(*) FROM users WHERE role != 'ADMIN' AND role != 'SADMIN') as total_users";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get top borrowers
$top_borrowers_sql = "SELECT u.username, u.email, COUNT(*) as borrow_count 
                     FROM borrowings b 
                     JOIN users u ON b.user_id = u.id 
                     GROUP BY b.user_id 
                     ORDER BY borrow_count DESC 
                     LIMIT 5";
$top_borrowers_result = mysqli_query($conn, $top_borrowers_sql);

// Get users with books
$users_sql = "SELECT u.id, u.username, u.email, u.allowed,
             (SELECT COUNT(*) FROM borrowings WHERE user_id = u.id AND status = 'borrowed') as active_borrows,
             (SELECT COUNT(*) FROM borrowings WHERE user_id = u.id AND status = 'borrowed' AND due_date < NOW()) as overdue_borrows
             FROM users u
             WHERE u.role != 'ADMIN' AND u.role != 'SADMIN'
             ORDER BY username";
$users_result = mysqli_query($conn, $users_sql);

// Get popular books
$popular_books_sql = "SELECT l.title, l.author, COUNT(*) as borrow_count 
                     FROM borrowings b 
                     JOIN library l ON b.book_id = l.book_id 
                     GROUP BY b.book_id 
                     ORDER BY borrow_count DESC 
                     LIMIT 5";
$popular_books_result = mysqli_query($conn, $popular_books_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Admin Dashboard</title>
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            background-color: #f0f2f5;
            color: #333;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 0;
            border-bottom: 2px solid #ddd;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 24px;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-link {
            text-decoration: none;
            color: #3498db;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-link:hover {
            color: #2980b9;
        }

        .logout {
            background-color: #e74c3c;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .logout:hover {
            background-color: #c0392b;
        }

        /* Dashboard grid layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .stat-card h3 {
            color: #7f8c8d;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: 700;
            color: #2c3e50;
        }

        /* Section styles */
        .section {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section h2 {
            color: #2c3e50;
            font-size: 20px;
        }

        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        /* Button styles */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-success {
            background-color: #2ecc71;
            color: white;
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-warning {
            background-color: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background-color: #d35400;
        }

        /* Status indicators */
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-borrowed {
            background-color: #f1c40f;
            color: #444;
        }

        .status-overdue {
            background-color: #e74c3c;
            color: white;
        }

        .status-returned {
            background-color: #2ecc71;
            color: white;
        }

        /* Form styles */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow: auto;
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 60%;
            max-width: 600px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #333;
        }

        /* Tabs */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            transition: background-color 0.3s;
            border-radius: 4px 4px 0 0;
        }

        .tab.active {
            background-color: #3498db;
            color: white;
            border-bottom: 3px solid #2980b9;
        }

        .tab:hover:not(.active) {
            background-color: #f5f5f5;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            th, td {
                padding: 8px;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .modal-content {
                width: 90%;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Additional utility classes */
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .mt-20 {
            margin-top: 20px;
        }
        
        .mb-20 {
            margin-bottom: 20px;
        }
        
        .d-flex {
            display: flex;
        }
        
        .justify-between {
            justify-content: space-between;
        }
        
        .align-center {
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Library Admin Dashboard</h1>
            <div class="nav-links">
                <a href="#" class="nav-link tab-link" onclick="openTab('books')">Books</a>
                <a href="#" class="nav-link tab-link" onclick="openTab('borrowings')">Borrowings</a>
                <a href="#" class="nav-link tab-link" onclick="openTab('users')">Users</a>
                <a href="#" class="nav-link tab-link" onclick="openTab('reports')">Reports</a>
                <a href="dashboard.php" class="nav-link">User View</a>
                <a href="logout.php" class="logout">Logout</a>
            </div>
        </div>
        
        <!-- Dashboard Statistics -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <h3>Total Books</h3>
                <div class="number"><?php echo $stats['total_books']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Copies</h3>
                <div class="number"><?php echo $stats['total_copies']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Borrowed Copies</h3>
                <div class="number"><?php echo $stats['borrowed_copies']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Borrowings</h3>
                <div class="number"><?php echo $stats['active_borrowings']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Overdue Books</h3>
                <div class="number"><?php echo $stats['overdue_borrowings']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Registered Users</h3>
                <div class="number"><?php echo $stats['total_users']; ?></div>
            </div>
        </div>
        
        <!-- Tab Content -->
        <div class="section tab-content" id="books-tab">
            <div class="section-header">
                <h2>Manage Books</h2>
                <button class="btn btn-primary" onclick="openModal('addBookModal')">Add New Book</button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Available / Total</th>
                        <th>ISBN</th>
                        <th>Published</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($book = mysqli_fetch_assoc($books_result)): ?>
                        <tr>
                            <td><?php echo $book['book_id']; ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['category']); ?></td>
                            <td><?php echo $book['available_copies']; ?> / <?php echo $book['total_copies']; ?></td>
                            <td><?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($book['published_year'] ?? 'N/A'); ?></td>
                            <td>
                                <button class="btn btn-primary" onclick="openUpdateModal(<?php echo $book['book_id']; ?>)">Update</button>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                    <button type="submit" name="delete_book" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this book?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section tab-content" id="borrowings-tab">
            <div class="section-header">
                <h2>Current Borrowings</h2>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Book Title</th>
                        <th>Borrowed By</th>
                        <th>Email</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($borrowing = mysqli_fetch_assoc($borrowings_result)): ?>
                        <tr>
                            <td><?php echo $borrowing['borrow_id']; ?></td>
                            <td><?php echo htmlspecialchars($borrowing['title']); ?></td>
                            <td><?php echo htmlspecialchars($borrowing['username']); ?></td>
                            <td><?php echo htmlspecialchars($borrowing['email']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($borrowing['borrow_date'])); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($borrowing['due_date'])); ?></td>
                            <td>
                                <span class="status <?php echo strtotime($borrowing['due_date']) < time() ? 'status-overdue' : 'status-borrowed'; ?>">
                                    <?php echo strtotime($borrowing['due_date']) < time() ? 'Overdue' : 'Borrowed'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (strtotime($borrowing['due_date']) < time() && !$borrowing['notice_sent']): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="borrow_id" value="<?php echo $borrowing['borrow_id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $borrowing['user_id']; ?>">
                                        <button type="submit" name="send_notice" class="btn btn-warning">Send Notice</button>
                                    </form>
                                <?php elseif (strtotime($borrowing['due_date']) < time() && $borrowing['notice_sent']): ?>
                                    <span class="status">Notice Sent</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <div class="section-header mt-20">
                <h2>Overdue Books</h2>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Book Title</th>
                        <th>Borrowed By</th>
                        <th>Email</th>
                        <th>Due Date</th>
                        <th>Days Overdue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($overdue = mysqli_fetch_assoc($overdue_result)): ?>
                        <tr>
                            <td><?php echo $overdue['borrow_id']; ?></td>
                            <td><?php echo htmlspecialchars($overdue['title']); ?></td>
                            <td><?php echo htmlspecialchars($overdue['username']); ?></td>
                            <td><?php echo htmlspecialchars($overdue['email']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($overdue['due_date'])); ?></td>
                            <td><?php echo floor((time() - strtotime($overdue['due_date'])) / 86400); ?></td>
                            <td>
                                <?php if (!$overdue['notice_sent']): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="borrow_id" value="<?php echo $overdue['borrow_id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $overdue['user_id']; ?>">
                                        <button type="submit" name="send_notice" class="btn btn-warning">Send Notice</button>
                                    </form>
                                <?php else: ?>
                                    <span class="status">Notice Sent</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section tab-content" id="users-tab">
            <div class="section-header">
                <h2>Manage Users</h2>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Current Books</th>
                        <th>Limit</th>
                        <th>Overdue Books</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo $user['active_borrows']; ?> / <?php echo $user['allowed']; ?></td>
                            <td><?php echo $user['allowed']; ?></td>
                            <td>
                                <?php if ($user['overdue_borrows'] > 0): ?>
                                    <span class="status status-overdue"><?php echo $user['overdue_borrows']; ?></span>
                                <?php else: ?>
                                    0
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-primary" onclick="openUserLimitModal(<?php echo $user['id']; ?>, <?php echo $user['allowed']; ?>)">Update Limit</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section tab-content" id="reports-tab">
            <div class="section-header">
                <h2>Reports & Statistics</h2>
            </div>
            
            <div class="dashboard-grid">
                <div class="section">
                    <h3>Top Borrowers</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Borrowings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($borrower = mysqli_fetch_assoc($top_borrowers_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($borrower['username']); ?></td>
                                    <td><?php echo htmlspecialchars($borrower['email']); ?></td>
                                    <td><?php echo $borrower['borrow_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="section">
                    <h3>Popular Books</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Borrowings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($popular = mysqli_fetch_assoc($popular_books_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($popular['title']); ?></td>
                                    <td><?php echo htmlspecialchars($popular['author']); ?></td>
                                    <td><?php echo $popular['borrow_count']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Modals -->
        <div id="addBookModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('addBookModal')">&times;</span>
                <h2>Add New Book</h2>
                <form method="post">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="author">Author</label>
                            <input type="text" id="author" name="author" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="copies">Number of Copies</label>
                            <input type="number" id="copies" name="copies" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="form-group">
                            <label for="isbn">ISBN</label>
                            <input type="text" id="isbn" name="isbn" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="published_year">Published Year</label>
                            <input type="text" id="published_year" name="published_year" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="publisher">Publisher</label>
                            <input type="text" id="publisher" name="publisher" class="form-control">
                        </div>
                    </div>
                    <div class="form-group text-right">
                        <button type="submit" name="add_book" class="btn btn-primary">Add Book</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="updateCopiesModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('updateCopiesModal')">&times;</span>
                <h2>Update Book Copies</h2>
                <form method="post">
                    <input type="hidden" id="update_book_id" name="book_id">
                    <div class="form-group">
                        <label for="additional_copies">Add Copies</label>
                        <input type="number" id="additional_copies" name="additional_copies" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="form-group text-right">
                        <button type="submit" name="update_copies" class="btn btn-primary">Update Copies</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="userLimitModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('userLimitModal')">&times;</span>
                <h2>Update User Borrowing Limit</h2>
                <form method="post">
                    <input type="hidden" id="limit_user_id" name="user_id">
                    <div class="form-group">
                        <label for="new_limit">New Borrowing Limit</label>
                        <input type="number" id="new_limit" name="new_limit" class="form-control" min="1" required>
                    </div>
                    <div class="form-group text-right">
                        <button type="submit" name="update_user_limit" class="btn btn-primary">Update Limit</button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
            // Make the first tab active by default
            document.addEventListener('DOMContentLoaded', function() {
                openTab('books');
            });
            
            function openTab(tabName) {
                // Hide all tab contents
                var tabContents = document.getElementsByClassName('tab-content');
                for (var i = 0; i < tabContents.length; i++) {
                    tabContents[i].style.display = 'none';
                }
                
                // Remove active class from all tabs
                var tabs = document.getElementsByClassName('tab-link');
                for (var i = 0; i < tabs.length; i++) {
                    tabs[i].classList.remove('active');
                }
                
                // Show the selected tab content and mark it as active
                document.getElementById(tabName + '-tab').style.display = 'block';
                
                // Find the clicked tab and add active class
                var tabs = document.getElementsByClassName('tab-link');
                for (var i = 0; i < tabs.length; i++) {
                    if (tabs[i].getAttribute('onclick').includes(tabName)) {
                        tabs[i].classList.add('active');
                    }
                }
            }
            
            function openModal(modalId) {
                document.getElementById(modalId).style.display = 'block';
            }
            
            function closeModal(modalId) {
                document.getElementById(modalId).style.display = 'none';
            }
            
            function openUpdateModal(bookId) {
                document.getElementById('update_book_id').value = bookId;
                openModal('updateCopiesModal');
            }
            
            function openUserLimitModal(userId, currentLimit) {
                document.getElementById('limit_user_id').value = userId;
                document.getElementById('new_limit').value = currentLimit;
                openModal('userLimitModal');
            }
            
            // Close modals when clicking outside
            window.onclick = function(event) {
                var modals = document.getElementsByClassName('modal');
                for (var i = 0; i < modals.length; i++) {
                    if (event.target == modals[i]) {
                        modals[i].style.display = 'none';
                    }
                }
            }
        </script>
    </div>
</body>
</html>

