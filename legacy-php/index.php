<!DOCTYPE html>
<html lang="en">
<head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>Welcome - Smart Campus VMS</title>    
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     <style>
          @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

          body, html {
          height: 100%;
          margin: 0;
          font-family: 'Inter', sans-serif;
          background: #f8fafc; /* Matches your dashboard background */
          display: flex;
          align-items: center;
          justify-content: center;
          }

          .welcome-container {
          background: #ffffff;
          padding: 50px;
          border-radius: 16px;
          box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
          border: 1px solid #e2e8f0;
          text-align: center;
          max-width: 450px;
          width: 100%;
          }

          .logo-circle {
          background: #2563eb;
          color: white;
          width: 60px;
          height: 60px;
          border-radius: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 24px;
          font-weight: 700;
          margin: 0 auto 20px;
          }

          h1 {
          font-size: 24px;
          color: #0f172a;
          margin-bottom: 8px;
          }

          p {
          color: #64748b;
          font-size: 14px;
          margin-bottom: 30px;
          line-height: 1.5;
          }

          .button-group {
          display: flex;
          flex-direction: column;
          gap: 12px;
          }

          .btn {
          padding: 14px;
          border-radius: 8px;
          font-size: 14px;
          font-weight: 600;
          text-decoration: none;
          transition: 0.2s;
          display: block;
          }

          .btn-login {
          background: #0f172a;
          color: #ffffff;
          border: 1px solid #0f172a;
          }

          .btn-login:hover {
          background: #1e293b;
          }

          .btn-register {
          background: #ffffff;
          color: #0f172a;
          border: 1px solid #e2e8f0;
          }

          .btn-register:hover {
          background: #f1f5f9;
          }

          .footer-text {
          margin-top: 25px;
          font-size: 12px;
          color: #94a3b8;
          }
     </style>
</head>
<body>

     <div class="welcome-container">
          <div class="logo-circle">
               <i class="fa-solid fa-shield-halved"></i>
          </div>

          <h1>Smart Campus VMS</h1>
          <p>Welcome to the Vehicle Management System. Please log in to access your dashboard or register a new vehicle.</p>

          <div class="button-group">
               <a href="login.php" class="btn btn-login">
                    <i class="fa-solid fa-right-to-bracket"></i> Login to Portal
               </a>
               <a href="register.php" class="btn btn-register">
                    <i class="fa-solid fa-user-plus"></i> Create New Account
               </a>
          </div>

          <div class="footer-text">
               &copy; 2026 Smart Campus Security Department
          </div>
     </div>

</body>
</html>