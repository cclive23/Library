<?php
include 'logic.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Portal</title>
    <style>
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
          font-family: 'Georgia', serif;
        }
        
        body {
          display: flex;
          justify-content: center;
          align-items: center;
          min-height: 100vh;
          background: #f5f5f5;
          background-image: linear-gradient(rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.9)), 
                            url('https://cdnjs.cloudflare.com/ajax/libs/simple-icons/3.0.1/book.svg');
          background-size: 300px;
          background-position: center;
        }
        
        .container {
          position: relative;
          width: 400px;
          height: 500px;
          background: white;
          border-radius: 12px;
          box-shadow: 0 10px 30px rgba(70, 48, 28, 0.2);
          overflow: hidden;
          border: 1px solid #e0d8c9;
        }
        
        .form-wrapper {
          position: absolute;
          width: 100%;
          height: 100%;
          transition: 0.5s ease-in-out;
        }
        
        .login-form {
          left: <?php echo isset($_GET['form']) && $_GET['form'] === 'signup' ? '-100%' : '0'; ?>;
        }
        
        .signup-form {
          left: <?php echo isset($_GET['form']) && $_GET['form'] === 'signup' ? '0' : '100%'; ?>;
        }
        
        form {
          width: 100%;
          height: 100%;
          padding-top: 100px;
          padding-left: 40px;
          padding-right: 40px;
          display: flex;
          flex-direction: column;
          justify-content: flex-start;
        }
        
        .title {
          text-align: center;
          margin-bottom: 30px;
          color: #5b4636;
          font-size: 24px;
          font-weight: bold;
        }
        
        /* Form elements styling */
        form input {
          width: 100%;
          padding: 14px;
          margin-bottom: 18px;
          border: 1px solid #d8ccbc;
          border-radius: 6px;
          outline: none;
          font-size: 16px;
          transition: all 0.3s;
          background-color: #fcfaf7;
        }
        
        form input:focus {
          border-color: #8b5a2b;
          box-shadow: 0 0 5px rgba(139, 90, 43, 0.3);
        }
        
        form input::placeholder {
          color: #a99c88;
        }
        
        form button {
          width: 100%;
          padding: 14px;
          border: none;
          border-radius: 6px;
          background: #8b5a2b;
          color: white;
          font-size: 16px;
          font-weight: bold;
          cursor: pointer;
          margin-top: 10px;
          transition: all 0.3s;
          letter-spacing: 1px;
        }
        
        form button:hover {
          background: #6d4621;
          transform: translateY(-2px);
          box-shadow: 0 4px 8px rgba(139, 90, 43, 0.3);
        }
        
        /* Toggle buttons styling */
        .toggle-container {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          display: flex;
          z-index: 10;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .toggle-btn {
          flex: 1;
          padding: 18px 0;
          text-align: center;
          background: #e6dfd3;
          color: #5b4636;
          text-decoration: none;
          font-weight: bold;
          transition: all 0.3s;
          letter-spacing: 0.5px;
        }
        
        .toggle-btn.active {
          background: #8b5a2b;
          color: white;
        }
        
        .toggle-btn:hover:not(.active) {
          background: #d8ccbc;
        }
        
        .form-footer {
          text-align: center;
          margin-top: 20px;
          font-size: 14px;
          color: #a99c88;
        }
        
        .form-footer a {
          color: #8b5a2b;
          text-decoration: none;
        }
        
        .form-footer a:hover {
          text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Toggle Buttons -->
        <div class="toggle-container">
            <a href="?form=login" class="toggle-btn <?php echo !isset($_GET['form']) || $_GET['form'] === 'login' ? 'active' : ''; ?>">Login</a>
            <a href="?form=signup" class="toggle-btn <?php echo isset($_GET['form']) && $_GET['form'] === 'signup' ? 'active' : ''; ?>">Signup</a>
        </div>

        <!-- Login form -->
        <div class="form-wrapper login-form">
            <form action="" method="post">
                <div class="title">Library Member Login</div>
                <input type="text" name="username" placeholder="Username">
                <input type="password" name="password" placeholder="Password">
                <button type="submit" name="login" value="login">Login</button>
                <div class="form-footer">
                    <a href="#">Forgot Password?</a>
                </div>
            </form>
        </div>
        
        <!-- Signup form -->
        <div class="form-wrapper signup-form">
            <form action="" method="post">
                <div class="title">New Library Membership</div>
                <input type="text" name="username" placeholder="Username">
                <input type="email" name="email" placeholder="Email">
                <input type="password" name="password" placeholder="Password">
                <input type="password" name="password2" placeholder="Confirm Password">
                <button type="submit" name="signup" value="signup">Create Account</button>
                <div class="form-footer">
                    By signing up, you agree to our <a href="#">Terms of Service</a>
                </div>
            </form>
        </div>
    </div>
    
    
</body>
</html>
