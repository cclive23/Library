<?php
session_start();
require_once 'logic.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$conn = get_db_connection();
$user_id = $_SESSION['user_id'];

// Handle book request
if (isset($_POST['request'])) {
    $book_id = $_POST['book_id'];

    // Fetch user's allowed borrow count
    $user_check_sql = "SELECT allowed FROM users WHERE id = ?";
    $user_check_stmt = mysqli_prepare($conn, $user_check_sql);
    mysqli_stmt_bind_param($user_check_stmt, "i", $user_id);
    mysqli_stmt_execute($user_check_stmt);
    $user_result = mysqli_stmt_get_result($user_check_stmt);
    $user = mysqli_fetch_assoc($user_result);

    // Check if user can still request books
    if ($user['allowed'] > 0) {
        // Check if there's an existing request for this book by this user
        $check_existing_sql = "SELECT COUNT(*) as count FROM borrowings 
                              WHERE user_id = ? AND book_id = ? 
                              AND (book_status IS NULL OR book_status = 'borrowed')";
        $check_stmt = mysqli_prepare($conn, $check_existing_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $user_id, $book_id);
        mysqli_stmt_execute($check_stmt);
        $exist_result = mysqli_stmt_get_result($check_stmt);
        $exists = mysqli_fetch_assoc($exist_result);

        if ($exists['count'] == 0) {
            try {
                // Start transaction
                mysqli_begin_transaction($conn);
                
                // Current timestamp for borrow_date
                $current_timestamp = date('Y-m-d H:i:s');
                
                // Calculate due_date (14 days from now)
                $due_date = date('Y-m-d H:i:s', strtotime('+14 days'));
                
                // Insert request record
                $request_sql = "INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, request) 
                                VALUES (?, ?, ?, ?, 'requested')";
                $request_stmt = mysqli_prepare($conn, $request_sql);
                mysqli_stmt_bind_param($request_stmt, "iiss", $user_id, $book_id, $current_timestamp, $due_date);
                
                if (mysqli_stmt_execute($request_stmt)) {
                    // Commit transaction
                    mysqli_commit($conn);
                    echo "<script>alert('Book requested successfully! Waiting for approval.');</script>";
                } else {
                    // Rollback transaction
                    mysqli_rollback($conn);
                    echo "<script>alert('Error requesting book: " . mysqli_error($conn) . "');</script>";
                }
            } catch (Exception $e) {
                // Rollback transaction
                mysqli_rollback($conn);
                echo "<script>alert('Error requesting book: " . $e->getMessage() . "');</script>";
            }
        } else {
            echo "<script>alert('You have already requested or borrowed this book.');</script>";
        }
    } else {
        echo "<script>alert('You have reached your borrowing limit. Return a book to request another.');</script>";
    }
}

// Handle request deletion
if (isset($_POST['delete_request'])) {
    $borrow_id = $_POST['borrow_id'];
    $book_id = $_POST['book_id'];
    
    try {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        // Delete the request
        $delete_sql = "DELETE FROM borrowings WHERE borrow_id = ? AND user_id = ? AND book_status IS NULL";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "ii", $borrow_id, $user_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            // Check if any rows were affected
            if (mysqli_affected_rows($conn) > 0) {
                // Commit transaction
                mysqli_commit($conn);
                echo "<script>alert('Request deleted successfully!');</script>";
            } else {
                // Rollback transaction
                mysqli_rollback($conn);
                echo "<script>alert('Unable to delete request. It might already be processed.');</script>";
            }
        } else {
            // Rollback transaction
            mysqli_rollback($conn);
            echo "<script>alert('Error deleting request: " . mysqli_error($conn) . "');</script>";
        }
    } catch (Exception $e) {
        // Rollback transaction
        mysqli_rollback($conn);
        echo "<script>alert('Error deleting request: " . $e->getMessage() . "');</script>";
    }
}

// Get books available for request (available_copies > 0)
$books_sql = "SELECT * FROM library WHERE available_copies > 0 ORDER BY title";
$books_result = mysqli_query($conn, $books_sql);

// Get user's borrowed books
$borrowed_sql = "SELECT b.*, l.title, l.author, l.cover_image 
                FROM borrowings b 
                JOIN library l ON b.book_id = l.book_id 
                WHERE b.user_id = ? AND b.book_status = 'borrowed'
                ORDER BY b.borrow_date DESC";
$borrowed_stmt = mysqli_prepare($conn, $borrowed_sql);
mysqli_stmt_bind_param($borrowed_stmt, "i", $user_id);
mysqli_stmt_execute($borrowed_stmt);
$borrowed_result = mysqli_stmt_get_result($borrowed_stmt);

// Get user's pending requests
$pending_sql = "SELECT b.*, l.title, l.author, l.cover_image 
               FROM borrowings b 
               JOIN library l ON b.book_id = l.book_id 
               WHERE b.user_id = ? AND b.book_status IS NULL
               ORDER BY b.borrow_date DESC";
$pending_stmt = mysqli_prepare($conn, $pending_sql);
mysqli_stmt_bind_param($pending_stmt, "i", $user_id);
mysqli_stmt_execute($pending_stmt);
$pending_result = mysqli_stmt_get_result($pending_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Dashboard</title>
    <link rel="stylesheet" href="user.css">
    <script src="user.js" defer></script>
</head>
<body>
   
    <div class="container">
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <a href="logout.php" class="logout">Logout</a>
        </div>
        
        <div class="section">
            <h2>Your Borrowed Books</h2>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($borrowed_result) > 0): ?>
                        <?php while ($borrowed = mysqli_fetch_assoc($borrowed_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($borrowed['title']); ?></td>
                                <td><?php echo htmlspecialchars($borrowed['author']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($borrowed['borrow_date'])); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($borrowed['due_date'])); ?></td>
                                <td>
                                    <span class="status <?php echo strtotime($borrowed['due_date']) < time() ? 'status-overdue' : 'status-borrowed'; ?>">
                                        <?php echo strtotime($borrowed['due_date']) < time() ? 'Overdue' : 'Borrowed'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-cover show-cover" 
                                            data-title="<?php echo htmlspecialchars($borrowed['title']); ?>"
                                            data-cover="<?php echo htmlspecialchars($borrowed['cover_image'] ?? 'images/default-cover.jpg'); ?>">
                                        Cover
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No borrowed books.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2>Your Pending Requests</h2>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Request Date</th>
                        <th>Status</th>
                        <th colspan="2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($pending_result) > 0): ?>
                        <?php while ($pending = mysqli_fetch_assoc($pending_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pending['title']); ?></td>
                                <td><?php echo htmlspecialchars($pending['author']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($pending['borrow_date'])); ?></td>
                                <td>
                                    <span class="status status-pending">
                                        <?php echo ucfirst(htmlspecialchars($pending['request'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-cover show-cover" 
                                            data-title="<?php echo htmlspecialchars($pending['title']); ?>"
                                            data-cover="<?php echo htmlspecialchars($pending['cover_image'] ?? 'images/default-cover.jpg'); ?>">
                                        Cover
                                    </button>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this request?');">
                                        <input type="hidden" name="borrow_id" value="<?php echo $pending['borrow_id']; ?>">
                                        <input type="hidden" name="book_id" value="<?php echo $pending['book_id']; ?>">
                                        <button type="submit" name="delete_request" class="btn" style="background-color: #a35b47; color: white;">
                                            Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No pending requests.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2>Available Books</h2>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Available Copies</th>
                        <th>Cover</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($book = mysqli_fetch_assoc($books_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['category']); ?></td>
                            <td><?php echo $book['available_copies']; ?></td>
                            <td>
                                <button type="button" class="btn btn-cover show-cover" 
                                        data-title="<?php echo htmlspecialchars($book['title']); ?>"
                                        data-cover="<?php echo htmlspecialchars($book['cover_image'] ?? 'images/default-cover.jpg'); ?>">
                                    View Cover
                                </button>
                            </td>
                            <td>
                                <button type="button" class="btn btn-borrow show-request" 
                                        data-title="<?php echo htmlspecialchars($book['title']); ?>"
                                        data-id="<?php echo $book['book_id']; ?>">
                                    Request
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for showing book cover -->
    <div id="coverModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modal-title"></h2>
            <img id="book-cover" class="book-cover" src="" alt="Book cover">
            <button id="close-modal" class="btn btn-close">Close</button>
        </div>
    </div>

    <!-- Modal for requesting books -->
<div id="requestModal" class="modal">
    <div class="modal-content">
        <span class="close request-close">&times;</span>
        <h2 id="request-title">Request Book</h2>
        <form method="post" id="requestForm">
            <input type="hidden" name="book_id" id="request-book-id">
            
            <div class="form-group">
                <label for="duration">Borrowing Duration: <span id="duration-days">7</span> days</label>
                <input type="range" id="duration-slider" name="duration" min="2" max="14" value="7" class="slider">
                <div class="date-display">
                    <p>Borrow date: <span id="borrow-date"></span></p>
                    <p>Return by: <span id="return-date"></span></p>
                </div>
                <small>You can adjust the slider to select a borrowing period between 2 and 14 days.</small>
            </div>
            
            <button type="submit" name="request" class="btn btn-borrow">Confirm Request</button>
        </form>
    </div>
</div>

    
</body>
</html>