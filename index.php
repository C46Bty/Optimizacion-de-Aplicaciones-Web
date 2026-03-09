<?php
require_once 'db.php';
$db = getDB();

// Parameters
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'pub_date';
$order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$feedFilter = (int)($_GET['feed'] ?? 0);

// Allowed sort columns
$allowedSort = ['pub_date', 'title', 'feed_name', 'categories'];
if (!in_array($sort, $allowedSort)) $sort = 'pub_date';

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(title LIKE ? OR description LIKE ? OR categories LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($feedFilter) {
    $where[] = "feed_id = ?";
    $params[] = $feedFilter;
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";
$query = "SELECT * FROM news $whereSQL ORDER BY $sort $order";

$stmt = $db->prepare($query);
$stmt->execute($params);
$news = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalNews = $db->query("SELECT COUNT(*) FROM news")->fetchColumn();
$totalFeeds = $db->query("SELECT COUNT(*) FROM feeds")->fetchColumn();
$latestDate = $db->query("SELECT MAX(pub_date) FROM news")->fetchColumn();

// All feeds for filter
$feeds = $db->query("SELECT * FROM feeds ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

function sortUrl($col, $currentSort, $currentOrder) {
    $newOrder = ($currentSort === $col && $currentOrder === 'DESC') ? 'ASC' : 'DESC';
    $params = $_GET;
    $params['sort'] = $col;
    $params['order'] = $newOrder;
    return '?' . http_build_query($params);
}

function sortIcon($col, $currentSort, $currentOrder) {
    if ($currentSort !== $col) return '<i class="bi bi-arrow-down-up text-muted opacity-50"></i>';
    return $currentOrder === 'ASC'
        ? '<i class="bi bi-sort-up text-primary"></i>'
        : '<i class="bi bi-sort-down text-primary"></i>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSS Reader – Noticias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary-custom sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-rss-fill fs-4"></i>
            <span class="fw-bold">RSS Reader</span>
        </a>
        <div class="navbar-nav ms-auto flex-row gap-3 align-items-center">
            <a class="nav-link text-white fw-semibold" href="index.php">
                <i class="bi bi-newspaper me-1"></i>Noticias
            </a>
            <a class="nav-link text-white opacity-75" href="feeds.php">
                <i class="bi bi-rss me-1"></i>Feeds
            </a>
        </div>
    </div>
</nav>

<!-- Hero Banner -->
<div class="hero-banner">
    <div class="container">
        <div class="row align-items-center py-4">
            <div class="col-md-7">
                <h1 class="hero-title"><i class="bi bi-globe2 me-2"></i>Tu Lector de Noticias RSS</h1>
                <p class="hero-subtitle">Agrega tus fuentes favoritas y mantente informado en un solo lugar.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <div class="stat-pill"><i class="bi bi-newspaper me-1"></i><?= $totalNews ?> noticias</div>
                    <div class="stat-pill"><i class="bi bi-rss me-1"></i><?= $totalFeeds ?> feeds</div>
                    <?php if ($latestDate): ?>
                    <div class="stat-pill"><i class="bi bi-clock me-1"></i>Actualizado: <?= date('d/m/Y H:i', strtotime($latestDate)) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-5 text-end d-none d-md-block">
                <button class="btn btn-update btn-lg" id="updateBtn" onclick="updateFeeds()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Actualizar Feeds
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">

    <!-- Alert for update status -->
    <div id="updateAlert" class="d-none"></div>

    <!-- Search & Filter Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="index.php" id="searchForm">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold small text-muted mb-1">BUSCAR NOTICIAS</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0"
                                placeholder="Buscar por título, descripción o categoría..."
                                value="<?= htmlspecialchars($search) ?>" id="searchInput">
                            <?php if ($search): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()">
                                <i class="bi bi-x"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small text-muted mb-1">FILTRAR POR FEED</label>
                        <select name="feed" class="form-select" onchange="this.form.submit()">
                            <option value="0">Todos los feeds</option>
                            <?php foreach ($feeds as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= $feedFilter == $f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['name'] ?: $f['url']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i>Filtrar
                        </button>
                    </div>
                    <div class="col-md-2 d-md-none">
                        <button class="btn btn-update w-100" onclick="updateFeeds(); return false;">
                            <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results info -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">
            <?php if ($search || $feedFilter): ?>
                <span class="fw-semibold"><?= count($news) ?></span> resultado(s) encontrado(s)
                <?php if ($search): ?>
                    para "<strong><?= htmlspecialchars($search) ?></strong>"
                <?php endif; ?>
            <?php else: ?>
                Mostrando <span class="fw-semibold"><?= count($news) ?></span> noticias
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">Ordenar por:</span>
            <div class="btn-group btn-group-sm">
                <a href="<?= sortUrl('pub_date', $sort, $order) ?>" class="btn <?= $sort==='pub_date' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= sortIcon('pub_date', $sort, $order) ?> Fecha
                </a>
                <a href="<?= sortUrl('title', $sort, $order) ?>" class="btn <?= $sort==='title' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= sortIcon('title', $sort, $order) ?> Título
                </a>
                <a href="<?= sortUrl('feed_name', $sort, $order) ?>" class="btn <?= $sort==='feed_name' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= sortIcon('feed_name', $sort, $order) ?> Fuente
                </a>
                <a href="<?= sortUrl('categories', $sort, $order) ?>" class="btn <?= $sort==='categories' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= sortIcon('categories', $sort, $order) ?> Categoría
                </a>
            </div>
        </div>
    </div>

    <!-- News Grid -->
    <?php if (empty($news)): ?>
    <div class="empty-state text-center py-5">
        <div class="mb-4">
            <i class="bi bi-inbox display-1 text-muted opacity-50"></i>
        </div>
        <h4 class="text-muted">
            <?php if ($totalFeeds == 0): ?>
                No hay feeds configurados
            <?php elseif ($search): ?>
                No se encontraron noticias para "<?= htmlspecialchars($search) ?>"
            <?php else: ?>
                No hay noticias aún
            <?php endif; ?>
        </h4>
        <p class="text-muted">
            <?php if ($totalFeeds == 0): ?>
                <a href="feeds.php" class="btn btn-primary mt-2"><i class="bi bi-plus me-1"></i>Agregar Feeds</a>
            <?php elseif ($search): ?>
                <button onclick="clearSearch()" class="btn btn-outline-primary mt-2"><i class="bi bi-x me-1"></i>Limpiar búsqueda</button>
            <?php else: ?>
                <button class="btn btn-primary mt-2" onclick="updateFeeds()"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar ahora</button>
            <?php endif; ?>
        </p>
    </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" id="newsGrid">
        <?php foreach ($news as $item): ?>
        <div class="col">
            <div class="card news-card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                    <!-- Feed source -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge feed-badge">
                            <i class="bi bi-rss me-1"></i>
                            <?= htmlspecialchars($item['feed_name'] ?: 'Desconocido') ?>
                        </span>
                        <span class="text-muted small news-date">
                            <i class="bi bi-clock me-1"></i>
                            <?= $item['pub_date'] ? date('d/m/Y H:i', strtotime($item['pub_date'])) : 'Sin fecha' ?>
                        </span>
                    </div>

                    <!-- Title -->
                    <h5 class="card-title news-title">
                        <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank" class="text-decoration-none">
                            <?= htmlspecialchars($item['title'] ?: 'Sin título') ?>
                        </a>
                    </h5>

                    <!-- Description -->
                    <?php if ($item['description']): ?>
                    <p class="card-text news-description text-muted flex-grow-1">
                        <?= htmlspecialchars($item['description']) ?>
                    </p>
                    <?php endif; ?>

                    <!-- Categories -->
                    <?php if ($item['categories']): ?>
                    <div class="mt-2 mb-3">
                        <?php foreach (explode(',', $item['categories']) as $cat): ?>
                            <?php $cat = trim($cat); if ($cat): ?>
                            <span class="badge category-badge me-1 mb-1"><?= htmlspecialchars($cat) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- URL / Read more -->
                    <div class="mt-auto pt-2 border-top">
                        <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank" class="btn btn-sm btn-read-more">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Leer artículo completo
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<!-- Footer -->
<footer class="footer-custom mt-5 py-4">
    <div class="container text-center">
        <p class="mb-1"><i class="bi bi-rss-fill me-2 text-warning"></i><strong>RSS Reader</strong></p>
        <p class="text-muted small mb-0">Lector personalizable de feeds RSS &mdash; <?= date('Y') ?></p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateFeeds() {
    const btn = document.getElementById('updateBtn');
    const alert = document.getElementById('updateAlert');

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Actualizando...';
    }

    alert.className = 'alert alert-info d-flex align-items-center mb-4';
    alert.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Obteniendo noticias de los feeds, por favor espera...';

    fetch('fetch.php')
        .then(r => r.json())
        .then(data => {
            alert.className = 'alert alert-' + (data.success ? 'success' : 'warning') + ' mb-4';
            alert.innerHTML = '<i class="bi bi-' + (data.success ? 'check-circle-fill' : 'exclamation-triangle-fill') + ' me-2"></i>' + data.message;
            if (data.new_count > 0) {
                setTimeout(() => location.reload(), 1500);
            }
        })
        .catch(err => {
            alert.className = 'alert alert-danger mb-4';
            alert.innerHTML = '<i class="bi bi-x-circle-fill me-2"></i>Error al conectar con el servidor.';
        })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Actualizar Feeds';
            }
        });
}

function clearSearch() {
    const params = new URLSearchParams(window.location.search);
    params.delete('search');
    params.delete('feed');
    window.location.search = params.toString();
}

// Live search (debounced)
let searchTimer;
document.getElementById('searchInput')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        document.getElementById('searchForm').submit();
    }, 500);
});
</script>
</body>
</html>
