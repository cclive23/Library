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

// Handle book borrowing
if (isset($_POST['borrow'])) {
    $book_id = $_POST['book_id'];

    // Fetch user's allowed borrow count
    $user_check_sql = "SELECT allowed FROM users WHERE id = ?";
    $user_check_stmt = mysqli_prepare($conn, $user_check_sql);
    mysqli_stmt_bind_param($user_check_stmt, "i", $user_id);
    mysqli_stmt_execute($user_check_stmt);
    $user_result = mysqli_stmt_get_result($user_check_stmt);
    $user = mysqli_fetch_assoc($user_result);

    // Check if user can still borrow books
    if ($user['allowed'] > 0) {
        // Check if book is available
        $check_sql = "SELECT available_copies FROM library WHERE book_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $book_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $book = mysqli_fetch_assoc($result);

        if ($book['available_copies'] > 0) {
            // Set due date to 14 days from now
            $due_date = date('Y-m-d H:i:s', strtotime('+14 days'));

            // Start transaction
            mysqli_begin_transaction($conn);

            try {
                // Insert borrowing record
                $borrow_sql = "INSERT INTO borrowings (user_id, book_id, due_date) VALUES (?, ?, ?)";
                $borrow_stmt = mysqli_prepare($conn, $borrow_sql);
                mysqli_stmt_bind_param($borrow_stmt, "iis", $user_id, $book_id, $due_date);
                mysqli_stmt_execute($borrow_stmt);

                // Update available copies
                $update_sql = "UPDATE library SET available_copies = available_copies - 1 WHERE book_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "i", $book_id);
                mysqli_stmt_execute($update_stmt);

                // Decrement allowed books for the user
                $decrement_sql = "UPDATE users SET allowed = allowed - 1 WHERE id = ?";
                $decrement_stmt = mysqli_prepare($conn, $decrement_sql);
                mysqli_stmt_bind_param($decrement_stmt, "i", $user_id);
                mysqli_stmt_execute($decrement_stmt);

                mysqli_commit($conn);
                echo "<script>alert('Book borrowed successfully!');</script>";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo "<script>alert('Error borrowing book: " . $e->getMessage() . "');</script>";
            }
        } else {
            echo "<script>alert('Book is not available for borrowing.');</script>";
        }
    } else {
        echo "<script>alert('You have reached your borrowing limit. Return a book to borrow another.');</script>";
    }
}

// Handle book return
if (isset($_POST['return'])) {
    $borrow_id = $_POST['borrow_id'];

    mysqli_begin_transaction($conn);

    try {
        // Update borrowing record
        $return_sql = "UPDATE borrowings SET return_date = CURRENT_TIMESTAMP, status = 'returned' 
                      WHERE borrow_id = ? AND user_id = ?";
        $return_stmt = mysqli_prepare($conn, $return_sql);
        mysqli_stmt_bind_param($return_stmt, "ii", $borrow_id, $user_id);
        mysqli_stmt_execute($return_stmt);

        // Update available copies
        $book_id = $_POST['book_id'];
        $update_sql = "UPDATE library SET available_copies = available_copies + 1 WHERE book_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $book_id);
        mysqli_stmt_execute($update_stmt);

        // Increment allowed books for the user
        $increment_sql = "UPDATE users SET allowed = allowed + 1 WHERE id = ?";
        $increment_stmt = mysqli_prepare($conn, $increment_sql);
        mysqli_stmt_bind_param($increment_stmt, "i", $user_id);
        mysqli_stmt_execute($increment_stmt);

        mysqli_commit($conn);
        echo "<script>alert('Book returned successfully!');</script>";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>alert('Error returning book: " . $e->getMessage() . "');</script>";
    }
}

// Get all available books
$books_sql = "SELECT * FROM library WHERE available_copies > 0 ORDER BY title";
$books_result = mysqli_query($conn, $books_sql);

// Get user's borrowed books
$borrowed_sql = "SELECT b.*, l.title, l.author 
                FROM borrowings b 
                JOIN library l ON b.book_id = l.book_id 
                WHERE b.user_id = ? AND b.status = 'borrowed'
                ORDER BY b.borrow_date DESC";
$borrowed_stmt = mysqli_prepare($conn, $borrowed_sql);
mysqli_stmt_bind_param($borrowed_stmt, "i", $user_id);
mysqli_stmt_execute($borrowed_stmt);
$borrowed_result = mysqli_stmt_get_result($borrowed_stmt);
?>


<!DOCTYPE html>
<html lang="en">
<body>
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
                background-color: #f5f5f5;
                color: #333;
            }

            .container {
                max-width: 1200px;
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
                border-bottom: 2px solid #eee;
            }

            .header h1 {
                color: #2c3e50;
                font-size: 24px;
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

            /* Section styles */
            .section {
                background-color: white;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 30px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .section h2 {
                color: #2c3e50;
                margin-bottom: 20px;
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

            .btn-borrow {
                background-color: #3498db;
                color: white;
            }

            .btn-borrow:hover {
                background-color: #2980b9;
            }

            .btn-return {
                background-color: #2ecc71;
                color: white;
            }

            .btn-return:hover {
                background-color: #27ae60;
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

            /* Responsive design */
            @media (max-width: 768px) {
                .container {
                    padding: 10px;
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
            }

            /* Form styles */
            form {
                margin: 0;
            }

            /* Additional utility classes */
            .text-center {
                text-align: center;
            }

            .mt-20 {
                margin-top: 20px;
            }

            .mb-20 {
                margin-bottom: 20px;
            }
</style>
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
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="borrow_id" value="<?php echo $borrowed['borrow_id']; ?>">
                                    <input type="hidden" name="book_id" value="<?php echo $borrowed['book_id']; ?>">
                                    <button type="submit" name="return" class="btn btn-return">Return</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                    <button type="submit" name="borrow" class="btn btn-borrow">Borrow</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>