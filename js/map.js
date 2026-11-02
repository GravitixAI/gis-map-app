/*
 * Lesson 01
 * The browser draws the map. PHP only sends this page.
 * The numbers in the footer are longitude and latitude on WGS84.
 * The pictures themselves are Web Mercator tiles.
 */
(function ($) {
    "use strict";

    var STREETS_STYLE = "https://tiles.openfreemap.org/styles/liberty";
    var AERIAL_TILES = "https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}";

    var vectorVisibility = {};
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

        setBasemap("streets");
        updateReadout();
    });

    map.on("moveend", updateReadout);

    map.on("error", function () {
        $("#readout").text("A tile request failed. The page is up; the picture did not finish loading.");
    });

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

    function updateReadout() {
        var center = map.getCenter();
        var lon = center.lng.toFixed(5);
        var lat = center.lat.toFixed(5);
        var zoom = map.getZoom().toFixed(2);
        $("#readout").text("WGS84  " + lon + ", " + lat + "   zoom " + zoom);
    }

    $(function () {
        $("#layers-panel").dialog({
            width: 280,
            minWidth: 240,
            position: { my: "right top", at: "right-16 top+68", of: window },
            resizable: false
        });

        $("input[name=basemap]").on("change", function () {
            setBasemap($(this).val());
        });
    });
}(jQuery));
