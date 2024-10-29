<?php
/**
 * Advanced Media Manager plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\ADVMEDIA;

if (!defined('ABSPATH')) exit; // Exit if accessed directly
class Plugin
{
    private static $instance = null;
    private static $string = 'advanced-media-manager';

    public static function getInstance()
    {
        if (Plugin::$instance == null)
        {
            Plugin::$instance = new Plugin;

            return Plugin::$instance;
        }
        return Plugin::$instance;
    }

    public function getPluginSlug()
    {
        return Plugin::$string;
    }

    public static function activate()
    {

    }

    public static function deactivate()
    {

    }
}

