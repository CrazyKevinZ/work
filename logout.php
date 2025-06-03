<?php
session_start();
session_destroy(); // 清除所有session
header("Location: login.php"); // 跳转到登录页
exit;
?>