/** Daet, Camarines Norte — map viewport lock */
(function (global) {
    global.DaetMapConfig = {
        center: [14.1128, 122.9559],
        defaultZoom: 13,
        minZoom: 11,
        maxZoom: 18,
        maxBounds: [[14.00, 122.88], [14.22, 123.05]],
        tileUrl: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        tileAttribution: '&copy; Esri',
        label: 'Daet, Camarines Norte',
    };
})(typeof window !== 'undefined' ? window : global);
