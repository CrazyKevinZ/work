<?php
// 新增项目
if (isset($_POST['add_project'])) {
    $name = trim($_POST['name'] ?? '');
    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO projects (name) VALUES (?)");
        $stmt->execute([$name]);
    }
}

// 删除项目
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM projects WHERE id=?")->execute([$id]);
}

// 设置默认项目
if (isset($_GET['set_default'])) {
    $id = intval($_GET['set_default']);
    $pdo->exec("UPDATE projects SET is_default=0");
    $stmt = $pdo->prepare("UPDATE projects SET is_default=1 WHERE id=?");
    $stmt->execute([$id]);
}

// 排序项目
if (isset($_POST['sort_order'])) {
    foreach ($_POST['sort_order'] as $id => $order) {
        $stmt = $pdo->prepare("UPDATE projects SET sort_order=? WHERE id=?");
        $stmt->execute([intval($order), intval($id)]);
    }
}

// 编辑项目
if (isset($_POST['edit_id'])) {
    $id = intval($_POST['edit_id']);
    $name = trim($_POST['edit_name']);
    if ($name) {
        $stmt = $pdo->prepare("UPDATE projects SET name=? WHERE id=?");
        $stmt->execute([$name, $id]);
    }
}

$project_list = $pdo->query("SELECT * FROM projects ORDER BY sort_order, id DESC")->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <strong>项目管理</strong>
    </div>
    <div class="card-body">
        <form method="post" class="row g-2 mb-3">
            <div class="col-auto">
                <input type="text" name="name" class="form-control" placeholder="输入项目名称" required>
            </div>
            <div class="col-auto">
                <button type="submit" name="add_project" class="btn btn-success">新增项目</button>
            </div>
        </form>
        <form method="post">
        <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th width="40">ID</th>
                    <th>项目名称</th>
                    <th width="80">排序</th>
                    <th width="80">默认</th>
                    <th width="180">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($project_list as $prj): ?>
                <tr>
                    <td><?= $prj['id'] ?></td>
                    <td>
                        <?php if (isset($_GET['edit']) && $_GET['edit'] == $prj['id']): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="edit_id" value="<?= $prj['id'] ?>">
                                <input type="text" name="edit_name" value="<?= htmlspecialchars($prj['name']) ?>" class="form-control form-control-sm d-inline-block" style="width:160px;" required>
                                <button type="submit" class="btn btn-sm btn-primary">保存</button>
                                <a href="?page=project" class="btn btn-sm btn-secondary">取消</a>
                            </form>
                        <?php else: ?>
                            <?= htmlspecialchars($prj['name']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <input type="number" name="sort_order[<?= $prj['id'] ?>]" value="<?= $prj['sort_order'] ?>" class="form-control form-control-sm" style="width:70px;">
                    </td>
                    <td class="text-center">
                        <?php if ($prj['is_default']): ?>
                            <span class="badge bg-success">默认</span>
                        <?php else: ?>
                            <a href="?page=project&set_default=<?= $prj['id'] ?>" class="btn btn-sm btn-outline-success">设为默认</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=project&edit=<?= $prj['id'] ?>" class="btn btn-warning btn-sm">编辑</a>
                        <a href="?page=project&delete=<?= $prj['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('确定删除此项目？');">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">保存排序</button>
        </form>
    </div>
</div>