<?php
session_start();
require_once 'logic.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || ($_SESSION['role'] != 'ADMIN' && $_SESSION['role'] != 'SADMIN')) {
    header("Location: index.php");
    exit();
}

$conn = get_db_connection();
$is_sadmin = ($_SESSION['role'] == 'SADMIN');

// Handle book addition with image upload
if (isset($_POST['add_book'])) {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $category = $_POST['category'];
    $copies = $_POST['copies'];
    $isbn = $_POST['isbn'];
    $published_year = $_POST['published_year'];
    
    // Process image upload if present
    $cover_image_path = NULL;
    if (isset($_FILES['book_cover']) && $_FILES['book_cover']['error'] == 0) {
        $upload_dir = 'uploads/book_covers/';
        $filename = time() . '_' . basename($_FILES['book_cover']['name']);
        $target_file = $upload_dir . $filename;
        
        // Check if image file is an actual image
        $check = getimagesize($_FILES['book_cover']['tmp_name']);
        if ($check !== false) {
            // Check file size (limit to 5MB)
            if ($_FILES['book_cover']['size'] < 5000000) {
                // Check file type
                $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                if (in_array($file_type, ['jpg', 'jpeg', 'png', 'gif'])) {
                    // Try to upload file
                    if (move_uploaded_file($_FILES['book_cover']['tmp_name'], $target_file)) {
                        $cover_image_path = $target_file;
                    } else {
                        echo "<script>alert('Sorry, there was an error uploading your file.');</script>";
                    }
                } else {
                    echo "<script>alert('Sorry, only JPG, JPEG, PNG & GIF files are allowed.');</script>";
                }
            } else {
                echo "<script>alert('Sorry, your file is too large. Max size is 5MB.');</script>";
            }
        } else {
            echo "<script>alert('File is not an image.');</script>";
        }
    }
    
    // Insert book with image path if available
    $add_sql = "INSERT INTO library (title, author, category, total_copies, available_copies, isbn, publication_year, cover_image) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $add_stmt = mysqli_prepare($conn, $add_sql);
    mysqli_stmt_bind_param($add_stmt, "sssiisis", $title, $author, $category, $copies, $copies, $isbn, $published_year, $cover_image_path);
    
    if (mysqli_stmt_execute($add_stmt)) {
        echo "<script>alert('Book added successfully!');</script>";
    } else {
        echo "<script>alert('Error adding book: " . mysqli_error($conn) . "');</script>";
    }
}

// Handle book update
if (isset($_POST['update_book'])) {
    $book_id = $_POST['book_id'];
    $title = $_POST['title'];
    $author = $_POST['author'];
    $category = $_POST['category'];
    $isbn = $_POST['isbn'];
    $published_year = $_POST['published_year'];
    $additional_copies = $_POST['additional_copies'];
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Process image upload if present
        $cover_image_sql = "";
        $cover_image_param = "";
        
        if (isset($_FILES['book_cover']) && $_FILES['book_cover']['error'] == 0) {
            $upload_dir = 'uploads/book_covers/';
            $filename = time() . '_' . basename($_FILES['book_cover']['name']);
            $target_file = $upload_dir . $filename;
            
            // Check if image file is an actual image
            $check = getimagesize($_FILES['book_cover']['tmp_name']);
            if ($check !== false) {
                // Check file size (limit to 5MB)
                if ($_FILES['book_cover']['size'] < 5000000) {
                    // Check file type
                    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                    if (in_array($file_type, ['jpg', 'jpeg', 'png', 'gif'])) {
                        // Try to upload file
                        if (move_uploaded_file($_FILES['book_cover']['tmp_name'], $target_file)) {
                            // Get old image path to delete later
                            $get_old_image_sql = "SELECT cover_image FROM library WHERE book_id = ?";
                            $get_old_image_stmt = mysqli_prepare($conn, $get_old_image_sql);
                            mysqli_stmt_bind_param($get_old_image_stmt, "i", $book_id);
                            mysqli_stmt_execute($get_old_image_stmt);
                            $old_image_result = mysqli_stmt_get_result($get_old_image_stmt);
                            $old_image_row = mysqli_fetch_assoc($old_image_result);
                            $old_image_path = $old_image_row['cover_image'];
                            
                            // Set new image path
                            $cover_image_sql = ", cover_image = ?";
                            $cover_image_param = $target_file;
                            
                            // Delete old file if exists
                            if ($old_image_path && file_exists($old_image_path)) {
                                unlink($old_image_path);
                            }
                        } else {
                            throw new Exception("Error uploading file");
                        }
                    } else {
                        throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed");
                    }
                } else {
                    throw new Exception("File is too large (max 5MB)");
                }
            } else {
                throw new Exception("File is not an image");
            }
        }
        
        // Update book info
        $update_sql = "UPDATE library SET 
                       title = ?, 
                       author = ?, 
                       category = ?, 
                       isbn = ?, 
                       publication_year = ?,
                       total_copies = total_copies + ?, 
                       available_copies = available_copies + ?
                       $cover_image_sql 
                       WHERE book_id = ?";
        
        $update_stmt = mysqli_prepare($conn, $update_sql);
        
        if ($cover_image_sql) {
            mysqli_stmt_bind_param($update_stmt, "ssssiisi", 
                $title, $author, $category, $isbn, $published_year, 
                $additional_copies, $additional_copies, $cover_image_param, $book_id);
        } else {
            mysqli_stmt_bind_param($update_stmt, "ssssiiii", 
                $title, $author, $category, $isbn, $published_year, 
                $additional_copies, $additional_copies, $book_id);
        }
        
        if (mysqli_stmt_execute($update_stmt)) {
            mysqli_commit($conn);
            echo "<script>alert('Book updated successfully!');</script>";
        } else {
            throw new Exception(mysqli_error($conn));
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>alert('Error updating book: " . $e->getMessage() . "');</script>";
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

// Handle book return
if (isset($_POST['return_book'])) {
    $borrow_id = $_POST['borrow_id'];
    $book_id = $_POST['book_id'];
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update borrowing status
        $update_borrow_sql = "UPDATE borrowings SET status = 'returned', return_date = NOW() WHERE borrow_id = ?";
        $update_borrow_stmt = mysqli_prepare($conn, $update_borrow_sql);
        mysqli_stmt_bind_param($update_borrow_stmt, "i", $borrow_id);
        mysqli_stmt_execute($update_borrow_stmt);
        
        // Increment available copies in library
        $update_lib_sql = "UPDATE library SET available_copies = available_copies + 1 WHERE book_id = ?";
        $update_lib_stmt = mysqli_prepare($conn, $update_lib_sql);
        mysqli_stmt_bind_param($update_lib_stmt, "i", $book_id);
        mysqli_stmt_execute($update_lib_stmt);

        // Increment allowed books for the user
        $increment_sql = "UPDATE users SET allowed = allowed + 1 WHERE id = ?";
        $increment_stmt = mysqli_prepare($conn, $increment_sql);
        mysqli_stmt_bind_param($increment_stmt, "i", $user_id);
        mysqli_stmt_execute($increment_stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        echo "<script>alert('Book returned successfully!');</script>";
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        echo "<script>alert('Error returning book: " . $e->getMessage() . "');</script>";
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

// Create new admin (SADMIN only)
if (isset($_POST['create_admin']) && $is_sadmin) {
    $username = $_POST['admin_username'];
    $email = $_POST['admin_email'];
    $password = $_POST['admin_password'];
    $role = $_POST['admin_role'];
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $create_sql = "INSERT INTO users (username, email, password, role, allowed) VALUES (?, ?, ?, ?, 5)";
    $create_stmt = mysqli_prepare($conn, $create_sql);
    mysqli_stmt_bind_param($create_stmt, "ssss", $username, $email, $hashed_password, $role);
    
    if (mysqli_stmt_execute($create_stmt)) {
        echo "<script>alert('New admin created successfully!'); window.location.href='admin.php';</script>";
    } else {
        echo "<script>alert('Error creating admin: " . mysqli_error($conn) . "');</script>";
    }
}

// Handle admin removal (SADMIN only)
if (isset($_POST['remove_admin']) && $is_sadmin) {
    $user_id = $_POST['user_id'];
    
    // Check that user is not removing themselves
    if ($user_id == $_SESSION['user_id']) {
        echo "<script>alert('You cannot remove yourself!');</script>";
    } else {
        // Get the user's role first to confirm they're an admin
        $check_sql = "SELECT role FROM users WHERE id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $user_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $row = mysqli_fetch_assoc($result);
        
        // Only proceed if user is an admin or super admin
        if ($row && ($row['role'] == 'ADMIN' || $row['role'] == 'SADMIN')) {
            $delete_sql = "DELETE FROM users WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            mysqli_stmt_bind_param($delete_stmt, "i", $user_id);
            
            if (mysqli_stmt_execute($delete_stmt)) {
                echo "<script>alert('Admin removed successfully!');</script>";
            } else {
                echo "<script>alert('Error removing admin: " . mysqli_error($conn) . "');</script>";
            }
        } else {
            echo "<script>alert('Invalid user or not an admin account!');</script>";
        }
    }
}

// Get all books
$books_sql = "SELECT * FROM library ORDER BY title";
$books_result = mysqli_query($conn, $books_sql);

// Get all active borrowings
$borrowings_sql = "SELECT b.*, l.title, l.author, l.book_id, u.username, u.email 
                  FROM borrowings b 
                  JOIN library l ON b.book_id = l.book_id 
                  JOIN users u ON b.user_id = u.id 
                  WHERE b.status = 'borrowed'
                  ORDER BY b.due_date ASC";
$borrowings_result = mysqli_query($conn, $borrowings_sql);

// Get overdue books
$overdue_sql = "SELECT b.*, l.title, l.author, l.book_id, u.username, u.email 
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

// Get users - different queries for SADMIN and regular ADMIN
if ($is_sadmin) {
    // SADMIN can see all users including admins
    $users_sql = "SELECT u.id, u.username, u.email, u.allowed, u.role,
                 (SELECT COUNT(*) FROM borrowings WHERE user_id = u.id AND status = 'borrowed') as active_borrows,
                 (SELECT COUNT(*) FROM borrowings WHERE user_id = u.id AND status = 'borrowed' AND due_date < NOW()) as overdue_borrows
                 FROM users u
                 ORDER BY username";
} else {
    // Regular ADMIN can only see regular users
    $users_sql = "SELECT u.id, u.username, u.email, u.allowed, u.role,
                 (SELECT COUNT(*) FROM borrowings WHERE user_id = u.id AND status = 'borrowed') as active_borrows,
                 (SELECT COUNT(*) FROM borrowings WHERE user_id = u.id AND status = 'borrowed' AND due_date < NOW()) as overdue_borrows
                 FROM users u
                 WHERE u.role != 'ADMIN' AND u.role != 'SADMIN'
                 ORDER BY username";
}
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
    <link rel="stylesheet" href="admin.css">
    <script src="admin.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Library Admin Dashboard <?php echo $is_sadmin ? '(Super Admin)' : '(Admin)'; ?></h1>
            <div class="nav-links">
                <a href="#" class="nav-link tab-link" onclick="openTab('books')">Books</a>
                <a href="#" class="nav-link tab-link" onclick="openTab('borrowings')">Borrowings</a>
                <a href="#" class="nav-link tab-link" onclick="openTab('users')">Users</a>
                <a href="#" class="nav-link tab-link" onclick="openTab('reports')">Reports</a>
                <?php if ($is_sadmin): ?>
                <a href="#" class="nav-link tab-link" onclick="openTab('admins')">Manage Admins</a>
                <?php endif; ?>
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
                        <th>#</th>
                        <th>Cover</th>
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
                    <?php $book_count = 1; while ($book = mysqli_fetch_assoc($books_result)): ?>
                        <tr>
                            <td><?php echo $book_count++; ?></td>
                            <td>
                                <?php if (isset($book['cover_image']) && $book['cover_image']): ?>
                                    <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" alt="Book cover" class="book-cover-preview">
                                <?php else: ?>
                                    <img src="images/book_covers/default.jpg" alt="Default cover" class="book-cover-preview">
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['category']); ?></td>
                            <td><?php echo $book['available_copies']; ?> / <?php echo $book['total_copies']; ?></td>
                            <td><?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($book['publication_year'] ?? 'N/A'); ?></td>
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
                        <th>#</th>
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
                    <?php $borrow_count = 1; while ($borrowing = mysqli_fetch_assoc($borrowings_result)): ?>
                        <tr>
                            <td><?php echo $borrow_count++; ?></td>
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
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="borrow_id" value="<?php echo $borrowing['borrow_id']; ?>">
                                    <input type="hidden" name="book_id" value="<?php echo $borrowing['book_id']; ?>">
                                    <button type="submit" name="return_book" class="btn btn-success" onclick="return confirm('Mark this book as returned?')">Return Book</button>
                                </form>
                                
                                <?php if (strtotime($borrowing['due_date']) < time() && !$borrowing['notice_sent']): ?>
                                    <form method="post" style="display: inline; margin-left: 5px;">
                                        <input type="hidden" name="borrow_id" value="<?php echo $borrowing['borrow_id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $borrowing['user_id']; ?>">
                                        <button type="submit" name="send_notice" class="btn btn-warning">Send Notice</button>
                                    </form>
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
                        <th>#</th>
                        <th>Book Title</th>
                        <th>Borrowed By</th>
                        <th>Email</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Days Overdue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $overdue_count = 1; 
                    if (mysqli_num_rows($overdue_result) > 0):
                        while ($overdue = mysqli_fetch_assoc($overdue_result)): 
                            $days_overdue = floor((time() - strtotime($overdue['due_date'])) / (60 * 60 * 24));
                    ?>
                        <tr>
                            <td><?php echo $overdue_count++; ?></td>
                            <td><?php echo htmlspecialchars($overdue['title']); ?></td>
                            <td><?php echo htmlspecialchars($overdue['username']); ?></td>
                            <td><?php echo htmlspecialchars($overdue['email']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($overdue['borrow_date'])); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($overdue['due_date'])); ?></td>
                            <td><?php echo $days_overdue; ?> days</td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="borrow_id" value="<?php echo $overdue['borrow_id']; ?>">
                                    <input type="hidden" name="book_id" value="<?php echo $overdue['book_id']; ?>">
                                    <button type="submit" name="return_book" class="btn btn-success" onclick="return confirm('Mark this book as returned?')">Return Book</button>
                                </form>
                                
                                <?php if (!$overdue['notice_sent']): ?>
                                    <form method="post" style="display: inline; margin-left: 5px;">
                                        <input type="hidden" name="borrow_id" value="<?php echo $overdue['borrow_id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $overdue['user_id']; ?>">
                                        <button type="submit" name="send_notice" class="btn btn-warning">Send Notice</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="8" class="text-center">No overdue books at this time.</td>
                        </tr>
                    <?php endif; ?>
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
                        <th>#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Borrow Limit</th>
                        <th>Active Borrowings</th>
                        <th>Overdue Books</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $user_count = 1; while ($user = mysqli_fetch_assoc($users_result)): ?>
                        <tr>
                            <td><?php echo $user_count++; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge <?php echo strtolower('role-' . $user['role']); ?>">
                                    <?php echo $user['role']; ?>
                                </span>
                            </td>
                            <td><?php echo $user['allowed']; ?></td>
                            <td><?php echo $user['active_borrows']; ?></td>
                            <td><?php echo $user['overdue_borrows']; ?></td>
                            <td>
                                <button class="btn btn-primary" onclick="openUpdateLimitModal(<?php echo $user['id']; ?>, <?php echo $user['allowed']; ?>)">
                                    Update Limit
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section tab-content" id="reports-tab">
    <!-- Remove the tab buttons since we'll show both reports -->
    
    <div class="reports-container">
        <div class="report-box" id="popular-books-tab">
            <h3>Most Popular Books</h3>
            <table class="numbered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Times Borrowed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($popular = mysqli_fetch_assoc($popular_books_result)): ?>
                        <tr>
                            <td></td>
                            <td><?php echo htmlspecialchars($popular['title']); ?></td>
                            <td><?php echo htmlspecialchars($popular['author']); ?></td>
                            <td><?php echo $popular['borrow_count']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="report-box" id="top-borrowers-tab">
            <h3>Top Borrowers</h3>
            <table class="numbered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Books Borrowed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($borrower = mysqli_fetch_assoc($top_borrowers_result)): ?>
                        <tr>
                            <td></td>
                            <td><?php echo htmlspecialchars($borrower['username']); ?></td>
                            <td><?php echo htmlspecialchars($borrower['email']); ?></td>
                            <td><?php echo $borrower['borrow_count']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
        
        <?php if ($is_sadmin): ?>
        <div class="section tab-content" id="admins-tab">
            <div class="section-header">
                <h2>Manage Administrators</h2>
                <button class="btn btn-primary" onclick="openModal('addAdminModal')">Add New Admin</button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $admin_sql = "SELECT * FROM users WHERE role = 'ADMIN' OR role = 'SADMIN' ORDER BY role DESC, username ASC";
                    $admin_result = mysqli_query($conn, $admin_sql);
                    $admin_count = 1;
                    
                    while ($admin = mysqli_fetch_assoc($admin_result)): 
                    ?>
                        <tr>
                            <td><?php echo $admin_count++; ?></td>
                            <td><?php echo htmlspecialchars($admin['username']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td>
                                <span class="role-badge <?php echo strtolower('role-' . $admin['role']); ?>">
                                    <?php echo $admin['role']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($admin['id'] != $_SESSION['user_id']): // Cannot modify self ?>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this admin?');">
                                        <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                                        <button type="submit" name="remove_admin" class="btn btn-danger">Remove</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Modals -->
        <!-- Add Book Modal -->
        <div id="addBookModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('addBookModal')">&times;</span>
                <h2>Add New Book</h2>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title">Title:</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="author">Author:</label>
                            <input type="text" id="author" name="author" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="category">Category:</label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="Fiction">Fiction</option>
                                <option value="Non-Fiction">Non-Fiction</option>
                                <option value="Science Fiction">Science Fiction</option>
                                <option value="Mystery">Mystery</option>
                                <option value="Romance">Romance</option>
                                <option value="Biography">Biography</option>
                                <option value="History">History</option>
                                <option value="Self-Help">Self-Help</option>
                                <option value="Reference">Reference</option>
                                <option value="Textbook">Textbook</option>
                                <option value="Children">Children</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="copies">Number of Copies:</label>
                            <input type="number" id="copies" name="copies" class="form-control" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="isbn">ISBN (optional):</label>
                            <input type="text" id="isbn" name="isbn" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="published_year">Publication Year (optional):</label>
                            <input type="number" id="published_year" name="published_year" class="form-control" min="1800" max="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="book_cover">Book Cover (optional):</label>
                        <input type="file" id="book_cover" name="book_cover" class="form-control" accept="image/*" onchange="previewImage(this)">
                        <img id="coverPreview" class="cover-preview" style="display: none;">
                    </div>
                    <button type="submit" name="add_book" class="btn btn-primary">Add Book</button>
                </form>
            </div>
        </div>
        
        <!-- Update Copies Modal -->
        <div id="updateModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('updateModal')">&times;</span>
                <h2>Update Book Copies</h2>
                <form method="post">
                    <input type="hidden" id="update_book_id" name="book_id">
                    <div class="form-group">
                        <label for="additional_copies">Add/Remove Copies (use negative numbers to remove):</label>
                        <input type="number" id="additional_copies" name="additional_copies" class="form-control" required>
                    </div>
                    <button type="submit" name="update_copies" class="btn btn-primary">Update Copies</button>
                </form>
            </div>
        </div>
        
        <!-- Update User Limit Modal -->
        <div id="updateLimitModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('updateLimitModal')">&times;</span>
                <h2>Update User Borrowing Limit</h2>
                <form method="post">
                    <input type="hidden" id="update_user_id" name="user_id">
                    <div class="form-group">
                        <label for="new_limit">New Borrowing Limit:</label>
                        <input type="number" id="new_limit" name="new_limit" class="form-control" min="0" max="20" required>
                    </div>
                    <button type="submit" name="update_user_limit" class="btn btn-primary">Update Limit</button>
                </form>
            </div>
        </div>
        
        <!-- Add Admin Modal -->
        <?php if ($is_sadmin): ?>
        <div id="addAdminModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('addAdminModal')">&times;</span>
                <h2>Add New Administrator</h2>
                <form method="post">
                    <div class="form-group">
                        <label for="admin_username">Username:</label>
                        <input type="text" id="admin_username" name="admin_username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_email">Email:</label>
                        <input type="email" id="admin_email" name="admin_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_password">Password:</label>
                        <input type="password" id="admin_password" name="admin_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_role">Role:</label>
                        <select id="admin_role" name="admin_role" class="form-control" required>
                            <option value="ADMIN">Regular Admin</option>
                            <option value="SADMIN">Super Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="create_admin" class="btn btn-primary">Create Admin</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        
    </div>
</body>
</html>
<?php mysqli_close($conn); ?>







