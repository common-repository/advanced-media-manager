<?php
/**
 * Advanced Media Manager plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\ADVMEDIA;
require_once SMS3PLUGINDIR . '/vendor/autoload.php';

use Directory;
use Smackcoders\ADVMEDIA\Aws\S3\S3Client;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Class ServiceProvider
 * @package Smackcoders\ADVMEDIA
 */
class ServiceProvider
{

    protected static $instance = null, $smack_instance, $install;

    public static function getInstance()
    {
        if (null == self::$instance)
        {
            self::$instance = new self;
            self::$instance->doHooks();
            Self::$install = SmackAWSInstall::getInstance();
        }
        return self::$instance;
    }

    /**
     * ServiceProvider constructor.
     */
    public function __construct()
    {

        $this->plugin = Plugin::getInstance();

    }

    /**
     * ServiceProvider hooks.
     */
    public function doHooks()
    {
        add_action('wp_ajax_get_service_provider', array(
            $this,
            'get_service_provider'
        ));
        add_action('wp_ajax_remove_service_provider', array(
            $this,
            'remove_service_provider'
        ));
        add_action('wp_ajax_get_bucket_details', array(
            $this,
            'get_Bucket_Details'
        ));
        add_action('wp_ajax_storage_settings_details', array(
            $this,
            'storage_settings_details'
        ));
        add_action('wp_ajax_sync_media_details', array(
            $this,
            'sync_media_details'
        ));
        add_action('wp_ajax_sync_process_details', array(
            $this,
            'sync_process_details'
        ));
        add_action('wp_ajax_get_actual_component_details', array(
            $this,
            'set_component_details'
        ));
        add_action('wp_ajax_displaycurrentpage', array(
            $this,
            'displaycurrentpage'
        ));
        add_action('wp_ajax_displaycurrenttab', array(
            $this,
            'displaycurrenttab'
        ));
        add_action('wp_ajax_setting_tabs', array(
            $this,
            'setting_tabs'
        ));
    }

    /**
     * Function for getting service provider
     *
     */
    public function setting_tabs(){
        $tab['setting'] = get_option("tabsetting");
        $tab['sync'] = get_option("tabsync");
        $tab['download'] = get_option("tabdownload");
        echo wp_json_encode($tab);
        wp_die();
    }

    public static function get_service_provider()
    {
            $media_provider = sanitize_text_field($_POST['provider']);
            $provider_awsaccess_key = sanitize_text_field($_POST['awsaccesskey']);
            $provider_digitalaccess_key = sanitize_text_field($_POST['digitalaccesskey']);
            $provider_awssecret_key = sanitize_text_field($_POST['awssecretkey']);
            $provider_digitalsecret_key = sanitize_text_field($_POST['digitalsecretkey']);
            $bucket_awsregion = sanitize_text_field($_POST['awsregion']);
            $bucket_digitalregion = sanitize_text_field($_POST['digitalregion']);
            global $wpdb;
            $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
            if (empty($table_rows))
            {
                $storage_table = $wpdb->prefix . "storage_bucket_manager";
                $wpdb->insert($storage_table , array('media_service_provider' => $media_provider ), array('%s'));
            }
            else
            {
                $storage_table = $wpdb->prefix . "storage_bucket_manager";
                $provider = $table_rows[0]->media_service_provider;
                $wpdb->update($storage_table , array('media_service_provider' => $media_provider), array('media_service_provider' => $provider));
            }

            if ($media_provider == 'digitalOcean')
            {
                $table_rows = $wpdb->get_results("select * from {$wpdb->prefix}digital_bucket_manager");
                if (empty($table_rows))
                {
                    $digital_table = $wpdb->prefix . "digital_bucket_manager";
                    $wpdb->insert($digital_table , array('digital_accesskey' =>$provider_digitalaccess_key ,'digital_secretkey' => $provider_digitalsecret_key, 'digital_bucket_region' => $bucket_digitalregion ), array( '%s', '%s', '%s'));
                }
                else
                {
                    $digital_table = $wpdb->prefix . "digital_bucket_manager";
                    $keys = $table_rows[0]->digital_accesskey;
                    $wpdb->update($digital_table , array('digital_accesskey' => $provider_digitalaccess_key , 'digital_secretkey' => $provider_digitalsecret_key, 'digital_bucket_region' => $bucket_digitalregion), array('digital_accesskey' => $keys));
                }
                $buckets = self::authenticate_digital_ocean($provider_digitalaccess_key, $provider_digitalsecret_key);
            }
            elseif ($media_provider == 'amazonS3')
            {
                $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "amazon_bucket_manager");
                if (empty($table_rows))
                {
                    $amazon_table = $wpdb->prefix . "amazon_bucket_manager";
                    $wpdb->insert( $amazon_table, array('aws_accesskey' => $provider_awsaccess_key, 'aws_secretkey' => $provider_awssecret_key, 'aws_bucket_region' =>  $bucket_awsregion), array( '%s', '%s', '%s'));
                }
                else
                {
                    $amazon_table = $wpdb->prefix . "amazon_bucket_manager";
                    $keys = $table_rows[0]->aws_accesskey;
                    $wpdb->update($amazon_table, array('aws_accesskey' => $provider_awsaccess_key , 'aws_secretkey' => $provider_awssecret_key, 'aws_bucket_region' => $bucket_awsregion),  array('aws_accesskey' => $keys));
                }
                $buckets = self::authenticate_aws_data($provider_awsaccess_key, $provider_awssecret_key);
            }

            $result['buckets'] = $buckets;
            $result['success'] = true;
            echo wp_json_encode($result);
            wp_die();

    }

    /**
     * Function for getting bucket details
     *
     */
    public static function get_Bucket_Details()
    {        
            $selected_bucket = sanitize_text_field($_POST['bucketname']);
            $existing_bucket = sanitize_text_field($_POST['existingbucket']);
            $awsselectedbucketbox = sanitize_text_field($_POST['awsselectedbucketbox']);
            $digitalselectedbucketbox = sanitize_text_field($_POST['digitalselectedbucketbox']);
            $currentpage = "storagesetting";
            $currenttab = "settings";
            update_option('tabsetting','true');
            global $wpdb;
            $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
            if (empty($table_rows))
            {
                $storage_table = $wpdb->prefix . "storage_bucket_manager";
                $wpdb->insert( $storage_table , array('media_selected_bucket' => $selected_bucket, 'media_existing_bucket' => $existing_bucket, 'currentpage' => $currentpage, 'currenttab' => $currenttab), array( '%s', '%s', '%s', '%s'));
            }
            else
            {
                $storage_table = $wpdb->prefix . "storage_bucket_manager";
                $provider = $table_rows[0]->media_service_provider;
                $wpdb->update($storage_table, array('media_selected_bucket' => $selected_bucket, 'media_existing_bucket' => $existing_bucket, 'currentpage' => $currentpage, 'currenttab' => $currenttab), array('media_service_provider' => $provider));
            }
            //digital
            $table_drows = $wpdb->get_results("select * from {$wpdb->prefix}digital_bucket_manager");
            if (empty($table_drows))
            {
                $digital_table = $wpdb->prefix . "digital_bucket_manager";
                $wpdb->insert($digital_table , array('digitalselectedbucketbox' => $digitalselectedbucketbox), array('%s'));
            }
            else
            {
                $digital_table = $wpdb->prefix . "digital_bucket_manager";
                $keys = $table_drows[0]->digital_accesskey;
                $wpdb->update( $digital_table, array('digitalselectedbucketbox' => $digitalselectedbucketbox), array('digital_accesskey' => $keys));
            }
            //amazon
            $table_arows = $wpdb->get_results("select * from {$wpdb->prefix}amazon_bucket_manager");
    
            if (empty($table_arows))
            {
                $amazon_table = $wpdb->prefix . "amazon_bucket_manager";
                $wpdb->insert( $amazon_table, array('awsselectedbucketbox' => $awsselectedbucketbox), array('%s'));
            }
            else
            {
                $amazon_table = $wpdb->prefix . "amazon_bucket_manager";
                $keys = $table_arows[0]->aws_accesskey;
                $wpdb->update($amazon_table , array('awsselectedbucketbox' => $awsselectedbucketbox), array('aws_accesskey' => $keys));
            }

            $media_provider = $wpdb->get_var("SELECT media_service_provider FROM " . $wpdb->prefix . "storage_bucket_manager");
            if (!empty($selected_bucket) && isset($selected_bucket))
            {
                if ($media_provider == 'digitalOcean' && $digitalselectedbucketbox == 'new')
                {
                    self::create_new_do_bucket($selected_bucket);
                }
                if($media_provider == 'amazonS3' && $awsselectedbucketbox == 'new')
                {
                    self::create_new_aws_bucket($selected_bucket);
                }

            }
            if (!empty($existing_bucket) && isset($existing_bucket))
            {
                $media_bucket = $wpdb->get_var("SELECT media_existing_bucket FROM " . $wpdb->prefix . "storage_bucket_manager");
            }
            else
            {
                $media_bucket = $wpdb->get_var("SELECT media_selected_bucket FROM " . $wpdb->prefix . "storage_bucket_manager");
            }

            $media_bucket = trim($media_bucket);

            if ($media_provider == 'digitalOcean')
            {
                $region = self::get_bucket_region();

                $endpoint = 'https://' . $media_bucket . '.' . $region . '.digitaloceanspaces.com';
                $edgepoint = 'https://' . $media_bucket . '.' . $region . '.cdn' . '.digitaloceanspaces.com';
            }
            elseif ($media_provider == 'amazonS3')
            {
                $region = self::get_aws_bucket_region();
                $endpoint = 'https://' . $media_bucket . '.' . 's3-' . $region . '.amazonaws.com';
                $edgepoint = 'https://' . $media_bucket . '.' . 's3-' . $region . '.cdn' . '.amazonaws.com';

            }

            $wpdb->update($storage_table , array('media_bucket_origin' => $endpoint, 'media_bucket_edge' => $edgepoint), array('media_service_provider' => $provider));
            $result['origin'] = $endpoint;
            $result['edge'] = $edgepoint;
            $result['success'] = true;
            echo wp_json_encode($result);
            wp_die();

    }

    /**
     * Function for getting storage settings details
     *
     */
    public static function storage_settings_details()
    {
            $copy_media_files = sanitize_text_field($_POST['copyfiles']);
            $aws_media_year = sanitize_text_field($_POST['awsyearpath']);
            $digital_media_year = sanitize_text_field($_POST['digitalyearpath']);

            $aws_delete = sanitize_text_field($_POST['awsdelete']);
            $digital_delete = sanitize_text_field($_POST['digitaldelete']);

            $provider = sanitize_text_field($_POST['provider']);
            $updated_bucket = sanitize_text_field($_POST['updatedbucket']);
            $rewrite_url = sanitize_text_field($_POST['rewriteurl']);

            $aws_status_path = sanitize_text_field($_POST['awsstatusPath']);
            $digital_status_path = sanitize_text_field($_POST['digitalstatusPath']);
            $aws_path_location = sanitize_text_field($_POST['awspath']);
            $digital_path_location = sanitize_text_field($_POST['digitalpath']);

            $aws_cname_path = sanitize_text_field($_POST['awscnamePath']);
            $digital_cname_path = sanitize_text_field($_POST['digitalcnamePath']);
            $aws_domainname = sanitize_text_field($_POST['awscname']);
            $digital_domainname = sanitize_text_field($_POST['digitalcname']);

            update_option("tabsync",'true');
            update_option('tabdownload','true');
            global $wpdb;
            $media_provider = $wpdb->get_var("SELECT media_service_provider FROM " . $wpdb->prefix . "storage_bucket_manager");
            $akey = $wpdb->get_var("SELECT aws_accesskey FROM " . $wpdb->prefix . "amazon_bucket_manager");
            $dkey = $wpdb->get_var("SELECT digital_accesskey FROM " . $wpdb->prefix . "digital_bucket_manager");

            $storage_table = $wpdb->prefix . "storage_bucket_manager";
            $wpdb->update($storage_table , array('copy_media_files' => $copy_media_files, 'updated_media_bucket' => $updated_bucket, 'media_rewrite_url' => $rewrite_url), array('media_service_provider' => $media_provider));

            $amazon_table = $wpdb->prefix . "amazon_bucket_manager";
            $wpdb->update($amazon_table , array('do_aws_domain_name' => $aws_domainname, 'aws_copy_year_path' => $aws_media_year, 'aws_delete' => $aws_delete, 'aws_media_file_path' => $aws_path_location, 'aws_status_path_settings' => $aws_status_path, 'aws_cname_path_settings' => $aws_cname_path), array('aws_accesskey' => $akey));

            $digital_table = $wpdb->prefix . "digital_bucket_manager";
            $wpdb->update($digital_table , array('do_digital_domain_name' => $digital_domainname, 'digital_copy_year_path' => $digital_media_year, 'digital_delete' => $digital_delete, 'digital_media_file_path' => $digital_path_location, 'digital_status_path_settings' => $digital_status_path, 'digital_cname_path_settings' => $digital_cname_path), array('digital_accesskey' => $dkey));

            $access_option_name = '$provider' . '_access_key';
            $secret_option_name = '$provider' . '_secret_key';

            $result['success'] = true;
            echo wp_json_encode($result);
            wp_die();
    }

    public static function sync_media_details()
    {
        $aws_offload_media = sanitize_text_field($_POST['awsoffloadmedia']);
        $digital_offload_media = sanitize_text_field($_POST['digitaloffloadmedia']);

        global $wpdb; 
        $media_bucket = $wpdb->get_var("SELECT updated_media_bucket FROM " . $wpdb->prefix . "storage_bucket_manager");
        $akey = $wpdb->get_var("SELECT aws_accesskey FROM " . $wpdb->prefix . "amazon_bucket_manager");
        $dkey = $wpdb->get_var("SELECT digital_accesskey FROM " . $wpdb->prefix . "digital_bucket_manager");

        $amazon_table = $wpdb->prefix . "amazon_bucket_manager";
        $wpdb->update($amazon_table , array('aws_offload_media_files' => $aws_offload_media), array('aws_accesskey' => $akey));

        $digital_table = $wpdb->prefix . "digital_bucket_manager";
        $wpdb->update($digital_table , array('digital_offload_media_files' => $digital_offload_media), array('digital_accesskey' => $dkey));

        if(!empty($media_bucket)){

            $provider = $wpdb->get_var("SELECT media_service_provider FROM " . $wpdb->prefix . "storage_bucket_manager");
            if ($provider == 'digitalOcean')
            {
                $offload_media = $digital_offload_media;
            }
            elseif ($provider == 'amazonS3')
            {
                $offload_media = $aws_offload_media;
            }
            if ($offload_media == 'true')
            {
                global $wpdb;
                $attachment = $wpdb->get_results("select ID from {$wpdb->prefix}posts where post_type= 'attachment'");                
                foreach ($attachment as $attach_id)
                {
                    $old_id = $attach_id->ID;
                    $attachment_id[] = $old_id;
                }
            }
            $result['success'] = true;
            $result['attachment_id'] = isset($attachment_id) ? $attachment_id : "";
        }   
        $result = isset($result) ? $result : "";
        echo wp_json_encode($result);
        wp_die();
    }

    public static function sync_process_details()
    {
        $media_number = intval($_POST['PageNumber']);

        global $wpdb; 
        $provider = $wpdb->get_var("SELECT media_service_provider FROM " . $wpdb->prefix . "storage_bucket_manager");
        $attachment = $wpdb->get_results("select ID from {$wpdb->prefix}posts where post_type= 'attachment'");
        
        $attachment_number = $media_number * 5 ;
        for($i= $attachment_number - 5; $i < $attachment_number; $i++){

            if(!empty($attachment[$i]))
            {
                $old_id = $attachment[$i]->ID;
                if ($provider == 'digitalOcean')
                {
                    $do_results = Self::$install->upload_to_digitalocean($old_id);
                }
                elseif ($provider == 'amazonS3')
                { 
                    $aws_results = Self::$install->upload_to_amazons3($old_id);
                }
            }   
        } 
        $result['success'] = true; 
        echo wp_json_encode($result);
        wp_die();
    }

    public static function displaycurrentpage()
    {
        global $wpdb;
        $currentpage = sanitize_text_field($_POST['currentpage']);
        $currenttab = sanitize_text_field($_POST['currenttab']);
        $storage_table = $wpdb->prefix . "storage_bucket_manager";
        $table_rows = $wpdb->get_results("select * from $storage_table");
        if(empty($table_rows)){
            $wpdb->insert( $storage_table , array('currentpage' => $currentpage,'currenttab' => $currenttab), array('%s', '%s'));
        }
        else{
            $provider = $wpdb->get_var("SELECT media_service_provider FROM $storage_table");
            $wpdb->update($storage_table, array('currentpage' => $currentpage, 'currenttab' => $currenttab), array('media_service_provider' => $provider));
        }
        $result['success'] = true;
        echo wp_json_encode($result);
        wp_die();
    }

    public static function displaycurrenttab()
    {
        global $wpdb;
        $currenttab = sanitize_text_field($_POST['currenttab']);
        
        if($currenttab == 'media'){
            $currentpage = "mediadisplay";
        }
        elseif($currenttab == 'settings'){
            $currentpage = "storagesetting";
        }
        elseif($currenttab == 'sync'){
            $currentpage = "syncmedia";
        }
        elseif($currenttab == 'download'){
            $currentpage = "download";
        }

        $storage_table = $wpdb->prefix . "storage_bucket_manager";
        $table_rows = $wpdb->get_results("select * from $storage_table");
        if(empty($table_rows)){
            $wpdb->insert( $storage_table, array('currenttab' => $currenttab, 'currentpage' => $currentpage), array('%s', '%s'));
        }
        else{
            $provider = $wpdb->get_var("SELECT media_service_provider FROM $storage_table");
            $wpdb->update( $storage_table, array('currenttab' => $currenttab, 'currentpage' => $currentpage), array('media_service_provider' => $provider));
        }
        $result['success'] = true;
        echo wp_json_encode($result);
        wp_die();
    }

    public static function set_component_details()
    {
        global $wpdb;

        $table_rows = $wpdb->get_results("select * from {$wpdb->prefix}storage_bucket_manager");
        $table_aws_rows = $wpdb->get_results("select * from {$wpdb->prefix}amazon_bucket_manager");
        $table_digital_rows = $wpdb->get_results("select * from {$wpdb->prefix}digital_bucket_manager");
        
        if(!empty($table_rows)){
            $provider = $table_rows[0]->media_service_provider;
            $media_existing_bucket = $table_rows[0]->media_existing_bucket;
            $copy_status = $table_rows[0]->copy_media_files;
            $rewrite_url = $table_rows[0]->media_rewrite_url;
            $end_points = $table_rows[0]->media_bucket_origin;
            $edge_point = $table_rows[0]->media_bucket_edge;
            $current_page = $table_rows[0]->currentpage;
            $current_tab = $table_rows[0]->currenttab;
        }

        if(!empty($table_aws_rows)){
            $aws_accesskey = $table_aws_rows[0]->aws_accesskey;
            $aws_secretkey = $table_aws_rows[0]->aws_secretkey;
            $aws_bucket_region = $table_aws_rows[0]->aws_bucket_region;
            $awsselectedbucket = $table_aws_rows[0]->awsselectedbucketbox;
            $aws_copy_year = $table_aws_rows[0]->aws_copy_year_path;
            $aws_delete = $table_aws_rows[0]->aws_delete;
            $aws_offload_media = $table_aws_rows[0]->aws_offload_media_files;
            $aws_domain_name = $table_aws_rows[0]->do_aws_domain_name;
            $aws_media_path = $table_aws_rows[0]->aws_media_file_path;
            $aws_status_path = $table_aws_rows[0]->aws_status_path_settings;
            $aws_cname_path = $table_aws_rows[0]->aws_cname_path_settings;
        }
        
        if(!empty($table_digital_rows)){
            $digital_accesskey = $table_digital_rows[0]->digital_accesskey;
            $digital_secretkey = $table_digital_rows[0]->digital_secretkey;
            $digital_bucket_region = $table_digital_rows[0]->digital_bucket_region;
            $digitalselectedbucket = $table_digital_rows[0]->digitalselectedbucketbox;
            $digital_copy_year = $table_digital_rows[0]->digital_copy_year_path;
            $digital_delete = $table_digital_rows[0]->digital_delete;
            $digital_offload_media = $table_digital_rows[0]->digital_offload_media_files;
            $digital_domain_name = $table_digital_rows[0]->do_digital_domain_name;
            $digital_media_path = $table_digital_rows[0]->digital_media_file_path;
            $digital_status_path = $table_digital_rows[0]->digital_status_path_settings;
            $digital_cname_path = $table_digital_rows[0]->digital_cname_path_settings;
        }
        $provider = isset($provider) ? $provider : "";
        if ($provider == 'digitalOcean')
        {
            $bucket_list = $wpdb->get_var("SELECT do_existing_buckets FROM {$wpdb->prefix}digital_bucket_manager");
            $buckets = unserialize($bucket_list);
        }
        elseif ($provider == 'amazonS3')
        {
            $bucket_list = $wpdb->get_var("SELECT aws_existing_buckets FROM {$wpdb->prefix}amazon_bucket_manager");
            $buckets = unserialize($bucket_list);
        }

        $result['success'] = true;
        $result['provider'] = !empty($provider) ? $provider : "amazonS3";
        $result['aws_accesskey'] = isset($aws_accesskey) ? $aws_accesskey : "";
        $result['digital_accesskey'] = isset($digital_accesskey) ? $digital_accesskey : "";
        $result['aws_secretkey'] = isset($aws_secretkey) ? $aws_secretkey : "";
        $result['digital_secretkey'] = isset($digital_secretkey) ? $digital_secretkey : "";
        $result['aws_bucket_region'] = isset($aws_bucket_region) ? $aws_bucket_region : "";
        $result['digital_bucket_region'] = isset($digital_bucket_region) ? $digital_bucket_region :"";

        $result['awsselectedbucket'] = isset($awsselectedbucket) ? $awsselectedbucket : "";
        $result['digitalselectedbucket'] = isset($digitalselectedbucket) ? $digitalselectedbucket : "";
        $result['media_existing_bucket'] = isset($media_existing_bucket) ? $media_existing_bucket : "";
        $result['origin'] = isset($end_points) ? $end_points : "";

        $result['copy_files'] = isset($copy_status) ? $copy_status : "";
        $aws_copy_year =isset($aws_copy_year) ? $aws_copy_year : "";
        $result['aws_copy_year'] = ($aws_copy_year === "true") ? true : false;

        $digital_copy_year = isset($digital_copy_year) ? $digital_copy_year : "";
        $result['digital_copy_year'] = ($digital_copy_year === "true") ? true : false;

        $aws_delete =isset($aws_delete) ? $aws_delete : "";
        $result['aws_delete'] = ($aws_delete === "true") ? true : false;

        $digital_delete = isset($digital_delete) ? $digital_delete : "";
        $result['digital_delete'] = ($digital_delete === "true") ? true : false;

        $result['rewrite_url'] = isset($rewrite_url) ? $rewrite_url : "";
        $aws_offload_media = isset($aws_offload_media) ? $aws_offload_media : "";
        $result['aws_offload_media'] = ($aws_offload_media === "true") ? true : false;

        $digital_offload_media = isset($digital_offload_media) ? $digital_offload_media : "";
        $result['digital_offload_media'] = ($digital_offload_media === "true") ? true : false;

        $aws_media_path = isset($aws_media_path) ? $aws_media_path : "";
        $result['aws_media_path'] = !is_null($aws_media_path) ? $aws_media_path : "";

        $digital_media_path = isset($digital_media_path) ? $digital_media_path : "";
        $result['digital_media_path'] = !is_null($digital_media_path) ? $digital_media_path : "";

        $aws_status_path = isset($aws_status_path) ? $aws_status_path : "";
        $result['aws_status_path'] = ($aws_status_path === "true") ? true : false;

        $digital_status_path = isset($digital_status_path) ? $digital_status_path : "";
        $result['digital_status_path'] = ($digital_status_path === "true") ? true : false;

        $aws_domain_name =isset($aws_domain_name) ? $aws_domain_name : "";
        $result['aws_domain_name'] = !is_null($aws_domain_name) ? $aws_domain_name : "";

        $digital_domain_name = isset($digital_domain_name) ? $digital_domain_name : "";
        $result['digital_domain_name'] = !is_null($digital_domain_name) ? $digital_domain_name : "";

        $aws_cname_path = isset($aws_cname_path) ? $aws_cname_path : "";
        $result['aws_cname_path'] = ($aws_cname_path === "true") ? true : false;

        $digital_cname_path = isset($digital_cname_path) ? $digital_cname_path : "";
        $result['digital_cname_path'] = ($digital_cname_path === "true") ? true : false;

        $result['edge'] = isset($edge_point) ? $edge_point : "";
        $result['current_page'] = isset($current_page) ? $current_page : "";
        $result['current_tab'] = isset($current_tab) ? $current_tab : "media";
        $result['bucket_list'] = isset($buckets) ? $buckets : "";

        echo wp_json_encode($result);
        wp_die();

    }

    /**
     * Function for remove bucket details
     *
     */
    public static function remove_service_provider()
    {
        global $wpdb;
        update_option("tabsetting",'false');
        update_option("tabsync",'false');
        $media_provider = sanitize_text_field($_POST['provider']);

        if ($media_provider == 'digitalOcean')
        {
            $wpdb->delete( $wpdb->prefix . 'storage_bucket_manager' , array('media_service_provider' => 'amazonS3' ), array('%s'));
           
            $amazon_buckets = $wpdb->get_var("SELECT aws_accesskey FROM " . $wpdb->prefix . "amazon_bucket_manager");
            $wpdb->delete( $wpdb->prefix . 'amazon_bucket_manager' , array('aws_accesskey' => $amazon_buckets ), array('%s'));

        }
        elseif ($media_provider == 'amazonS3')
        {
            $wpdb->delete( $wpdb->prefix . 'storage_bucket_manager' , array('media_service_provider' => 'digitalOcean' ), array('%s'));
            
            $digital_buckets = $wpdb->get_var("SELECT digital_accesskey FROM " . $wpdb->prefix . "digital_bucket_manager");
            $wpdb->delete( $wpdb->prefix . 'digital_bucket_manager' , array('digital_accesskey' => $digital_buckets ), array('%s'));
        }
        $get_rows = $wpdb->get_results("select * from wp_storage_bucket_manager",ARRAY_A);        
        if(!empty($get_rows) && isset($get_rows[0]) && empty($get_rows[0]['media_service_provider'])){            
            $wpdb->update($wpdb->prefix . "storage_bucket_manager" ,  array('media_service_provider' => $media_provider),array('cloud_media_menu' => "home"));            
        }
        else 
            $wpdb->insert( $wpdb->prefix . 'storage_bucket_manager' , array('media_service_provider' => $media_provider ), array('%s'));
        $result['success'] = true;
        echo wp_json_encode($result);
        wp_die();
    }
    
    /**
     * Function for authenticate digital_ocean
     *
     */
    public static function authenticate_digital_ocean($access_key, $secret_key)
    {
        $region = self::get_bucket_region();
        $client = new \Aws\S3\S3Client(['version' => 'latest', 'region' => $region, 'endpoint' => 'https://' . $region . '.digitaloceanspaces.com', 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], ]);
        $spaces = $client->listBuckets();
        foreach ($spaces['Buckets'] as $space)
        {
            $bucketlist[] = $space['Name'];
        }
        $bucket_list = serialize($bucketlist);
        global $wpdb;
        $digital_table = $wpdb->prefix . "digital_bucket_manager";
        $dkey = $wpdb->get_var("SELECT digital_accesskey FROM $digital_table");
        $wpdb->update($digital_table, array('do_existing_buckets' => $bucket_list), array('digital_accesskey' => $dkey));

        return $bucketlist;

    }

    public static function authenticate_aws_data($access_key, $secret_key)
    {
        $aws_region = self::get_aws_bucket_region();
        $s3Client = new \Aws\S3\S3Client(['version' => 'latest', 'region' => $aws_region, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], ]);
        $spaces = $s3Client->listBuckets();

        foreach ($spaces['Buckets'] as $space)
        {
            $bucketlist[] = $space['Name'];
        }

        $bucket_list = serialize($bucketlist);
        global $wpdb;
        $amazon_table = $wpdb->prefix . "amazon_bucket_manager";
        $akey = $wpdb->get_var("SELECT aws_accesskey FROM $amazon_table");
        $wpdb->update($amazon_table , array('aws_existing_buckets' => $bucket_list), array('aws_accesskey' => $akey));

        return $bucketlist;

    }

    /**
     * Function for offload media_images
     *
     */
    public static function do_media_transfer_handle($id)
    {
        $region = self::get_bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
        $table_digital_rows = $wpdb->get_results("select * from {$wpdb->prefix}digital_bucket_manager");
        $provider = $table_rows[0]->media_service_provider;
        $access_key = $table_digital_rows[0]->digital_accesskey;
        $secret_key = $table_digital_rows[0]->digital_secretkey;
        $copy_status = $table_rows[0]->copy_media_files;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $copy_year = $table_digital_rows[0]->digital_copy_year_path;
        $host = "digitaloceanspaces.com";
        $endpoint = "https://" . $media_bucket . "." . $region . "." . $host;

        try
        {

            $s3Client = \Aws\S3\S3Client::factory(['version' => 'latest', 'region' => $region, 'endpoint' => $endpoint, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], 'bucket_endpoint' => true, ]);

            //===Media Transfer===//
            if ($copy_status == 'true')
            {
                $file_path = get_attached_file($id);

                $upload_directory = wp_upload_dir();
                if ($copy_year == 'true')
                {
                    $base_directory = $upload_directory['basedir'] . '/';

                }
                else
                {
                    $base_directory = $upload_directory['path'] . '/';
                }
                $data_info = wp_get_attachment_metadata($id);
                $include_path = str_replace($base_directory, '', $file_path);
                $copy_status = $table_rows[0]->copy_media_files;
                $copy_year = $table_digital_rows[0]->digital_copy_year_path;
                $media_path = $table_digital_rows[0]->digital_media_file_path;
                if (!empty($media_path) && isset($media_path))
                {
                    $include_path = $media_path . $include_path;
                }
                $filename_only = basename(get_attached_file($id));
                $size_path = str_replace($filename_only, '', $file_path);
                $date_directory = str_replace($base_directory, '', $size_path);
                $filename_type = get_post_mime_type($id);
                $result = $s3Client->putObject(['Bucket' => $media_bucket, 'Key' => $include_path, 'SourceFile' => $file_path, 'ACL' => 'public-read', 'ContentType' => $filename_type]);
                if (isset($data_info['sizes']))
                {
                    foreach ($data_info['sizes'] as $sizedata)
                    {
                        $path = $size_path . $sizedata['file'];
                        $name = $include_path . '/' . $date_directory . $sizedata['file'];
                        $result = $s3Client->putObject(['Bucket' => $media_bucket, 'Key' => $name, 'SourceFile' => $path, 'ACL' => 'public-read', 'ContentType' => $filename_type]);
                    }
                }

                if (isset($data_info['original_image']))
                {
                    $orig_path = $include_path . '/' . $size_path . $data_info['original_image'];
                    $orig_name = $date_directory . $data_info['original_image'];
                    $result = $s3Client->putObject(['Bucket' => $media_bucket, 'Key' => $orig_name, 'SourceFile' => $orig_path, 'ACL' => 'public-read', 'ContentType' => $filename_type]);
                }
            }

            //===Media Transfer End===//
            
        }
        catch(S3Exception $e)
        {
            return 'error';
        }

        return $id;
    }

    /**
     * Function for getting bucket region
     *
     */
    public static function get_bucket_region()
    {
        global $wpdb;
        $region = $wpdb->get_var("SELECT digital_bucket_region FROM {$wpdb->prefix}digital_bucket_manager");
        switch ($region)
        {
            case 'New York':
                $reg = 'nyc3';
            break;
            case 'Amsterdam':
                $reg = 'ams3';
            break;
            case 'Singapore':
                $reg = 'sgp1';
            break;
            case 'San Francisco':
                $reg = 'sfo2';
            break;
            case 'Frankfurt':
                $reg = 'fra1';
            break;
            default:
                $reg = 'nyc3';
            break;
        }
        return $reg;

    }

    public static function get_aws_bucket_region()
    {
        global $wpdb;
        $region = $wpdb->get_var("SELECT aws_bucket_region FROM " . $wpdb->prefix . "amazon_bucket_manager");
        switch ($region)
        {
            case 'US East (N. Virginia)':
                $reg = 'us-east-1';
            break;
            case 'US East (Ohio)':
                $reg = 'us-east-2';
            break;
            case 'US West (N. California)':
                $reg = 'us-west-1';
            break;
            case 'US West (Oregon)':
                $reg = 'us-west-2';
            break;
            case 'Canada (Central)':
                $reg = 'ca-central-1';
            break;
            case 'Asia Pacific (Mumbai)':
                $reg = 'ap-south-1';
            break;
            case 'Asia Pacific (Seoul)':
                $reg = 'ap-northeast-2';
            break;
            case 'Asia Pacific (Singapore)':
                $reg = 'ap-southeast-1';
            break;
            case 'Asia Pacific (Sydney)':
                $reg = 'ap-southeast-2';
            break;
            case 'Asia Pacific (Tokyo)':
                $reg = 'ap-northeast-1';
            break;
            case 'EU (Frankfurt)':
                $reg = 'eu-central-1';
            break;
            case 'EU (Ireland)':
                $reg = 'eu-west-1';
            break;
            case 'EU (London)':
                $reg = 'eu-west-2';
            break;
            case 'EU (Paris)':
                $reg = 'eu-west-3';
            break;
            case 'South America (Sao Paulo)':
                $reg = 'sa-east-1';
            break;
            default:
                $reg = 'us-east-1';
            break;
        }
        return $reg;

    }

    /**
     * Function for creating new bucket
     *
     */
    public static function create_new_do_bucket($bucket_name)
    {
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from {$wpdb->prefix}storage_bucket_manager");
        $table_digital_rows = $wpdb->get_results("select * from {$wpdb->prefix}digital_bucket_manager");
        $bucket_name = trim($bucket_name);
        $provider = $table_rows[0]->media_service_provider;
        $access_key = $table_digital_rows[0]->digital_accesskey;
        $secret_key = $table_digital_rows[0]->digital_secretkey;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $region = self::get_bucket_region();
        $host = "digitaloceanspaces.com";
        $endpoint = "https://" . $media_bucket . "." . $region . "." . $host;
        try{
        $s3Client = \Aws\S3\S3Client::factory(['version' => 'latest', 'region' => $region, 'endpoint' => $endpoint, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], 'bucket_endpoint' => true, ]);
        if (!empty($bucket_name) && isset($bucket_name))
        {
            $s3Client->CreateSpace($bucket_name);
            throw new \Aws\S3\Exception\S3Exception();   
        }
        }
        catch(\Aws\S3\Exception\S3Exception $err){
            $errcode = $err->getStatusCode();      
            $result['success'] = false;
            $result['errcode'] = $errcode;
            echo wp_json_encode($result);
            wp_die();                    
        }
    }

    public static function create_new_aws_bucket($bucket_name)
    {
        global $wpdb;        
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
        $table_aws_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "amazon_bucket_manager");
        $bucket_name = trim($bucket_name);
        $provider = $table_rows[0]->media_service_provider;
        $access_key = $table_aws_rows[0]->aws_accesskey;
        $secret_key = $table_aws_rows[0]->aws_secretkey;
        $aws_region = self::get_aws_bucket_region();
        try{
        $s3Client = new \Aws\S3\S3Client(['version' => 'latest', 'region' => $aws_region, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], ]);
        if (!empty($bucket_name) && isset($bucket_name))
        {
            
            $s3Client->createBucket(array(
                'Bucket' => $bucket_name
            ));       
            throw new \Aws\S3\Exception\S3Exception();         
        }
    }
    catch(\Aws\S3\Exception\S3Exception $err){
        $errcode = $err->getStatusCode();      
        $result['success'] = false;
        $result['errcode'] = $errcode;
        echo wp_json_encode($result);
        wp_die();  
       // $errmessage = $err->getMessage();        
    }

    }

}

