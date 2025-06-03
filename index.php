<?php
require_once 'includes/auth_check.php';
$page = $_GET['page'] ?? 'project';
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>工程项目人员管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container">
<?php
switch($page) {
    case 'project':
        include 'pages/project.php'; break;
    case 'worker':
        include 'pages/worker.php'; break;
    case 'construction':
        include 'pages/construction.php'; break;
    case 'construction_query':
        include 'pages/construction_query.php'; break;
    case 'worker_query':
        include 'pages/worker_query.php'; break;
    default:
        include 'pages/project.php';
}
?>
</div>
</body>
</html>