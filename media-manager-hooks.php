<?php
/**
 * Advanced Media Manager plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\ADVMEDIA;

if (!defined('ABSPATH'))
{
    die;
}

$plugin_ajax_hooks = [

'get_service_provider', 'remove_service_provider', 'check_currentmenu', 'cloud_image_list', 'delete_image_option', 'get_bucket_details', 'storage_settings_details', 'sync_media_details', 'sync_process_details', 'get_actual_component_details', 'displaycurrentpage', 'displaycurrenttab','csv_options','setting_tabs','get_cloud_media_details','download_media_details','download_single_media_details','search_cloud_media_details' ];

