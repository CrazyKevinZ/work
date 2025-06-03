<?php
session_start();
if (empty($_SESSION['is_logged_in'])) {
    header('Location: login.php');
    exit;
}
?>