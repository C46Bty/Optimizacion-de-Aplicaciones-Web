<?php
require_once 'db.php';

header('Content-Type: application/json');

function fetchRSS($url) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'Mozilla/5.0 RSS Reader/1.0',
            'header' => "Accept: application/rss+xml, application/xml, text/xml\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);

    $content = @file_get_contents($url, false, $ctx);
    if ($content === false) {
        // Try with cURL
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 RSS Reader/1.0',
            ]);
            $content = curl_exec($ch);
            curl_close($ch);
        }
    }
    if (!$content) return null;

    // Remove BOM if present
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    if ($xml === false) return null;

    $items = [];
    $feedName = '';

    // RSS 2.0
    if (isset($xml->channel)) {
        $feedName = (string)$xml->channel->title;
        foreach ($xml->channel->item as $item) {
            $namespaces = $item->getNamespaces(true);
            $categories = [];
            foreach ($item->category as $cat) {
                $c = trim((string)$cat);
                if ($c) $categories[] = $c;
            }
            // dc:subject
            if (isset($namespaces['dc'])) {
                $dc = $item->children($namespaces['dc']);
                if (isset($dc->subject)) {
                    foreach ($dc->subject as $s) {
                        $c = trim((string)$s);
                        if ($c && !in_array($c, $categories)) $categories[] = $c;
                    }
                }
            }
            // media:category
            if (isset($namespaces['media'])) {
                $media = $item->children($namespaces['media']);
                if (isset($media->category)) {
                    foreach ($media->category as $mc) {
                        $c = trim((string)$mc);
                        if ($c && !in_array($c, $categories)) $categories[] = $c;
                    }
                }
            }

            $desc = '';
            if (isset($namespaces['content'])) {
                $content_ns = $item->children($namespaces['content']);
                if (isset($content_ns->encoded)) {
                    $desc = strip_tags((string)$content_ns->encoded);
                }
            }
            if (!$desc) $desc = strip_tags((string)$item->description);
            $desc = trim(preg_replace('/\s+/', ' ', $desc));
            if (strlen($desc) > 600) $desc = substr($desc, 0, 600) . '...';

            $pubDate = (string)$item->pubDate;
            if (!$pubDate && isset($namespaces['dc'])) {
                $dc = $item->children($namespaces['dc']);
                $pubDate = (string)$dc->date;
            }
            $timestamp = $pubDate ? @strtotime($pubDate) : time();
            if (!$timestamp) $timestamp = time();

            $link = (string)$item->link;
            if (!$link) {
                $linkNode = $item->children('', false)->link;
                $link = (string)$linkNode;
            }

            $guid = (string)$item->guid;
            if (!$guid) $guid = $link;
            if (!$guid) $guid = md5((string)$item->title . $pubDate);

            $items[] = [
                'title' => trim((string)$item->title),
                'url' => $link,
                'description' => $desc,
                'pub_date' => date('Y-m-d H:i:s', $timestamp),
                'categories' => implode(', ', $categories),
                'guid' => $guid,
            ];
        }
    }
    // Atom feed
    elseif (isset($xml->entry)) {
        $feedName = (string)$xml->title;
        foreach ($xml->entry as $entry) {
            $link = '';
            foreach ($entry->link as $l) {
                $rel = (string)$l->attributes()->rel;
                if ($rel === 'alternate' || $rel === '' || !$rel) {
                    $link = (string)$l->attributes()->href;
                    break;
                }
            }
            $categories = [];
            foreach ($entry->category as $cat) {
                $term = (string)$cat->attributes()->term;
                if ($term) $categories[] = $term;
            }
            $desc = strip_tags((string)$entry->summary);
            if (!$desc) $desc = strip_tags((string)$entry->content);
            $desc = trim(preg_replace('/\s+/', ' ', $desc));
            if (strlen($desc) > 600) $desc = substr($desc, 0, 600) . '...';

            $pubDate = (string)$entry->updated ?: (string)$entry->published;
            $timestamp = $pubDate ? @strtotime($pubDate) : time();
            if (!$timestamp) $timestamp = time();

            $guid = (string)$entry->id ?: $link ?: md5((string)$entry->title);

            $items[] = [
                'title' => trim((string)$entry->title),
                'url' => $link,
                'description' => $desc,
                'pub_date' => date('Y-m-d H:i:s', $timestamp),
                'categories' => implode(', ', $categories),
                'guid' => $guid,
            ];
        }
    }

    return ['name' => $feedName, 'items' => $items];
}

// --- Obtener feeds ---
$db = getDB();
$feeds = $db->query("SELECT * FROM feeds")->fetchAll(PDO::FETCH_ASSOC);

if (empty($feeds)) {
    echo json_encode(['success' => false, 'message' => 'No hay feeds configurados.', 'new_count' => 0]);
    exit;
}

$totalNew = 0;
$skipped = 0;
$errors = [];
$cacheMinutes = 30; // Omitir feed si fue actualizado hace menos de 30 min

$stmt = $db->prepare("
    INSERT OR IGNORE INTO news (feed_id, feed_name, title, url, description, pub_date, categories, guid)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$updateFetch = $db->prepare("UPDATE feeds SET last_fetched=CURRENT_TIMESTAMP WHERE id=?");

foreach ($feeds as $feed) {
    // Cache por feed: omitir si fue actualizado recientemente
    if ($feed['last_fetched']) {
        $age = time() - strtotime($feed['last_fetched']);
        if ($age < $cacheMinutes * 60) {
            $skipped++;
            continue;
        }
    }

    $result = fetchRSS($feed['url']);
    if ($result === null) {
        $errors[] = htmlspecialchars($feed['name'] ?: $feed['url']);
        continue;
    }

    // Update feed name if it was empty
    if ($result['name'] && !$feed['name']) {
        $db->prepare("UPDATE feeds SET name=? WHERE id=?")->execute([$result['name'], $feed['id']]);
    }

    $feedName = $result['name'] ?: $feed['name'] ?: $feed['url'];

    foreach ($result['items'] as $item) {
        try {
            $stmt->execute([
                $feed['id'],
                $feedName,
                $item['title'],
                $item['url'],
                $item['description'],
                $item['pub_date'],
                $item['categories'],
                $item['guid'],
            ]);
            if ($db->lastInsertId()) $totalNew++;
        } catch (Exception $e) {
            // Duplicate guid — skip
        }
    }

    $updateFetch->execute([$feed['id']]);
}

$msg = "Actualización completada. $totalNew nuevas noticias agregadas.";
if ($skipped > 0) $msg .= " ($skipped feed(s) omitidos por caché de {$cacheMinutes} min).";
if ($errors) $msg .= " Error en: " . implode(', ', $errors) . ".";

echo json_encode(['success' => true, 'message' => $msg, 'new_count' => $totalNew]);
