<?php
declare(strict_types=1);

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$lesson = '01 — A map on the page';
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
        <p class="panel-kicker">Overlays</p>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="layer-parcels" disabled>
            <label class="form-check-label" for="layer-parcels">Parcels</label>
        </div>
        <p class="hint">Parcels wait until we pick a county and look at a real file. This page only draws tiles.</p>
    </div>

    <footer id="readout" aria-live="polite">Loading map…</footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/maplibre-gl@5.6.1/dist/maplibre-gl.js"></script>
    <script src="js/map.js"></script>
</body>
</html>
