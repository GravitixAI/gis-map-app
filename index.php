<?php
declare(strict_types=1);

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

require __DIR__ . '/api/session.php';

$lesson = '02 — Shapes from MySQL';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GIS Map Lab</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@5.6.1/dist/maplibre-gl.css">
    <link rel="stylesheet" href="css/app.css">
</head>
<body>
    <header class="lab-bar">
        <div class="lab-brand">GIS Map Lab</div>
        <div class="lab-lesson"><?php echo htmlspecialchars($lesson, ENT_QUOTES, 'UTF-8'); ?></div>
    </header>

    <main id="map" role="application" aria-label="Map"></main>

    <div id="layers-panel" title="Layers">
        <p class="panel-kicker">Basemap</p>
        <div class="btn-group w-100" role="group" aria-label="Basemap">
            <input class="btn-check" type="radio" name="basemap" id="basemap-streets" value="streets" checked>
            <label class="btn btn-outline-primary" for="basemap-streets">Streets</label>
            <input class="btn-check" type="radio" name="basemap" id="basemap-aerial" value="aerial">
            <label class="btn btn-outline-primary" for="basemap-aerial">Aerial</label>
        </div>
        <p class="panel-kicker">Draw</p>
        <div class="lab-field">
            <label for="feature-name">Name</label>
            <input class="form-control form-control-sm" id="feature-name" maxlength="80" autocomplete="off" placeholder="Home base">
        </div>
        <div class="lab-field">
            <label for="player-name">Player</label>
            <input class="form-control form-control-sm" id="player-name" maxlength="40" autocomplete="off" placeholder="Your name">
        </div>
        <div class="btn-group btn-group-sm w-100" role="group" aria-label="Draw tool">
            <input class="btn-check" type="radio" name="draw-tool" id="draw-off" value="" checked>
            <label class="btn btn-outline-secondary" for="draw-off">Off</label>
            <input class="btn-check" type="radio" name="draw-tool" id="draw-pin" value="pin">
            <label class="btn btn-outline-secondary" for="draw-pin">Pin</label>
            <input class="btn-check" type="radio" name="draw-tool" id="draw-path" value="path">
            <label class="btn btn-outline-secondary" for="draw-path">Path</label>
            <input class="btn-check" type="radio" name="draw-tool" id="draw-polygon" value="polygon">
            <label class="btn btn-outline-secondary" for="draw-polygon">Polygon</label>
        </div>
        <button type="button" class="btn btn-primary btn-sm w-100 mt-2" id="draw-finish" disabled>Save shape</button>
        <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-1" id="draw-clear" disabled>Clear sketch</button>
        <p class="hint" id="draw-hint">Choose Pin, Path, or Polygon, then click the map. A path needs two clicks. A polygon needs three, then Save shape.</p>
        <p class="panel-kicker">Saved</p>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="layer-game" checked>
            <label class="form-check-label" for="layer-game">Game shapes <span id="game-count"></span></label>
        </div>
        <p class="hint" id="save-status"></p>
    </div>

    <footer id="readout" aria-live="polite">Loading map…</footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/maplibre-gl@5.6.1/dist/maplibre-gl.js"></script>
    <script>window.GIS_TOKEN = <?php echo json_encode(gis_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script src="js/map.js?v=2"></script>
</body>
</html>
