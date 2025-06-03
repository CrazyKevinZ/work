<?php
// 数据库连接已在外部完成

// 新增/编辑人员
$edit_row = null;
$add_edit_error = '';
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM workers WHERE id=?");
    $stmt->execute([$eid]);
    $edit_row = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (isset($_POST['add_worker']) || isset($_POST['edit_worker'])) {
    $name = trim($_POST['name'] ?? '');
    $job_type = trim($_POST['job_type'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $remark = trim($_POST['remark'] ?? '');
    if ($name === '') {
        $add_edit_error = "姓名不能为空！";
    } else {
        if (isset($_POST['edit_worker'])) {
            $wid = intval($_POST['edit_id']);
            $stmt = $pdo->prepare("UPDATE workers SET name=?, job_type=?, phone=?, remark=? WHERE id=?");
            $stmt->execute([$name, $job_type, $phone, $remark, $wid]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO workers (name, job_type, phone, remark) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $job_type, $phone, $remark]);
        }
        header("Location: ?page=worker");
        exit;
    }
}

// 删除人员
if (isset($_GET['delete'])) {
    $wid = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM workers WHERE id=?")->execute([$wid]);
    header("Location: ?page=worker");
    exit;
}

// 获取人员列表
$list = $pdo->query("SELECT * FROM workers ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card">
  <div class="card-header"><strong>人员管理</strong></div>
  <div class="card-body">

    <?php if ($add_edit_error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($add_edit_error) ?></div>
    <?php endif; ?>

    <?php if ($edit_row): ?>
    <!-- 编辑模式 -->
    <form method="post" class="mb-3 border rounded p-3 bg-light">
      <input type="hidden" name="edit_id" value="<?= htmlspecialchars($edit_row['id'] ?? '') ?>">
      <div class="row g-2">
        <div class="col">
          <label class="form-label">姓名</label>
          <input name="name" class="form-control" required value="<?= htmlspecialchars($edit_row['name'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">工种</label>
          <input name="job_type" class="form-control" value="<?= htmlspecialchars($edit_row['job_type'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">电话</label>
          <input name="phone" class="form-control" value="<?= htmlspecialchars($edit_row['phone'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">备注</label>
          <input name="remark" class="form-control" value="<?= htmlspecialchars($edit_row['remark'] ?? '') ?>">
        </div>
        <div class="col">
          <button type="submit" name="edit_worker" class="btn btn-primary mt-4">保存修改</button>
          <a href="?page=worker" class="btn btn-secondary mt-4">取消</a>
        </div>
      </div>
    </form>
    <?php else: ?>
    <!-- 新增模式 -->
    <form method="post" class="mb-3">
      <div class="row g-2">
        <div class="col">
          <label class="form-label">姓名</label>
          <input name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">工种</label>
          <input name="job_type" class="form-control" value="<?= htmlspecialchars($_POST['job_type'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">电话</label>
          <input name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">备注</label>
          <input name="remark" class="form-control" value="<?= htmlspecialchars($_POST['remark'] ?? '') ?>">
        </div>
        <div class="col">
          <button type="submit" name="add_worker" class="btn btn-primary mt-4">添加人员</button>
        </div>
      </div>
    </form>
    <?php endif; ?>

    <h6>人员名单：</h6>
    <table class="table table-bordered table-sm">
      <thead>
        <tr>
          <th>序号</th>
          <th>姓名</th>
          <th>工种</th>
          <th>电话</th>
          <th>备注</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php $no = 1; ?>
        <?php foreach($list as $row): ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= htmlspecialchars($row['name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['job_type'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['phone'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['remark'] ?? '') ?></td>
          <td>
            <a href="?page=worker&edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning">编辑</a>
            <a href="?page=worker&delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('确认删除?');">删除</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($list)): ?>
        <tr><td colspan="6" class="text-center text-muted">无人员</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>