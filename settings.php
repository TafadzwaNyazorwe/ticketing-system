<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/config/config.php';
require_admin();

$tab = $_GET['tab'] ?? 'general';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_general':
            foreach (['site_name', 'company_name', 'support_email'] as $key) {
                $val = trim($_POST[$key] ?? '');
                $stmt = db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $val, $val]);
            }
            flash('success', 'General settings updated.');
            redirect('settings.php?tab=general');
            break;

        case 'add_branch':
            $stmt = db()->prepare("INSERT INTO branches (name, code, location, contact_phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([trim($_POST['name']), trim($_POST['code']), trim($_POST['location']), trim($_POST['contact_phone'])]);
            flash('success', 'Branch added.');
            redirect('settings.php?tab=branches');
            break;

        case 'delete_branch':
            db()->prepare("UPDATE branches SET status='inactive' WHERE id=?")->execute([(int)$_POST['id']]);
            flash('success', 'Branch deactivated.');
            redirect('settings.php?tab=branches');
            break;

        case 'add_category':
            $stmt = db()->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->execute([trim($_POST['name']), trim($_POST['description'])]);
            flash('success', 'Category added.');
            redirect('settings.php?tab=categories');
            break;

        case 'delete_category':
            db()->prepare("UPDATE categories SET status='inactive' WHERE id=?")->execute([(int)$_POST['id']]);
            flash('success', 'Category deactivated.');
            redirect('settings.php?tab=categories');
            break;

        case 'add_priority':
            $stmt = db()->prepare("INSERT INTO priorities (name, color, sla_hours, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([trim($_POST['name']), $_POST['color'], (int)$_POST['sla_hours'], (int)$_POST['sort_order']]);
            flash('success', 'Priority added.');
            redirect('settings.php?tab=priorities');
            break;

        case 'delete_priority':
            db()->prepare("DELETE FROM priorities WHERE id=?")->execute([(int)$_POST['id']]);
            flash('success', 'Priority removed.');
            redirect('settings.php?tab=priorities');
            break;
    }
}

$branches = db()->query("SELECT * FROM branches ORDER BY status DESC, name")->fetchAll();
$categories = db()->query("SELECT * FROM categories ORDER BY status DESC, name")->fetchAll();
$priorities = db()->query("SELECT * FROM priorities ORDER BY sort_order")->fetchAll();
$activityLog = db()->query("SELECT a.*, u.full_name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 50")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<section class="card">
    <div class="tabs">
        <a href="?tab=general" class="tab-link <?= $tab === 'general' ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i> General</a>
        <a href="?tab=branches" class="tab-link <?= $tab === 'branches' ? 'active' : '' ?>"><i class="fa-solid fa-code-branch"></i> Branches</a>
        <a href="?tab=categories" class="tab-link <?= $tab === 'categories' ? 'active' : '' ?>"><i class="fa-solid fa-tags"></i> Categories</a>
        <a href="?tab=priorities" class="tab-link <?= $tab === 'priorities' ? 'active' : '' ?>"><i class="fa-solid fa-flag"></i> Priorities &amp; SLA</a>
        <a href="?tab=activity" class="tab-link <?= $tab === 'activity' ? 'active' : '' ?>"><i class="fa-solid fa-list-check"></i> Activity Log</a>
    </div>

    <div class="card-body">
        <?php if ($tab === 'general'): ?>
        <form method="post" class="stack-form form-narrow">
            <input type="hidden" name="action" value="update_general">
            <?= csrf_field() ?>
            <div class="form-field"><label>Site Name</label><input type="text" name="site_name" value="<?= e(get_setting('site_name', SITE_NAME)) ?>"></div>
            <div class="form-field"><label>Company Name</label><input type="text" name="company_name" value="<?= e(get_setting('company_name', COMPANY_NAME)) ?>"></div>
            <div class="form-field"><label>Support Email</label><input type="email" name="support_email" value="<?= e(get_setting('support_email', '')) ?>"></div>
            <p class="field-hint"><i class="fa-solid fa-circle-info"></i> To add your logo, drop the image file into <code>assets/images/logo/</code> and update the reference in <code>includes/sidebar.php</code>.</p>
            <div class="form-actions"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button></div>
        </form>

        <?php elseif ($tab === 'branches'): ?>
        <div class="settings-two-col">
            <form method="post" class="stack-form">
                <input type="hidden" name="action" value="add_branch">
                <?= csrf_field() ?>
                <h4>Add Branch</h4>
                <div class="form-field"><label>Branch Name</label><input type="text" name="name" required></div>
                <div class="form-field"><label>Branch Code</label><input type="text" name="code" required></div>
                <div class="form-field"><label>Location</label><input type="text" name="location"></div>
                <div class="form-field"><label>Contact Phone</label><input type="text" name="contact_phone"></div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Branch</button>
            </form>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Code</th><th>Location</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($branches as $b): ?>
                        <tr>
                            <td><?= e($b['name']) ?></td><td><?= e($b['code']) ?></td><td><?= e($b['location']) ?></td>
                            <td><span class="badge <?= $b['status']==='active'?'badge-resolved':'badge-closed' ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                            <td>
                                <?php if ($b['status'] === 'active'): ?>
                                <form method="post" onsubmit="return confirm('Deactivate this branch?');">
                                    <input type="hidden" name="action" value="delete_branch"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><?= csrf_field() ?>
                                    <button type="submit" class="icon-link icon-danger" style="border:none;background:none;"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'categories'): ?>
        <div class="settings-two-col">
            <form method="post" class="stack-form">
                <input type="hidden" name="action" value="add_category">
                <?= csrf_field() ?>
                <h4>Add Category</h4>
                <div class="form-field"><label>Category Name</label><input type="text" name="name" required></div>
                <div class="form-field"><label>Description</label><input type="text" name="description"></div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Category</button>
            </form>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Description</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><?= e($c['name']) ?></td><td><?= e($c['description']) ?></td>
                            <td><span class="badge <?= $c['status']==='active'?'badge-resolved':'badge-closed' ?>"><?= e(ucfirst($c['status'])) ?></span></td>
                            <td>
                                <?php if ($c['status'] === 'active'): ?>
                                <form method="post" onsubmit="return confirm('Deactivate this category?');">
                                    <input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><?= csrf_field() ?>
                                    <button type="submit" class="icon-link icon-danger" style="border:none;background:none;"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'priorities'): ?>
        <div class="settings-two-col">
            <form method="post" class="stack-form">
                <input type="hidden" name="action" value="add_priority">
                <?= csrf_field() ?>
                <h4>Add Priority Level</h4>
                <div class="form-field"><label>Name</label><input type="text" name="name" required></div>
                <div class="form-field"><label>Colour</label><input type="color" name="color" value="#7C3AED"></div>
                <div class="form-field"><label>SLA (hours)</label><input type="number" name="sla_hours" value="48" min="1" required></div>
                <div class="form-field"><label>Sort Order</label><input type="number" name="sort_order" value="0"></div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Priority</button>
            </form>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Colour</th><th>SLA (h)</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($priorities as $p): ?>
                        <tr>
                            <td><?= e($p['name']) ?></td>
                            <td><span class="priority-dot" style="background:<?= e($p['color']) ?>"></span> <?= e($p['color']) ?></td>
                            <td><?= (int)$p['sla_hours'] ?>h</td>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete this priority level?');">
                                    <input type="hidden" name="action" value="delete_priority"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><?= csrf_field() ?>
                                    <button type="submit" class="icon-link icon-danger" style="border:none;background:none;"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'activity'): ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>User</th><th>Action</th><th>Details</th><th>IP</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($activityLog as $log): ?>
                    <tr>
                        <td><?= e($log['full_name'] ?? 'System') ?></td>
                        <td><span class="tag-mini"><?= e($log['action']) ?></span></td>
                        <td><?= e($log['details']) ?></td>
                        <td><?= e($log['ip_address']) ?></td>
                        <td><?= time_ago($log['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
