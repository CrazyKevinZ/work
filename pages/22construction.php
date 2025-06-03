<?php
// 获取项目和库选工人
$projects = $pdo->query("SELECT * FROM projects ORDER BY sort_order, id DESC")->fetchAll();
$workers = $pdo->query("SELECT * FROM workers ORDER BY id DESC")->fetchAll();

// 新增施工记录
if (isset($_POST['add_construction'])) {
    $fields = ['project_id','construction_name','construction_date','time_am','time_pm','time_ot','work_content','remark'];
    $data = [];
    foreach($fields as $f){ $data[$f] = $_POST[$f] ?? null; }
    $sql = "INSERT INTO constructions (project_id, construction_name, construction_date, time_am, time_pm, time_ot, work_content, remark)
            VALUES (:project_id, :construction_name, :construction_date, :time_am, :time_pm, :time_ot, :work_content, :remark)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    $cid = $pdo->lastInsertId();

    // 施工人员（库选）
    if (!empty($_POST['worker_id'])) {
        foreach($_POST['worker_id'] as $wid) {
            $job_type = $_POST['worker_job_type'][$wid] ?? '';
            $hour = $_POST['worker_hour'][$wid] ?? '';
            $stmt = $pdo->prepare("INSERT INTO construction_workers (construction_id, worker_id, temp_worker, job_type, attendance_hour)
                                   VALUES (?, ?, NULL, ?, ?)");
            $stmt->execute([$cid, $wid, $job_type, $hour]);
        }
    }
    // 施工人员（临时工）
    if (!empty($_POST['temp_worker']) && is_array($_POST['temp_worker'])) {
        foreach($_POST['temp_worker'] as $k=>$name){
            $name = trim($name);
            if($name){
                $job_type = $_POST['temp_job_type'][$k] ?? '';
                $hour = $_POST['temp_hour'][$k] ?? '';
                $stmt = $pdo->prepare("INSERT INTO construction_workers (construction_id, worker_id, temp_worker, job_type, attendance_hour)
                                       VALUES (?, NULL, ?, ?, ?)");
                $stmt->execute([$cid, $name, $job_type, $hour]);
            }
        }
    }
}

// 施工记录简单列表
$list = $pdo->query("SELECT c.*, p.name as project_name FROM constructions c LEFT JOIN projects p ON c.project_id=p.id ORDER BY c.id DESC LIMIT 20")->fetchAll();
?>

<div class="card">
  <div class="card-header"><strong>施工管理</strong></div>
  <div class="card-body">
  <form method="post" class="mb-3">
    <div class="row g-2">
      <div class="col">
        <select name="project_id" class="form-select" required>
          <option value="">选择项目</option>
          <?php foreach($projects as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col">
        <input name="construction_name" class="form-control" placeholder="施工项目" required>
      </div>
      <div class="col">
        <input type="date" name="construction_date" class="form-control" required>
      </div>
      <div class="col">
        <input name="time_am" class="form-control" placeholder="上午时间段 例: 8:00-12:00">
      </div>
      <div class="col">
        <input name="time_pm" class="form-control" placeholder="下午时间段 例: 13:00-17:00">
      </div>
      <div class="col">
        <input name="time_ot" class="form-control" placeholder="加班时间段 例: 18:00-20:00">
      </div>
    </div>
    <div class="row g-2 mt-2">
      <div class="col">
        <textarea name="work_content" class="form-control" rows="2" placeholder="工作内容"></textarea>
      </div>
      <div class="col">
        <input name="remark" class="form-control" placeholder="备注">
      </div>
    </div>
    <hr>
    <div><b>施工人员（库选）：</b></div>
    <div class="row g-2 mb-2">
      <?php foreach($workers as $w): ?>
        <div class="col-2">
          <label>
            <input type="checkbox" name="worker_id[]" value="<?= $w['id'] ?>"> <?= htmlspecialchars($w['name']) ?>
          </label>
          <input type="text" class="form-control form-control-sm mt-1" name="worker_job_type[<?= $w['id'] ?>]" placeholder="工种">
          <input type="number" step="0.01" class="form-control form-control-sm mt-1" name="worker_hour[<?= $w['id'] ?>]" placeholder="考勤小时数">
        </div>
      <?php endforeach;?>
    </div>
    <div><b>施工人员（临时工）：</b> 最多5人</div>
    <div class="row g-2 mb-2">
      <?php for($i=0;$i<5;$i++): ?>
        <div class="col">
          <input name="temp_worker[]" class="form-control mb-1" placeholder="姓名">
          <input name="temp_job_type[]" class="form-control mb-1" placeholder="工种">
          <input type="number" step="0.01" name="temp_hour[]" class="form-control mb-1" placeholder="考勤小时数">
        </div>
      <?php endfor;?>
    </div>
    <button type="submit" name="add_construction" class="btn btn-primary mt-2">提交施工记录</button>
  </form>
  <hr>
  <h6>最近施工记录：</h6>
  <table class="table table-bordered table-sm">
    <thead>
      <tr>
        <th>ID</th><th>项目</th><th>施工项目</th><th>日期</th><th>上午</th><th>下午</th><th>加班</th><th>内容</th><th>备注</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($list as $row): ?>
      <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['project_name']) ?></td>
        <td><?= htmlspecialchars($row['construction_name']) ?></td>
        <td><?= htmlspecialchars($row['construction_date']) ?></td>
        <td><?= htmlspecialchars($row['time_am']) ?></td>
        <td><?= htmlspecialchars($row['time_pm']) ?></td>
        <td><?= htmlspecialchars($row['time_ot']) ?></td>
        <td><?= htmlspecialchars($row['work_content']) ?></td>
        <td><?= htmlspecialchars($row['remark']) ?></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
  </div>
</div>