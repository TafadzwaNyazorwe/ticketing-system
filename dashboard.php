<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/config/config.php';
require_login();
$me = current_user();
$isAdmin = is_admin();

// ---- Scope: admins see everything, agents see only their assigned tickets
$scopeWhere = $isAdmin ? "1=1" : "assigned_to = :uid";
$params = $isAdmin ? [] : ['uid' => $me['id']];

// ---- Stat cards
$stat = function (string $where = '1=1') use ($scopeWhere, $params) {
    $sql = "SELECT COUNT(*) AS c FROM tickets WHERE ($scopeWhere) AND ($where)";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetch()['c'];
};

$totalTickets   = $stat();
$openTickets    = $stat("status = 'Open'");
$progressTickets= $stat("status = 'In Progress'");
$holdTickets    = $stat("status = 'On Hold'");
$resolvedTickets= $stat("status = 'Resolved'");
$closedTickets  = $stat("status = 'Closed'");
$overdueTickets = $stat("due_at IS NOT NULL AND due_at < NOW() AND status NOT IN ('Resolved','Closed')");

$totalBranches  = (int)db()->query("SELECT COUNT(*) c FROM branches WHERE status='active'")->fetch()['c'];
$totalAgents    = (int)db()->query("SELECT COUNT(*) c FROM users WHERE role='agent' AND status='active'")->fetch()['c'];

$resolutionRate = $totalTickets > 0 ? round((($resolvedTickets + $closedTickets) / $totalTickets) * 100) : 0;

// ---- Recent tickets
$sql = "SELECT t.*, b.name AS branch_name, p.name AS priority_name, p.color AS priority_color,
               c.name AS category_name, u.full_name AS assigned_name
        FROM tickets t
        LEFT JOIN branches b ON b.id = t.branch_id
        LEFT JOIN priorities p ON p.id = t.priority_id
        LEFT JOIN categories c ON c.id = t.category_id
        LEFT JOIN users u ON u.id = t.assigned_to
        WHERE ($scopeWhere)
        ORDER BY t.created_at DESC LIMIT 8";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$recentTickets = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="stat-grid">
    <div class="stat-card stat-purple">
        <div class="stat-icon"><i class="fa-solid fa-ticket"></i></div>
        <div class="stat-body"><span class="stat-value"><?= $totalTickets ?></span><span class="stat-label">Total Tickets</span></div>
    </div>
    <div class="stat-card stat-blue">
        <div class="stat-icon"><i class="fa-regular fa-folder-open"></i></div>
        <div class="stat-body"><span class="stat-value"><?= $openTickets ?></span><span class="stat-label">Open</span></div>
    </div>
    <div class="stat-card stat-amber">
        <div class="stat-icon"><i class="fa-solid fa-spinner"></i></div>
        <div class="stat-body"><span class="stat-value"><?= $progressTickets ?></span><span class="stat-label">In Progress</span></div>
    </div>
    <div class="stat-card stat-green">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-body"><span class="stat-value"><?= $resolvedTickets ?></span><span class="stat-label">Resolved</span></div>
    </div>
    <div class="stat-card stat-red">
        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="stat-body"><span class="stat-value"><?= $overdueTickets ?></span><span class="stat-label">Overdue (SLA)</span></div>
    </div>
    <?php if ($isAdmin): ?>
    <div class="stat-card stat-slate">
        <div class="stat-icon"><i class="fa-solid fa-code-branch"></i></div>
        <div class="stat-body"><span class="stat-value"><?= $totalBranches ?></span><span class="stat-label">Active Branches</span></div>
    </div>
    <?php endif; ?>
</section>

<section class="grid-2col">
    <div class="card">
        <div class="card-head">
            <h3><i class="fa-solid fa-chart-pie"></i> Tickets by Status</h3>
        </div>
        <div class="card-body"><canvas id="statusChart" height="220"></canvas></div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3><i class="fa-solid fa-chart-line"></i> Tickets — Last 14 Days</h3>
        </div>
        <div class="card-body"><canvas id="trendChart" height="220"></canvas></div>
    </div>
</section>

<section class="grid-2col">
    <div class="card">
        <div class="card-head"><h3><i class="fa-solid fa-gauge"></i> Progress Overview</h3></div>
        <div class="card-body progress-list">
            <?php
            $progressRows = [
                ['label' => 'Open',        'value' => $openTickets,     'class' => 'bar-open'],
                ['label' => 'In Progress', 'value' => $progressTickets, 'class' => 'bar-progress'],
                ['label' => 'On Hold',     'value' => $holdTickets,     'class' => 'bar-hold'],
                ['label' => 'Resolved',    'value' => $resolvedTickets, 'class' => 'bar-resolved'],
                ['label' => 'Closed',      'value' => $closedTickets,   'class' => 'bar-closed'],
            ];
            foreach ($progressRows as $row):
                $pct = $totalTickets > 0 ? round(($row['value'] / $totalTickets) * 100) : 0;
            ?>
            <div class="progress-row">
                <div class="progress-row-label"><span><?= e($row['label']) ?></span><span><?= $row['value'] ?> (<?= $pct ?>%)</span></div>
                <div class="progress-track"><div class="progress-fill <?= $row['class'] ?>" style="width:<?= $pct ?>%"></div></div>
            </div>
            <?php endforeach; ?>

            <div class="progress-row" style="margin-top:18px;">
                <div class="progress-row-label"><span><strong>Overall Resolution Rate</strong></span><span><strong><?= $resolutionRate ?>%</strong></span></div>
                <div class="progress-track"><div class="progress-fill bar-brand" style="width:<?= $resolutionRate ?>%"></div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3><i class="fa-solid fa-building"></i> Tickets by Branch</h3></div>
        <div class="card-body"><canvas id="branchChart" height="220"></canvas></div>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <h3><i class="fa-regular fa-clock"></i> Recent Tickets</h3>
        <a href="<?= BASE_URL ?>/tickets.php" class="btn btn-sm btn-outline">View All <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="card-body table-wrap">
        <table class="data-table">
            <thead><tr>
                <th>Ticket No.</th><th>Subject</th><th>Branch</th><th>Priority</th><th>Assigned To</th><th>Status</th><th>Logged</th><th></th>
            </tr></thead>
            <tbody>
                <?php if (!$recentTickets): ?>
                <tr><td colspan="8" class="empty-row">No tickets yet. <a href="<?= BASE_URL ?>/add_ticket.php">Log the first one</a>.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentTickets as $t): ?>
                <tr>
                    <td><a href="<?= BASE_URL ?>/view_ticket.php?id=<?= (int)$t['id'] ?>" class="ticket-no-link"><?= e($t['ticket_no']) ?></a></td>
                    <td class="truncate-cell"><?= e($t['subject']) ?></td>
                    <td><?= e($t['branch_name'] ?? '—') ?></td>
                    <td><span class="priority-dot" style="background:<?= e($t['priority_color'] ?? '#999') ?>"></span><?= e($t['priority_name'] ?? '—') ?></td>
                    <td><?= e($t['assigned_name'] ?? 'Unassigned') ?></td>
                    <td><span class="badge <?= status_badge_class($t['status']) ?>"><?= e($t['status']) ?></span></td>
                    <td><?= time_ago($t['created_at']) ?></td>
                    <td><a href="<?= BASE_URL ?>/view_ticket.php?id=<?= (int)$t['id'] ?>" class="icon-link"><i class="fa-solid fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
fetch('<?= BASE_URL ?>/ajax/chart_data.php?scope=dashboard')
  .then(r => r.json())
  .then(data => {
    const purple = ['#5B21B6','#7C3AED','#A78BFA','#C4B5FD','#8B5CF6','#DDD6FE'];

    new Chart(document.getElementById('statusChart'), {
      type: 'doughnut',
      data: {
        labels: data.status.labels,
        datasets: [{ data: data.status.values, backgroundColor: purple, borderWidth: 0 }]
      },
      options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { family: 'Inter' } } } }, cutout: '65%' }
    });

    new Chart(document.getElementById('trendChart'), {
      type: 'line',
      data: {
        labels: data.trend.labels,
        datasets: [{ label: 'Tickets Logged', data: data.trend.values, borderColor: '#7C3AED', backgroundColor: 'rgba(124,58,237,0.12)', fill: true, tension: 0.35, pointRadius: 3 }]
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    new Chart(document.getElementById('branchChart'), {
      type: 'bar',
      data: {
        labels: data.branch.labels,
        datasets: [{ label: 'Tickets', data: data.branch.values, backgroundColor: '#7C3AED', borderRadius: 6, maxBarThickness: 34 }]
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
