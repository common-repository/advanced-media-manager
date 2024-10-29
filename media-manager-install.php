<?php
/**
 * Advanced Media Manager plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */
namespace Smackcoders\ADVMEDIA;
use \Aws\S3\Exception\S3Exception as awsException;

if (!defined('ABSPATH')) exit; // Exit if accessed directly
class SmackAWSInstall
{

    protected static $instance = null, $smack_instance, $tables_instance;

    /**
     * SmackAWSInstall Constructor
     */
    private function __construct()
    {
        $this->plugin = Plugin::getInstance();
        self::$tables_instance = new Tables();
    }

    /**
     * SmackAWSInstall Instance
     */
    public static function getInstance()
    {
        if (null == self::$instance)
        {
            self::$instance = new self;
            self::$instance->doHooks();
        }
        return self::$instance;
    }

    /**
     * SmackAWSInstall constructor.
     */
    public static function csvOptions()
    {
        $callbackUrl['callbackurl'] = site_url() . '/wp-admin/admin.php?action=csv_options&show=settings';
        echo json_encode($callbackUrl);
        wp_die();
    }

    public function doHooks()
    {
        add_action('wp_ajax_csv_options', array(
            $this,
            'csvOptions'
        ));
        add_action('wp_ajax_check_currentmenu', array(
            $this,
            'check_currentmenu'
        )); 
        add_action('wp_ajax_cloud_image_list', array(
            $this,
            'cloud_image_list'
        ));
        add_action('wp_ajax_delete_image_option', array(
            $this,
            'delete_image_option'
        ));
        add_action('wp_ajax_get_cloud_media_details', array(
            $this,
            'get_cloud_media_details'
        ));
        add_action('wp_ajax_download_media_details', array(
            $this,
            'download_media_details'
        ));
        add_action('wp_ajax_download_single_media_details', array(
            $this,
            'download_single_media_details'
        ));
        add_action('wp_ajax_search_cloud_media_details', array(
            $this,
            'search_cloud_media_details'
        ));
    }

    public static function check_currentmenu()
    {
        global $wpdb;
        $current_menu = $wpdb->get_var("SELECT cloud_media_menu FROM " . $wpdb->prefix . "storage_bucket_manager");
        
        $result['current_menu'] = $current_menu;
        echo json_encode($result);
        wp_die();
    }

    public static function get_cloud_media_details(){
        global $wpdb;
        $media_provider = $wpdb->get_var("SELECT media_service_provider FROM " . $wpdb->prefix . "storage_bucket_manager");
        if ($media_provider == 'digitalOcean')
        {
            self::get_digitalocean_details();
        }
        else
        {
            self::get_aws3_details();
        }  
    }

    public static function get_aws3_details(){
        require_once 'controllers/service-provider.php';
        $provider = new ServiceProvider();
        $region = $provider->get_aws_bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
        $table_aws_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "amazon_bucket_manager");

        $provider = $table_rows[0]->media_service_provider;
        $access_key = $table_aws_rows[0]->aws_accesskey;
        $secret_key = $table_aws_rows[0]->aws_secretkey;
        $copy_status = $table_rows[0]->copy_media_files;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $copy_year = $table_aws_rows[0]->aws_copy_year_path;
        
        $s3Client = new \Aws\S3\S3Client(['version' => 'latest', 'region' => $region, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], ]);
     
        $args = array(
            'Bucket' => $media_bucket,
            'Prefix' => '',
        );
        $i = 0;
        $s3Clientss = $s3Client->getPaginator('ListObjects',$args);
        foreach($s3Clientss as $client){
            foreach($client['Contents'] as $valued){
                if(!empty($valued['Owner'])){
                    $i++;
                    $download_data['key'] = basename($valued['Key']);
                    $download_data['file'] = $valued['Key'];
                    $download_data['extension'] = pathinfo($valued['Key'], PATHINFO_EXTENSION);
                    $download_data['size']  = number_format($valued['Size'] / 1024, 2) . ' KB';
                    $download_data['date'] = $valued['LastModified'];
                    $download_value[] = $download_data;
                }
            }
        }
        $result_table['table_value'] = $download_value;
        $result_table['count'] = $i;
        $result_table['success'] = true;
        echo json_encode($result_table);
        wp_die();
    }

    public static function get_digitalocean_details(){

        $region = self::bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from  {$wpdb->prefix}storage_bucket_manager");
        $table_digital_rows = $wpdb->get_results("select * from  {$wpdb->prefix}digital_bucket_manager");

        $access_key = $table_digital_rows[0]->digital_accesskey;
        $secret_key = $table_digital_rows[0]->digital_secretkey;
        $delete_enable = $table_digital_rows[0]->digital_delete;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $copy_year = $table_digital_rows[0]->digital_copy_year_path;
        $host = "digitaloceanspaces.com";
        $endpoint = "https://" . $media_bucket . "." . $region . "." . $host;

        $s3Client = \Aws\S3\S3Client::factory(['version' => 'latest', 'region' => $region, 'endpoint' => $endpoint, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], 'bucket_endpoint' => true, ]);
        
        $args = array(
            'Bucket' => $media_bucket
        );
        
        $i = 0;
        $s3Clientss = $s3Client->getPaginator('ListObjects',$args);
        foreach($s3Clientss as $client){
            foreach($client['Contents'] as $valued){
                if(!empty($valued['Owner'])){
                    $i++;
                    $download_data['key'] = basename($valued['Key']);
                    $download_data['file'] = $valued['Key'];
                    $download_data['extension'] = pathinfo($valued['Key'], PATHINFO_EXTENSION);
                    $download_data['size']  = number_format($valued['Size'] / 1024, 2) . ' KB';
                    $download_data['date'] = $valued['LastModified'];
                    $download_value[] = $download_data;
                }
            }
        }
        
        $result_table['table_value'] = $download_value;
        $result_table['count'] = $i;
        $result_table['success'] = true;
        echo json_encode($result_table);
        wp_die();
    }

    public static function search_cloud_media_details(){
        global $wpdb;
        $search_key = sanitize_text_field($_POST['search_key']);
        $search_array =str_replace( "\\", "", sanitize_text_field($_POST['tablearray']));
        $s_Array = json_decode($search_array);
        $i=0;
        if(!empty($search_key)){
            foreach($s_Array as $valued){
                if(strpos($valued->file,$search_key)){
                    $i++;
                    $download_data['key'] = basename($valued->key);
                    $download_data['file'] = $valued->file;
                    $download_data['extension'] = pathinfo($valued->key, PATHINFO_EXTENSION);
                    $download_data['size']  = number_format($valued->size / 1024, 2) . ' KB';
                    $download_data['date'] = $valued->date;
                    $download_value[] = $download_data;
                }
            }
        }else{
            $download_value = $s_Array;
            $i = count($array);
        }
        $result_table['table_value'] = $download_value;
        $result_table['count'] = $i;
        $result_table['success'] = true;
        echo json_encode($result_table);
        wp_die();
    }

    public function smack_direct_filesystem() {
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        return new \WP_Filesystem_Direct( new \StdClass() );
    }

    public function smack_mkdir_p( $target ) {
        
        if ( self::smack_direct_filesystem()->exists( $target ) ) {
            return self::smack_direct_filesystem()->is_dir( $target );
        }
    
        if ( self::smack_mkdir( $target ) ) {
            return true;
        } elseif ( self::smack_direct_filesystem()->is_dir( dirname( $target ) ) ) {
            return false;
        }
    
        if ( ( '/' !== $target ) && ( self::smack_mkdir_p( dirname( $target ) ) ) ) {
            return self::smack_mkdir_p( $target );
        }
    
        return false;
    }

    public function smack_mkdir( $path ) {
        $chmod = 0755;
        return self::smack_direct_filesystem()->mkdir( $path, $chmod );
    }


    public static function download_media_details(){
        global $wpdb;
        $download_type = sanitize_text_field($_POST['download_type']);
        $download_files =str_replace( "\\", "", sanitize_text_field($_POST['download_array']));
        $fillle = json_decode($download_files);
        $media_provider = $wpdb->get_var("SELECT media_service_provider FROM " . $wpdb->prefix . "storage_bucket_manager");
        if ($media_provider == 'digitalOcean')
        {
            self::downloadall_digitalocean_details($fillle,$download_type);
        }
        else
        {
            self::downloadall_aws3_details($fillle,$download_type);
        }           
    }

    public static function downloadall_digitalocean_details($files,$type){
        $download_all_do_files = $files;
        $region = self::bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from  {$wpdb->prefix}storage_bucket_manager");
        $table_digital_rows = $wpdb->get_results("select * from  {$wpdb->prefix}digital_bucket_manager");

        $access_key = $table_digital_rows[0]->digital_accesskey;
        $secret_key = $table_digital_rows[0]->digital_secretkey;
        $delete_enable = $table_digital_rows[0]->digital_delete;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $copy_year = $table_digital_rows[0]->digital_copy_year_path;
        $host = "digitaloceanspaces.com";
        $endpoint = "https://" . $media_bucket . "." . $region . "." . $host;

        $s3Client = \Aws\S3\S3Client::factory(['version' => 'latest', 'region' => $region, 'endpoint' => $endpoint, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], 'bucket_endpoint' => true, ]);

        if($type === 'exit'){
            $content_path = WP_CONTENT_DIR;
            $wordpress_path = str_replace('/wp-content','',$content_path);
            foreach($download_all_do_files as $all_do_file){
                $cache_dir_path = dirname( $all_do_file->file );
                $create_dir_path = $wordpress_path.'/'.$cache_dir_path;
                $create_dir_path = str_replace('//','/',$create_dir_path);
                if(!empty($cache_dir_path)){
                    $save_file = $create_dir_path.'/'.$all_do_file->key;
                }
                else{
                    $dir = wp_upload_dir();
                    $save_file = $dir['path'].'/'.$all_do_file->key;
                }
                self::smack_mkdir_p($create_dir_path);
                $argsss = array(
                    'Bucket' => $media_bucket,
                    'Key' => $all_do_file->file,
                    'SaveAs' => $save_file,
                );
                $s3Clientww = $s3Client->getObject($argsss);
            }
        }else{
            foreach($download_all_do_files as $all_do_file){
                $dir = wp_upload_dir();
                $save_file = $dir['path'].'/'.$all_do_file->key;
                $argsss = array(
                    'Bucket' => $media_bucket,
                    'Key' => $all_do_file->file,
                    'SaveAs' => $save_file,
                );
                $s3Clientww = $s3Client->getObject($argsss);
            }
        }

        $result_tab['success'] = true;
        echo json_encode($result_tab);
        wp_die();
    }

    public static function downloadall_aws3_details($files,$type){        
        $download_all_aws_files = $files;
        require_once 'controllers/service-provider.php';
        $provider = new ServiceProvider();
        $region = $provider->get_aws_bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
        $table_aws_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "amazon_bucket_manager");

        $provider = $table_rows[0]->media_service_provider;
        $access_key = $table_aws_rows[0]->aws_accesskey;
        $secret_key = $table_aws_rows[0]->aws_secretkey;
        $copy_status = $table_rows[0]->copy_media_files;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $copy_year = $table_aws_rows[0]->aws_copy_year_path;
        
        $s3Client = new \Aws\S3\S3Client(['version' => 'latest', 'region' => $region, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], ]);
     
        $args = array(
            'Bucket' => $media_bucket,
            'Prefix' => '',
        );
        if($type === 'exit'){
            $content_path = WP_CONTENT_DIR;
            $wordpress_path = str_replace('/wp-content','',$content_path);
            foreach($download_all_aws_files as $all_do_file){
                $cache_dir_path = dirname( $all_do_file->file );
                $create_dir_path = $wordpress_path.'/'.$cache_dir_path;
                $create_dir_path = str_replace('//','/',$create_dir_path);
                if(!empty($cache_dir_path)){
                    $save_file = $create_dir_path.'/'.$all_do_file->key;
                }
                else{
                    $dir = wp_upload_dir();
                    $save_file = $dir['path'].'/'.$all_do_file->key;
                }
                self::smack_mkdir_p($create_dir_path);
                $argsss = array(
                    'Bucket' => $media_bucket,
                    'Key' => $all_do_file->file,
                    'SaveAs' => $save_file,
                );
                $s3Clientww = $s3Client->getObject($argsss);
            }
        }else{
            foreach($download_all_aws_files as $all_do_file){
                $dir = wp_upload_dir();
                $save_file = $dir['path'].'/'.$all_do_file->key;
                $argsss = array(
                    'Bucket' => $media_bucket,
                    'Key' => $all_do_file->file,
                    'SaveAs' => $save_file,
                );
                $s3Clientww = $s3Client->getObject($argsss);
            }
        }

       
        $result_tab['success'] = true;
        echo json_encode($result_tab);
        wp_die();
    }

    public static function downloadsingle_digitalocean_details($field){
        $file = $field;
        $region = self::bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from  {$wpdb->prefix}storage_bucket_manager");
        $table_digital_rows = $wpdb->get_results("select * from  {$wpdb->prefix}digital_bucket_manager");

        $access_key = $table_digital_rows[0]->digital_accesskey;
        $secret_key = $table_digital_rows[0]->digital_secretkey;
        $delete_enable = $table_digital_rows[0]->digital_delete;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $copy_year = $table_digital_rows[0]->digital_copy_year_path;
        $host = "digitaloceanspaces.com";
        $endpoint = "https://" . $media_bucket . "." . $region . "." . $host;

        $s3Client = \Aws\S3\S3Client::factory(['version' => 'latest', 'region' => $region, 'endpoint' => $endpoint, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], 'bucket_endpoint' => true, ]);
        
        $args = array(
            'Bucket' => $media_bucket,
            'Prefix' => '',
        );
        
        $key = basename($file);
        $dir = wp_upload_dir();
        $save_file = $dir['path'].'/'.$key; 
        $argsss = array(
            'Bucket' => $media_bucket,
            'Key' => $file,
            'SaveAs' => $save_file,
        );
        $s3Clientww = $s3Client->getObject($argsss);
       
        $result_tab['success'] = true;
        echo json_encode($result_tab);
        wp_die();
    }

    public static function downloadsingle_aws3_details($filed){
        $file = $filed;
        require_once 'controllers/service-provider.php';
        $provider = new ServiceProvider();
        $region = $provider->get_aws_bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
        $table_aws_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "amazon_bucket_manager");

        $provider = $table_rows[0]->media_service_provider;
        $access_key = $table_aws_rows[0]->aws_accesskey;
        $secret_key = $table_aws_rows[0]->aws_secretkey;
        $copy_status = $table_rows[0]->copy_media_files;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $copy_year = $table_aws_rows[0]->aws_copy_year_path;
        
        $s3Client = new \Aws\S3\S3Client(['version' => 'latest', 'region' => $region, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], ]);
     
        $args = array(
            'Bucket' => $media_bucket,
            'Prefix' => '',
        );
        
        $key = basename($file);
        $dir = wp_upload_dir();
        $save_file = $dir['path'].'/'.$key; 
        $argsss = array(
            'Bucket' => $media_bucket,
            'Key' => $file,
            'SaveAs' => $save_file,
        );
        $s3Clientww = $s3Client->getObject($argsss);
       
        $result_tab['success'] = true;
        echo json_encode($result_tab);
        wp_die();
    }

    public static function download_single_media_details(){
        global $wpdb;
        $media_provider = $wpdb->get_var("SELECT media_service_provider FROM " . $wpdb->prefix . "storage_bucket_manager");
        $file = sanitize_text_field($_POST['file']);
        if ($media_provider == 'digitalOcean')
        {
            self::downloadsingle_digitalocean_details($file);
        }
        else
        {
            self::downloadsingle_aws3_details($file);
        }  
         
    }

    public static function delete_image_option(){
        global $wpdb;
        $delete_image_id = intval($_POST['matchtab']);
        
        $delete_data = str_replace("\\", '', sanitize_text_field($_POST['bulkdelete']));
        $delete_image_data = json_decode($delete_data, True);

        if($delete_image_id){
            wp_delete_attachment($delete_image_id);
            self::$instance->cloud_media_delete_attachment($delete_image_id);
        }
        if($delete_image_data){
            foreach($delete_image_data as $deleted_data){
                if($deleted_data['checked']){
                    $result['checked'] = true;
                    $delete_id = $deleted_data['ID'];

                    wp_delete_attachment($delete_id);
                    self::$instance->cloud_media_delete_attachment($delete_id);
                }
            }
        }

        $result['success'] = true;
        echo json_encode($result);
        wp_die();
    }

    public static function cloud_image_list()
    {
        global $wpdb;

        $matchtab = sanitize_text_field($_POST['matchtab']);
        if($matchtab == 'All'){
            $meta_attachment = $wpdb->get_results( $wpdb->prepare("SELECT * from {$wpdb->prefix}posts where post_type = %s and guid not like %s ", 'attachment','http://localhost%'));
        }
        if($matchtab == 'Attached'){
            $meta_attachment = $wpdb->get_results( $wpdb->prepare("SELECT * from {$wpdb->prefix}posts where post_type = %s and post_parent != %d and guid not like %s", 'attachment',0,'http://localhost%'));
        }
        if($matchtab == 'Unattached'){
            $meta_attachment = $wpdb->get_results( $wpdb->prepare("SELECT * from {$wpdb->prefix}posts where post_type = %s and post_parent = %d and guid not like %s", 'attachment',0,'http://localhost%'));
        }
        
        if($matchtab){

            $all = $wpdb->get_results( $wpdb->prepare("SELECT * from {$wpdb->prefix}posts where post_type = %s and guid not like %s ", 'attachment','http://localhost%'));
            $attached = $wpdb->get_results( $wpdb->prepare("SELECT * from {$wpdb->prefix}posts where post_type = %s and post_parent != %d and guid not like %s", 'attachment',0,'http://localhost%'));
            $unattached = $wpdb->get_results( $wpdb->prepare("SELECT * from {$wpdb->prefix}posts where post_type = %s and post_parent = %d and guid not like %s", 'attachment',0,'http://localhost%'));
        }

        if(!empty($meta_attachment)){
            foreach($meta_attachment as $meta){
                $post_date = explode(" ",$meta->post_date);
                $meta->post_date = $post_date[0];
                $meta->checked = false;
                $image[] = $meta;
            }
        }
        
        $result['image'] = isset($image) ? $image :"";
        $result['all_image'] = isset($all) ? $all :"";
        $result['attached_image'] = isset($attached) ? $attached :"";
        $result['unattached_image'] = isset($unattached) ? $unattached :"";
        echo json_encode($result);
        wp_die();
    }

    /**
     * Content media url modificaction for DigitalOcean
     *
     * @param $content
     */
    public static function content_media_url_modification($content)
    {
        preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $content, $match);
        $content_urls = $match[0];
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
        $table_aws_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "amazon_bucket_manager");
        $table_digital_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "digital_bucket_manager");

        $rewrite_url = $table_rows[0]->media_rewrite_url;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $upload_directory = wp_upload_dir();
        $media_provider = $table_rows[0]->media_service_provider;
        if ($media_provider == 'digitalOcean')
        {
            $copy_year = $table_digital_rows[0]->digital_copy_year_path;
            $domain_name = $table_digital_rows[0]->do_digital_domain_name;
            $media_path = $table_digital_rows[0]->digital_media_file_path;
        }
        elseif ($media_provider == 'amazonS3')
        {
            $copy_year = $table_aws_rows[0]->aws_copy_year_path;
            $domain_name = $table_aws_rows[0]->do_aws_domain_name;
            $media_path = $table_aws_rows[0]->aws_media_file_path;
        }

        $media_path = substr_replace($media_path, "", -1);
        $end_points = $table_rows[0]->media_bucket_origin;
        if ($rewrite_url == 'true')
        {
            foreach ($content_urls as $content_url)
            {
                if (!empty($domain_name) && isset($domain_name))
                {
                    if (!empty($media_path) && isset($media_path))
                    {
                        $do_storage_location = $domain_name . '/' . $media_path;
                    }
                    else
                    {
                        $do_storage_location = $domain_name;
                    }
                }
                else
                {
                    if (!empty($media_path) && isset($media_path))
                    {
                        $do_storage_location = $end_points . '/' . $media_path;
                    }
                    else
                    {
                        $do_storage_location = $end_points;
                    }
                }

                if ($copy_year == 'true')
                {
                    $media_base_url = $upload_directory['baseurl'];

                }
                else
                {
                    $media_base_url = $upload_directory['url'];
                }

                if (strpos($content_url, $do_storage_location) !== false)
                {
                    $content = str_replace($media_base_url, $do_storage_location, $content);
                }
                else
                {
                    $content = str_replace($media_base_url, $do_storage_location, $content);
                }
            }
        }
        return $content;
    }

    /**
     *  media url modificaction for DigitalOcean
     *
     * @param $attachment_url
     */
    public static function do_media_url_modification($attachment_url)
    {
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
        $table_aws_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "amazon_bucket_manager");
        $table_digital_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "digital_bucket_manager");

        $rewrite_url = $table_rows[0]->media_rewrite_url;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $provider = $table_rows[0]->media_service_provider;
        $id = self::$instance->get_attachment_id_from_url($attachment_url);                    
        if($id){        
        $get_status = $wpdb->get_results("select meta_value from {$wpdb->prefix}postmeta where post_id = $id and meta_key = 'smack_storage_status'");
        
        $mime_type = get_post_mime_type($id);
        $media_provider = $table_rows[0]->media_service_provider;
        if ($media_provider == 'digitalOcean')
        {
            $copy_year = $table_digital_rows[0]->digital_copy_year_path;
            $domain_name = $table_digital_rows[0]->do_digital_domain_name;
            $media_path = $table_digital_rows[0]->digital_media_file_path;
        }
        elseif ($media_provider == 'amazonS3')
        {
            $copy_year = $table_aws_rows[0]->aws_copy_year_path;
            $domain_name = $table_aws_rows[0]->do_aws_domain_name;
            $media_path = $table_aws_rows[0]->aws_media_file_path;
        }
        $origin = $table_rows[0]->media_bucket_origin;
        $mime_type_explode = explode('/', $mime_type);
        $extension = $mime_type_explode[0];
        
        if ($extension == 'image' || $extension == 'video' || $extension == 'text' || $extension == 'audio' || $extension == 'application')
        {
            $upload_directory = wp_upload_dir();
            if ($copy_year == 'true')
            {
                $media_base_url = $upload_directory['baseurl'];
            }
            else
            {
                $media_base_url = $upload_directory['url'];
            }
            $media_path = substr_replace($media_path, "", -1);
            $get_status =  $wpdb->get_var( $wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}postmeta where meta_key = %s and post_id = %d",'smack_storage_status',$id));            
            
            if ($rewrite_url == 'true' && ($get_status =='inserted' || $get_status == 'updated' ))
            {
                if (!empty($domain_name) && isset($domain_name))
                {
                    if (!empty($media_path) && isset($media_path))
                    {
                        $do_storage_location = $domain_name . '/' . $media_path;
                    }
                    else
                    {
                        $do_storage_location = $domain_name;
                    }
                }
                else
                {
                    if (!empty($media_path) && isset($media_path))
                    {
                        $do_storage_location = $origin . '/' . $media_path;
                    }
                    else
                    {
                        $do_storage_location = $origin;
                    }
                }

                if($get_status == 'updated'){
                    $get_guid =  $wpdb->get_var( $wpdb->prepare("SELECT guid FROM {$wpdb->prefix}posts where id = %d",$id));
                    if (strstr($get_guid, $do_storage_location)){
                        $attachment_url = str_replace($media_base_url, $do_storage_location, $attachment_url);
                    }
                    else{
                        $attachment_url = $get_guid;
                    }
                }
                elseif($get_status == 'inserted'){
                    $attachment_url = str_replace($media_base_url, $do_storage_location, $attachment_url);
                }
                
                update_post_meta($id,'smack_storage_status','updated');
            }
            $sql = $wpdb->update($wpdb->prefix . 'posts' , array('guid' => $attachment_url), array('ID' => $id));
            return $attachment_url;
        }
        }
    }
    
    /**
     *  media  source modificaction for DigitalOcean
     *
     * @param $attr
     */
    public static function do_media_src_modification($attr)
    {
        $region = self::bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
        $table_aws_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "amazon_bucket_manager");
        $table_digital_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "digital_bucket_manager");

        $rewrite_url = $table_rows[0]->media_rewrite_url;
        $upload_directory = wp_upload_dir();
        $media_provider = $table_rows[0]->media_service_provider;
        if ($media_provider == 'digitalOcean')
        {
            $copy_year = $table_digital_rows[0]->digital_copy_year_path;
            $domain_name = $table_digital_rows[0]->do_digital_domain_name;
            $media_path = $table_digital_rows[0]->digital_media_file_path;
        }
        elseif ($media_provider == 'amazonS3')
        {
            $copy_year = $table_aws_rows[0]->aws_copy_year_path;
            $domain_name = $table_aws_rows[0]->do_aws_domain_name;
            $media_path = $table_aws_rows[0]->aws_media_file_path;
        }
        $media_path = substr_replace($media_path, "", -1);
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        if ($copy_year == 'true')
        {
            $media_base_url = $upload_directory['baseurl'];

        }
        else
        {
            $media_base_url = $upload_directory['url'];
        }
       
        if ($rewrite_url == 'true')
        {
            if (!empty($domain_name) && isset($domain_name))
            {
                if (!empty($media_path) && isset($media_path))
                {
                    $do_storage_location = $domain_name . '/' . $media_path;
                }
                else
                {
                    $do_storage_location = $domain_name;
                }
            }
            else
            {
                if (!empty($media_path) && isset($media_path))
                {
                    $do_storage_location = 'https://' . $media_bucket . '.' . $region . '.digitaloceanspaces.com/' . $media_path;
                }
                else
                {
                    $do_storage_location = 'https://' . $media_bucket . '.' . $region . '.digitaloceanspaces.com';
                }
            }

            $main_url = str_replace($do_storage_location, $media_base_url, $attr['src']);
            $id = self::$instance->get_attachment_id_from_url($main_url);
            $mime_type = get_post_mime_type($id);
            $mime_type_explode = explode('/', $mime_type);
            $extension = $mime_type_explode[0];

            if ($extension == 'image' || $extension == 'video' || $extension == 'text' || $extension == 'audio' || $extension == 'application')
            {
                $attr['srcset'] = str_replace($media_base_url, $do_storage_location, $attr['srcset']);
            }
        }
        return $attr;
    }

    public function cloud_media_upload_attachment($id)
    {
        global $wpdb;
        $mime = get_post_mime_type($id);
        $mime_type = explode('/', $mime);
        $extension_type = $mime_type[0];
        if ($extension_type == 'image' || $extension_type == 'video' || $extension_type == 'text' || $extension_type == 'audio' || $extension_type == 'application')
        {
            $media_provider = $wpdb->get_var("SELECT media_service_provider FROM " . $wpdb->prefix . "storage_bucket_manager");

            if ($media_provider == 'digitalOcean')
            {
                $result = self::upload_to_digitalocean($id);
            }
            else
            {
               $result = self::upload_to_amazons3($id);
            }            
        }
    }

    public function cloud_media_delete_attachment($id){
        global $wpdb;
        $mime = get_post_mime_type($id);
        $mime_type = explode('/', $mime);
        $extension_type = $mime_type[0];

        if ($extension_type == 'image' || $extension_type == 'video' || $extension_type == 'text' || $extension_type == 'audio' || $extension_type == 'application')
        {
            $media_provider = $wpdb->get_var("SELECT media_service_provider FROM " . $wpdb->prefix . "storage_bucket_manager");

            if ($media_provider == 'digitalOcean')
            {
                self::remove_from_digitalocean($id);
            }
            else
            {
                self::remove_from_amazons3($id);
            }

        }
    }

    public static function remove_from_digitalocean($id)
    {
        $region = self::bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from  {$wpdb->prefix}storage_bucket_manager");
        $table_digital_rows = $wpdb->get_results("select * from  {$wpdb->prefix}digital_bucket_manager");

        $access_key = $table_digital_rows[0]->digital_accesskey;
        $secret_key = $table_digital_rows[0]->digital_secretkey;
        $delete_enable = $table_digital_rows[0]->digital_delete;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $copy_year = $table_digital_rows[0]->digital_copy_year_path;
        $host = "digitaloceanspaces.com";
        $endpoint = "https://" . $media_bucket . "." . $region . "." . $host;

        try
        {
            $s3Client = \Aws\S3\S3Client::factory(['version' => 'latest', 'region' => $region, 'endpoint' => $endpoint, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], 'bucket_endpoint' => true, ]);

            if(!empty($media_bucket) && $delete_enable == "true"){
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
                $media_path = $table_digital_rows[0]->digital_media_file_path;
                if (!empty($media_path) && isset($media_path))
                {
                    $include_path = $media_path . $include_path;
                }

                $filename_only = basename(get_attached_file($id));
                $size_path = str_replace($filename_only, '', $file_path);
                $date_directory = str_replace($base_directory, '', $size_path);
                $result = $s3Client->deleteObject(['Bucket' => $media_bucket, 'Key' => $include_path]);
              /*  if (isset($data_info['sizes']))
                {
                    foreach ($data_info['sizes'] as $sizedata)
                    {
                        $name = $date_directory . $sizedata['file'];
                        $result = $s3Client->deleteObject(['Bucket' => $media_bucket, 'Key' => $name]);
                    }
                }

                if (isset($data_info['original_image']))
                {
                    $orig_name = $date_directory . $data_info['original_image'];
                    $result = $s3Client->deleteObject(['Bucket' => $media_bucket, 'Key' => $orig_name]);
                }*/
            }
        }
        catch(S3Exception $e)
        {
            return 'error';
        }
        return $id;
    }

    public static function remove_from_amazons3($id)
    {
        require_once 'controllers/service-provider.php';
        $provider = new ServiceProvider();
        $region = $provider->get_aws_bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
        $table_aws_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "amazon_bucket_manager");

        $access_key = $table_aws_rows[0]->aws_accesskey;
        $secret_key = $table_aws_rows[0]->aws_secretkey;
        $delete_enable = $table_aws_rows[0]->aws_delete;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $copy_year = $table_aws_rows[0]->aws_copy_year_path;
        try
        {
            $s3Client = new \Aws\S3\S3Client(['version' => 'latest', 'region' => $region, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ]]);
         
            if(!empty($media_bucket) && $delete_enable == "true"){
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
                $media_path = $table_aws_rows[0]->aws_media_file_path;
                if (!empty($media_path) && isset($media_path))
                {
                    $include_path = $media_path . $include_path;
                }
                $filename_only = basename(get_attached_file($id));
                $size_path = str_replace($filename_only, '', $file_path);
                $date_directory = str_replace($base_directory, '', $size_path);
                $checked = $s3Client->deleteObject(['Bucket' => $media_bucket, 'Key' => $include_path]);
             /*   if (isset($data_info['sizes']))
                {
                    foreach ($data_info['sizes'] as $sizedata)
                    {
                        $name = $date_directory . $sizedata['file'];
                        $s3Client->deleteObject(['Bucket' => $media_bucket, 'Key' => $name]);
                    }
                }
                if (isset($data_info['original_image']))
                {
                    $orig_name = $date_directory . $data_info['original_image'];
                    $s3Client->deleteObject(['Bucket' => $media_bucket, 'Key' => $orig_name]);
                }*/
            }
        }
        catch(S3Exception $e)
        {
            return 'error';
        }
        return $id;
    }

    /**
     *  upload media to DigitalOcean
     *
     * @param $id
     */
    public static function upload_to_digitalocean($id)
    {

        $region = self::bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from  {$wpdb->prefix}storage_bucket_manager");
        $table_digital_rows = $wpdb->get_results("select * from  {$wpdb->prefix}digital_bucket_manager");

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
            /*    if (isset($data_info['sizes']))
                {
                    foreach ($data_info['sizes'] as $sizedata)
                    {
                        $path = $size_path . $sizedata['file'];
                        $name = $date_directory . $sizedata['file'];
                        $result = $s3Client->putObject(['Bucket' => $media_bucket, 'Key' => $name, 'SourceFile' => $path, 'ACL' => 'public-read', 'ContentType' => $filename_type]);
                    }
                }

                if (isset($data_info['original_image']))
                {
                    $orig_path = $size_path . $data_info['original_image'];
                    $orig_name = $date_directory . $data_info['original_image'];
                    $result = $s3Client->putObject(['Bucket' => $media_bucket, 'Key' => $orig_name, 'SourceFile' => $orig_path, 'ACL' => 'public-read', 'ContentType' => $filename_type, ]);
                }*/
                if (empty($data_info)){
                    $wpdb->insert($wpdb->prefix . "bucket_broken_images" , array('broken_image_id' => $id ), array('%d'));
                }
            }
            update_post_meta($id,'smack_storage_status','inserted');
        }
        catch(\Aws\S3\Exception\S3Exception $err){
            $errcode = $err->getStatusCode();      
            $result['success'] = false;
            $result['errcode'] = $errcode;
            update_post_meta($id,'smack_storage_status','Error: '. $errcode );
          return $result;                   
        }
        return $id;
    }

    public static function upload_to_amazons3($id)
    {
        require_once 'controllers/service-provider.php';
        
        $provider = new ServiceProvider();
        $region = $provider->get_aws_bucket_region();
        global $wpdb;
        $table_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "storage_bucket_manager");
        $table_aws_rows = $wpdb->get_results("select * from " . $wpdb->prefix . "amazon_bucket_manager");

        $provider = $table_rows[0]->media_service_provider;
        $access_key = $table_aws_rows[0]->aws_accesskey;
        $secret_key = $table_aws_rows[0]->aws_secretkey;
        $copy_status = $table_rows[0]->copy_media_files;
        $media_bucket = $table_rows[0]->updated_media_bucket;
        $media_bucket = trim($media_bucket);
        $copy_year = $table_aws_rows[0]->aws_copy_year_path;
        try
        {
            $s3Client = new \Aws\S3\S3Client(['version' => 'latest', 'region' => $region, 'credentials' => ['key' => $access_key, 'secret' => $secret_key, ], ]);
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
                $copy_year = $table_aws_rows[0]->aws_copy_year_path;
                $media_path = $table_aws_rows[0]->aws_media_file_path;
                if (!empty($media_path) && isset($media_path))
                {
                    $include_path = $media_path . $include_path;
                }
                $filename_only = basename(get_attached_file($id));
                $size_path = str_replace($filename_only, '', $file_path);
                $date_directory = str_replace($base_directory, '', $size_path);
                $filename_type = get_post_mime_type($id);                
                $result = $s3Client->putObject(['Bucket' => $media_bucket, 'Key' => $include_path, 'SourceFile' => $file_path, 'ACL' => 'public-read', 'ContentType' => $filename_type]);                   
                
              /*  if (isset($data_info['sizes']))
                {
                    foreach ($data_info['sizes'] as $sizedata)
                    {
                        $path = $size_path . $sizedata['file'];
                        $name = $date_directory . $sizedata['file'];
                        $s3Client->putObject(['Bucket' => $media_bucket, 'Key' => $name, 'SourceFile' => $path, 'ACL' => 'public-read', 'ContentType' => $filename_type]);
                    }
                }
                if (isset($data_info['original_image']))
                {
                    $orig_path = $size_path . $data_info['original_image'];
                    $orig_name = $date_directory . $data_info['original_image'];
                    $s3Client->putObject(['Bucket' => $media_bucket, 'Key' => $orig_name, 'SourceFile' => $orig_path, 'ACL' => 'public-read', 'ContentType' => $filename_type]);
                }  */              
                if (empty($data_info)){
                    $wpdb->insert($wpdb->prefix . "bucket_broken_images" , array('broken_image_id' => $id ), array('%d'));
                }
            }
            update_post_meta($id,'smack_storage_status','inserted');
        }
        catch(\Aws\S3\Exception\S3Exception $err){            
            $errcode = $err->getStatusCode();                
            $result['success'] = false;
            $result['errcode'] = $errcode;
            update_post_meta($id,'smack_storage_status','Error: '. $errcode );
          return $result;                   
        }        
        catch(awsException $err){            
            $errcode = $err->getStatusCode();                
            $result['success'] = false;
            $result['errcode'] = $errcode;
            if(is_numeric($errcode))
                update_post_meta($id,'smack_storage_status','Error: '. $errcode );
          return $result;                   
        }        
        return $id;
    }

    /**
     *  getting attachment_id from url
     *
     * @param $attachment_url
     */
    public static function get_attachment_id_from_url($attachment_url)
    {
        global $wpdb;
        $attachment_id = false;
        if ('' == $attachment_url) return;
        $upload_dir_paths = wp_upload_dir();
        if (false !== strpos($attachment_url, $upload_dir_paths['baseurl']))
        {
            $attachment_url = preg_replace('/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $attachment_url);
            $attachment_url = str_replace($upload_dir_paths['baseurl'] . '/', '', $attachment_url);
            $attachment_id = $wpdb->get_var($wpdb->prepare("SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = %s AND wpostmeta.meta_value = %s AND wposts.post_type = %s", '_wp_attached_file',$attachment_url,'attachment'));
        }
        return $attachment_id;
    }

    /**
     *  getting bucket region
     *
     *
     */
    public static function bucket_region()
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

}

