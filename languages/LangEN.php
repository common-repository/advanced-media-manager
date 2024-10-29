<?php
/**
 * Advanced Media Manager plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */

namespace Smackcoders\ADVMEDIA;

if (!defined('ABSPATH')) exit; // Exit if accessed directly
class LangEN
{
    private static $en_instance = null, $media_instance;

    public static function getInstance()
    {
        if (LangEN::$en_instance == null)
        {
            LangEN::$en_instance = new LangEN;
            return LangEN::$en_instance;
        }
        return LangEN::$en_instance;
    }

    public static function contents()
    {
        $response = array(
            'Continue' => 'Continue',
            'ACTION' => 'ACTION',
            'BACK' => 'BACK',
            'Action' => 'Action',
            'Name' => 'Name',
            'UploadMedia' => 'Upload Media',
            'UploadedListofFiles' => 'Uploaded List of Files',
            'UploadedMediaFileLists' => 'Uploaded Media File Lists',
            'Save' => 'Save',
            'Back' => 'Back',
            'Size' => 'Size',
            'MediaHandling' => 'Featured Image Media Handling',
            'ImageSizes' => 'Image Sizes',
            'Thumbnail' => 'Thumbnail',
            'Medium' => 'Medium',
            'MediumLarge' => 'Medium Large',
            'Large' => 'Large',
            'Custom' => 'Custom',
            'Slug' => 'Slug',
            'Width' => 'Width',
            'Height' => 'Height',
            'Format' => 'Format',
            'FileName' => 'File Name',
            'Process' => 'Process',
            'Completed' => 'Completed',
            'NoRecord' => 'No Record',
            'Date' => 'Date',
            'Select' => 'Select',
            'Updated' => 'Updated',
            'Skipped' => 'Skipped',
            'Time' => 'Time',
            'SaveChanges' => 'Save Changes',
            'Download' => 'Download',
            'Media' => 'Media',
            'AccessKey' => 'AccessKey',
            'Status' => 'Status',
            'Loading' => 'Loading',
            'Message' => 'Message',
            'All' => 'All',
            'Publish' => 'Publish',
            'Private' => 'Private',
            'Totalnoofrecords' => 'Total no of records',
            'CurrentProcessingRecord' => 'Current Processing Record',
            'RemainingRecord' => 'Remaining Record',
            'TimeElapsed' => 'Time Elapsed',
            'Protected' => 'Protected'

        );
        return $response;
    }
}

