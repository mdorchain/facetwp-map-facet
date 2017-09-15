<?php
/*
Plugin Name: FacetWP - Map Facet
Description: Map facet type
Version: 0.1
Author: FacetWP, LLC
Author URI: https://facetwp.com/
GitHub URI: facetwp/facetwp-map-facet
*/

defined( 'ABSPATH' ) or exit;

/**
 * FacetWP registration hook
 */
add_filter( 'facetwp_facet_types', function( $facet_types ) {
    $facet_types['map'] = new FacetWP_Facet_Map_Addon();
    return $facet_types;
});


/**
 * Hierarchy Select facet class
 */
class FacetWP_Facet_Map_Addon
{

    public $map_facet;
    public $is_active = false;


    function __construct() {
        $this->label = __( 'Map', 'fwp' );

        define( 'FACETWP_MAP_URL', plugins_url( '', __FILE__ ) );

        add_filter( 'facetwp_assets', array( $this, 'assets' ) );
        add_filter( 'facetwp_query_args', array( $this, 'short_circuit' ) );
        add_filter( 'facetwp_index_row', array( $this, 'index_latlng' ), 1, 2 );
        add_filter( 'facetwp_render_output', array( $this, 'add_marker_data' ), 10, 2 );
    }


    /**
     * Is there a map facet in use?
     */
    function short_circuit( $query_args ) {
        $this->is_active = $this->is_map_active();
        return $query_args;
    }


    function get_map_design( $slug ) {
        $designs = array(
            'light-dream' => '[{"featureType":"landscape","stylers":[{"hue":"#FFBB00"},{"saturation":43.400000000000006},{"lightness":37.599999999999994},{"gamma":1}]},{"featureType":"road.highway","stylers":[{"hue":"#FFC200"},{"saturation":-61.8},{"lightness":45.599999999999994},{"gamma":1}]},{"featureType":"road.arterial","stylers":[{"hue":"#FF0300"},{"saturation":-100},{"lightness":51.19999999999999},{"gamma":1}]},{"featureType":"road.local","stylers":[{"hue":"#FF0300"},{"saturation":-100},{"lightness":52},{"gamma":1}]},{"featureType":"water","stylers":[{"hue":"#0078FF"},{"saturation":-13.200000000000003},{"lightness":2.4000000000000057},{"gamma":1}]},{"featureType":"poi","stylers":[{"hue":"#00FF6A"},{"saturation":-1.0989010989011234},{"lightness":11.200000000000017},{"gamma":1}]}]',
            'avocado-world' => '[{"featureType":"water","elementType":"geometry","stylers":[{"visibility":"on"},{"color":"#aee2e0"}]},{"featureType":"landscape","elementType":"geometry.fill","stylers":[{"color":"#abce83"}]},{"featureType":"poi","elementType":"geometry.fill","stylers":[{"color":"#769E72"}]},{"featureType":"poi","elementType":"labels.text.fill","stylers":[{"color":"#7B8758"}]},{"featureType":"poi","elementType":"labels.text.stroke","stylers":[{"color":"#EBF4A4"}]},{"featureType":"poi.park","elementType":"geometry","stylers":[{"visibility":"simplified"},{"color":"#8dab68"}]},{"featureType":"road","elementType":"geometry.fill","stylers":[{"visibility":"simplified"}]},{"featureType":"road","elementType":"labels.text.fill","stylers":[{"color":"#5B5B3F"}]},{"featureType":"road","elementType":"labels.text.stroke","stylers":[{"color":"#ABCE83"}]},{"featureType":"road","elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"featureType":"road.local","elementType":"geometry","stylers":[{"color":"#A4C67D"}]},{"featureType":"road.arterial","elementType":"geometry","stylers":[{"color":"#9BBF72"}]},{"featureType":"road.highway","elementType":"geometry","stylers":[{"color":"#EBF4A4"}]},{"featureType":"transit","stylers":[{"visibility":"off"}]},{"featureType":"administrative","elementType":"geometry.stroke","stylers":[{"visibility":"on"},{"color":"#87ae79"}]},{"featureType":"administrative","elementType":"geometry.fill","stylers":[{"color":"#7f2200"},{"visibility":"off"}]},{"featureType":"administrative","elementType":"labels.text.stroke","stylers":[{"color":"#ffffff"},{"visibility":"on"},{"weight":4.1}]},{"featureType":"administrative","elementType":"labels.text.fill","stylers":[{"color":"#495421"}]},{"featureType":"administrative.neighborhood","elementType":"labels","stylers":[{"visibility":"off"}]}]',
            'blue-water' => '[{"featureType":"administrative","elementType":"labels.text.fill","stylers":[{"color":"#444444"}]},{"featureType":"landscape","elementType":"all","stylers":[{"color":"#f2f2f2"}]},{"featureType":"poi","elementType":"all","stylers":[{"visibility":"off"}]},{"featureType":"road","elementType":"all","stylers":[{"saturation":-100},{"lightness":45}]},{"featureType":"road.highway","elementType":"all","stylers":[{"visibility":"simplified"}]},{"featureType":"road.arterial","elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"featureType":"transit","elementType":"all","stylers":[{"visibility":"off"}]},{"featureType":"water","elementType":"all","stylers":[{"color":"#46bcec"},{"visibility":"on"}]}]',
            'midnight-commander' => '[{"featureType":"all","elementType":"labels.text.fill","stylers":[{"color":"#ffffff"}]},{"featureType":"all","elementType":"labels.text.stroke","stylers":[{"color":"#000000"},{"lightness":13}]},{"featureType":"administrative","elementType":"geometry.fill","stylers":[{"color":"#000000"}]},{"featureType":"administrative","elementType":"geometry.stroke","stylers":[{"color":"#144b53"},{"lightness":14},{"weight":1.4}]},{"featureType":"landscape","elementType":"all","stylers":[{"color":"#08304b"}]},{"featureType":"poi","elementType":"geometry","stylers":[{"color":"#0c4152"},{"lightness":5}]},{"featureType":"road.highway","elementType":"geometry.fill","stylers":[{"color":"#000000"}]},{"featureType":"road.highway","elementType":"geometry.stroke","stylers":[{"color":"#0b434f"},{"lightness":25}]},{"featureType":"road.arterial","elementType":"geometry.fill","stylers":[{"color":"#000000"}]},{"featureType":"road.arterial","elementType":"geometry.stroke","stylers":[{"color":"#0b3d51"},{"lightness":16}]},{"featureType":"road.local","elementType":"geometry","stylers":[{"color":"#000000"}]},{"featureType":"transit","elementType":"all","stylers":[{"color":"#146474"}]},{"featureType":"water","elementType":"all","stylers":[{"color":"#021019"}]}]',
        );

        return isset( $designs[ $slug ] ) ? json_decode( $designs[ $slug ] ) : '';
    }


    function get_gmaps_url() {

        // hard-coded
        $api_key = defined( 'GMAPS_API_KEY' ) ? GMAPS_API_KEY : '';

        // admin ui
        $tmp_key = FWP()->helper->get_setting( 'gmaps_api_key' );
        $api_key = empty( $tmp_key ) ? $api_key : $tmp_key;

        // hook
        $api_key = apply_filters( 'facetwp_gmaps_api_key', $api_key );

        return '//maps.googleapis.com/maps/api/js?libraries=places&key=' . $api_key;
    }


    /**
     * Generate the facet HTML
     */
    function render( $params ) {
        $width = $this->map_facet['map_width'];
        $width = empty( $width ) ? 600 : $width;
        $width = is_numeric( $width ) ? $width . 'px' : $width;

        $height = (int) $this->map_facet['map_height'];
        $height = empty( $height ) ? 300 : $height;
        $height = is_numeric( $height ) ? $height . 'px' : $height;

        $output = '<div id="map" style="width:' . $width . '; height:' . $height . '"></div>';
        return $output;
    }


    function add_marker_data( $output, $params ) {
        if ( ! $this->is_active ) {
            return $output;
        }

        $settings = array(
            'imagePath' => FACETWP_MAP_URL . '/assets/img/m',
            'locations' => array(),
        );

        $settings['config'] = array(
            'cluster'       => $this->map_facet['cluster'],
            'default_lat'   => (float) $this->map_facet['default_lat'],
            'default_lng'   => (float) $this->map_facet['default_lng'],
        );

        $settings['init'] = array(
            'scrollWheel' => false,
            'styles' => $this->get_map_design( $this->map_facet['map_design'] ),
            'minZoom' => (int) $this->map_facet['min_zoom'] ?: 1,
            'maxZoom' => (int) $this->map_facet['max_zoom'] ?: 20,
            'center' => array(
                'lat' => (float) $this->map_facet['default_lat'],
                'lng' => (float) $this->map_facet['default_lng'],
            ),
        );

        // get all post IDs
        if ( isset( $this->map_facet['limit'] ) && 'all' == $this->map_facet['limit'] ) {
            $post_ids = (array) FWP()->facet->query_args['post__in'];
        }
        // get paginated post IDs
        else {
            $post_ids = (array) wp_list_pluck( FWP()->facet->query->get_posts(), 'ID' );
        }

        $coords = $this->get_coordinates( $post_ids );

        foreach ( $post_ids as $post_id ) {
            if ( isset( $coords[ $post_id ] ) ) {
                $args = array(
                    'content' => $this->get_content( $post_id ),
                    'position' => $coords[ $post_id ],
                );

                $args = apply_filters( 'facetwp_map_marker_args', $args, $post_id );

                if ( false !== $args ) {
                    $settings['locations'][] = $args;
                }
            }
        }

        $output['settings']['map'] = $settings;

        return $output;
    }


    /**
     * Grab all coordinates from the index table
     */
    function get_coordinates( $post_ids ) {
        global $wpdb;

        $return = array();

        if ( ! empty( $post_ids ) ) {
            $post_ids = implode( ',', $post_ids );

            $sql = "
            SELECT post_id, facet_value AS lat, facet_display_value AS lng
            FROM {$wpdb->prefix}facetwp_index
            WHERE post_id IN ($post_ids)";

            $result = $wpdb->get_results( $sql );

            foreach ( $result as $row ) {
                $return[ $row->post_id ] = array(
                    'lat' => (float) $row->lat,
                    'lng' => (float) $row->lng,
                );
            }
        }

        return $return;
    }


    /**
     * Is this page using a map facet?
     */
    function is_map_active() {
        foreach ( FWP()->facet->facets as $name => $facet ) {
            if ( 'map' == $facet['type'] ) {
                $this->map_facet = $facet; // save the facet
                return true;
            }
        }

        return false;
    }


    function get_content( $post_id ) {
        global $post;

        ob_start();

        // Preserve globals
        $temp_post = is_object( $post ) ? clone $post : $post;

        // Set the main $post object
        $post = get_post( $post_id );

        // Remove UTF-8 non-breaking spaces
        $html = preg_replace( "/\xC2\xA0/", ' ', $this->map_facet['marker_content'] );

        eval( '?>' . $html );

        // Reset globals
        $post = $temp_post;

        // Store buffered output
        return ob_get_clean();
    }


    /**
     * Filter the query based on the map bounds
     */
    function filter_posts( $params ) {
        /*
        global $wpdb;

        $facet = $params['facet'];
        $selected_values = (array) $params['selected_values'];
        $selected_values = array_pop( $selected_values );

        $sql = "
        SELECT DISTINCT post_id FROM {$wpdb->prefix}facetwp_index
        WHERE facet_name = '{$facet['name']}' AND facet_value IN ('$selected_values')";
        return $wpdb->get_col( $sql );
        */
        return (array) $params['selected_values'];
    }


    /**
     * Load the front-end scripts
     */
    function assets( $assets ) {
        $assets['gmaps'] = $this->get_gmaps_url();
        $assets['oms'] = FACETWP_MAP_URL . '/assets/js/oms.min.js';
        $assets['markerclusterer'] = FACETWP_MAP_URL . '/assets/js/markerclusterer.js';
        $assets['facetwp-map-front'] = FACETWP_MAP_URL . '/assets/js/front.js';
        return $assets;
    }


    /**
     * Output any front-end scripts
     */
    function front_scripts() {
?>
<script>
(function($) {
    wp.hooks.addAction('facetwp/refresh/map', function($this, facet_name) {
        var selected_values = [];
        FWP.facets[facet_name] = selected_values;

        if (FWP.loaded) {
            FWP.static_facet = facet_name;
        }
    });

    wp.hooks.addFilter('facetwp/selections/map', function(label, params) {
        return 'Reset map';//FWP_JSON['map']['clearText'];
    });
})(jQuery);
</script>
<?php
    }


    /**
     * Output any admin scripts
     */
    function admin_scripts() {
?>
<script>
(function($) {
    wp.hooks.addAction('facetwp/load/map', function($this, obj) {
        $this.find('.facet-source').val(obj.source);
        $this.find('.facet-source-other').val(obj.source_other);
        $this.find('.facet-limit').val(obj.limit);
        $this.find('.facet-cluster').val(obj.cluster);
        $this.find('.facet-map-design').val(obj.map_design);
        $this.find('.facet-marker-content').val(obj.marker_content);
        $this.find('.facet-min-zoom').val(obj.min_zoom);
        $this.find('.facet-max-zoom').val(obj.max_zoom);
        $this.find('.facet-map-width').val(obj.map_width);
        $this.find('.facet-map-height').val(obj.map_height);
        $this.find('.facet-default-lat').val(obj.default_lat);
        $this.find('.facet-default-lng').val(obj.default_lng);
    });

    wp.hooks.addFilter('facetwp/save/map', function(obj, $this) {
        obj['source'] = $this.find('.facet-source').val();
        obj['source_other'] = $this.find('.facet-source-other').val();
        obj['limit'] = $this.find('.facet-limit').val();
        obj['cluster'] = $this.find('.facet-cluster').val();
        obj['map_design'] = $this.find('.facet-map-design').val();
        obj['marker_content'] = $this.find('.facet-marker-content').val();
        obj['min_zoom'] = $this.find('.facet-min-zoom').val();
        obj['max_zoom'] = $this.find('.facet-max-zoom').val();
        obj['map_width'] = $this.find('.facet-map-width').val();
        obj['map_height'] = $this.find('.facet-map-height').val();
        obj['default_lat'] = $this.find('.facet-default-lat').val();
        obj['default_lng'] = $this.find('.facet-default-lng').val();
        return obj;
    });
})(jQuery);
</script>
<?php
    }


    /**
    * Output admin settings HTML
    */
    function settings_html() {
        $sources = FWP()->helper->get_data_sources();
?>
        <tr>
            <td>
                <?php _e('Other data source', 'fwp'); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content"><?php _e( 'Use a separate value for the longitude?', 'fwp' ); ?></div>
                </div>
            </td>
            <td>
                <select class="facet-source-other">
                    <option value=""><?php _e( 'None', 'fwp' ); ?></option>
                    <?php foreach ( $sources as $group ) : ?>
                    <optgroup label="<?php echo $group['label']; ?>">
                        <?php foreach ( $group['choices'] as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td>
                <?php _e('Marker clustering', 'fwp'); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content"><?php _e( 'Group marker clusters?', 'fwp' ); ?></div>
                </div>
            </td>
            <td>
                <select class="facet-cluster">
                    <option value="yes"><?php _e( 'Yes', 'fwp' ); ?></option>
                    <option value="no"><?php _e( 'No', 'fwp' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <td><?php _e('Map design', 'fwp'); ?>:</td>
            <td>
                <select class="facet-map-design">
                    <option value="default"><?php _e( 'Default', 'fwp' ); ?></option>
                    <option value="light-dream"><?php _e( 'Light Dream', 'fwp' ); ?></option>
                    <option value="avocado-world"><?php _e( 'Avocado World', 'fwp' ); ?></option>
                    <option value="blue-water"><?php _e( 'Blue Water', 'fwp' ); ?></option>
                    <option value="midnight-commander"><?php _e( 'Midnight Commander', 'fwp' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <td><?php _e('Limit', 'fwp'); ?>:</td>
            <td>
                <select class="facet-limit">
                    <option value="all"><?php _e( 'Show all results', 'fwp' ); ?></option>
                    <option value="paged"><?php _e( 'Show current page results', 'fwp' ); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <td>
                <?php _e('Zoom', 'fwp'); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content"><?php _e( 'Force zoom bounds (between 1 and 20)?', 'fwp' ); ?></div>
                </div>
            </td>
            <td>
                <input type="text" class="facet-min-zoom" value="1" placeholder="Min" style="width:96px" />
                <input type="text" class="facet-max-zoom" value="20" placeholder="Max" style="width:96px" />
            </td>
        </tr>
        <tr>
            <td><?php _e('Map size', 'fwp'); ?>:</td>
            <td>
                <input type="text" class="facet-map-width" value="" placeholder="Width" style="width:96px" />
                <input type="text" class="facet-map-height" value="" placeholder="Height" style="width:96px" />
            </td>
        </tr>
        <tr>
            <td>
                <?php _e('Default coordinates', 'fwp'); ?>:
                <div class="facetwp-tooltip">
                    <span class="icon-question">?</span>
                    <div class="facetwp-tooltip-content"><?php _e( 'Center the map here if there are no results', 'fwp' ); ?></div>
                </div>
            </td>
            <td>
                <input type="text" class="facet-default-lat" value="" placeholder="Latitude" style="width:96px" />
                <input type="text" class="facet-default-lng" value="" placeholder="Longitude" style="width:96px" />
            </td>
        </tr>
        <tr>
            <td><?php _e('Marker content', 'fwp'); ?>:</td>
            <td><textarea class="facet-marker-content"></textarea></td>
        </tr>
<?php
    }


    /**
     * Index the coordinates
     * We expect a comma-separated "latitude, longitude"
     */
    function index_latlng( $params, $class ) {

        $facet = FWP()->helper->get_facet_by_name( $params['facet_name'] );

        if ( false !== $facet && 'map' == $facet['type'] ) {
            $latlng = $params['facet_value'];

            // Only handle "lat, lng" strings
            if ( is_string( $latlng ) ) {
                $latlng = preg_replace( '/[^0-9.,-]/', '', $latlng );

                if ( ! empty( $facet['source_other'] ) ) {
                    $other_params = $params;
                    $other_params['facet_source'] = $facet['source_other'];
                    $rows = $class->get_row_data( $other_params );

                    if ( false === strpos( $latlng, ',' ) ) {
                        $lng = $rows[0]['facet_display_value'];
                        $lng = preg_replace( '/[^0-9.,-]/', '', $lng );
                        $latlng .= ',' . $lng;
                    }
                }

                if ( preg_match( "/^([\d.-]+),([\d.-]+)$/", $latlng ) ) {
                    $latlng = explode( ',', $latlng );
                    $params['facet_value'] = $latlng[0];
                    $params['facet_display_value'] = $latlng[1];
                }
            }
        }

        return $params;
    }
}
