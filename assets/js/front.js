var FWP_MAP = FWP_MAP || {};

(function($) {

    FWP_MAP.markersArray = [];
    FWP_MAP.activeMarker = null;

    $(document).on('facetwp-loaded', function() {
        if (! FWP.loaded) {

            FWP_MAP.map = new google.maps.Map(document.getElementById('map'), FWP.settings.map.init);

            FWP_MAP.oms = new OverlappingMarkerSpiderfier(FWP_MAP.map, {
                markersWontMove: true,
                markersWontHide: true,
                basicFormatEvents: true
            });
        }
        else {
            clearOverlays();
        }

        // this needs to re-init on each refresh
        FWP_MAP.bounds = new google.maps.LatLngBounds();

        $.each(FWP.settings.map.locations, function(idx, val) {
            var marker = new google.maps.Marker({
                map: FWP_MAP.map,
                position: val.position,
                info: new google.maps.InfoWindow({
                    content: val.content
                })
            });

            google.maps.event.addListener(marker, 'spider_click', function() {
                if (null !== FWP_MAP.activeMarker) {
                    FWP_MAP.activeMarker.info.close();
                }

                marker.info.open(FWP_MAP.map, marker);
                FWP_MAP.activeMarker = marker;
            });

            FWP_MAP.oms.addMarker(marker);
            FWP_MAP.markersArray.push(marker);
            FWP_MAP.bounds.extend(marker.getPosition());
        });

        var config = FWP.settings.map.config;

        if ('yes' === config.cluster) {
            FWP_MAP.mc = new MarkerClusterer(FWP_MAP.map, FWP_MAP.markersArray, {
                imagePath: FWP.settings.map.imagePath,
                maxZoom: 14
            });
        }

        if (0 < FWP.settings.map.locations.length) {
            FWP_MAP.map.fitBounds(FWP_MAP.bounds);
        }
        else if (0 < config.default_lat && 0 < config.default_lng) {
            FWP_MAP.map.setCenter({
                lat: parseFloat(config.default_lat),
                lng: parseFloat(config.default_lng)
            });
        }
    });

    // Clear markers
    function clearOverlays() {
        FWP_MAP.oms.removeAllMarkers();
        FWP_MAP.markersArray = [];

        // clear clusters
        if ('undefined' !== typeof FWP_MAP.mc) {
            FWP_MAP.mc.clearMarkers();
        }
    }

})(jQuery);