/*
 * Lesson 02
 * Tiles still come from the web. Drawn shapes come from MySQL
 * through api/features.php, as one GeoJSON FeatureCollection.
 */
(function ($) {
    "use strict";

    var STREETS_STYLE = "https://tiles.openfreemap.org/styles/liberty";
    var AERIAL_TILES = "https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}";
    var GAME_LAYERS = ["game-fill", "game-line-hit", "game-line", "game-pins", "game-labels"];

    var vectorVisibility = {};
    var mapReady = false;
    var drawKind = "";
    var sketch = [];
    var popup = null;
    var map = new maplibregl.Map({
        container: "map",
        style: STREETS_STYLE,
        center: [-97.7431, 30.2672],
        zoom: 11,
        hash: true
    });

    map.addControl(new maplibregl.NavigationControl(), "top-right");
    map.addControl(new maplibregl.ScaleControl({ maxWidth: 120, unit: "imperial" }), "bottom-left");

    map.on("load", function () {
        map.getStyle().layers.forEach(function (layer) {
            var visibility = layer.layout && layer.layout.visibility;
            vectorVisibility[layer.id] = visibility || "visible";
        });

        map.addSource("aerial", {
            type: "raster",
            tiles: [AERIAL_TILES],
            tileSize: 256,
            attribution: "Imagery &copy; Esri, Maxar, Earthstar Geographics"
        });

        var firstId = map.getStyle().layers[0] && map.getStyle().layers[0].id;
        map.addLayer({
            id: "aerial",
            type: "raster",
            source: "aerial",
            layout: { visibility: "none" }
        }, firstId);

        map.addSource("game", {
            type: "geojson",
            data: { type: "FeatureCollection", features: [] }
        });
        map.addSource("sketch", {
            type: "geojson",
            data: { type: "FeatureCollection", features: [] }
        });

        map.addLayer({
            id: "game-fill",
            type: "fill",
            source: "game",
            filter: ["==", ["get", "kind"], "polygon"],
            paint: { "fill-color": "#3d8bfd", "fill-opacity": 0.35 }
        });
        map.addLayer({
            id: "game-line-hit",
            type: "line",
            source: "game",
            filter: ["any",
                ["==", ["get", "kind"], "polygon"],
                ["==", ["get", "kind"], "path"]
            ],
            paint: { "line-color": "#000000", "line-width": 14, "line-opacity": 0 }
        });
        map.addLayer({
            id: "game-line",
            type: "line",
            source: "game",
            filter: ["any",
                ["==", ["get", "kind"], "polygon"],
                ["==", ["get", "kind"], "path"]
            ],
            layout: { "line-cap": "round", "line-join": "round" },
            paint: {
                "line-color": ["match", ["get", "kind"], "path", "#f59e0b", "#1d4ed8"],
                "line-width": ["match", ["get", "kind"], "path", 4, 2]
            }
        });
        map.addLayer({
            id: "game-pins",
            type: "circle",
            source: "game",
            filter: ["==", ["get", "kind"], "pin"],
            paint: {
                "circle-radius": 6,
                "circle-color": "#e11d48",
                "circle-stroke-width": 2,
                "circle-stroke-color": "#ffffff"
            }
        });
        map.addLayer({
            id: "game-labels",
            type: "symbol",
            source: "game",
            layout: {
                "text-field": ["get", "name"],
                "text-font": ["Noto Sans Regular"],
                "text-size": 12,
                "text-anchor": ["match", ["get", "kind"], "pin", "top", "center"],
                "text-offset": ["match", ["get", "kind"], "pin", ["literal", [0, 1.15]], ["literal", [0, 0]]],
                "text-allow-overlap": true
            },
            paint: {
                "text-color": "#13202c",
                "text-halo-color": "#ffffff",
                "text-halo-width": 1.5
            }
        });
        map.addLayer({
            id: "sketch-line",
            type: "line",
            source: "sketch",
            filter: ["==", ["get", "role"], "edge"],
            layout: { "line-cap": "round", "line-join": "round" },
            paint: { "line-color": "#f43f5e", "line-width": 3, "line-dasharray": [1.2, 1] }
        });
        map.addLayer({
            id: "sketch-verts",
            type: "circle",
            source: "sketch",
            filter: ["==", ["get", "role"], "verts"],
            paint: {
                "circle-radius": 4,
                "circle-color": "#f43f5e",
                "circle-stroke-width": 2,
                "circle-stroke-color": "#ffffff"
            }
        });

        setBasemap($("input[name=basemap]:checked").val() || "streets");
        mapReady = true;
        updateReadout();
        loadFeatures();
    });

    map.on("click", function (event) {
        if (!mapReady) {
            return;
        }
        if (drawKind === "pin") {
            saveFeature("pin", {
                type: "Point",
                coordinates: [event.lngLat.lng, event.lngLat.lat]
            });
            return;
        }
        if (drawKind === "path" || drawKind === "polygon") {
            sketch.push([event.lngLat.lng, event.lngLat.lat]);
            refreshSketch();
            updateSketchButtons();
            return;
        }
        showFeaturePopup(event);
    });

    map.on("moveend", updateReadout);

    map.on("error", function () {
        $("#readout").text("A tile request failed. The page is up; the picture did not finish loading.");
    });

    function fitMap() {
        map.resize();
    }

    function setBasemap(mode) {
        var showAerial = mode === "aerial";
        if (!map.getLayer("aerial")) {
            return;
        }
        map.setLayoutProperty("aerial", "visibility", showAerial ? "visible" : "none");
        Object.keys(vectorVisibility).forEach(function (id) {
            if (!map.getLayer(id)) {
                return;
            }
            map.setLayoutProperty(id, "visibility", showAerial ? "none" : vectorVisibility[id]);
        });
    }

    function setGameVisible(show) {
        GAME_LAYERS.forEach(function (id) {
            if (map.getLayer(id)) {
                map.setLayoutProperty(id, "visibility", show ? "visible" : "none");
            }
        });
    }

    function updateReadout() {
        var center = map.getCenter();
        var lon = center.lng.toFixed(5);
        var lat = center.lat.toFixed(5);
        var zoom = map.getZoom().toFixed(2);
        $("#readout").text("WGS84  " + lon + ", " + lat + "   zoom " + zoom);
    }

    function setDrawTool(kind) {
        drawKind = kind;
        sketch = [];
        if (mapReady) {
            refreshSketch();
            map.doubleClickZoom[kind ? "disable" : "enable"]();
            map.getCanvas().style.cursor = kind ? "crosshair" : "";
        }
        updateSketchButtons();
    }

    function updateSketchButtons() {
        var needed = drawKind === "path" ? 2 : 3;
        var ready = (drawKind === "path" || drawKind === "polygon") && sketch.length >= needed;
        $("#draw-finish").prop("disabled", !ready);
        $("#draw-clear").prop("disabled", sketch.length === 0);
    }

    function refreshSketch() {
        var features = [];
        if (sketch.length) {
            features.push({
                type: "Feature",
                properties: { role: "verts" },
                geometry: { type: "MultiPoint", coordinates: sketch.slice() }
            });
        }
        if (sketch.length >= 2) {
            var line = sketch.slice();
            if (drawKind === "polygon" && sketch.length >= 3) {
                line = line.concat([sketch[0]]);
            }
            features.push({
                type: "Feature",
                properties: { role: "edge" },
                geometry: { type: "LineString", coordinates: line }
            });
        }
        map.getSource("sketch").setData({ type: "FeatureCollection", features: features });
    }

    function shapeName() {
        return $.trim($("#feature-name").val());
    }

    function saveFeature(kind, geometry) {
        var name = shapeName();
        if (!name) {
            $("#save-status").text("Give the shape a name, then click the map again.");
            return;
        }
        $("#save-status").text("Saving…");
        $.ajax({
            url: "api/features.php",
            method: "POST",
            contentType: "application/json",
            headers: { "X-GIS-Token": window.GIS_TOKEN },
            data: JSON.stringify({
                kind: kind,
                name: name,
                player: $.trim($("#player-name").val()),
                geometry: geometry
            })
        }).done(function () {
            sketch = [];
            if (map.getSource("sketch")) {
                refreshSketch();
            }
            updateSketchButtons();
            $("#save-status").text("Saved.");
            loadFeatures();
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.error;
            $("#save-status").text(message || "The database did not keep that shape.");
        });
    }

    function loadFeatures() {
        $.getJSON("api/features.php").done(function (data) {
            if (!data || data.type !== "FeatureCollection" || !$.isArray(data.features)) {
                $("#save-status").text("Saved shapes came back in an unexpected form.");
                return;
            }
            map.getSource("game").setData(data);
            $("#game-count").text("(" + data.features.length + ")");
        }).fail(function () {
            $("#save-status").text("Saved shapes did not load. The map is up; the database did not answer.");
        });
    }

    function showFeaturePopup(event) {
        var hits = map.queryRenderedFeatures(event.point, { layers: ["game-fill", "game-line-hit", "game-pins"] });
        if (!hits.length) {
            if (popup) {
                popup.remove();
            }
            return;
        }
        var feature = hits[0];
        var props = feature.properties || {};
        var id = feature.id;
        var html = '<div class="feature-popup"><strong></strong><div class="kind"></div></div>';
        var body = $(html);
        body.find("strong").text(props.name || "Shape");
        var detail = props.kind || "";
        if (props.player) {
            detail += " · " + props.player;
        }
        body.find(".kind").text(detail);
        if (id) {
            body.append(
                $("<button>", {
                    type: "button",
                    class: "btn btn-sm btn-outline-danger feature-delete",
                    text: "Remove",
                    "data-id": id
                })
            );
        }
        if (popup) {
            popup.remove();
        }
        popup = new maplibregl.Popup({ offset: 12, maxWidth: "240px" })
            .setLngLat(event.lngLat)
            .setDOMContent(body.get(0))
            .addTo(map);
    }

    function removeFeature(id) {
        $.ajax({
            url: "api/features.php?id=" + encodeURIComponent(id),
            method: "DELETE",
            headers: { "X-GIS-Token": window.GIS_TOKEN }
        }).done(function () {
            if (popup) {
                popup.remove();
            }
            $("#save-status").text("Removed.");
            loadFeatures();
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.error;
            $("#save-status").text(message || "That shape stayed on the map.");
        });
    }

    $(function () {
        $("#layers-panel").dialog({
            width: 300,
            minWidth: 260,
            maxHeight: Math.max(320, $(window).height() - 100),
            position: { my: "right top", at: "right-16 top+68", of: window },
            resizable: false,
            open: fitMap
        });
        fitMap();
        requestAnimationFrame(fitMap);
        setTimeout(fitMap, 50);
        if (typeof ResizeObserver === "function") {
            new ResizeObserver(fitMap).observe(document.getElementById("map"));
        }

        $("input[name=basemap]").on("change", function () {
            setBasemap($(this).val());
        });

        $("input[name=draw-tool]").on("change", function () {
            setDrawTool($(this).val());
        });

        $("#layer-game").on("change", function () {
            setGameVisible($(this).is(":checked"));
        });

        $("#draw-finish").on("click", function () {
            if (drawKind !== "path" && drawKind !== "polygon") {
                return;
            }
            var geometry = drawKind === "path"
                ? { type: "LineString", coordinates: sketch.slice() }
                : { type: "Polygon", coordinates: [sketch.concat([sketch[0]])] };
            saveFeature(drawKind, geometry);
        });

        $("#draw-clear").on("click", function () {
            sketch = [];
            if (mapReady) {
                refreshSketch();
            }
            updateSketchButtons();
        });

        $(document).on("click", ".feature-delete", function () {
            removeFeature($(this).data("id"));
        });
    });
}(jQuery));
