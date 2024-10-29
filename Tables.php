<?php
/**
 * Advanced Media Manager plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\ADVMEDIA;

if (!defined('ABSPATH')) exit; // Exit if accessed directly
class Tables
{

    private static $instance = null;

    public static function getInstance()
    {
        if (Tables::$instance == null)
        {
            Tables::$instance = new Tables;
            Tables::$instance->create_tables();
            return Tables::$instance;
        }
        return Tables::$instance;
    }

    public function create_tables()
    {
        global $wpdb;

        $amazon_table = $wpdb->prefix . "amazon_bucket_manager";
        $wpdb->query("CREATE TABLE IF NOT EXISTS $amazon_table (
			`aws_accesskey` VARCHAR(255) ,
			`aws_secretkey` VARCHAR(255) ,
			`aws_bucket_region` VARCHAR(255) ,
			`awsselectedbucketbox` VARCHAR(255) ,
			`do_aws_domain_name` VARCHAR(255) ,
			`aws_copy_year_path` VARCHAR(255) ,
			`aws_delete` VARCHAR(255) ,
			`aws_media_file_path` VARCHAR(255) ,
			`aws_offload_media_files` VARCHAR(255) ,
			`aws_status_path_settings` VARCHAR(255) ,
			`aws_cname_path_settings` VARCHAR(255) ,
			`aws_existing_buckets` VARCHAR(255) 
				) ENGINE=InnoDB");

        $digital_table = $wpdb->prefix . "digital_bucket_manager";
        $wpdb->query("CREATE TABLE IF NOT EXISTS $digital_table (
			`digital_accesskey` VARCHAR(255) ,
			`digital_secretkey` VARCHAR(255) ,
			`digital_bucket_region` VARCHAR(255) ,
			`digitalselectedbucketbox` VARCHAR(255) ,
			`do_digital_domain_name` VARCHAR(255) ,
			`digital_copy_year_path` VARCHAR(255) ,
			`digital_delete` VARCHAR(255) ,
			`digital_media_file_path` VARCHAR(255) ,
			`digital_offload_media_files` VARCHAR(255) ,
			`digital_status_path_settings` VARCHAR(255) ,
			`digital_cname_path_settings` VARCHAR(255) ,
			`do_existing_buckets` VARCHAR(255) 
				) ENGINE=InnoDB");

        $storage_table = $wpdb->prefix . "storage_bucket_manager";
        $wpdb->query("CREATE TABLE IF NOT EXISTS $storage_table (
            `media_service_provider` VARCHAR(255) ,
            `currentpage` VARCHAR(255) ,
            `currenttab` VARCHAR(255) ,
            `media_selected_bucket` VARCHAR(255) ,
            `media_existing_bucket` VARCHAR(255) ,
            `media_bucket_origin` VARCHAR(255) ,
            `media_bucket_edge` VARCHAR(255) ,
            `copy_media_files` VARCHAR(255) ,
            `updated_media_bucket` VARCHAR(255) ,
            `cloud_media_menu` VARCHAR(255) ,
            `media_rewrite_url` VARCHAR(255) 
                ) ENGINE=InnoDB");

        $broken_images = $wpdb->prefix . "bucket_broken_images";
        $wpdb->query("CREATE TABLE IF NOT EXISTS $broken_images (
            `broken_image_id` VARCHAR(255) 
                ) ENGINE=InnoDB");
    }
}

