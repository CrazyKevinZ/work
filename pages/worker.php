<?php
// 数据库连接已在外部完成

// 获取所有开户行号用于下拉和自动补全（去重）
$bank_accounts = $pdo->query("SELECT DISTINCT bank_account FROM workers WHERE bank_account IS NOT NULL AND bank_account != ''")->fetchAll(PDO::FETCH_COLUMN);

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
    $gender = trim($_POST['gender'] ?? '');
    $id_card = trim($_POST['id_card'] ?? '');
    $job_type = trim($_POST['job_type'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bank_account = trim($_POST['bank_account'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_card = trim($_POST['bank_card'] ?? '');
    $entry_date = $_POST['entry_date'] ?? '';
    $leave_date = $_POST['leave_date'] ?? '';
    $remark = trim($_POST['remark'] ?? '');

    // 离职日期默认值处理
    if ($entry_date && !$leave_date) {
        $leave_date = date('Y-m-d', strtotime($entry_date . ' +3 years'));
    }

    // 校验
    if ($name === '') {
        $add_edit_error = "姓名不能为空！";
    } else {
        if (isset($_POST['edit_worker'])) {
            $wid = intval($_POST['edit_id']);
            $stmt = $pdo->prepare("UPDATE workers SET name=?, gender=?, id_card=?, job_type=?, address=?, phone=?, bank_account=?, bank_name=?, bank_card=?, entry_date=?, leave_date=?, remark=? WHERE id=?");
            $stmt->execute([
                $name, $gender, $id_card, $job_type, $address, $phone, $bank_account, $bank_name, $bank_card,
                $entry_date ?: null, $leave_date ?: null, $remark, $wid
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO workers (name, gender, id_card, job_type, address, phone, bank_account, bank_name, bank_card, entry_date, leave_date, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name, $gender, $id_card, $job_type, $address, $phone, $bank_account, $bank_name, $bank_card,
                $entry_date ?: null, $leave_date ?: null, $remark
            ]);
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

<style>
.table th.seq, .table td.seq {
    width: 56px;
    text-align: center;
    vertical-align: middle !important;
    font-size: 1.25rem;
    color: #fff;
    background: #007bff;
    border-right: 2px solid #fff;
    border-radius: 6px 0 0 6px;
}
.table th.name, .table td.name {
    min-width: 100px;
    font-weight: bold;
    font-size: 1.1rem;
    color: #007bff;
    vertical-align: middle !important;
    /* 单行省略号 */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: #f8f9fa;
}
.table td.name {
    background: #eaf3fb;
}
.table tr {
    height: 54px;
}
</style>

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
          <label class="form-label">性别</label>
          <select name="gender" class="form-select">
            <option value="">--</option>
            <option value="男" <?= (($edit_row['gender'] ?? '') == '男') ? 'selected' : '' ?>>男</option>
            <option value="女" <?= (($edit_row['gender'] ?? '') == '女') ? 'selected' : '' ?>>女</option>
          </select>
        </div>
        <div class="col">
          <label class="form-label">身份证号</label>
          <input name="id_card" class="form-control" value="<?= htmlspecialchars($edit_row['id_card'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">工种</label>
          <input name="job_type" class="form-control" value="<?= htmlspecialchars($edit_row['job_type'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">家庭地址</label>
          <input name="address" class="form-control" value="<?= htmlspecialchars($edit_row['address'] ?? '') ?>">
        </div>
      </div>
      <div class="row g-2 mt-2">
        <div class="col">
          <label class="form-label">电话号码</label>
          <input name="phone" class="form-control" value="<?= htmlspecialchars($edit_row['phone'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">开户行号</label>
          <input name="bank_account" class="form-control" list="bank_account_list" value="<?= htmlspecialchars($edit_row['bank_account'] ?? '') ?>">
          <datalist id="bank_account_list">
            <?php foreach($bank_accounts as $item): ?>
              <option value="<?= htmlspecialchars($item) ?>">
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="col">
          <label class="form-label">开户银行</label>
          <input name="bank_name" class="form-control" value="<?= htmlspecialchars($edit_row['bank_name'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">银行卡号</label>
          <input name="bank_card" class="form-control" value="<?= htmlspecialchars($edit_row['bank_card'] ?? '') ?>">
        </div>
      </div>
      <div class="row g-2 mt-2">
        <div class="col">
          <label class="form-label">入职日期</label>
          <input type="date" name="entry_date" class="form-control" value="<?= htmlspecialchars($edit_row['entry_date'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">离职日期</label>
          <input type="date" name="leave_date" class="form-control" value="<?= htmlspecialchars($edit_row['leave_date'] ?? '') ?>">
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
          <label class="form-label">性别</label>
          <select name="gender" class="form-select">
            <option value="">--</option>
            <option value="男" <?= (($_POST['gender'] ?? '') == '男') ? 'selected' : '' ?>>男</option>
            <option value="女" <?= (($_POST['gender'] ?? '') == '女') ? 'selected' : '' ?>>女</option>
          </select>
        </div>
        <div class="col">
          <label class="form-label">身份证号</label>
          <input name="id_card" class="form-control" value="<?= htmlspecialchars($_POST['id_card'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">工种</label>
          <input name="job_type" class="form-control" value="<?= htmlspecialchars($_POST['job_type'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">家庭地址</label>
          <input name="address" class="form-control" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
        </div>
      </div>
      <div class="row g-2 mt-2">
        <div class="col">
          <label class="form-label">电话号码</label>
          <input name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">开户行号</label>
          <input name="bank_account" class="form-control" list="bank_account_list" value="<?= htmlspecialchars($_POST['bank_account'] ?? '') ?>">
          <datalist id="bank_account_list">
            <?php foreach($bank_accounts as $item): ?>
              <option value="<?= htmlspecialchars($item) ?>">
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="col">
          <label class="form-label">开户银行</label>
          <input name="bank_name" class="form-control" value="<?= htmlspecialchars($_POST['bank_name'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">银行卡号</label>
          <input name="bank_card" class="form-control" value="<?= htmlspecialchars($_POST['bank_card'] ?? '') ?>">
        </div>
      </div>
      <div class="row g-2 mt-2">
        <div class="col">
          <label class="form-label">入职日期</label>
          <input type="date" name="entry_date" class="form-control" value="<?= htmlspecialchars($_POST['entry_date'] ?? '') ?>">
        </div>
        <div class="col">
          <label class="form-label">离职日期</label>
          <input type="date" name="leave_date" class="form-control" value="<?= htmlspecialchars($_POST['leave_date'] ?? '') ?>">
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
    <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle">
      <thead>
        <tr>
          <th class="seq">序号</th>
          <th class="name">姓名</th>
          <th>性别</th>
          <th>身份证号</th>
          <th>工种</th>
          <th>家庭地址</th>
          <th>电话</th>
          <th>开户行号</th>
          <th>开户银行</th>
          <th>银行卡号</th>
          <th>入职日期</th>
          <th>离职日期</th>
          <th>备注</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <?php $no = 1; ?>
        <?php foreach($list as $row): ?>
        <tr>
          <td class="seq"><?= $no++ ?></td>
          <td class="name"><?= htmlspecialchars($row['name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['gender'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['id_card'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['job_type'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['address'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['phone'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['bank_account'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['bank_name'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['bank_card'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['entry_date'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['leave_date'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['remark'] ?? '') ?></td>
          <td>
            <a href="?page=worker&edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning">编辑</a>
            <a href="?page=worker&delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('确认删除?');">删除</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($list)): ?>
        <tr><td colspan="14" class="text-center text-muted">无人员</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>