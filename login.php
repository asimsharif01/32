<!DOCTYPE html>
<html lang="en" class="h-100">


<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="keywords" content="">
    <meta name="author" content="">
    <meta name="robots" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Web App - CRM">
    <meta property="og:title" content="Web App - CRM">
    <meta property="og:description" content="Web App - CRM">
    <meta property="og:image" content="social-image.png">
    <meta name="format-detection" content="telephone=no">

    <!-- PAGE TITLE HERE -->
    <title>CRM</title>

    <!-- FAVICONS ICON -->
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
    <link href="vendor/bootstrap-select/dist/css/bootstrap-select.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">

</head>
<style>
	body {
  background: linear-gradient(to bottom, #f5f7fa, #e4eaf2);

}

.login-form {
  background: #ffffff;
  border-radius: 20px;
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.05), 0 6px 12px rgba(0, 0, 0, 0.03);
  padding: 40px 30px;
  transition: all 0.3s ease-in-out;
}

.login-form:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 30px rgba(0, 0, 0, 0.08);
}

.login-form .title {
  font-size: 2rem;
  font-weight: 600;
  color: #343a40;
  margin-bottom: 10px;
}

.login-form p {
  color: #6c757d;
  font-size: 0.95rem;
}

.form-control {
  border: none;
  background: #f8f9fc;
  border-radius: 12px;
  padding: 12px 15px;
  box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.03);
  transition: all 0.2s;
}

.form-control:focus {
  background: #fff;
  box-shadow: 0 0 0 4px rgba(99, 132, 255, 0.15);
  border-color: transparent;
}


.btn-primary {
  background: linear-gradient(to right, #4a6cf7, #657ef8);
  border: none;
  padding: 12px 20px;
  font-weight: 500;
  border-radius: 12px;
  transition: all 0.3s ease-in-out;
}

.btn-primary:hover {
  background: linear-gradient(to right, #657ef8, #4a6cf7);
  box-shadow: 0 6px 18px rgba(74, 108, 247, 0.3);
}

.custom-checkbox .form-check-input {
  border-radius: 4px;
}

.custom-checkbox .form-check-input:checked {
  background-color: #4a6cf7;
  border-color: #4a6cf7;
}
  .back-home {
        position: absolute;
        top: 20px;
        left: 20px;
        background: linear-gradient(135deg, #6366f1, #3b82f6);
        color: #fff;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        padding: 10px 18px;
        border-radius: 9999px;
        /* pill shape */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .back-home:hover {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }
</style>
<body class="vh-100">
    <div class="authincation h-100">
        <div class="container-fluid h-100">
          <a href="https://livingindependentlyforequality.com" class="back-home"><span class="me-2">📂 </span>Switch Database</a>
            <div class="row h-100">
                <div class="col-lg-6 col-md-12 col-sm-12 mx-auto align-self-center">
                    <div class="login-form">
                        <div class="text-center">
                            <h3 class="title">Sign In</h3>
                            <p>Sign in to your account to start using CRM</p>
                        </div>
                        <?php
                          if (isset($_GET['error'])) {
                              $errorMessage = $_GET['error'];
                              echo '<div class="error-message text-center text-danger">' . $errorMessage . '</div>';
                          }
                          ?>
                        <form action="login_script.php" method="post">
                            <div class="mb-4">
                                <label class="mb-1 text-dark">Email</label>
                                <input type="email" class="form-control form-control" value="" name="email" required>
                            </div>
                            <div class="mb-4 position-relative">
                                <label class="mb-1 text-dark">Password</label>
                                <input type="password" id="dz-password" class="form-control" value="" name="password" required>
                                <span class="show-pass eye">

                                    <i class="fa fa-eye-slash"></i>
                                    <i class="fa fa-eye"></i>

                                </span>
                            </div>
                            <div class="form-row d-flex justify-content-between mt-4 mb-2">
                                <div class="mb-4">
                                  
                                </div>
                                <div class="mb-4">

                                </div>
                            </div>
                            <div class="text-center mb-4">
                                <button type="submit" name="submit" class="btn btn-primary btn-block">Sign In</button>
                            </div>


                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!--**********************************
	Scripts
***********************************-->
    <!-- Required vendors -->
    <script src="vendor/global/global.min.js"></script>
    <script src="vendor/bootstrap-select/dist/js/bootstrap-select.min.js"></script>
    <script src="js/deznav-init.js"></script>
    <script src="js/demo.js"></script>
    <script src="js/custom.js"></script>
    <script src="js/styleSwitcher.js"></script>

</body>

</html>