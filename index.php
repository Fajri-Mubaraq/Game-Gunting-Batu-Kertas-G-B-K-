<?php
// index.php - Entry point Lucky Battle
// Redirect otomatis ke Landing Page

session_start();

// Jika sudah login, langsung ke main menu
if (isset($_SESSION['user_id']) || isset($_SESSION['username'])) {
    header("Location: Frontend/main_menu.php");
    exit();
}

// Belum login, arahkan ke landing page
header("Location: Frontend/Landing_page.php");
exit();
?>