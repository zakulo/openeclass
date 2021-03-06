<?php

/* ========================================================================
 * Open eClass 2.4
 * E-learning and Course Management System
 * ========================================================================
 * Copyright 2003-2011  Greek Universities Network - GUnet
 * A full copyright notice can be read in "/info/copyright.txt".
 * For a full list of contributors, see "credits.txt".
 *
 * Open eClass is an open platform distributed in the hope that it will
 * be useful (without any warranty), under the terms of the GNU (General
 * Public License) as published by the Free Software Foundation.
 * The full license can be read in "/info/license/license_gpl.txt".
 *
 * Contact address: GUnet Asynchronous eLearning Group,
 *                  Network Operations Center, University of Athens,
 *                  Panepistimiopolis Ilissia, 15784, Athens, Greece
 *                  e-mail: info@openeclass.org
 * ======================================================================== */

/* ==========================================================================
  fileDisplayLib.inc.php
  @last update: 30-06-2006 by Thanos Kyritsis
  @authors list: Thanos Kyritsis <atkyritsis@upnet.gr>

  based on Claroline version 1.3 licensed under GPL
  copyright (c) 2001, 2006 Universite catholique de Louvain (UCL)

  original file: fileDisplayLib.inc.php Revision: 1.2

  Claroline authors: Thomas Depraetere <depraetere@ipm.ucl.ac.be>
  Hugues Peeters    <peeters@ipm.ucl.ac.be>
  Christophe Gesche <gesche@ipm.ucl.ac.be>
  ==============================================================================
  @Description:

  @Comments:

  @todo:
  ==============================================================================
 */

/* * ***************************************
  GENERIC FUNCTION :STRIP SUBMIT VALUE
 * *************************************** */

function stripSubmitValue(&$submitArray) {
    while ($array_element = each($submitArray)) {
        $name = $array_element['key'];
        $GLOBALS[$name] = stripslashes($GLOBALS [$name]);
        $GLOBALS[$name] = str_replace("\"", "'", $GLOBALS [$name]);
    }
}

/*
 * Define the image to display for each file extension
 * This needs an existing image repository to work
 *
 * @author - Thanos Kyritsis <atkyritsis@upnet.gr>
 * @author - Hugues Peeters <peeters@ipm.ucl.ac.be>
 * @param  - fileName (string) - name of a file
 * @retrun - the gif image to chose
 */

function choose_image($fileName) {
    static $type, $image;

    // Table initialisation
    if (!$type || !$image) {
        $type = array(
            'word' => array('doc', 'dot', 'rtf', 'mcw', 'wps', 'docx'),
            'web' => array('htm', 'html', 'htx', 'xml', 'xsl', 'php', 'phps', 'meta'),
            'css' => array('css'),
            'image' => array('gif', 'jpg', 'png', 'bmp', 'jpeg', 'tif', 'tiff'),
            'audio' => array('wav', 'mp2', 'mp3', 'mp4', 'vqf'),
            'midi' => array('midi', 'mid'),
            'video' => array('avi', 'mpg', 'mpeg', 'mov', 'divx', 'wmv', 'asf', 'asx'),
            'real' => array('ram', 'rm'),
            'flash' => array('swf', 'flv'),
            'excel' => array('xls', 'xlt', 'xlsx'),
            'compressed' => array('zip', 'tar', 'gz', 'bz2', 'tar.gz', 'tar.bz2', '7z'),
            'rar' => array('rar'),
            'code' => array('js', 'cpp', 'c', 'java'),
            'acrobat' => array('pdf'),
            'powerpoint' => array('ppt', 'pptx', 'pps', 'ppsx'),
            'text' => array('txt'),
        );

        $image = array(
            'word' => 'doc',
            'web' => 'html',
            'css' => 'css',
            'image' => 'gif',
            'audio' => 'wav',
            'midi' => 'midi',
            'video' => 'mpg',
            'ram' => 'ram',
            'flash' => 'flash',
            'excel' => 'xls',
            'compressed' => 'zip',
            'rar' => 'rar',
            'code' => 'js',
            'acrobat' => 'pdf',
            'powerpoint' => 'ppt',
            'text' => 'txt',
        );
    }

    // function core
    if (preg_match('/\.([[:alnum:]]+)$/', $fileName, $extension)) {
        $ext = strtolower($extension[1]);
        foreach ($type as $genericType => $typeList) {
            if (in_array($ext, $typeList)) {
                return $image[$genericType];
            }
        }
    }

    return 'default';
}

/*
 * Transform the file size in a human readable format
 *
 * @author - ???
 * @param  - fileSize (int) - size of the file in bytes
 */

function format_file_size($fileSize) {
    if ($fileSize >= 1073741824) {
        $fileSize = round($fileSize / 1073741824 * 100) / 100 . " GB";
    } elseif ($fileSize >= 1048576) {
        $fileSize = round($fileSize / 1048576 * 100) / 100 . " MB";
    } elseif ($fileSize >= 1024) {
        $fileSize = round($fileSize / 1024 * 100) / 100 . " KB";
    } else {
        $fileSize = $fileSize . " B";
    }

    return $fileSize;
}

/*
 * Transform the file path in a url
 *
 * @param - filePaht (string) - relative local path of the file on the Hard disk
 * @return - relative url
 */

function format_url($filePath) {
    $stringArray = explode("/", $filePath);

    for ($i = 0; $i < sizeof($stringArray); $i++) {
        $stringArray[$i] = rawurlencode($stringArray[$i]);
    }

    return implode("/", $stringArray);
}

function file_url_escape($name) {
    return str_replace(array('%2F', '%2f'), array('//', '//'), rawurlencode($name));
}

function public_file_path($disk_path, $filename = null) {
    global $group_sql;
    static $seen_paths;
    $dirpath = dirname($disk_path);        
    
    if ($dirpath == '/') {
        $dirname = '';
    } else {
        if (!isset($seen_paths[$disk_path])) {
            $components = explode('/', $dirpath);
            array_shift($components);
            $partial_path = '';
            $dirname = '';
            foreach ($components as $c) {
                $partial_path .= '/' . $c;
                if (!isset($seen_paths[$partial_path])) {
                    $name = Database::get()->querySingle("SELECT filename FROM document
                                                                       WHERE $group_sql AND
                                                                             path = ?s", $partial_path)->filename;                    
                    $dirname .= '/' . file_url_escape($name);
                    $seen_paths[$partial_path] = $dirname;
                } else {
                    $dirname = $seen_paths[$partial_path];
                }
            }
        } else {
            $dirname = $seen_paths[$partial_path];
        }
    }
    if (!isset($filename)) {
        $filename = Database::get()->querySingle("SELECT filename FROM document
                                               WHERE $group_sql AND
                                                     path = ?s", $disk_path)->filename;
    }
    return $dirname . '/' . file_url_escape($filename);
}

/**
 * Generate download URL for documents
 * @global type $course_code
 * @global type $urlServer
 * @global type $group_id
 * @global type $ebook_id
 * @param type $path
 * @param type $filename
 * @param type $courseCode
 * @return type
 */
function file_url($path, $filename = null, $courseCode = null) {
    global $course_code, $urlServer, $group_id, $ebook_id;
    $courseCode = ($courseCode == null) ? $course_code : $courseCode;

    if (defined('EBOOK_DOCUMENTS')) {
        return htmlspecialchars($urlServer .
                "modules/ebook/show.php/$courseCode/$ebook_id/_" .
                public_file_path($path, $filename), ENT_QUOTES);
    } else {
        $gid = defined('GROUP_DOCUMENTS') ? ",$group_id" : '';
        if (defined('COMMON_DOCUMENTS')) {
            $courseCode = 'common';
        }
        return htmlspecialchars($urlServer .
                "modules/document/file.php/$courseCode$gid" .
                public_file_path($path, $filename), ENT_QUOTES);
    }
}

/**
 *
 * @global type $course_code
 * @global type $urlServer
 * @global type $group_id
 * @global type $ebook_id
 * @param type $path
 * @param type $filename
 * @param type $courseCode
 * @return type
 */
function file_playurl($path, $filename = null, $courseCode = null) {
    global $course_code, $urlServer, $group_id, $ebook_id;
    $courseCode = ($courseCode == null) ? $course_code : $courseCode;

    if (defined('EBOOK_DOCUMENTS')) {
        return htmlspecialchars($urlServer .
                "modules/ebook/play.php/$courseCode/$ebook_id/_" .
                public_file_path($path, $filename), ENT_QUOTES);
    } else {
        $gid = defined('GROUP_DOCUMENTS') ? ",$group_id" : '';
        if (defined('COMMON_DOCUMENTS'))
            $courseCode = 'common';

        return htmlspecialchars($urlServer .
                "modules/document/play.php/$courseCode$gid" .
                public_file_path($path, $filename), ENT_QUOTES);
    }
}

/**
 * Initialize copyright/license global arrays for documents
 */
function copyright_info_init() {
    global $language;

    if ($language != 'en') {
        $link_suffix = 'deed.' . $language;
    } else {
        $link_suffix = '';
    }

    $GLOBALS['copyright_icons'] = array(
        '0' => '',
        '2' => '',
        '1' => 'copyrighted',
        '3' => 'cc/by',
        '4' => 'cc/by-sa',
        '5' => 'cc/by-nd',
        '6' => 'cc/by-nc',
        '7' => 'cc/by-nc-sa',
        '8' => 'cc/by-nc-nd');

    $GLOBALS['copyright_titles'] = array(
        '0' => $GLOBALS['langCopyrightedUnknown'],
        '2' => $GLOBALS['langCopyrightedFree'],
        '1' => $GLOBALS['langCopyrightedNotFree'],
        '3' => $GLOBALS['langCreativeCommonsCCBY'],
        '4' => $GLOBALS['langCreativeCommonsCCBYSA'],
        '5' => $GLOBALS['langCreativeCommonsCCBYND'],
        '6' => $GLOBALS['langCreativeCommonsCCBYNC'],
        '7' => $GLOBALS['langCreativeCommonsCCBYNCSA'],
        '8' => $GLOBALS['langCreativeCommonsCCBYNCND']);

    $GLOBALS['copyright_links'] = array(
        '0' => null,
        '2' => null,
        '1' => null,
        '3' => 'http://creativecommons.org/licenses/by/3.0/' . $link_suffix,
        '4' => 'http://creativecommons.org/licenses/by-sa/3.0/' . $link_suffix,
        '5' => 'http://creativecommons.org/licenses/by-nd/3.0/' . $link_suffix,
        '6' => 'http://creativecommons.org/licenses/by-nc/3.0/' . $link_suffix,
        '7' => 'http://creativecommons.org/licenses/by-nc-sa/3.0/' . $link_suffix,
        '8' => 'http://creativecommons.org/licenses/by-nc-nd/3.0/' . $link_suffix);
}
