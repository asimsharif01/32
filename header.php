<?php
// header.php
// ── Auth guard — must be at the very top before ANY output ───────────────
// Uses session_status() to avoid "session already started" warning
// when the including page has already called session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
 
// Single clean flag — set by login_script.php on successful login
if (empty($_SESSION['auth'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include 'db.php'; ?>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="keywords" content="">
    <meta name="author" content="">
    <meta name="robots" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Larson & Company">
    <meta property="og:title" content="Larson & Company">
    <meta property="og:description" content="Larson & Company">
    <meta property="og:image" content="social-image.png">
    <meta name="format-detection" content="telephone=no">

    <!-- PAGE TITLE HERE -->
    <title>Larson & Company</title>
    <!-- FAVICONS ICON -->
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">

    <link href="vendor/bootstrap-select/dist/css/bootstrap-select.min.css" rel="stylesheet">
    <link href="vendor/swiper/css/swiper-bundle.min.css" rel="stylesheet">
    <link href="vendor/datatables/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="vendor/jvmap/jquery-jvectormap.css" rel="stylesheet">
    <link href="vendor/bootstrap-datetimepicker/css/bootstrap-datetimepicker.min.css" rel="stylesheet">

    <!-- tagify-css -->
    <link href="vendor/tagify/dist/tagify.css" rel="stylesheet">

    <!-- Style css -->
    <link href="css/style.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">

</head>

<script>
// Logout — simple redirect, no AJAX needed since logout.php redirects itself
function logout() {
    window.location.href = 'logout.php';
}
</script>
<style>
     .header-right .header-media {
    width: 1.875rem;
    height: -0.125rem;
    margin-right: 4px;
    margin-top: 0px;

}
</style>
<body data-typography="poppins" data-theme-version="light" data-layout="vertical" data-nav-headerbg="black"
    data-headerbg="color_1">

    <!--*******************
        Preloader start
    ********************-->
    <div id="preloader">
        <div class="lds-ripple">
            <div></div>
            <div></div>
        </div>
    </div>
    <!--*******************
        Preloader end
    ********************-->

    <!--**********************************
        Main wrapper start
    ***********************************-->
    <div id="main-wrapper">
        <!--**********************************
            Nav header start
        ***********************************-->
        <div class="nav-header">
            <a href="index.php" class="brand-logo">
                <img src="images/logo.png" width="130">
            </a>
            <div class="nav-control">
                <div class="hamburger">
                    <span class="line"></span>
                    <span class="line"></span>
                    <span class="line"></span>
                </div>
            </div>
        </div>
        <!--**********************************
            Nav header end
        ***********************************-->



        <!--**********************************
            Header start
        ***********************************-->
        <div class="header">
            <div class="header-content">
                <nav class="navbar navbar-expand">
                    <div class="collapse navbar-collapse justify-content-between">
                        <div class="header-left">

                        </div>
                        <ul class="navbar-nav header-right">

                            <li class="nav-item dropdown notification_dropdown d-none">
                                <a class="nav-link" href="javascript:void(0);" role="button" data-bs-toggle="dropdown">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z"
                                            stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path
                                            d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21"
                                            stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <div id="DZ_W_Notification1" class="widget-media dz-scroll p-3"
                                        style="height:380px;">
                                        <ul class="timeline">
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2">
                                                        <img alt="image" width="50" src="images/avatar/1.jpg">
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Dr sultads Send you Photo</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2 media-info">
                                                        KG
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Resport created successfully</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2 media-success">
                                                        <i class="fa fa-home"></i>
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Reminder : Treatment Time!</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2">
                                                        <img alt="image" width="50" src="images/avatar/1.jpg">
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Dr sultads Send you Photo</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2 media-danger">
                                                        KG
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Resport created successfully</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2 media-primary">
                                                        <i class="fa fa-home"></i>
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Reminder : Treatment Time!</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2">
                                                        <img alt="image" width="50" src="images/avatar/1.jpg">
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Dr sultads Send you Photo</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2 media-info">
                                                        KG
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Resport created successfully</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2 media-success">
                                                        <i class="fa fa-home"></i>
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Reminder : Treatment Time!</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2">
                                                        <img alt="image" width="50" src="images/avatar/1.jpg">
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Dr sultads Send you Photo</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2 media-danger">
                                                        KG
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Resport created successfully</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel">
                                                    <div class="media me-2 media-primary">
                                                        <i class="fa fa-home"></i>
                                                    </div>
                                                    <div class="media-body">
                                                        <h6 class="mb-1">Reminder : Treatment Time!</h6>
                                                        <small class="d-block">29 July 2020 - 02:26 PM</small>
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                    <a class="all-notification" href="javascript:void(0);">See all notifications <i
                                            class="ti-arrow-end"></i></a>
                                </div>
                            </li>

                            <li class="nav-item ps-3">
                                <div class="dropdown header-profile2">
                                    <a class="nav-link" href="javascript:void(0);" role="button"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <div class="header-info2 d-flex align-items-center">
                                            <div class="header-media">
                                                <?php
													$sql="SELECT profile_image FROM users WHERE id = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->bind_param("i", $_SESSION['id']);
                                                    $stmt->execute();
                                                    $result = $stmt->get_result();
                                                    if ($result->num_rows > 0) {
                                                        $row = $result->fetch_assoc();
                                                        $profileImage = $row['profile_image'] ?? 'default-profile.jpg';
                                                    } else {
                                                        $profileImage = 'default-profile.jpg';
                                                    }
                                                    echo "<img src='uploads/users/" . htmlspecialchars($profileImage) . "' class='avatar avatar-md' alt='Profile'>";
													?>
                                                
                                               
                                            </div>
                                            <div class="header-info">
                                                <?php 
												// Display user's name and email from session
												$userName = $_SESSION['name'] ?? 'User';
												$userEmail = $_SESSION['email'] ?? 'Email';

                                                echo "<h6>$userName</h6>";
                                                echo "<p>$userEmail</p>";
												?>
                                            </div>

                                        </div>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end" style="">
                                        <div class="card border-0 mb-0">
                                            <div class="card-header py-2">
                                                <div class="products">
                                                    <?php
													$sql="SELECT profile_image FROM users WHERE id = ?";
                                                    $stmt = $conn->prepare($sql);
                                                    $stmt->bind_param("i", $_SESSION['id']);
                                                    $stmt->execute();
                                                    $result = $stmt->get_result();
                                                    if ($result->num_rows > 0) {
                                                        $row = $result->fetch_assoc();
                                                        $profileImage = $row['profile_image'] ?? 'default-profile.jpg';
                                                    } else {
                                                        $profileImage = 'default-profile.jpg';
                                                    }
                                                    echo "<img src='uploads/users/" . htmlspecialchars($profileImage) . "' class='avatar avatar-md' alt='Profile'>";
													?>

                                                    
                                                    <div>
                                                        <?php 
												// Display user's name and email from session
												$userName = $_SESSION['name'] ?? 'User';
												$userRole = $_SESSION['role'] ?? 'Role';
                                                        echo "<h6>$userName</h6>";
                                                        echo "<span>$userRole</span>";
														?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="card-footer px-0 py-2">

                                                <a onclick="logout()" href="#" class="dropdown-item ai-icon">
                                                    <svg class="profle-logout" xmlns="http://www.w3.org/2000/svg"
                                                        width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                        stroke="#ff7979" stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                                        <polyline points="16 17 21 12 16 7"></polyline>
                                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                                    </svg>
                                                    <span class="ms-2 text-danger">Logout </span>
                                                </a>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </div>
        <!--**********************************
            Header end ti-comment-alt
        ***********************************-->

        <!--**********************************
            Sidebar start
        ***********************************-->
        <div class="deznav">
            <div class="deznav-scroll">
                <ul class="metismenu" id="menu">
                    <li class="menu-title">Portal</li>
                    <li><a href="index.php" class="" aria-expanded="false">
                            <div class="menu-icon">
                                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 8.5L11 2L19 8.5V18C19 18.6 18.6 19 18 19H4C3.4 19 3 18.6 3 18V8.5Z"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path d="M8 19V11H14V19" stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                            </div>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>
                    <li><a href="transactions.php" class="" aria-expanded="false">
                            <div class="menu-icon">
                                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14 7C15.5 7 16.5 8 16.5 9.5" stroke="#888888" stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path d="M17 13C18.5 13.5 19 14 19 15.5" stroke="#888888" stroke-width="1.5"
                                        stroke-linecap="round" />
                                    <path
                                        d="M11 13C8.5 13 6 13.5 6 16C6 17 8 17.5 11 17.5C14 17.5 16 17 16 16C16 13.5 13.5 13 11 13Z"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path
                                        d="M11 10C12.933 10 14.5 8.433 14.5 6.5C14.5 4.567 12.933 3 11 3C9.067 3 7.5 4.567 7.5 6.5C7.5 8.433 9.067 10 11 10Z"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                            </div>
                            <span class="nav-text">Transactions</span>
                        </a>
                    </li>
                    <li><a href="agents.php" class="" aria-expanded="false">
                            <div class="menu-icon">
                                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M11 13C8.5 13 6 13.5 6 16C6 17 8 17.5 11 17.5C14 17.5 16 17 16 16C16 13.5 13.5 13 11 13Z"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path
                                        d="M11 10C12.933 10 14.5 8.433 14.5 6.5C14.5 4.567 12.933 3 11 3C9.067 3 7.5 4.567 7.5 6.5C7.5 8.433 9.067 10 11 10Z"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path d="M18 7V10M20 8.5H16" stroke="#888888" stroke-width="1.5"
                                        stroke-linecap="round" />
                                </svg>
                            </div>
                            <span class="nav-text">Agents</span>
                        </a>
                    </li>

                    <li><a href="reports.php" class="" aria-expanded="false">
                            <div class="menu-icon">
                                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M4 3H18C18.6 3 19 3.4 19 4V18C19 18.6 18.6 19 18 19H4C3.4 19 3 18.6 3 18V4C3 3.4 3.4 3 4 3Z"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path d="M7 14L9.5 10L12.5 13L16 8" stroke="#888888" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M15 14H7" stroke="#888888" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                            </div>
                            <span class="nav-text">Reports</span>
                        </a>
                    </li>

                    <li><a href="users.php" class="" aria-expanded="false">
                            <div class="menu-icon">
                                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M7 13C5 13 3 13.5 3 15.5C3 16.5 4.5 17 7 17C9.5 17 11 16.5 11 15.5C11 13.5 9 13 7 13Z"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path
                                        d="M7 10.5C8.4 10.5 9.5 9.4 9.5 8C9.5 6.6 8.4 5.5 7 5.5C5.6 5.5 4.5 6.6 4.5 8C4.5 9.4 5.6 10.5 7 10.5Z"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path
                                        d="M16 13C15 13 13.5 13.5 13.5 15C13.5 16 14.5 16.5 16 16.5C17.5 16.5 18.5 16 18.5 15C18.5 13.5 17 13 16 13Z"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path
                                        d="M16 11C17.1 11 18 10.1 18 9C18 7.9 17.1 7 16 7C14.9 7 14 7.9 14 9C14 10.1 14.9 11 16 11Z"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                            </div>
                            <span class="nav-text">System Users</span>
                        </a>
                    </li>
                    <hr>
                    <li class="menu-title">System Listings</li>
                    <li>
                        <a href="financing_types.php" class="" aria-expanded="false">
                            <div class="menu-icon">
                                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M11 1V5M11 17V21M4 11H1M21 11H17M5.5 5.5L8.5 8.5M13.5 13.5L16.5 16.5M16.5 5.5L13.5 8.5M8.5 13.5L5.5 16.5"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round" />
                                    <circle cx="11" cy="11" r="3" stroke="#888888" stroke-width="1.5" />
                                </svg>
                            </div>
                            <span class="nav-text">Financing Types</span>
                        </a>
                    </li>

                    <li>
                        <a href="lead_sources.php" class="" aria-expanded="false">
                            <div class="menu-icon">
                                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 5H19L12 12V19L10 17V12L3 5Z" stroke="#888888" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M8 5L13 11" stroke="#888888" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                            </div>
                            <span class="nav-text">Lead Sources</span>
                        </a>
                    </li>

                    <li>
                        <a href="property_types.php" class="" aria-expanded="false">
                            <div class="menu-icon">
                                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2 8L11 2L20 8V18C20 18.6 19.6 19 19 19H3C2.4 19 2 18.6 2 18V8Z"
                                        stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path d="M7 13V19H15V13" stroke="#888888" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path d="M9 15H13" stroke="#888888" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                            </div>
                            <span class="nav-text">Property Types</span>
                        </a>
                    </li>

                    <li>
                        <a href="sales_statuses.php" class="" aria-expanded="false">
                            <div class="menu-icon">
                                <svg width="22" height="22" viewBox="0 0 22 22" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 19H19" stroke="#888888" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M5 16L5 10" stroke="#888888" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M9 16L9 6" stroke="#888888" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M13 16L13 12" stroke="#888888" stroke-width="1.5" stroke-linecap="round" />
                                    <path d="M17 16L17 14" stroke="#888888" stroke-width="1.5" stroke-linecap="round" />
                                    <circle cx="5" cy="8" r="1.5" stroke="#888888" stroke-width="1.5" />
                                    <circle cx="9" cy="4" r="1.5" stroke="#888888" stroke-width="1.5" />
                                    <circle cx="13" cy="10" r="1.5" stroke="#888888" stroke-width="1.5" />
                                    <circle cx="17" cy="12" r="1.5" stroke="#888888" stroke-width="1.5" />
                                </svg>
                            </div>
                            <span class="nav-text">Sales Statuses</span>
                        </a>
                    </li>

                </ul>
            </div>
        </div>
        <!--**********************************
            Sidebar end
        ***********************************-->