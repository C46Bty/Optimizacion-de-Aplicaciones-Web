<?php
require_once 'db.php';
$db = getDB();

$message = '';
$messageType = '';

// Add feed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $url = trim($_POST['url'] ?? '');
        $name = trim($_POST['name'] ?? '');
        if ($url) {
            try {
                $db->prepare("INSERT INTO feeds (url, name) VALUES (?, ?)")->execute([$url, $name]);
                $message = "Feed agregado exitosamente.";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = "Error: La URL ya existe o es inválida.";
                $messageType = 'danger';
            }
        } else {
            $message = "Por favor ingresa una URL válida.";
            $messageType = 'warning';
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM news WHERE feed_id=?")->execute([$id]);
        $db->prepare("DELETE FROM feeds WHERE id=?")->execute([$id]);
        $message = "Feed eliminado correctamente.";
        $messageType = 'success';
    }
}

$feeds = $db->query("SELECT f.*, COUNT(n.id) as news_count FROM feeds f LEFT JOIN news n ON n.feed_id=f.id GROUP BY f.id ORDER BY f.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Suggested feeds
$suggested = [
    ['name' => 'BBC World News', 'url' => 'http://feeds.bbci.co.uk/news/world/rss.xml'],
    ['name' => 'CNN Top Stories', 'url' => 'http://rss.cnn.com/rss/edition.rss'],
    ['name' => 'The Guardian World', 'url' => 'https://www.theguardian.com/world/rss'],
    ['name' => 'Al Jazeera English', 'url' => 'https://www.aljazeera.com/xml/rss/all.xml'],
    ['name' => 'NASA Breaking News', 'url' => 'https://www.nasa.gov/rss/dyn/breaking_news.rss'],
    ['name' => 'TechCrunch', 'url' => 'https://techcrunch.com/feed/'],
    ['name' => 'Hacker News', 'url' => 'https://news.ycombinator.com/rss'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestiona tus feeds RSS en RSS Reader. Agrega, organiza y elimina tus fuentes de noticias favoritas.">
    <meta property="og:title" content="Gestión de Feeds – RSS Reader">
    <meta property="og:description" content="Agrega y administra tus fuentes de noticias RSS.">
    <meta property="og:type" content="website">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#1a56db">
    <title>Gestión de Feeds – RSS Reader</title>
    <!-- DNS prefetch (fallback) + Preconnect al CDN -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <!-- Bootstrap CSS: síncrono (base del layout, evita CLS) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons: async — decorativo, no bloquea el render -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          media="print" onload="this.media='all';this.onload=null">
    <noscript><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"></noscript>
    <!-- Estilos propios: inline (elimina 1 request HTTP) -->
    <style><?php readfile(__DIR__ . '/assets/css/style.css'); ?></style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary-custom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-rss-fill fs-4"></i>
            <span class="fw-bold">RSS Reader</span>
        </a>
        <div class="navbar-nav ms-auto flex-row gap-2">
            <a class="nav-link text-white opacity-75 hover-white" href="index.php">
                <i class="bi bi-newspaper me-1"></i>Noticias
            </a>
            <a class="nav-link text-white fw-semibold" href="feeds.php">
                <i class="bi bi-rss me-1"></i>Feeds
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <div class="d-flex align-items-center mb-4">
        <div class="icon-circle me-3"><i class="bi bi-rss-fill"></i></div>
        <div>
            <h2 class="mb-0 fw-bold">Gestión de Feeds RSS</h2>
            <p class="text-muted mb-0">Agrega y administra tus fuentes de noticias</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Add Feed Form -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="fw-bold"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Agregar Feed</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">URL del Feed RSS *</label>
                            <input type="url" name="url" class="form-control" placeholder="https://example.com/feed.rss" required>
                            <div class="form-text">Ingresa la URL completa del feed RSS o Atom.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nombre personalizado</label>
                            <input type="text" name="name" class="form-control" placeholder="Ej: BBC Noticias (opcional)">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-lg me-2"></i>Agregar Feed
                        </button>
                    </form>

                    <hr class="my-4">

                    <h6 class="fw-semibold text-muted mb-3"><i class="bi bi-lightning-fill text-warning me-2"></i>Feeds Sugeridos</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($suggested as $s): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm suggested-btn"
                            data-url="<?= htmlspecialchars($s['url']) ?>"
                            data-name="<?= htmlspecialchars($s['name']) ?>">
                            <?= htmlspecialchars($s['name']) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feeds List -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="bi bi-list-ul text-primary me-2"></i>Feeds Configurados</h5>
                    <span class="badge bg-primary rounded-pill"><?= count($feeds) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($feeds)): ?>
                    <div class="text-center py-5 px-3">
                        <i class="bi bi-inbox text-muted" style="font-size:3rem"></i>
                        <p class="text-muted mt-3">No hay feeds configurados aún.<br>Agrega tu primer feed RSS.</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($feeds as $feed): ?>
                        <div class="list-group-item px-4 py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1 me-3">
                                    <div class="fw-semibold d-flex align-items-center gap-2">
                                        <i class="bi bi-rss text-warning"></i>
                                        <?= htmlspecialchars($feed['name'] ?: 'Sin nombre') ?>
                                        <span class="badge bg-light text-dark border"><?= $feed['news_count'] ?> noticias</span>
                                    </div>
                                    <div class="text-muted small mt-1 text-truncate" style="max-width:350px">
                                        <i class="bi bi-link-45deg"></i>
                                        <a href="<?= htmlspecialchars($feed['url']) ?>" target="_blank" rel="noopener noreferrer" class="text-muted">
                                            <?= htmlspecialchars($feed['url']) ?>
                                        </a>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        Agregado: <?= date('d/m/Y H:i', strtotime($feed['created_at'])) ?>
                                        <?php if ($feed['last_fetched']): ?>
                                        &nbsp;·&nbsp;<i class="bi bi-arrow-clockwise me-1"></i>
                                        Actualizado: <?= date('d/m/Y H:i', strtotime($feed['last_fetched'])) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <form method="POST" onsubmit="return confirm('¿Eliminar este feed y todas sus noticias?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $feed['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js" defer></script>
<script>
document.querySelectorAll('.suggested-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelector('input[name="url"]').value = btn.dataset.url;
        document.querySelector('input[name="name"]').value = btn.dataset.name;
        document.querySelector('input[name="url"]').focus();
    });
});
</script>
</body>
</html>
