<?php
$pageTitle = 'Analytics';
require_once __DIR__ . '/config/config.php';
require_admin();

// ---- Agent performance leaderboard
$agentPerf = db()->query("
    SELECT u.id, u.full_name,
        COUNT(t.id) AS total_assigned,
        SUM(CASE WHEN t.status IN ('Resolved','Closed') THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN t.status NOT IN ('Resolved','Closed') AND t.due_at < NOW() THEN 1 ELSE 0 END) AS overdue,
        ROUND(AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END), 1) AS avg_resolution_hours
    FROM users u
    LEFT JOIN tickets t ON t.assigned_to = u.id
    WHERE u.role = 'agent' AND u.status = 'active'
    GROUP BY u.id
    ORDER BY resolved DESC, total_assigned DESC
")->fetchAll();

// ---- SLA compliance
$slaStats = db()->query("
    SELECT
        COUNT(*) AS total_with_sla,
        SUM(CASE WHEN status IN ('Resolved','Closed') AND resolved_at <= due_at THEN 1 ELSE 0 END) AS met,
        SUM(CASE WHEN status NOT IN ('Resolved','Closed') AND due_at < NOW() THEN 1 ELSE 0 END) AS currently_breached
    FROM tickets WHERE due_at IS NOT NULL
")->fetch();
$slaCompliance = $slaStats['total_with_sla'] > 0 ? round(($slaStats['met'] / $slaStats['total_with_sla']) * 100) : 0;

// ---- Category breakdown
$categoryStats = db()->query("
    SELECT c.name, COUNT(t.id) AS total
    FROM categories c LEFT JOIN tickets t ON t.category_id = c.id
    GROUP BY c.id ORDER BY total DESC
")->fetchAll();

// ---- Priority breakdown
$priorityStats = db()->query("
    SELECT p.name, p.color, COUNT(t.id) AS total
    FROM priorities p LEFT JOIN tickets t ON t.priority_id = p.id
    GROUP BY p.id ORDER BY p.sort_order
")->fetchAll();

// ---- Monthly trend (last 6 months)
$monthlyLabels = [];
$monthlyValues = [];
$monthlyResolved = [];
for ($i = 5; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-{$i} months"));
    $monthEnd = date('Y-m-t', strtotime("-{$i} months"));
    $monthlyLabels[] = date('M Y', strtotime($monthStart));

    $stmt = db()->prepare("SELECT COUNT(*) c FROM tickets WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);
    $monthlyValues[] = (int)$stmt->fetch()['c'];

    $stmt2 = db()->prepare("SELECT COUNT(*) c FROM tickets WHERE resolved_at BETWEEN ? AND ?");
    $stmt2->execute([$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);
    $monthlyResolved[] = (int)$stmt2->fetch()['c'];
}

// ---- Average first response / resolution
$avgResolution = db()->query("SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)),1) avg_h FROM tickets WHERE resolved_at IS NOT NULL")->fetch()['avg_h'] ?? 0;

require_once __DIR__ . '/includes/header.php';
?>

<section class="stat-grid">
    <div class="stat-card stat-purple"><div class="stat-icon"><i class="fa-solid fa-shield-halved"></i></div><div class="stat-body"><span class="stat-value"><?= $slaCompliance ?>%</span><span class="stat-label">SLA Compliance</span></div></div>
    <div class="stat-card stat-red"><div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="stat-body"><span class="stat-value"><?= (int)$slaStats['currently_breached'] ?></span><span class="stat-label">SLA Breaches (open)</span></div></div>
    <div class="stat-card stat-blue"><div class="stat-icon"><i class="fa-regular fa-clock"></i></div><div class="stat-body"><span class="stat-value"><?= $avgResolution ?: 0 ?>h</span><span class="stat-label">Avg. Resolution Time</span></div></div>
    <div class="stat-card stat-green"><div class="stat-icon"><i class="fa-solid fa-users"></i></div><div class="stat-body"><span class="stat-value"><?= count($agentPerf) ?></span><span class="stat-label">Active Agents</span></div></div>
</section>

<section class="grid-2col">
    <div class="card">
        <div class="card-head"><h3><i class="fa-solid fa-chart-column"></i> 6-Month Trend: Logged vs Resolved</h3></div>
        <div class="card-body"><canvas id="monthlyChart" height="230"></canvas></div>
    </div>
    <div class="card">
        <div class="card-head"><h3><i class="fa-solid fa-tags"></i> Tickets by Category</h3></div>
        <div class="card-body"><canvas id="categoryChart" height="230"></canvas></div>
    </div>
</section>

<section class="grid-2col">
    <div class="card">
        <div class="card-head"><h3><i class="fa-solid fa-flag"></i> Tickets by Priority</h3></div>
        <div class="card-body"><canvas id="priorityChart" height="230"></canvas></div>
    </div>
    <div class="card">
        <div class="card-head"><h3><i class="fa-solid fa-gauge-high"></i> SLA Compliance</h3></div>
        <div class="card-body">
            <canvas id="slaChart" height="230"></canvas>
            <p class="chart-footnote"><?= (int)$slaStats['met'] ?> of <?= (int)$slaStats['total_with_sla'] ?> resolved tickets met their SLA target.</p>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-head"><h3><i class="fa-solid fa-ranking-star"></i> Agent Performance Leaderboard</h3></div>
    <div class="card-body table-wrap">
        <table class="data-table">
            <thead><tr><th>Agent</th><th>Assigned</th><th>Resolved</th><th>Resolution Rate</th><th>Overdue</th><th>Avg. Resolution Time</th></tr></thead>
            <tbody>
                <?php if (!$agentPerf): ?><tr><td colspan="6" class="empty-row">No agents yet.</td></tr><?php endif; ?>
                <?php foreach ($agentPerf as $a):
                    $rate = $a['total_assigned'] > 0 ? round(($a['resolved'] / $a['total_assigned']) * 100) : 0; ?>
                <tr>
                    <td><strong><?= e($a['full_name']) ?></strong></td>
                    <td><?= (int)$a['total_assigned'] ?></td>
                    <td><?= (int)$a['resolved'] ?></td>
                    <td>
                        <div class="progress-track progress-track-inline"><div class="progress-fill bar-brand" style="width:<?= $rate ?>%"></div></div>
                        <span><?= $rate ?>%</span>
                    </td>
                    <td><span class="<?= (int)$a['overdue'] > 0 ? 'text-danger' : '' ?>"><?= (int)$a['overdue'] ?></span></td>
                    <td><?= $a['avg_resolution_hours'] ? $a['avg_resolution_hours'] . 'h' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($monthlyLabels) ?>,
        datasets: [
            { label: 'Logged', data: <?= json_encode($monthlyValues) ?>, backgroundColor: '#C4B5FD', borderRadius: 6, maxBarThickness: 28 },
            { label: 'Resolved', data: <?= json_encode($monthlyResolved) ?>, backgroundColor: '#7C3AED', borderRadius: 6, maxBarThickness: 28 }
        ]
    },
    options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('categoryChart'), {
    type: 'polarArea',
    data: {
        labels: <?= json_encode(array_column($categoryStats, 'name')) ?>,
        datasets: [{ data: <?= json_encode(array_map('intval', array_column($categoryStats, 'total'))) ?>, backgroundColor: ['#5B21B6','#7C3AED','#8B5CF6','#A78BFA','#C4B5FD','#DDD6FE'] }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
});

new Chart(document.getElementById('priorityChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($priorityStats, 'name')) ?>,
        datasets: [{ data: <?= json_encode(array_map('intval', array_column($priorityStats, 'total'))) ?>, backgroundColor: <?= json_encode(array_column($priorityStats, 'color')) ?>, borderRadius: 6, maxBarThickness: 40 }]
    },
    options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('slaChart'), {
    type: 'doughnut',
    data: {
        labels: ['Met SLA', 'Missed / Breached'],
        datasets: [{ data: [<?= (int)$slaStats['met'] ?>, <?= max(0, (int)$slaStats['total_with_sla'] - (int)$slaStats['met']) ?>], backgroundColor: ['#7C3AED', '#E9D5FF'], borderWidth: 0 }]
    },
    options: { plugins: { legend: { position: 'bottom' } }, cutout: '65%' }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
