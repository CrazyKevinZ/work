<?php
// 数据库连接已在外部完成

// 获取项目和工人列表
$projects = $pdo->query("SELECT * FROM projects ORDER BY sort_order, id DESC")->fetchAll();
$workers = $pdo->query("SELECT * FROM workers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// 默认项目ID
$default_project_id = null;
foreach ($projects as $proj) {
    if (!empty($proj['is_default'])) {
        $default_project_id = $proj['id'];
        break;
    }
}

// 删除施工记录
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM constructions WHERE id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM construction_workers WHERE construction_id=?")->execute([$id]);
    header("Location: ?page=construction");
    exit;
}

// 编辑模式：获取要编辑的数据
$edit_row = null;
$edit_workers = [];
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM constructions WHERE id=?");
    $stmt->execute([$eid]);
    $edit_row = $stmt->fetch(PDO::FETCH_ASSOC);
    // 获取参与人员
    $stmt = $pdo->prepare("SELECT * FROM construction_workers WHERE construction_id=?");
    $stmt->execute([$eid]);
    $edit_workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 新增/修改施工记录
$add_edit_error = '';
$edit_success = false;
if (isset($_POST['add_construction']) || isset($_POST['edit_construction'])) {
    $is_edit = isset($_POST['edit_construction']);
    $fields = [
        'project_id','construction_part','construction_date',
        'time_am','time_pm','time_ot','work_content','remark'
    ];
    $data = [];
    foreach($fields as $f){ $data[$f] = $_POST[$f] ?? null; }

    if ($is_edit) {
        $cid = intval($_POST['edit_id']);
        $set = [];
        foreach($fields as $f) $set[] = "$f=:$f";
        $sql = "UPDATE constructions SET ".implode(',',$set)." WHERE id=:id";
        $stmt = $pdo->prepare($sql);
        $data['id'] = $cid;
        $stmt->execute($data);
        // 清除旧人员
        $pdo->prepare("DELETE FROM construction_workers WHERE construction_id=?")->execute([$cid]);
    } else {
        $sql = "INSERT INTO constructions (project_id, construction_part, construction_date, time_am, time_pm, time_ot, work_content, remark)
                VALUES (:project_id, :construction_part, :construction_date, :time_am, :time_pm, :time_ot, :work_content, :remark)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $cid = $pdo->lastInsertId();
    }

    // 施工人员（库选）
    if (!empty($_POST['worker_id'])) {
        foreach($_POST['worker_id'] as $wid) {
            // 自动拉工种
            $job_type = '';
            foreach($workers as $w) {
                if ($w['id'] == $wid) {
                    $job_type = $w['job_type'];
                    break;
                }
            }
            $hour_raw = $_POST['worker_hour'][$wid] ?? '10.00'; // 默认10小时
            $hour = ($hour_raw === '' || $hour_raw === null) ? 10.00 : $hour_raw;
            $stmt = $pdo->prepare("INSERT INTO construction_workers (construction_id, worker_id, temp_worker, job_type, attendance_hour)
                                   VALUES (?, ?, NULL, ?, ?)");
            $stmt->execute([$cid, $wid, $job_type, $hour]);
        }
    }
    // 施工人员（临时工）
    if (!empty($_POST['temp_worker']) && is_array($_POST['temp_worker'])) {
        foreach($_POST['temp_worker'] as $k=>$name){
            $name = trim($name);
            if($name){ // 仅当有姓名时才插入
                $job_type = $_POST['temp_job_type'][$k] ?? '普工';
                if ($job_type === '') $job_type = '普工';
                $hour_raw = $_POST['temp_hour'][$k] ?? '10.00';
                $hour = ($hour_raw === '' || $hour_raw === null) ? 10.00 : $hour_raw;
                $stmt = $pdo->prepare("INSERT INTO construction_workers (construction_id, worker_id, temp_worker, job_type, attendance_hour)
                                       VALUES (?, NULL, ?, ?, ?)");
                $stmt->execute([$cid, $name, $job_type, $hour]);
            }
            // 如果姓名为空，不做任何处理（工种、小时数也不存储）
        }
    }
    header("Location: ?page=construction");
    exit;
}

// 施工记录列表
$list = $pdo->query("SELECT c.*, p.name as project_name FROM constructions c LEFT JOIN projects p ON c.project_id=p.id ORDER BY c.id DESC LIMIT 20")->fetchAll();

// 统计这些日期的当日人数
$date_set = [];
foreach ($list as $row) {
    $date_set[$row['construction_date']] = true;
}
$in_dates = array_keys($date_set);
$worker_stats = [];
if ($in_dates) {
    $placeholders = implode(',', array_fill(0, count($in_dates), '?'));
    $sql_stats = "SELECT 
        c.construction_date, 
        COUNT(DISTINCT CASE WHEN cw.worker_id IS NOT NULL THEN cw.worker_id END) 
        + COUNT(DISTINCT CASE WHEN cw.worker_id IS NULL THEN cw.temp_worker END) AS total_workers
    FROM construction_workers cw
    LEFT JOIN constructions c ON cw.construction_id = c.id
    WHERE c.construction_date IN ($placeholders)
    GROUP BY c.construction_date";
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute($in_dates);
    foreach($stmt_stats->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $worker_stats[$row['construction_date']] = (int)$row['total_workers'];
    }
}
?>

<div class="card">
  <div class="card-header"><strong>施工管理</strong></div>
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
        <label class="form-label">所属项目</label>
        <select name="project_id" class="form-select" required>
          <option value="">选择项目</option>
          <?php foreach($projects as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $edit_row['project_id']==$p['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['name']) ?>
              <?php if (!empty($p['is_default'])) echo '(默认)'; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col">
        <label class="form-label">施工部位</label>
        <input name="construction_part" class="form-control" placeholder="施工部位" required value="<?= htmlspecialchars($edit_row['construction_part'] ?? '') ?>">
      </div>
      <div class="col">
        <label class="form-label">施工日期</label>
        <input type="date" name="construction_date" class="form-control" required value="<?= htmlspecialchars($edit_row['construction_date'] ?? '') ?>">
      </div>
      <div class="col">
        <label class="form-label">上午时间段</label>
        <input name="time_am" class="form-control" placeholder="上午时间段" value="<?= htmlspecialchars($edit_row['time_am'] ?? '') ?>">
      </div>
      <div class="col">
        <label class="form-label">下午时间段</label>
        <input name="time_pm" class="form-control" placeholder="下午时间段" value="<?= htmlspecialchars($edit_row['time_pm'] ?? '') ?>">
      </div>
      <div class="col">
        <label class="form-label">加班时间段</label>
        <input name="time_ot" class="form-control" placeholder="加班时间段" value="<?= htmlspecialchars($edit_row['time_ot'] ?? '') ?>">
      </div>
    </div>
    <div class="row g-2 mt-2">
      <div class="col">
        <label class="form-label">工作内容</label>
        <textarea name="work_content" class="form-control" rows="2" placeholder="工作内容"><?= htmlspecialchars($edit_row['work_content'] ?? '') ?></textarea>
      </div>
      <div class="col">
        <label class="form-label">备注</label>
        <input name="remark" class="form-control" placeholder="备注" value="<?= htmlspecialchars($edit_row['remark'] ?? '') ?>">
      </div>
    </div>
    <hr>
    <div><b>施工人员（库选）：</b></div>
    <div class="row g-2 mb-2">
      <?php
        $edit_worker_ids = [];
        $edit_worker_hours = [];
        foreach($edit_workers as $ew) {
            if ($ew['worker_id']) {
                $edit_worker_ids[] = $ew['worker_id'];
                $edit_worker_hours[$ew['worker_id']] = $ew['attendance_hour'];
            }
        }
      ?>
      <?php foreach($workers as $w): ?>
        <div class="col-2">
          <label>
            <input type="checkbox" name="worker_id[]" value="<?= $w['id'] ?>"
              <?= in_array($w['id'], $edit_worker_ids) ? 'checked' : '' ?>>
            <?= htmlspecialchars($w['name']) ?>
          </label>
          <div class="small text-muted">工种：<?= htmlspecialchars($w['job_type'] ?? '') ?></div>
          <input type="hidden" name="worker_job_type[<?= $w['id'] ?>]" value="<?= htmlspecialchars($w['job_type'] ?? '') ?>">
          <input type="number" step="0.01" class="form-control form-control-sm mt-1" name="worker_hour[<?= $w['id'] ?>]" placeholder="考勤小时数" value="<?= htmlspecialchars($edit_worker_hours[$w['id']] ?? '10.00') ?>">
        </div>
      <?php endforeach;?>
    </div>
    <div><b>施工人员（临时工）：</b> 最多5人</div>
    <div class="row g-2 mb-2">
      <?php
        $edit_temp = [];
        foreach($edit_workers as $ew) {
            if (!$ew['worker_id']) {
                $edit_temp[] = $ew;
            }
        }
        for($i=0;$i<5;$i++):
            $ename = $edit_temp[$i]['temp_worker'] ?? '';
            $ejob = $edit_temp[$i]['job_type'] ?? '普工';
            $ehour = $edit_temp[$i]['attendance_hour'] ?? '10.00';
      ?>
        <div class="col">
          <input name="temp_worker[]" class="form-control mb-1" placeholder="姓名" value="<?= htmlspecialchars($ename) ?>">
          <input name="temp_job_type[]" class="form-control mb-1" placeholder="工种" value="<?= htmlspecialchars($ejob) ?>">
          <input type="number" step="0.01" name="temp_hour[]" class="form-control mb-1" placeholder="考勤小时数" value="<?= htmlspecialchars($ehour) ?>">
        </div>
      <?php endfor;?>
    </div>
    <button type="submit" name="edit_construction" class="btn btn-primary mt-2">保存修改</button>
    <a href="?page=construction" class="btn btn-secondary mt-2">取消</a>
  </form>
  <?php else: ?>
  <!-- 新增模式 -->
  <form method="post" class="mb-3">
    <div class="row g-2">
      <div class="col">
        <label class="form-label">所属项目</label>
        <select name="project_id" class="form-select" required>
          <option value="">选择项目</option>
          <?php foreach($projects as $p): ?>
            <option value="<?= $p['id'] ?>"
              <?= (isset($_POST['project_id']) && $_POST['project_id'] == $p['id'])
                  || (!isset($_POST['project_id']) && $default_project_id == $p['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['name']) ?>
              <?php if (!empty($p['is_default'])) echo '(默认)'; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col">
        <label class="form-label">施工部位</label>
        <input name="construction_part" class="form-control" placeholder="施工部位" required value="<?= htmlspecialchars($_POST['construction_part'] ?? '') ?>">
      </div>
      <div class="col">
        <label class="form-label">施工日期</label>
        <input type="date" name="construction_date" class="form-control" required value="<?= htmlspecialchars($_POST['construction_date'] ?? date('Y-m-d')) ?>">
      </div>
      <div class="col">
        <label class="form-label">上午时间段</label>
        <input name="time_am" class="form-control" placeholder="上午时间段 例: 8:00-12:00" value="<?= htmlspecialchars($_POST['time_am'] ?? '') ?>">
      </div>
      <div class="col">
        <label class="form-label">下午时间段</label>
        <input name="time_pm" class="form-control" placeholder="下午时间段 例: 13:00-17:00" value="<?= htmlspecialchars($_POST['time_pm'] ?? '') ?>">
      </div>
      <div class="col">
        <label class="form-label">加班时间段</label>
        <input name="time_ot" class="form-control" placeholder="加班时间段 例: 18:00-20:00" value="<?= htmlspecialchars($_POST['time_ot'] ?? '') ?>">
      </div>
    </div>
    <div class="row g-2 mt-2">
      <div class="col">
        <label class="form-label">工作内容</label>
        <textarea name="work_content" class="form-control" rows="2" placeholder="工作内容"><?= htmlspecialchars($_POST['work_content'] ?? '') ?></textarea>
      </div>
      <div class="col">
        <label class="form-label">备注</label>
        <input name="remark" class="form-control" placeholder="备注" value="<?= htmlspecialchars($_POST['remark'] ?? '') ?>">
      </div>
    </div>
    <hr>
    <div><b>施工人员（库选）：</b></div>
    <div class="row g-2 mb-2">
      <?php foreach($workers as $w): ?>
        <div class="col-2">
          <label>
            <input type="checkbox" name="worker_id[]" value="<?= $w['id'] ?>"
              <?= !empty($_POST['worker_id']) && in_array($w['id'], (array)$_POST['worker_id']) ? 'checked' : '' ?>>
            <?= htmlspecialchars($w['name']) ?>
          </label>
          <div class="small text-muted">工种：<?= htmlspecialchars($w['job_type'] ?? '') ?></div>
          <input type="hidden" name="worker_job_type[<?= $w['id'] ?>]" value="<?= htmlspecialchars($w['job_type'] ?? '') ?>">
          <input type="number" step="0.01" class="form-control form-control-sm mt-1" name="worker_hour[<?= $w['id'] ?>]" placeholder="考勤小时数" value="<?= htmlspecialchars($_POST['worker_hour'][$w['id']] ?? '10.00') ?>">
        </div>
      <?php endforeach;?>
    </div>
    <div><b>施工人员（临时工）：</b> 最多5人</div>
    <div class="row g-2 mb-2">
      <?php for($i=0;$i<5;$i++): ?>
        <div class="col">
          <input name="temp_worker[]" class="form-control mb-1" placeholder="姓名" value="<?= htmlspecialchars($_POST['temp_worker'][$i] ?? '') ?>">
          <input name="temp_job_type[]" class="form-control mb-1" placeholder="工种" value="<?= htmlspecialchars($_POST['temp_job_type'][$i] ?? '普工') ?>">
          <input type="number" step="0.01" name="temp_hour[]" class="form-control mb-1" placeholder="考勤小时数" value="<?= htmlspecialchars($_POST['temp_hour'][$i] ?? '10.00') ?>">
        </div>
      <?php endfor;?>
    </div>
    <button type="submit" name="add_construction" class="btn btn-primary mt-2">提交施工记录</button>
  </form>
  <?php endif; ?>
  <hr>
  <h6>最近施工记录：</h6>
  <table class="table table-bordered table-sm">
    <thead>
      <tr>
        <th>ID</th>
        <th>项目</th>
        <th>施工部位</th>
        <th>日期</th>
        <th>上午</th>
        <th>下午</th>
        <th>加班</th>
        <th>内容</th>
        <th>备注</th>
        <th>当日人数</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($list as $row): ?>
      <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['project_name']) ?></td>
        <td><?= htmlspecialchars($row['construction_part'] ?? $row['construction_name'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['construction_date']) ?></td>
        <td><?= htmlspecialchars($row['time_am']) ?></td>
        <td><?= htmlspecialchars($row['time_pm']) ?></td>
        <td><?= htmlspecialchars($row['time_ot']) ?></td>
        <td><?= htmlspecialchars($row['work_content']) ?></td>
        <td><?= htmlspecialchars($row['remark']) ?></td>
        <td><?= $worker_stats[$row['construction_date']] ?? 0 ?></td>
        <td>
            <a href="?page=construction&edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning">修改</a>
            <a href="?page=construction&delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('确认删除?');">删除</a>
        </td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
  </div>
</div>