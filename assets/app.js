/* ==========================================================================
   PinPath — client logic
   - Leaflet map with OpenStreetMap tiles
   - Nominatim search (dropdown) + reverse geocode on map click
   - Markers: blue = not visited, green = visited, red = current selection
   - Interactive saved-pin popups: mark visited, edit notes, remove
   - AJAX save (save-itinerary.php) + per-location update (update-location.php)
   ========================================================================== */

(function () {
    "use strict";

    var CFG = window.PINPATH || {};
    var NOMINATIM = "https://nominatim.openstreetmap.org";

    // Current unsaved selection (the red marker). null until the user picks somewhere.
    var selection = null;     // { name, lat, lng }
    var selMarker = null;     // red Leaflet marker
    var map;

    // Saved location markers keyed by location id, so we can recolor/remove them.
    var savedMarkers = {};    // { loc_001: { marker, data } }

    // ---- DOM refs ----------------------------------------------------------
    var $search   = document.getElementById("search");
    var $results  = document.getElementById("searchResults");
    var $name     = document.getElementById("placeName");
    var $lat      = document.getElementById("lat");
    var $lng      = document.getElementById("lng");
    var $addBtn   = document.getElementById("addBtn");
    var $status   = document.getElementById("status");

    // ---- Custom colored markers (DivIcons, no external images) -------------
    function pin(color) {
        return L.divIcon({
            className: "pp-pin",
            html:
                '<span style="background:' + color + ';width:18px;height:18px;' +
                "display:block;border:2px solid #fff;border-radius:50% 50% 50% 0;" +
                'transform:rotate(-45deg);box-shadow:0 1px 4px rgba(0,0,0,.4);"></span>',
            iconSize: [22, 22],
            iconAnchor: [11, 22],
            popupAnchor: [0, -20],
        });
    }
    var BLUE = "#2563eb", GREEN = "#16a34a", RED = "#dc2626";

    function esc(v) {
        return String(v == null ? "" : v)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function setStatus(msg, kind) {
        $status.textContent = msg || "";
        $status.className = "status" + (kind ? " " + kind : "");
    }

    // Normalize a (possibly legacy) location record with sane defaults.
    function withDefaults(loc) {
        return {
            id:         loc.id,
            name:       loc.name || "",
            lat:        loc.lat,
            lng:        loc.lng,
            added_at:   loc.added_at || "",
            visited:    loc.visited === true,
            notes:      typeof loc.notes === "string" ? loc.notes : "",
            updated_at: loc.updated_at || loc.added_at || "",
        };
    }

    // ---- Map init ----------------------------------------------------------
    function initMap() {
        map = L.map("map").setView(CFG.defaultCenter || [22.9734, 78.6569], CFG.defaultZoom || 5);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap contributors",
        }).addTo(map);

        map.on("click", onMapClick);
        renderSavedLocations();
    }

    // Draw markers for every saved location of the loaded itinerary.
    function renderSavedLocations() {
        var it = CFG.itinerary;
        if (!it || !Array.isArray(it.locations) || !it.locations.length) return;

        var bounds = [];
        it.locations.forEach(function (raw) {
            var loc = withDefaults(raw);
            addSavedMarker(loc);
            bounds.push([loc.lat, loc.lng]);
        });

        if (bounds.length === 1) map.setView(bounds[0], 13);
        else map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
    }

    // Create (or recreate) a marker for a saved location with its popup.
    function addSavedMarker(loc) {
        var marker = L.marker([loc.lat, loc.lng], {
            icon: pin(loc.visited ? GREEN : BLUE),
        }).addTo(map);

        marker.bindPopup(buildPopup(loc, marker), { minWidth: 240, maxWidth: 280 });
        savedMarkers[loc.id] = { marker: marker, data: loc };
        return marker;
    }

    // ---- Interactive popup -------------------------------------------------
    function buildPopup(loc, marker) {
        var wrap = document.createElement("div");
        wrap.className = "popup popup-edit";

        wrap.innerHTML =
            '<strong>' + esc(loc.name) + "</strong>" +
            '<div class="popup-coords">Lat: ' + esc(Number(loc.lat).toFixed(6)) +
            "<br>Lng: " + esc(Number(loc.lng).toFixed(6)) + "</div>" +
            '<label class="popup-visited"><input type="checkbox" class="pp-visited"' +
            (loc.visited ? " checked" : "") + "> Mark as visited</label>" +
            '<textarea class="pp-notes" rows="2" placeholder="Notes…">' + esc(loc.notes) + "</textarea>" +
            '<div class="popup-actions">' +
            '<span class="pp-save-state"></span>' +
            '<button type="button" class="pp-remove">Remove</button>' +
            "</div>";

        var cbVisited = wrap.querySelector(".pp-visited");
        var taNotes   = wrap.querySelector(".pp-notes");
        var saveState = wrap.querySelector(".pp-save-state");
        var btnRemove = wrap.querySelector(".pp-remove");

        function flash(text, kind) {
            saveState.textContent = text || "";
            saveState.className = "pp-save-state" + (kind ? " " + kind : "");
        }

        // Auto-save when the checkbox toggles.
        cbVisited.addEventListener("change", function () {
            flash("Saving…");
            updateLocation(loc.id, { visited: cbVisited.checked }, function (ok, res) {
                if (!ok) { flash("Error", "err"); return; }
                loc.visited = cbVisited.checked;
                // Recolor the marker to reflect visited state.
                marker.setIcon(pin(loc.visited ? GREEN : BLUE));
                if (savedMarkers[loc.id]) savedMarkers[loc.id].data.visited = loc.visited;
                flash("Saved", "ok");
            });
        });

        // Debounced auto-save for notes (700ms after last keystroke).
        var notesTimer = null;
        taNotes.addEventListener("input", function () {
            flash("Typing…");
            clearTimeout(notesTimer);
            notesTimer = setTimeout(function () {
                flash("Saving…");
                updateLocation(loc.id, { notes: taNotes.value }, function (ok) {
                    if (!ok) { flash("Error", "err"); return; }
                    loc.notes = taNotes.value;
                    if (savedMarkers[loc.id]) savedMarkers[loc.id].data.notes = loc.notes;
                    flash("Saved", "ok");
                });
            }, 700);
        });

        // Remove the location.
        btnRemove.addEventListener("click", function () {
            if (!window.confirm("Remove “" + loc.name + "” from this itinerary?")) return;
            flash("Removing…");
            removeLocation(loc.id, marker, function (ok) {
                if (!ok) flash("Error", "err");
            });
        });

        return wrap;
    }

    // ---- Backend calls -----------------------------------------------------
    function postJson(url, payload, cb) {
        fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) { cb(res.ok && res.j && res.j.success, res.j); })
            .catch(function () { cb(false, null); });
    }

    function updateLocation(locId, fields, cb) {
        var payload = {
            itinerary_id: CFG.itineraryId,
            location_id: locId,
            action: "update",
        };
        if ("visited" in fields) payload.visited = fields.visited;
        if ("notes" in fields)   payload.notes = fields.notes;
        postJson("update-location.php", payload, cb || function () {});
    }

    function removeLocation(locId, marker, cb) {
        postJson("update-location.php", {
            itinerary_id: CFG.itineraryId,
            location_id: locId,
            action: "delete",
        }, function (ok, res) {
            if (ok) {
                map.removeLayer(marker);
                delete savedMarkers[locId];
            }
            if (cb) cb(ok, res);
        });
    }

    // ---- Selection (red marker) -------------------------------------------
    function setSelection(name, lat, lng) {
        selection = { name: name, lat: lat, lng: lng };
        $name.value = name || "";
        $lat.value = Number(lat).toFixed(6);
        $lng.value = Number(lng).toFixed(6);
        $addBtn.disabled = false;

        var ll = [lat, lng];
        if (selMarker) selMarker.setLatLng(ll);
        else selMarker = L.marker(ll, { icon: pin(RED), zIndexOffset: 1000 }).addTo(map);
    }

    // ---- Map click -> reverse geocode -------------------------------------
    function onMapClick(e) {
        var lat = e.latlng.lat, lng = e.latlng.lng;
        setSelection($name.value || "Dropped pin", lat, lng);
        setStatus("Looking up address…");

        fetch(NOMINATIM + "/reverse?format=json&lat=" + lat + "&lon=" + lng, {
            headers: { "Accept": "application/json" },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var name = data.display_name || ("Pin " + lat.toFixed(4) + ", " + lng.toFixed(4));
                setSelection(name, lat, lng);
                setStatus("");
            })
            .catch(function () { setStatus(""); });
    }

    // ---- Nominatim search (debounced) -------------------------------------
    var searchTimer = null;
    function onSearchInput() {
        var q = $search.value.trim();
        clearTimeout(searchTimer);
        if (q.length < 3) { hideResults(); return; }
        searchTimer = setTimeout(function () { runSearch(q); }, 350);
    }

    function runSearch(q) {
        showResults('<li class="loading">Searching…</li>');
        fetch(NOMINATIM + "/search?format=json&limit=6&q=" + encodeURIComponent(q), {
            headers: { "Accept": "application/json" },
        })
            .then(function (r) { return r.json(); })
            .then(function (list) {
                if (!list || !list.length) {
                    showResults('<li class="no-results">No results</li>');
                    return;
                }
                $results.innerHTML = "";
                list.forEach(function (item) {
                    var li = document.createElement("li");
                    li.textContent = item.display_name;
                    li.addEventListener("click", function () {
                        var lat = parseFloat(item.lat), lng = parseFloat(item.lon);
                        setSelection(item.display_name, lat, lng);
                        map.setView([lat, lng], 14);
                        hideResults();
                        $search.value = "";
                    });
                    $results.appendChild(li);
                });
                $results.hidden = false;
            })
            .catch(function () { showResults('<li class="no-results">Search failed</li>'); });
    }

    function showResults(html) { $results.innerHTML = html; $results.hidden = false; }
    function hideResults() { $results.hidden = true; $results.innerHTML = ""; }

    document.addEventListener("click", function (e) {
        if (!e.target.closest(".search-wrap")) hideResults();
    });

    // ---- Add to itinerary (AJAX save) -------------------------------------
    function onAdd() {
        if (!selection) return;
        var name = $name.value.trim();
        if (!name) { setStatus("Please enter a place name.", "error"); return; }

        var itineraryId = CFG.itineraryId;
        var itineraryName = itineraryId;

        if (!itineraryId) {
            itineraryName = window.prompt("Name this itinerary:", "My Trip");
            if (itineraryName === null) return;
            itineraryName = itineraryName.trim();
            if (!itineraryName) { setStatus("Itinerary name is required.", "error"); return; }
        }

        var body = new URLSearchParams();
        body.set("itinerary_id", itineraryId || "");
        body.set("itinerary_name", itineraryName || "");
        body.set("location_name", name);
        body.set("lat", selection.lat);
        body.set("lng", selection.lng);

        $addBtn.disabled = true;
        setStatus("Saving…");

        fetch("save-itinerary.php", { method: "POST", body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) {
                    setStatus(res.error || "Save failed.", "error");
                    $addBtn.disabled = false;
                    return;
                }
                setStatus("Saved! Reloading…", "ok");
                window.location.href = "index.php?itinerary=" + encodeURIComponent(res.itinerary_id);
            })
            .catch(function () {
                setStatus("Network error while saving.", "error");
                $addBtn.disabled = false;
            });
    }

    // ---- Closable controls panel ------------------------------------------
    function toggleControls(show) {
        var panel = document.getElementById("controls");
        var openBtn = document.getElementById("controlsOpen");
        if (!panel || !openBtn) return;
        panel.hidden = !show;
        openBtn.hidden = show;
        if (map) setTimeout(function () { map.invalidateSize(); }, 50);
    }

    // ---- Wire up -----------------------------------------------------------
    document.addEventListener("DOMContentLoaded", function () {
        initMap();
        $search.addEventListener("input", onSearchInput);
        $addBtn.addEventListener("click", onAdd);
        $name.addEventListener("input", function () {
            if (selection) selection.name = $name.value;
        });

        var closeBtn = document.getElementById("controlsClose");
        var openBtn = document.getElementById("controlsOpen");
        if (closeBtn) closeBtn.addEventListener("click", function () { toggleControls(false); });
        if (openBtn) openBtn.addEventListener("click", function () { toggleControls(true); });
    });
})();
