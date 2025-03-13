<?php
// Configuration
define("DB_SERVER", "localhost");
define("DB_USERNAME", "root");
define("DB_PASS", "Goat2302#");
define("DB_NAME", "SpiderBit");

function get_db_connection() {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASS, DB_NAME);
            if (!$conn) {
                throw new mysqli_sql_exception(mysqli_connect_error());
            }
        } catch (mysqli_sql_exception $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    return $conn;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate username
function validate_username($username) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $username);
}

// Login function with role-based redirection
function handle_login($username, $password) {
    $conn = get_db_connection();
    
    $username = mysqli_real_escape_string($conn, $username);
    
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password'])) {
            // Store session variables
            $_SESSION["user_id"] = $user['id'];
            $_SESSION["username"] = $user['username'];
            $_SESSION["role"] = $user['role']; // Store user role in session

            mysqli_stmt_close($stmt);

            // Debugging statements
            error_log("Login successful. User role: " . $user['role']);

            // Redirect based on role
            if ($user['role'] === 'USER') {
                header("Location: user.php");
            } else { // ADMIN or SADMIN
                header("Location: admin.php");
            }
            exit();
        }
        mysqli_stmt_close($stmt);
        return ["success" => false, "message" => "Incorrect password"];
    }
    
    mysqli_stmt_close($stmt);
    return ["success" => false, "message" => "Username not found"];
}

// Signup function with email domain check for admin role
function handle_signup($username, $email, $password, $password2) {
    $conn = get_db_connection();
    $errors = [];

    // Validate username
    if (empty($username) || strlen($username) < 5) {
        $errors[] = "Username must be at least 5 characters.";
    } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $username)) {
        $errors[] = "Username must start with a letter and contain only alphanumeric characters with the exception of an underscore.";
    }

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Validate password
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    
    if ($password !== $password2) {
        $errors[] = "Passwords do not match.";
    }
    
    if (!empty($errors)) {
        return ["success" => false, "message" => implode("\n", $errors)];
    }
    
    // Check existing user
    $check_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ? OR email = ?");
    mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($result) > 0) {
        mysqli_stmt_close($check_stmt);
        return ["success" => false, "message" => "Username or email already exists!"];
    }
    mysqli_stmt_close($check_stmt);
    
    // Determine role based on email domain

    // Insert new user with role
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $hashed_password,);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return ["success" => true, "message" => "User created successfully! Please login."];
    }
    
    $error = mysqli_error($conn);
    mysqli_stmt_close($stmt);
    return ["success" => false, "message" => "Error creating account: " . $error];
}

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        $result = handle_login($_POST['username'], $_POST['password']);
        if (!$result['success']) {
            echo "<script>alert('" . addslashes($result['message']) . "');</script>";
        }
    }

    if (isset($_POST['signup'])) {
        $result = handle_signup(
            trim($_POST['username']),
            trim($_POST['email']),
            $_POST['password'],
            $_POST['password2']
        );
        if ($result['success']) {
            echo "<script>alert('" . addslashes($result['message']) . "');</script>";
            echo "<script>window.location.href = 'index.php?form=login';</script>";
            exit();
        }
        echo "<script>alert('" . addslashes($result['message']) . "');</script>";
    }
}
?>
