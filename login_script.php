<?php
include('db.php');
session_start(); // Start session at the beginning

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit'])) {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    echo $email;
    echo $password;
    

    // Prepare statement to avoid SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Fetch user data
        $userRow = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $userRow["password"])) {
            $_SESSION["id"] = $userRow["id"];
            $_SESSION["name"] = $userRow["name"];
            $_SESSION["email"] = $userRow["email"];
            $_SESSION["role"] = intval($userRow["role"]); // Convert role to integer for proper comparison
            $_SESSION["created_datetime"] = $userRow["created_datetime"];
            $_SESSION["status"] =  "living_departed"; // Set status to "living_departed"

            // If status is inactive, redirect to login page with an error message
            if ($userRow["status"] === "Inactive") {
                $errorMessage = "Your account is inactive. Please contact the administrator.";
                header("Location: login.php?error=" . urlencode($errorMessage));
                exit();
            }

            // Redirect based on role
            switch ($_SESSION["role"]) {
                case 1:
                    header("Location: index.php");
                    break;
                case 2:
                    header("Location: dashboard.php");
                    break;
                default:
                    $errorMessage = "Invalid role. Please contact the administrator.";
                    header("Location: login.php?error=" . urlencode($errorMessage));
                    exit();
            }
            exit(); // Ensure script stops executing after redirection
        } else {
            $errorMessage = "Incorrect password.";
            header("Location: login.php?error=" . urlencode($errorMessage));
            exit();
        }
    } else {
        $errorMessage = "User not found.";
        header("Location: login.php?error=" . urlencode($errorMessage));
        exit();
    }

    $stmt->close();
}

$conn->close();
?>
