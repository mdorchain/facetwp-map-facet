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

        // override using a facetwp_render_output hook with priority > 10
        $output['settings']['map']['config'] = $this->map_facet;
        $output['settings']['map']['init'] = array(
            'scrollWheel'   => false,
            'minZoom'       => $this->map_facet['min_zoom'] ?: 1,
            'maxZoom'       => $this->map_facet['max_zoom'] ?: 20,
        );

        $post_ids = FWP()->facet->query_args['post__in'];
        $coords = $this->get_coordinates( $post_ids );

        foreach ( $post_ids as $post_id ) {
            if ( isset( $coords[ $post_id ] ) ) {
                $args = array(
                    'content' => $this->get_content( $post_id ),
                    'position' => $coords[ $post_id ],
                );

                $args = apply_filters( 'facetwp_map_marker_args', $args, $post_id );

                if ( false !== $args ) {
                    $output['settings']['map']['locations'][] = $args;
                }
            }
        }

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
        $assets['oms'] = FACETWP_MAP_URL . '/assets/js/oms.min.js';
        $assets['markerclusterer'] = FACETWP_MAP_URL . '/assets/js/markerclusterer.js';
        $assets['facetwp-map-front'] = FACETWP_MAP_URL . '/assets/js/front.js';
        return $assets;
    }


    /**
     * Output any front-end scripts
     * TODO this doesn't work on initial pageload because FWP()->facet->facets isn't set
     * except for facets within the URL hash
     */
    function front_scripts() {
        FWP()->display->json['map'] = array(
            'url' => FACETWP_MAP_URL,
            'settings' => FWP()->facet->facets // BLAH
        );
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
            <td><?php _e('Marker clustering', 'fwp'); ?>:</td>
            <td>
                <select class="facet-cluster">
                    <option value="yes"><?php _e( 'Yes', 'fwp' ); ?></option>
                    <option value="no"><?php _e( 'No', 'fwp' ); ?></option>
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
            <td><?php _e('Zoom', 'fwp'); ?>:</td>
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
            <td><?php _e('Default coordinates', 'fwp'); ?>:</td>
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
