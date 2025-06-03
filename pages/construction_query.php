<?php
// 数据库连接已完成

// 获取所有项目列表
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY sort_order, id DESC")->fetchAll(PDO::FETCH_ASSOC);

// 获取参数
$selected_project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$selected_part = isset($_GET['construction_part']) ? trim($_GET['construction_part']) : '';

// 获取当前项目所有部位列表（用于部位下拉）
$parts = [];
if ($selected_project_id) {
    $stmt = $pdo->prepare("SELECT DISTINCT construction_part FROM constructions WHERE project_id = ? AND construction_part IS NOT NULL AND construction_part != '' ORDER BY construction_part");
    $stmt->execute([$selected_project_id]);
    $parts = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// 查询所有工种列表（包括库工与临时工的工种）
$job_types = [];
if ($selected_project_id) {
    $sql = "
        SELECT DISTINCT job_type FROM (
            SELECT w.job_type 
            FROM workers w 
            WHERE w.job_type IS NOT NULL AND w.job_type != ''
            UNION
            SELECT cw.job_type
            FROM construction_workers cw
            WHERE cw.job_type IS NOT NULL AND cw.job_type != ''
        ) t
        ORDER BY job_type
    ";
    $job_types = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
}

// 汇总统计
$summary = [];
$detail = [];
$daily_jobtype = [];
if ($selected_project_id && !$selected_part) {
    // 1. 选定项目但未选部位，统计项目下所有工种人数、总人数、天数
    // 查询所有该项目下的分天分工种人数
    $jobtype_sql = "
        SELECT 
            c.construction_date,
            cw.job_type,
            COUNT(DISTINCT CASE WHEN cw.worker_id IS NOT NULL THEN cw.worker_id END) 
            + COUNT(DISTINCT CASE WHEN cw.worker_id IS NULL THEN cw.temp_worker END) AS jt_count
        FROM constructions c
        LEFT JOIN construction_workers cw ON c.id = cw.construction_id
        WHERE c.project_id = ?
        GROUP BY c.construction_date, cw.job_type
    ";
    $stmt = $pdo->prepare($jobtype_sql);
    $stmt->execute([$selected_project_id]);
    $jt_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 统计每天每种工种的人数
    $jobtype_total = [];
    $all_dates = [];
    foreach ($jt_rows as $r) {
        $jt = $r['job_type'] ?: '未填写';
        $jobtype_total[$jt] = ($jobtype_total[$jt] ?? 0) + intval($r['jt_count']);
        $all_dates[$r['construction_date']] = true;
    }
    // 统计每天所有工种人数
    $sql = "
        SELECT 
            c.construction_date,
            SUM(
                (SELECT COUNT(DISTINCT CASE WHEN cw.worker_id IS NOT NULL THEN cw.worker_id END)
                        + COUNT(DISTINCT CASE WHEN cw.worker_id IS NULL THEN cw.temp_worker END)
                 FROM construction_workers cw WHERE cw.construction_id = c.id)
            ) AS day_people
        FROM constructions c
        WHERE c.project_id = ?
        GROUP BY c.construction_date
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selected_project_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_people = 0;
    $total_days = 0;
    foreach ($rows as $r) {
        $total_people += intval($r['day_people']);
        $total_days++;
    }
    $summary = [
        'project_id' => $selected_project_id,
        'project_name' => array_column($projects, 'name', 'id')[$selected_project_id] ?? '',
        'jobtype_total' => $jobtype_total,
        'total_people' => $total_people,
        'total_days' => $total_days,
    ];
} elseif ($selected_project_id && $selected_part !== '') {
    // 2. 选定项目和部位，统计该部位的施工工种人数、总人数、天数
    $jobtype_sql = "
        SELECT 
            c.construction_date,
            cw.job_type,
            COUNT(DISTINCT CASE WHEN cw.worker_id IS NOT NULL THEN cw.worker_id END) 
            + COUNT(DISTINCT CASE WHEN cw.worker_id IS NULL THEN cw.temp_worker END) AS jt_count
        FROM constructions c
        LEFT JOIN construction_workers cw ON c.id = cw.construction_id
        WHERE c.project_id = ? AND c.construction_part = ?
        GROUP BY c.construction_date, cw.job_type
    ";
    $stmt = $pdo->prepare($jobtype_sql);
    $stmt->execute([$selected_project_id, $selected_part]);
    $jt_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $jobtype_total = [];
    $all_dates = [];
    foreach ($jt_rows as $r) {
        $jt = $r['job_type'] ?: '未填写';
        $jobtype_total[$jt] = ($jobtype_total[$jt] ?? 0) + intval($r['jt_count']);
        $all_dates[$r['construction_date']] = true;
        // 明细每天每工种
        $daily_jobtype[$r['construction_date']][$jt] = intval($r['jt_count']);
    }
    // 统计每天所有工种人数
    $sql = "
        SELECT 
            c.construction_date,
            (SELECT COUNT(DISTINCT CASE WHEN cw.worker_id IS NOT NULL THEN cw.worker_id END)
                    + COUNT(DISTINCT CASE WHEN cw.worker_id IS NULL THEN cw.temp_worker END)
             FROM construction_workers cw WHERE cw.construction_id = c.id) AS day_people
        FROM constructions c
        WHERE c.project_id = ? AND c.construction_part = ?
        GROUP BY c.construction_date
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selected_project_id, $selected_part]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_people = 0;
    $total_days = 0;
    foreach ($rows as $r) {
        $total_people += intval($r['day_people']);
        $total_days++;
        $detail[] = [
            'construction_date' => $r['construction_date'],
            'day_people' => intval($r['day_people']),
            'jobtype' => $daily_jobtype[$r['construction_date']] ?? [],
        ];
    }
    $summary = [
        'project_id' => $selected_project_id,
        'project_name' => array_column($projects, 'name', 'id')[$selected_project_id] ?? '',
        'construction_part' => $selected_part,
        'jobtype_total' => $jobtype_total,
        'total_people' => $total_people,
        'total_days' => $total_days,
    ];
}
?>

<div class="card">
  <div class="card-header"><strong>施工统计查询</strong></div>
  <div class="card-body">
    <form class="row row-cols-lg-auto g-2 align-items-center mb-3" method="get">
      <input type="hidden" name="page" value="construction_query">
      <div class="col-3">
        <label class="form-label">选择项目：</label>
        <select name="project_id" class="form-select" onchange="this.form.submit()">
          <option value="">请选择项目</option>
          <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $selected_project_id == $p['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-3">
        <label class="form-label">选择施工部位：</label>
        <select name="construction_part" class="form-select" <?= $selected_project_id ? '' : 'disabled' ?> onchange="this.form.submit()">
          <option value="">全部/不选</option>
          <?php foreach ($parts as $part): ?>
            <option value="<?= htmlspecialchars($part) ?>" <?= $selected_part === $part ? 'selected' : '' ?>>
              <?= htmlspecialchars($part) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto align-self-end">
        <button type="submit" class="btn btn-primary">查询</button>
      </div>
    </form>

    <?php if ($selected_project_id): ?>
      <div class="alert alert-info">
        <b>项目：</b><?= htmlspecialchars($summary['project_name']) ?>
        <?php if ($selected_part): ?>
          ，<b>施工部位：</b><?= htmlspecialchars($selected_part) ?>
        <?php endif; ?>
        <br>
        <?php foreach ($job_types as $jt): ?>
            <b><?= htmlspecialchars($jt) ?>人数：</b><?= intval($summary['jobtype_total'][$jt] ?? 0) ?>&nbsp;
        <?php endforeach; ?>
        <b>总人数：</b><?= intval($summary['total_people']) ?>
        <b>，施工天数：</b><?= intval($summary['total_days']) ?>
      </div>
      <?php if ($selected_part && $detail): ?>
      <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm align-middle">
          <thead>
            <tr>
              <th>日期</th>
              <?php foreach ($job_types as $jt): ?>
                <th><?= htmlspecialchars($jt) ?>人数</th>
              <?php endforeach; ?>
              <th>当日总人数</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($detail as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['construction_date']) ?></td>
              <?php foreach ($job_types as $jt): ?>
                <td><?= intval($r['jobtype'][$jt] ?? 0) ?></td>
              <?php endforeach; ?>
              <td><?= intval($r['day_people']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    <?php elseif ($_GET): ?>
      <div class="alert alert-warning">请选择项目进行统计。</div>
    <?php endif; ?>
    <div class="text-muted small mt-2">
      注：工种人数为全部天数累加，最后为总人数及天数。<br>
      如需更复杂的统计（如分工种/分天明细），请联系开发者。
    </div>
  </div>
</div>