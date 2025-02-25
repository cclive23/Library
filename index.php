<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <style>
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
          font-family: Arial, sans-serif;
        }
        
        body {
          display: flex;
          justify-content: center;
          align-items: center;
          min-height: 100vh;
          background: #f5f5f5;
        }
        
        .container {
          position: relative;
          width: 400px;
          height: 450px;
          background: white;
          border-radius: 10px;
          box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
          overflow: hidden;
        }
        
        .form-wrapper {
          position: absolute;
          width: 100%;
          height: 100%;
          transition: 0.5s;
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
          padding-top: 80px; /* Increased top padding to move forms down */
          padding-left: 40px;
          padding-right: 40px;
          display: flex;
          flex-direction: column;
          justify-content: flex-start; /* Align content to top */
        }
        
        /* Form elements styling */
        form input {
          width: 100%;
          padding: 12px;
          margin-bottom: 15px;
          border: 1px solid #ddd;
          border-radius: 5px;
          outline: none;
          font-size: 16px;
        }
        
        form button {
          width: 100%;
          padding: 12px;
          border: none;
          border-radius: 5px;
          background: #4285f4;
          color: white;
          font-size: 16px;
          cursor: pointer;
          margin-top: 5px;
        }
        
        form button:hover {
          background: #3367d6;
        }
        
        /* Toggle buttons styling */
        .toggle-container {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          display: flex;
          z-index: 10;
        }
        
        .toggle-btn {
          flex: 1;
          padding: 15px 0;
          text-align: center;
          background:rgb(214, 212, 212);
          color: #333;
          text-decoration: none;
          font-weight: bold;
        }
        
        .toggle-btn.active {
          background: #4285f4;
          color: white;
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
                <input type="text" name="username" placeholder="Username">
                <input type="password" name="password" placeholder="Password">
                <button type="submit" name="login" value="login">Login</button>
            </form>
        </div>
        
        <!-- Signup form -->
        <div class="form-wrapper signup-form">
            <form action="" method="post">
                <input type="text" name="username" placeholder="Username">
                <input type="email" name="email" placeholder="Email">
                <input type="password" name="password" placeholder="Password">
                <input type="password" name="password2" placeholder="Confirm Password">
                <button type="submit" name="signup" value="signup">Signup</button>
            </form>
        </div>
    </div>
    
    
</body>
</html>
<?php
include 'logic.php';


?>