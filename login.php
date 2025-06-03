<?php
session_start();
require_once 'includes/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password === SYSTEM_PASSWORD) {
        $_SESSION['is_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = '密码错误，请重试。';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>系统登录</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width:400px;margin-top:100px;">
    <div class="card">
        <div class="card-header text-center">
            <h4>请输入访问密码</h4>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <input type="password" class="form-control" name="password" placeholder="密码" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">登录</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>