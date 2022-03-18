<?php
/**
 * Plugin Name: WordPress and WooCommerce options import/export
 * Plugin URI: https://wetail.io
 * Description: WP and Woo options import/export
 * Version: 0.0.1
 * Author: Stim (Wetail AB)
 * Author URI: https://wetail.io
 * License: GPL3
 */

namespace Wetail\WP\Optimex;

if ( class_exists( __NAMESPACE__ . '\Core' ) ) return Core::init();

final class Core {

	/**
	 * DEV debugging
	 */
    const debug = false;

    /**
     * WooCommerce tables to export for extended export option
     */
    const woo_ext = [
        'wc_tax_rate_classes',
        'wc_webhooks',
        'woocommerce_shipping_zones',
        'woocommerce_shipping_zone_locations',
        'woocommerce_shipping_zone_methods',
        'woocommerce_tax_rates',
        'woocommerce_tax_rate_locations'
    ];

    /**
     * These settings should never be exported/imported to prevent total crash
     */
    const blacklist = [
        'cron', 'siteurl', 'home', 'active_plugins', 'current_theme', 'auto_core_update_notified', 'template',
        'stylesheet', 'recently_edited', 'theme_switched', 'recently_activated', 'rewrite_rules', 'uninstall_plugins'
    ];

	/**
	 * These options are totally excluded from WP options
	 */
    const excluded_wp = [
	    '%transient%',  '%fl_%',    'bb_%',     'theme_mods%',  '%wpseo%',  '%wp_rocket%', '%wpallexport%',
        '%wp-all-export%', '%wpallimport%', '%wp-all-import%', 'wp_all_import%', 'wp_all_export%', 'wpai-%',
        'external_updates%', '%PMXE%', '%PMXI%', 'PMWI_%', '%astra%', '%db_version%', '%jetpack%', 'wpo_%',
        'storefront_%', 'bsf_%', 'brainstrom_%', 'wass_%', 'acf_%', 'tawcvs_%', 'gtm4wp-%', 'wetail_%', 'codisto_%',
        'custom_script_%', 'ppwl_%', 'breeze_%', 'mwp_%', 'wpm_%'
    ];

	/**
	 * Search limit for WP options (only WP)
     *
     * Note: Normally there are only up to 120 different options which are meant to be basic WP options
	 */
    const wp_options_limit = 120;

	/**
	 * These options are included for WooCommrce
	 */
    const included_woo = [
        '%woocommerce%', 'wc_%', '%shop%', '%product_cat%', '%vendo%', '%rtnd%', '%wfx_fix%', '%fortnox%', '%pacsoft%',
	    '%aelia%', '%klarna%',
    ];

	/**
	 * Private translations on import
     *
     * Note: we do not use gettext functions and automatic translations for this tiny plugin
	 */
	const translations = [
		'wp_options'  => 'WordPress options',
		'woo_options' => 'WooCommerce options',
		'woo_ext'     => 'WooCommerce extended options'
	];


    /**
     * Prevent duplicated calls
     *
     * @var bool
     */
    private static $initialized = false;

    /**
     * Export trusted key
     */
    const exkey = '@jduWOn)E39(*W-=871)W54+06kld';

    /**
     * Initialize hooks
     *
     * @return bool
     */
    public static function init(){

        if( self::$initialized ) return true;

        add_action( 'export_filters',   __CLASS__ . '::export_option'   , 99 );
        add_action( 'admin_init',       __CLASS__ . '::import_option'   , 99 );
        add_filter( 'export_args',      __CLASS__ . '::arguments'       , 99 );
        add_action( 'export_wp',        __CLASS__ . '::export'          , 99 );

        return ( self::$initialized = true );
    }

    /**
     * Register Importer
     *
     * @return void
     */
    public static function import_option() {
        if ( function_exists( 'register_importer' ) ) {
            register_importer(
                'wp-optimex',
                'WP and Woo settings',
                'Import WordPress or WooCommerce settings from a JSON file',
                __CLASS__ . '::import'
            );
        }
    }

    /**
     * Add a radio option to export options.
     *
     * @return void
     */
    public static function export_option() {
        ?>
            <script type="text/javascript">
                (function($){
                    let __show_desc = function(){
                        $( '.woptix-desc' ).hide();
                        if( $( '#woptix-option' ).prop( 'checked' ) )
                            $( '#woptix-desc-' + $( '#wpoptimex-sel' ).val() ).show();
                    };
                    $( document ).ready( function(){
                        $( 'input[name=content]' ).change( function(){
                            $( '#wpoptimex-sel' ).prop( 'disabled', ! $( '#woptix-option' ).prop( 'checked' ) );
                            __show_desc();
                        } );
                        $( '#wpoptimex-sel' ).off().change( __show_desc );
                    } );
                })(jQuery)
            </script>
            <style>
                .woptix-desc{
                    display: none;
                    margin-left: 25px;
                }
            </style>
            <p>
                <label>
                    <input type="radio" id="woptix-option" name="content" value="wpoptimex"/>
                    <select class="wc-enhanced-select" disabled name="wp-optimex-selection" id="wpoptimex-sel">
                        <option selected value="all">All WordPress and WooCommerce settings</option>
                        <option value="allwp">All WordPress settings</option>
                        <option value="allwoo">All WooCommerce settings</option>
                        <option value="wooext">Extended WooCommerce settings</option>
                    </select><br/>
                    <span class="description woptix-desc" id="woptix-desc-all">
                        This will export all available generic settings for WordPess and basic WooCommerce settings,
                        including settings for active gateways
                    </span>
                    <span class="description woptix-desc" id="woptix-desc-allwp">
                        This will export only available generic WordPess settings
                    </span>
                    <span class="description woptix-desc" id="woptix-desc-allwoo">
                        This will export only available basic WooCommerce settings,
                        including settings for active gateways
                    </span>
                    <span class="description woptix-desc" id="woptix-desc-wooext">
                        This will export available basic WooCommerce settings,
                        including settings for active gateways, and shipping methods, shipping zones, tax rates,
                        tax classes and all active webhooks
                    </span>
                </label>
            </p>
        <?php
    }

    /**
     * Check if we were selected to export
     *
     * @param  array $args
     * @return array
     */
    public static function arguments( $args ) {
        if( ! empty( $_GET['content'] ) && 'wpoptimex' == $_GET['content'] )
            $args = [
                'wpoptimex' => $_GET['wp-optimex-selection']
            ];
        return $args;
    }

	/**
     * Make proper condition on the selection for export
     *
	 * @param string $type
	 *
	 * @return string
	 */
    private static function excluded( $type = 'wp' ){
        $rex = ''; $ex = self::excluded_wp;
        if( $type === 'wp' ) $ex = array_merge( $ex, self::included_woo );
        self::log( $ex );
	    foreach( $ex as $e )
		    $rex .= " AND `option_name` NOT LIKE '$e' ";
	    if( 'woo' === $type ) {
		    $rex .= " AND ( 0 = 1 ";
		    $gateways = get_option( 'woocommerce_gateway_order' );
		    $wex = (
                ! empty( $gateways )
                    ? array_merge( self::included_woo, array_map(
                                function( $a ) { return "%$a%"; },
                                array_keys( $gateways )
                            )
                        )
                    : self::included_woo
            );
		    foreach( $wex as $w )
			    $rex .= " OR `option_name` LIKE '$w' ";
		    $rex .= ' )';
	    }
	    $blacklist = apply_filters( 'options_export_blacklist', self::blacklist );
	    if( ! empty( $blacklist ) && is_array( $blacklist ) )
		    $rex .= " AND `option_name` NOT IN ('" . implode( "','", $blacklist ) . "') ";
	    return $rex;
    }

    /**
     * Export options as a JSON file if that's what the user wants to do.
     *
     * @param  array $args The export arguments.
     * @return void
     */
    public static function export( $args ) {

        if ( empty( $args['wpoptimex'] ) ) return;

        global $wpdb;

        $sitename = sanitize_key( get_bloginfo( 'name' ) ) ??
                    str_replace( 'https://', '', str_replace( 'http://', '', untrailingslashit( site_url() ) ) ) ??
                    '__';

        $filename = $sitename . '_' . $args['wpoptimex'] . '_export_' . date( 'Y-m-d_h:m:s' ) . '.json';
        $charset = get_option( 'blog_charset' );

        header( "Content-Description: File Transfer" );
        header( "Content-Disposition: attachment; filename=$filename" );
        header( "Content-Type: application/json; charset=$charset", true );

        $export = [ 'key' => md5( self::exkey ) ];

        if( $args['wpoptimex'] === 'all' ||
            $args['wpoptimex'] === 'allwp' )
                $export[ 'wp_options' ] = $wpdb->get_results(
                    self::log(
                        "SELECT `option_name`, `option_value`, `autoload` FROM {$wpdb->options} " .
                        "WHERE 1 = 1 " .
                        self::excluded( 'wp' ) .
                        " GROUP BY option_id ORDER BY option_id ASC LIMIT " . self::wp_options_limit
                    )
                );

        if( $args['wpoptimex'] === 'all' ||
            $args['wpoptimex'] === 'allwoo' ||
            $args['wpoptimex'] === 'wooext' )
                $export[ 'woo_options' ] = $wpdb->get_results(
                    self::log(
                        "SELECT `option_name`, `option_value`, `autoload` FROM {$wpdb->options} " .
                        "WHERE 1 = 1 " .
                        self::excluded( 'woo' ) .
                        " GROUP BY option_id ORDER BY option_id ASC"
                    )
                );

        if( $args['wpoptimex'] === 'wooext' )
            foreach( self::woo_ext as $woo_ext )
                $export[ 'woo_ext' ][ $woo_ext ] = $wpdb->get_results(
                    "SELECT * FROM {$wpdb->prefix}$woo_ext"
                );

        echo json_encode( $export , JSON_PRETTY_PRINT );

        exit;

    }


	/**
	 * Print all importing data to verify
	 *
	 * @param $data
	 */
	private static function verify( $data ){
		?>
        <div style="border: 1px solid #999; background: #fff; padding: 15px; border-radius: 6px; margin-right: 10px;">
            <h3>Options to import:</h3>
            <form action="admin.php?import=wp-optimex&step=2" method="post" enctype="multipart/form-data">
				<?php
				if( isset( $data['key'] ) && md5( self::exkey ) === $data['key'] ) :
					foreach ( $data as $data_key => $options ) :
						if( $data_key === 'key' ) continue;
						if( ! empty( self::translations[ $data_key ] ) ) : ?>
                            <h4><?php echo self::translations[ $data_key ] ?></h4>
                            <p><label><input type="checkbox" checked id="checkall_<?php echo $data_key ?>"
                                             onchange="jQuery( '.wimp-<?php echo $data_key ?>' ).prop( 'checked', this.checked )"
                                    /> <b>All</b></label></p>
							<?php if ( $data_key === 'woo_ext' ) : ?>
								<?php foreach( $options as $option=>$_rows ) : ?>
                                    <p><label><input type="checkbox" class="wimp-<?php echo $data_key ?>"
                                                     name="<?php echo $option ?>"
                                                     value="1" checked /> <?php echo $option ?>
                                        </label>
                                    </p>
								<?php endforeach;
							else : ?>
								<?php foreach( $options as $option ) : ?>
                                    <p><label><input type="checkbox" class="wimp-<?php echo $data_key ?>"
                                                     name="<?php echo $option['option_name'] ?>"
                                                     value="1" checked /> <?php echo $option['option_name'] ?>
                                        </label>
                                    </p>
								<?php endforeach;
							endif;
						else : ?>
                            <h4>All options (unknown format)</h4>
                            <p><label><input type="checkbox" checked id="checkall_all"
                                             onchange="jQuery( '.wimp-all' ).prop( 'checked', this.checked )" /> <b>All</b></label></p>
							<?php foreach( $options as $option ) : ?>
                                <p>
                                    <label><input type="checkbox" class="wimp-all"
                                                  name="<?php echo $option['option_name'] ?>"
                                                  value="1" checked /> <?php echo $option['option_name'] ?>
                                    </label>
                                </p>
							<?php endforeach;
						endif;
					endforeach;
				else : ?>
                    <h4>All options (unknown format)</h4>
                    <p><label><input type="checkbox" checked id="checkall_all"
                                     onchange="jQuery( '.wimp-all' ).prop( 'checked', this.checked )" /> <b>All</b></label></p>
					<?php foreach( $data as $key=>$option ) :
						if( is_numeric( $key ) && isset( $option['option_name'] ) ) : ?>
                            <p>
                                <label><input type="checkbox" class="wimp-all"
                                              name="<?php echo $option['option_name'] ?>"
                                              value="1" checked /> <?php echo $option['option_name'] ?>
                                </label>
                            </p>
						<?php else : ?>
                            <p>
                                <label><input type="checkbox" class="wimp-all"
                                              name="<?php echo $key ?>"
                                              value="1" checked />
									<?php echo $key . ' | ' . substr( print_r( $option, 1 ), 0, 50 ) ?>
                                </label>
                            </p>
						<?php endif;
					endforeach;
				endif; ?>
                <a class="button button-secondary" href="admin.php?import=wp-optimex&step=0"> < Back to file upload</a>
                <button type="submit" class="button button-primary">Yes, data is <b>verified</b> by me, Import!</button>
            </form>
        </div>
		<?php
	}

    /**
     * Importing options
     *
     * @return void
     */
    public static function import() {
        $step = $_GET['step'] ?? 0;
        ?>
        <h1>Import WP or Woo options from JSON</h1>
        <?php
        switch( $step ){
            case 0:
                echo '<p><b>Select File</b> -> Verify -> Import</p><style>#upload{margin: 5px;}</style>';
                wp_import_upload_form( 'admin.php?import=wp-optimex&step=1' );
            break;
            case 1:
                echo '<p>Select File -> <b>Verify</b> -> Import</p>';
                $file = wp_import_handle_upload();
                $data = null;
                if( ( isset( $file['error'] ) ||
                        ! isset( $file['file'], $file['id'] )  ||
                            ! file_exists( $file['file'] ) )

                    && ( ! $data = get_transient( '__wpoptimex_import' ) ) ) {

                    if( ! empty( $file['id'] ) )
                        wp_import_cleanup( $file['id'] );

                    echo '<p style="border-left: 4px solid red; padding: 12px 20px">File upload error. Please, try again...</p>';
                    wp_import_upload_form( 'admin.php?import=wp-optimex&step=1' );
                    break;
                }
                if( ! $data ) {
                    $data = json_decode(file_get_contents($file['file']), true);
                    set_transient(
                        '__wpoptimex_import',
                        $data,
                        600
                    );
                    wp_import_cleanup($file['id']);
                }
                self::verify( $data );
            break;
            case 2:
                echo '<p>Select File -> Verify -> <b>Import</b></p>';
                if( ! ( $data = get_transient( '__wpoptimex_import' ) ) ){
                    echo '<p style="border-left: 4px solid red; padding: 12px 20px">Data not found!</p>';
                    break;
                }

                if( self::import_options( $data ) )
                    echo '<p style="border-left: 4px solid lime; padding: 15px;">All options were imported successfully!</p>';

                echo '<p><b>Done!</b></p>';

                echo '<a class="button button-secondary" href="admin.php?import=wp-optimex&step=0"> < Run another import</a>';

                delete_transient( '__wpoptimex_import' );

            break;
            default:
                echo 'WTF?! Hej!';
            break;
        }

    }

    /**
     * Import all options
     *
     * @param array $data
     * @return bool
     */
    private static function import_options( $data ){
        global $wpdb;
        foreach( $data as $key=>$values ) {
            if ( $key === 'key' ) continue;
            if ( $key === 'woo_ext' ){
                self::import_extended( $values );
                continue;
            }
            if ( ! empty( self::translations[ $key ] ) ) self::import_options( $values );
            $option_name    = $values['option_name']    ?? $key;
	        if( ! isset( $_POST[ $option_name ] ) ) continue;
            $option_value   = $values['option_value']   ?? $values;
            $autoload       = $values['autoload']       ?? 'yes';
            if( ! $option_name ) continue;
            if( ! get_option( $option_name ) )
                $wpdb->query(
                    "INSERT INTO {$wpdb->options}( `option_name`, `option_value`, `autoload` ) " .
                    "VALUES( '$option_name', '$option_value', '$autoload' )"
                );
            else
                $wpdb->query(
                    "UPDATE {$wpdb->options} " .
                    "SET `option_value` = '$option_value', " .
                        "`autoload` = '$autoload' " .
                    "WHERE `option_name` = '$option_name' " .
                    "LIMIT 1"
                );
        }
        return true;
    }

    /**
     * Import WooComemrce extended data
     *
     * @param array $data
     * @return bool
     */
    private static function import_extended( $data ){
        if( empty( $data ) ) return false;
        global $wpdb;
        foreach( $data as $key=>$values )
            if( isset( $_POST[ $key ] ) && $wpdb->query( "DELETE FROM {$wpdb->prefix}$key" ) )
                foreach( $values as $row ) {
                    $_values = implode( "','", $row );
                    $wpdb->query( "INSERT INTO {$wpdb->prefix}$key VALUES( '$_values' )" );
                }
        return true;
    }

	/**
     * Debug info
     *
	 * @param $data
	 *
	 * @return mixed
	 */
    private static function log( $data ){
        if( ! self::debug ) return $data;
        error_log( print_r( $data, 1 ) );
        return $data;
    }

}
