<?php
declare(strict_types=1);

/**
 * Mini app di ricerca concorsi con filtri:
 * - q (testo su titolo/ente)
 * - regione
 * - categoria
 * - requisiti[] (multi-select)
 * - match_all (AND se 1, altrimenti OR)
 */

$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'concorsi';
$user = getenv('DB_USER') ?: 'user';
$pass = getenv('DB_PASS') ?: 'pwd';

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1>Errore connessione DB</h1><pre>".htmlspecialchars($e->getMessage())."</pre>";
    exit;
}

// --- Input ---
$q           = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$regione     = isset($_GET['regione']) ? trim((string)$_GET['regione']) : '';
$categoria   = isset($_GET['categoria']) ? trim((string)$_GET['categoria']) : '';
$requisiti   = isset($_GET['requisiti']) ? (array)$_GET['requisiti'] : [];
$requisiti   = array_values(array_filter(array_map('trim', $requisiti), fn($v) => $v !== ''));
$matchAll    = isset($_GET['match_all']) && $_GET['match_all'] === '1';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 10;
$offset      = ($page - 1) * $perPage;

// --- Liste per i filtri (per le select) ---
$regioni   = $pdo->query("SELECT DISTINCT regione AS v FROM concorsi ORDER BY v")->fetchAll(PDO::FETCH_COLUMN);
$categorie = $pdo->query("SELECT DISTINCT categoria AS v FROM concorsi ORDER BY v")->fetchAll(PDO::FETCH_COLUMN);
$allReq    = $pdo->query("SELECT codice FROM requisiti ORDER BY codice")->fetchAll(PDO::FETCH_COLUMN);

// --- Costruzione query dinamica ---
$params = [];
$where = ["c.scadenza >= CURDATE()"];
if ($q !== '') {
    $where[] = "(c.titolo LIKE :q OR c.ente LIKE :q)";
    $params[':q'] = "%$q%";
}
if ($regione !== '') {
    $where[] = "c.regione = :regione";
    $params[':regione'] = $regione;
}
if ($categoria !== '') {
    $where[] = "c.categoria = :categoria";
    $params[':categoria'] = $categoria;
}

$useAnd = $matchAll && !empty($requisiti);
$useOr  = !$matchAll && !empty($requisiti);

$countSql = '';
$dataSql  = '';
$countParams = $params;
$dataParams  = $params;

if ($useAnd) {
    // AND: tutti i requisiti selezionati devono essere presenti
    $placeholders = [];
    foreach ($requisiti as $i => $cod) {
        $ph = ":reqA$i";
        $placeholders[] = $ph;
        $countParams[$ph] = $cod;
        $dataParams[$ph]  = $cod;
    }

    // Subquery: concorso_id che matchano TUTTI i requisiti
    $sub = "SELECT cr.concorso_id
            FROM concorso_requisito cr
            JOIN requisiti r ON r.id = cr.requisito_id
            WHERE r.codice IN (" . implode(',', $placeholders) . ")
            GROUP BY cr.concorso_id
            HAVING COUNT(DISTINCT r.codice) = " . count($requisiti);

    $from  = "FROM ($sub) t JOIN concorsi c ON c.id = t.concorso_id";
    $whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : '';

    $countSql = "SELECT COUNT(*) " . $from . $whereSql;
    $dataSql  = "SELECT c.id, c.titolo, c.ente, c.regione, c.categoria, c.scadenza, c.link_bando
                 " . $from . $whereSql . "
                 ORDER BY c.scadenza ASC
                 LIMIT :limit OFFSET :offset";
} else {
    // OR (o nessun filtro requisiti)
    $join = '';
    if ($useOr) {
        $placeholders = [];
        foreach ($requisiti as $i => $cod) {
            $ph = ":reqO$i";
            $placeholders[] = $ph;
            $params[$ph] = $cod;
        }
        $join .= " JOIN concorso_requisito cr ON cr.concorso_id = c.id
                   JOIN requisiti r ON r.id = cr.requisito_id";
        $where[] = "r.codice IN (" . implode(',', $placeholders) . ")";
        // aggiorna params per count/data
        $countParams = $params;
        $dataParams  = $params;
    }

    $from = "FROM concorsi c" . $join;
    $whereSql = $where ? (" WHERE " . implode(" AND ", $where)) : '';

    $countSql = "SELECT COUNT(" . ($useOr ? "DISTINCT c.id" : "*") . ") $from $whereSql";
    $dataSql  = "SELECT " . ($useOr ? "DISTINCT" : "") . " c.id, c.titolo, c.ente, c.regione, c.categoria, c.scadenza, c.link_bando
                 $from $whereSql
                 ORDER BY c.scadenza ASC
                 LIMIT :limit OFFSET :offset";
}

// --- Count ---
$stmt = $pdo->prepare($countSql);
$stmt->execute($countParams);
$total = (int)$stmt->fetchColumn();

// --- Data ---
$stmt = $pdo->prepare($dataSql);
foreach ($dataParams as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// --- Precarica requisiti per i risultati correnti ---
$reqMap = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $phs = [];
    $bind = [];
    foreach ($ids as $i => $id) {
        $ph = ":id$i";
        $phs[] = $ph;
        $bind[$ph] = (int)$id;
    }
    $sqlReq = "SELECT cr.concorso_id, r.codice
               FROM concorso_requisito cr
               JOIN requisiti r ON r.id = cr.requisito_id
               WHERE cr.concorso_id IN (" . implode(',', $phs) . ")
               ORDER BY r.codice";
    $st = $pdo->prepare($sqlReq);
    $st->execute($bind);
    while ($r = $st->fetch()) {
        $reqMap[(int)$r['concorso_id']][] = $r['codice'];
    }
}

?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ricerca Concorsi</title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Helvetica Neue',Arial,sans-serif;margin:20px;background:#fafafa;color:#222;}
        .row{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin-bottom:12px}
        label{display:block;font-size:12px;margin-bottom:4px;color:#444}
        input,select,button{padding:8px;font-size:14px}
        .btn{background:#0d6efd;border:0;color:#fff;border-radius:6px;padding:8px 12px;cursor:pointer}
        .btn:disabled{opacity:.6;cursor:not-allowed}
        .muted{color:#666;font-size:12px}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
        .list{list-style:none;padding:0;margin:0}
        .list-item{padding:12px 14px;border-bottom:1px solid #eee}
        .list-item:last-child{border-bottom:0}
        .badge{background:#eef;border-radius:12px;padding:2px 8px;font-size:12px}
        .chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
        .chip{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:12px;padding:2px 8px;font-size:12px}
        .grid{display:grid;grid-template-columns:1fr;gap:16px}
        @media (min-width: 992px){ .grid{grid-template-columns:2fr 1fr} }
        .ad-slot{min-height:90px;background:#f7f7f7;border:1px dashed #ccc;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#777}
        .pagination{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
        .pagination a,.pagination span{padding:6px 10px;border-radius:6px;border:1px solid #ddd;text-decoration:none;color:#333}
        .pagination .active{background:#0d6efd;color:#fff;border-color:#0d6efd}
    </style>
</head>
<body>

<h1>Ricerca Concorsi</h1>

<div class="ad-slot" style="margin:8px 0 16px;">(Spazio pubblicitario top - placeholder)</div>

<form method="get" class="card" style="padding:14px;">
    <div class="row">
        <div>
            <label>Cerca</label>
            <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Titolo o Ente">
        </div>
        <div>
            <label>Regione</label>
            <select name="regione">
                <option value="">Tutte</option>
                <?php foreach ($regioni as $r): ?>
                    <option value="<?=htmlspecialchars($r)?>" <?=$regione===$r?'selected':''?>><?=htmlspecialchars($r)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Categoria</label>
            <select name="categoria">
                <option value="">Tutte</option>
                <?php foreach ($categorie as $c): ?>
                    <option value="<?=htmlspecialchars($c)?>" <?=$categoria===$c?'selected':''?>><?=htmlspecialchars($c)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Requisiti (multi)</label>
            <select name="requisiti[]" multiple size="6" style="min-width:180px;">
                <?php foreach ($allReq as $cod): ?>
                    <option value="<?=htmlspecialchars($cod)?>" <?=in_array($cod,$requisiti,true)?'selected':''?>><?=htmlspecialchars($cod)?></option>
                <?php endforeach; ?>
            </select>
            <div style="margin-top:6px;">
                <label class="muted">
                    <input type="checkbox" name="match_all" value="1" <?=$matchAll?'checked':''?>>
                    Match tutti i requisiti (AND) — se non selezionato: OR
                </label>
            </div>
        </div>
        <div>
            <button type="submit" class="btn">Cerca</button>
        </div>
    </div>
</form>

<div class="grid">
    <div>
        <div class="card" style="padding:14px;">
            <p class="muted">Trovati <strong><?=$total?></strong> concorsi.</p>

            <?php if (!$rows): ?>
                <p>Nessun risultato.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($rows as $row): ?>
                        <li class="list-item">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                                <div>
                                    <div><strong><?=htmlspecialchars($row['titolo'])?></strong></div>
                                    <div class="muted">
                                        <?=htmlspecialchars($row['ente'])?>
                                        • <?=htmlspecialchars($row['regione'])?>
                                        • <?=htmlspecialchars($row['categoria'])?>
                                    </div>
                                    <?php
                                    $cid = (int)$row['id'];
                                    if (!empty($reqMap[$cid])) {
                                        echo '<div class="chips" aria-label="Requisiti">';
                                        foreach ($reqMap[$cid] as $rc) {
                                            echo '<span class="chip">'.htmlspecialchars($rc).'</span>';
                                        }
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                                <div style="text-align:right; min-width:200px;">
                                    <div class="badge">Scadenza: <?=htmlspecialchars($row['scadenza'])?></div><br>
                                    <?php
                                    $url = (string)$row['link_bando'];
                                    $safe = (preg_match('~^https?://~i', $url)) ? $url : '#';
                                    ?>
                                    <a href="<?=htmlspecialchars($safe)?>" target="_blank" rel="nofollow noopener">Bando</a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php
                $pages = (int)ceil($total / $perPage);
                if ($pages > 1):
                    echo '<div class="pagination">';
                    for ($p = 1; $p <= $pages; $p++) {
                        $qs = $_GET; $qs['page'] = $p; $url = '?'.http_build_query($qs);
                        if ($p === $page) echo '<span class="active">'.$p.'</span>';
                        else echo '<a href="'.htmlspecialchars($url).'">'.$p.'</a>';
                    }
                    echo '</div>';
                endif;
                ?>
            <?php endif; ?>
        </div>
    </div>

    <aside>
        <div class="ad-slot">(Sidebar ad - placeholder)</div>
        <div class="card" style="padding:12px;margin-top:12px;">
            <div style="font-weight:600;margin-bottom:6px;">In scadenza</div>
            <div class="muted">Aggiungi qui un widget con i prossimi 5 bandi (query dedicata).</div>
        </div>
    </aside>
</div>

<footer class="muted" style="margin-top:24px;">© <?=date('Y')?> Ricerca Concorsi (DEV)</footer>
</body>
</html>