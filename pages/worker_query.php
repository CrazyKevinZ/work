<?php
// 数据库连接已完成

// 获取所有工人列表
$workers = $pdo->query("SELECT id, name, job_type FROM workers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// 获取参数
$selected_worker_id = isset($_GET['worker_id']) ? intval($_GET['worker_id']) : 0;

// 查询该工人参与的所有施工记录（含库工与临时工）
$details = [];
$summary = [
    'total_projects' => 0,
    'total_parts' => 0,
    'total_days' => 0,
    'total_attendance_hour' => 0,
];
if ($selected_worker_id) {
    // 1. 查询库工参与的记录
    $sql1 = "
        SELECT 
            c.id as construction_id,
            p.name as project_name,
            c.construction_part,
            c.construction_date,
            cw.attendance_hour
        FROM construction_workers cw
        LEFT JOIN constructions c ON cw.construction_id = c.id
        LEFT JOIN projects p ON c.project_id = p.id
        WHERE cw.worker_id = ?
        ORDER BY c.construction_date DESC, c.id DESC
    ";
    // 2. 查询作为临时工参与的记录（姓名与工人表匹配）
    $sql2 = "
        SELECT 
            c.id as construction_id,
            p.name as project_name,
            c.construction_part,
            c.construction_date,
            cw.attendance_hour
        FROM construction_workers cw
        LEFT JOIN constructions c ON cw.construction_id = c.id
        LEFT JOIN projects p ON c.project_id = p.id
        WHERE cw.worker_id IS NULL AND cw.temp_worker = (SELECT name FROM workers WHERE id = ?)
        ORDER BY c.construction_date DESC, c.id DESC
    ";
    $stmt = $pdo->prepare($sql1);
    $stmt->execute([$selected_worker_id]);
    $rows1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare($sql2);
    $stmt->execute([$selected_worker_id]);
    $rows2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $details = array_merge($rows1, $rows2);

    // 汇总
    $project_set = [];
    $part_set = [];
    $date_set = [];
    $total_hour = 0;
    foreach ($details as $r) {
        $project_set[$r['project_name']] = true;
        $part_set[$r['construction_part']] = true;
        $date_set[$r['construction_date']] = true;
        $total_hour += floatval($r['attendance_hour']);
    }
    $summary = [
        'total_projects' => count($project_set),
        'total_parts' => count($part_set),
        'total_days' => count($date_set),
        'total_attendance_hour' => $total_hour,
    ];
}

?>

<div class="card">
  <div class="card-header"><strong>工人参与施工统计查询</strong></div>
  <div class="card-body">
    <form class="row row-cols-lg-auto g-2 align-items-center mb-3" method="get">
      <input type="hidden" name="page" value="worker_query">
      <div class="col-4">
        <label class="form-label">选择工人：</label>
        <select name="worker_id" class="form-select" onchange="this.form.submit()">
          <option value="">请选择工人</option>
          <?php foreach ($workers as $w): ?>
            <option value="<?= $w['id'] ?>" <?= $selected_worker_id == $w['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($w['name']) ?><?= $w['job_type'] ? "（{$w['job_type']}）" : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto align-self-end">
        <button type="submit" class="btn btn-primary">查询</button>
      </div>
    </form>

    <?php if ($selected_worker_id): ?>
      <div class="alert alert-info">
        <b>汇总：</b>
        参与项目数：<?= $summary['total_projects'] ?>，
        施工部位数：<?= $summary['total_parts'] ?>，
        施工天数：<?= $summary['total_days'] ?>，
        总工时：<?= $summary['total_attendance_hour'] ?>小时
      </div>
      <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
          <thead>
            <tr>
              <th>项目</th>
              <th>施工部位</th>
              <th>施工日期</th>
              <th>工时</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($details as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['project_name'] ?: '-') ?></td>
              <td><?= htmlspecialchars($r['construction_part'] ?: '-') ?></td>
              <td><?= htmlspecialchars($r['construction_date'] ?: '-') ?></td>
              <td><?= htmlspecialchars($r['attendance_hour'] ?: '-') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($details)): ?>
            <tr><td colspan="4" class="text-center text-muted">无数据</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php elseif ($_GET): ?>
      <div class="alert alert-warning">请选择工人进行统计。</div>
    <?php endif; ?>
    <div class="text-muted small mt-2">
      注：统计包括库工与同名临时工的参与记录，如有重名可能会合并统计。
    </div>
  </div>
</div>