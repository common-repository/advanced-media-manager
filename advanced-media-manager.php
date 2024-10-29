<?php
/**
 * Advanced Media Manager.
 *
 * Advanced Media Manager plugin file.
 *
 * @package   Smackcoders\ADVMEDIA
 * @copyright Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @wordpress-plugin
 * Plugin Name:  Advanced Media Manager
 * Version:    	 2.0
 * Description:  Automatically store wp media uploads to Amazon S3 and DigitalOcean Spaces.
 * Author:       Smackcoders
 * Author URI:   https://www.smackcoders.com/wordpress.html
 * Text Domain:  advanced-media-manager
 * Domain Path:  /languages
 * License:      GPL v3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Smackcoders\ADVMEDIA;

if (!defined('ABSPATH'))
{
    exit;
}

// Exit if accessed directly
define('SMS3PLUGINSLUG', 'advanced-media-manager');
define('SMS3PLUGINDIR', plugin_dir_path(__FILE__));
require_once 'plugin.php';
require_once 'Tables.php';
require_once 'media-manager-install.php';
require_once 'languages/LangEN.php';

include_once (ABSPATH . 'wp-admin/includes/plugin.php');

if (is_plugin_active('advanced-media-manager/advanced-media-manager.php'))
{
    $plugin_pages = ['advanced-media-manager'];
    include __DIR__ . '/media-manager-hooks.php';
    require_once 'controllers/service-provider.php';
}

class SmackAWS
{

    private static $instance = null;
    private static $table_instance = null;
    private static $plugin_instance = null;
    private static $service_provider = null;
    private static $install = null;
    private static $en_instance = null;
    public $version = '2.0';

    private function __construct()
    {
        add_action('init', array(
            __CLASS__,
            'show_admin_menus'
        ));
    }

    public static function initInstance()
    {
        add_action('init', array(
            __CLASS__,
            'show_admin_menus'
        ));
        SmackAWS::$install = SmackAWSInstall::getInstance();
    }

    public static function show_admin_menus()
    {
        $roles = wp_roles();
        $higher_level_roles = ['administrator'];

        // By default, administrator role will have the capabilities
        foreach ($roles->role_objects as $role)
        {
            if (in_array($role->name, $higher_level_roles))
            {
                if (!$role->has_cap('advanced-media-manager'))
                {
                    $role->add_cap('advanced-media-manager');
                }
            }
        }

        $current_user = wp_get_current_user();

        if (isset($current_user->roles[0]) && ($current_user->roles[0] == 'editor' || $current_user->roles[0] == 'author'))
        {
            add_action('admin_menu', array(
                __CLASS__,
                'editor_menu'
            ));
        }
        else
        {

            add_action('admin_menu', array(
                __CLASS__,
                'load_functionalities'
            ));
        }
    }

    public static function getInstance()
    {

        if (SmackAWS::$instance == null)
        {

            SmackAWS::$instance = new SmackAWS();
            SmackAWS::$table_instance = Tables::getInstance();
            SmackAWS::$service_provider = ServiceProvider::getInstance();
            SmackAWS::$plugin_instance = Plugin::getInstance();
            SmackAWS::$install = SmackAWSInstall::getInstance();
            SmackAWS::$en_instance = LangEN::getInstance();
            global $wpdb;
            $provider = $wpdb->get_var("SELECT updated_media_bucket FROM " . $wpdb->prefix . "storage_bucket_manager");
            if (isset($provider))
            {
                add_filter('add_attachment', array(
                    SmackAWS::$install,
                    'cloud_media_upload_attachment'
                ) , 10, 5);
               add_filter('wp_get_attachment_url', array(
                    SmackAWS::$install,
                    'do_media_url_modification'
                ) , 10, 1);
                add_filter('delete_attachment', array(
                    SmackAWS::$install,
                    'cloud_media_delete_attachment'
                ) , 10, 5);
                // add_filter('the_content', array(
                //     SmackAWS::$install,
                //     'content_media_url_modification'
                // ) , 10, 5);
                // add_filter('wp_get_attachment_image_attributes', array(
                //     SmackAWS::$install,
                //     'do_media_src_modification'
                // ) , 10, 5);
            }
            return SmackAWS::$instance;
        }
        return SmackAWS::$instance;
    }

    public static function load_functionalities()
    {
        remove_menu_page('advanced-media-manager');
        $my_page = add_menu_page('Advanced Media Manager ', 'Advanced Media Manager ', 'manage_options', 'advanced-media-manager', array(
            __CLASS__,
            'load_menu'
        ));
        $my_menu = add_submenu_page( "advanced-media-manager", "Cloud Images", 'Cloud Images', "manage_options", "cloud-images",
            array(__CLASS__,'cloud_images_page'
        ) );
        add_action('load-' . $my_page, array(
            __CLASS__,
            'load_admin_js'
        ));
        add_action('load-' . $my_menu, array(
            __CLASS__,
            'load_admin_js'
        ));
    }

    public static function load_admin_js()
    {
        add_action('admin_enqueue_scripts', array(
            __CLASS__,
            'media_manager_enqueue_function'
        ));
    }

    public function editor_menu()
    {
        remove_menu_page('advanced-media-manager');
        $my_page = add_menu_page('Advanced Media Manager ', 'Advanced Media Manager ', '2', 'advanced-media-manager', array(
            __CLASS__,
            'load_menu'
        ));
        add_action('load-' . $my_page, array(
            __CLASS__,
            'load_admin_js'
        ));
    }

    public static function load_menu()
    { 
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager"); 
        if(empty($table_rows)){
            $wpdb->insert($wpdb->prefix . "storage_bucket_manager" , array('cloud_media_menu' => "home" ), array('%s'));
        }    
        else{           
            $provider = $table_rows[0]->media_service_provider;
            $wpdb->update($wpdb->prefix . "storage_bucket_manager" , array('cloud_media_menu' => "home"), array('media_service_provider' => $provider));
        }
        
        ?><div id="wp-csv-importer-admin"></div><?php
        
    }

    public static function cloud_images_page() {

        global $wpdb;
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");                
        $provider = $table_rows[0]->media_service_provider;

        $wpdb->update($wpdb->prefix . "storage_bucket_manager" , array('cloud_media_menu' => "cloudimage"), array('media_service_provider' => $provider));
        
        ?><div id="wp-csv-importer-admin"></div><?php
        
	}

    public static function media_manager_enqueue_function()
    {
        wp_register_script(SmackAWS::$plugin_instance->getPluginSlug() . 'bootstrap', plugins_url('assets/js/deps/bootstrap.min.js', __FILE__) , array(
            'jquery'
        ));
        wp_enqueue_script(SmackAWS::$plugin_instance->getPluginSlug() . 'bootstrap');

        wp_enqueue_style(SmackAWS::$plugin_instance->getPluginSlug() . 'bootstrap-css', plugins_url('assets/css/deps/bootstrap.min.css', __FILE__));
        wp_enqueue_style(SmackAWS::$plugin_instance->getPluginSlug() . 'advanced-media-css', plugins_url('assets/css/deps/csv-importer.css', __FILE__));
        wp_enqueue_style(SmackAWS::$plugin_instance->getPluginSlug() . 'style-css', plugins_url('assets/css/deps/style.css', __FILE__));
        wp_enqueue_style(SmackAWS::$plugin_instance->getPluginSlug() . 'react-toasty-css', plugins_url('assets/css/deps/ReactToastify.min.css', __FILE__));
        wp_enqueue_style(SmackAWS::$plugin_instance->getPluginSlug() . 'react-confirm-alert-css', plugins_url('assets/css/deps/react-confirm-alert.css', __FILE__));

        wp_register_script(SmackAWS::$plugin_instance->getPluginSlug() . 'script_csv_importer', plugins_url('assets/js/advanced-media-manager.js', __FILE__) , array(
            'jquery'
        ));
        wp_enqueue_script(SmackAWS::$plugin_instance->getPluginSlug() . 'script_csv_importer');

        $contents = SmackAWS::$en_instance->contents();
        $response = wp_json_encode($contents);
        wp_localize_script(SmackAWS::$plugin_instance->getPluginSlug() . 'script_csv_importer', 'wpr_object', array(
            'file' => $response,
            __FILE__,
            'imagePath' => plugins_url('/assets/images/', __FILE__)
        ));

    }

    /**
     * Generates unique key for each file.
     * @param string $value - filename
     * @return string hashkey
     */
    public function convert_string2hash_key($value)
    {
        $file_name = hash_hmac('md5', "$value" . time() , 'secret');
        return $file_name;
    }

}

$activate_plugin = SmackAWSInstall::getInstance();
register_activation_hook(__FILE__, array(
    'Smackcoders\\ADVMEDIA\\Plugin',
    'activate'
));
register_deactivation_hook(__FILE__, array(
    'Smackcoders\\ADVMEDIA\\Plugin',
    'deactivate'
));
add_action('plugins_loaded', 'Smackcoders\\ADVMEDIA\\onpluginsload');

if (is_plugin_active('advanced-media-manager/advanced-media-manager.php'))
{
    add_action( 'admin_init', 'Smackcoders\\ADVMEDIA\\hook_new_media_columns' );
}

function hook_new_media_columns() {
    add_filter( 'manage_media_columns', 'Smackcoders\\ADVMEDIA\\filename_column' );
    add_action( 'manage_media_custom_column', 'Smackcoders\\ADVMEDIA\\filename_value', 10, 2 );
}

function filename_column( $cols ) {
    $cols["filename"] = "Advanced Tab";
    return $cols;
    
}

function filename_value( $column_name, $id ) {
    $meta = wp_get_attachment_url( $id );
    echo esc_html("<a href=$meta target=_blank>$meta</a>");  
    SmackAWSInstall::getInstance()->do_media_url_modification($meta);

    global $wpdb;
    $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "bucket_broken_images");
    if(isset($table_rows)){
        foreach($table_rows as $broken_image){
            $broken_image_id = $broken_image->broken_image_id;
            SmackAWSInstall::getInstance()->cloud_media_upload_attachment($broken_image_id);
            $wpdb->delete( $wpdb->prefix . 'bucket_broken_images' , array('broken_image_id' => $broken_image_id ), array('%d'));
        }
    }
}


function onpluginsload()
{
    $plugin_pages = ['advanced-media-manager'];
    include __DIR__ . '/media-manager-hooks.php';
    SmackAWS::getInstance();
}

?>
