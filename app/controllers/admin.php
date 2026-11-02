<?php
declare(strict_types=1);

function admin_home(): void
{
    require_admin();
    $stats = db()->query("SELECT
        (SELECT COUNT(*) FROM users) users,
        (SELECT COUNT(*) FROM properties) properties,
        (SELECT COUNT(*) FROM properties WHERE status='pending') pending,
        (SELECT COUNT(*) FROM properties WHERE status='published') published,
        (SELECT COUNT(*) FROM inquiries) inquiries,
        (SELECT COUNT(*) FROM property_images) images,
        (SELECT COALESCE(SUM(views),0) FROM properties) views")->fetch();
    $byCity = db()->query("SELECT c.name_ar, c.name_en, COUNT(p.id) n FROM cities c LEFT JOIN properties p ON p.city_id = c.id AND p.status='published'
        GROUP BY c.id ORDER BY n DESC LIMIT 8")->fetchAll();
    $pending = db()->query(PROPERTY_SELECT . " WHERE p.status='pending' ORDER BY p.created_at ASC LIMIT 10")->fetchAll();
    $journal = db()->query('PRAGMA journal_mode')->fetchColumn();

    render('admin/index', [
        'pageTitle' => t('admin') . ' — ' . app_name(),
        'stats'     => $stats,
        'byCity'    => $byCity,
        'pending'   => $pending,
        'journal'   => $journal,
        'active'    => 'admin',
    ]);
}

function admin_properties(): void
{
    require_admin();
    $status = in_array($_GET['status'] ?? '', PROPERTY_STATUSES, true) ? $_GET['status'] : '';
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $where = ['1=1'];
    $args = [];
    if ($status) {
        $where[] = 'p.status = ?';
        $args[] = $status;
    }
    if ($q !== '' && ($m = fts_query($q)) !== '') {
        $where[] = 'p.id IN (SELECT rowid FROM properties_fts WHERE properties_fts MATCH ?)';
        $args[] = $m;
    }
    $w = implode(' AND ', $where);
    $cnt = db()->prepare("SELECT COUNT(*) FROM properties p WHERE $w");
    $cnt->execute($args);
    $pg = paginate((int) $cnt->fetchColumn(), 30, max(1, input_int('page', 1, $_GET) ?? 1));
    $stmt = db()->prepare(PROPERTY_SELECT . " WHERE $w ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([...$args, $pg['per_page'], $pg['offset']]);

    render('admin/properties', [
        'pageTitle' => t('admin_listings') . ' — ' . app_name(),
        'items'     => $stmt->fetchAll(),
        'pg'        => $pg,
        'status'    => $status,
        'q'         => $q,
        'active'    => 'admin_listings',
    ]);
}

function admin_property_status(int $id): void
{
    require_admin();
    $status = input('status');
    if (!in_array($status, PROPERTY_STATUSES, true)) {
        abort(400);
    }
    db()->prepare("UPDATE properties SET status = ?, updated_at = datetime('now') WHERE id = ?")->execute([$status, $id]);
    flash('success', t('status_changed'));
    back('/admin/properties');
}

function admin_property_feature(int $id): void
{
    require_admin();
    db()->prepare('UPDATE properties SET is_featured = 1 - is_featured WHERE id = ?')->execute([$id]);
    flash('success', t('status_changed'));
    back('/admin/properties');
}

function admin_users(): void
{
    require_admin();
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $args = [];
    $where = '1=1';
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where = "(u.name LIKE ? ESCAPE '\\' OR u.email LIKE ? ESCAPE '\\')";
        $args = [$like, $like];
    }
    $stmt = db()->prepare("SELECT u.*, (SELECT COUNT(*) FROM properties p WHERE p.user_id = u.id) listings
        FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT 300");
    $stmt->execute($args);
    render('admin/users', [
        'pageTitle' => t('admin_users') . ' — ' . app_name(),
        'items'     => $stmt->fetchAll(),
        'q'         => $q,
        'active'    => 'admin_users',
    ]);
}

function admin_user_update(int $id): void
{
    $me = require_admin();
    if ($id === (int) $me['id']) {
        flash('error', t('cannot_edit_self'));
        redirect(url('admin/users'));
    }
    $role = input('role');
    $status = input('status');
    if (!in_array($role, ['user', 'agent', 'admin'], true) || !in_array($status, ['active', 'banned'], true)) {
        abort(400);
    }
    db()->prepare('UPDATE users SET role = ?, status = ? WHERE id = ?')->execute([$role, $status, $id]);
    flash('success', t('user_saved'));
    redirect(url('admin/users'));
}

function admin_cities(): void
{
    require_admin();
    $rows = db()->query('SELECT c.*, (SELECT COUNT(*) FROM properties p WHERE p.city_id = c.id) n FROM cities c ORDER BY c.name_en')->fetchAll();
    render('admin/cities', ['pageTitle' => t('admin_cities') . ' — ' . app_name(), 'items' => $rows, 'active' => 'admin_cities']);
}

function admin_city_create(): void
{
    require_admin();
    $ar = mb_substr(input('name_ar'), 0, 80);
    $en = mb_substr(input('name_en'), 0, 80);
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($en)), '-');
    if ($ar === '' || $en === '' || $slug === '') {
        with_old(compact('ar', 'en'), ['name_en' => t('v_required')]);
        redirect(url('admin/cities'));
    }
    $stmt = db()->prepare('INSERT OR IGNORE INTO cities (name_ar, name_en, slug) VALUES (?,?,?)');
    $stmt->execute([$ar, $en, $slug]);
    flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? t('city_added') : t('city_exists'));
    redirect(url('admin/cities'));
}

function admin_city_delete(int $id): void
{
    require_admin();
    db()->prepare('DELETE FROM cities WHERE id = ?')->execute([$id]);
    flash('success', t('city_deleted'));
    redirect(url('admin/cities'));
}
