#!/usr/bin/php-cgi -q 
<?php

/**
 * @file
 * Recursively bulk upload a given directory's entire file contents to a given
 * Rackspace Cloud Files container.
 *
 * @author Mike Smullin <mike@smullindesign.com>  
 * @license MIT
 *
 * Usage:
 *   ./scp-cloudfiles.sh.php -u=<user> -k=<api_key> -c=<container> -p=<path>
 */

// initialize
set_time_limit(0);
ini_set('register_globals', 'on');
error_reporting(E_ALL & ~E_NOTICE);
require_once './php-cloudfiles/cloudfiles.php';

// validate arguments
$user = $_GET['-u'];
$api_key = $_GET['-k'];
$container_name = $_GET['-c'];
$path = $_GET['-p'];
if (empty($user) || empty($api_key) || empty($container_name) || empty($path)) {
  echo <<<TEXT
usage: scp-cloudfiles -u=<user> -k=<api_key> -c=<container> -p=<path>
scp-cloudfiles command-line client, version 1.0-alpha.


TEXT;
  exit;
}

/**
 * Flush given output to stdout.
 *
 * @param String $s
 *   Text to output stdout.
 * @param Boolean $lb
 *   Include CR line break.
 */
function out($s = '', $lb = TRUE) {
  echo $s . ($lb? "\n" : '');
  ob_flush();
}

# Authenticate to Cloud Files.  The default is to automatically try
# to re-authenticate if an authentication token expires.
#
# NOTE: Some versions of cURL include an outdated certificate authority (CA)
#       file.  This API ships with a newer version obtained directly from
#       cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
#       call the CF_Authentication instance's 'ssl_use_cabundle()' method.
#
out(sprintf('Initializing new CF_Authentication as "%s" / "%s"...', $user, $api_key), FALSE);
$auth = new CF_Authentication($user, $api_key);
out('Done.');

out('Authenticating with Rackspace Cloud Files...', FALSE);
$auth->authenticate();
out('Done.');

# Establish a connection to the storage system
#
# NOTE: Some versions of cURL include an outdated certificate authority (CA)
#       file.  This API ships with a newer version obtained directly from
#       cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
#       call the CF_Connection instance's 'ssl_use_cabundle()' method.
#
out('Establishing a new connection to storage system...', FALSE);
$conn = new CF_Connection($auth);
out('Done.');

out(sprintf('Getting existing remote Container "%s"...', $container_name), FALSE);
try {
  $container = $conn->get_container($container_name);
}
catch (Exception $e) {
  out('Fail! Container does not exist!');
//  out('Attempting to automatically create new remote Container...', FALSE);
//  $container = $conn->create_container($container_name);
}
out('Done.');

if (is_dir($path)) {
  $dirs = array($path);
  while (NULL !== ($dir = array_pop($dirs))) {
    if ($dh = opendir($dir)) {
      while (FALSE !== ($_file = readdir($dh))) {
        if ($_file == '.' || $_file == '..') {
            continue;
        }
        $_path = $dir . '/' . $_file;
        if (is_dir($_path)) {
          $dirs[] = $_path;
        }
        else {
          $file = $_path;
          $object_name = ltrim(str_replace($path, '', $file), '/');
          
          
          try {
            $exists = $container->get_object($object_name);
            out(sprintf('File "%s" exists',$object_name));
          } catch (Exception $e) {
            //out(sprintf('File "%s" DOES NOT exist ... ',$object_name), FALSE);
            out(sprintf('Uploading file "%s"...', $object_name), FALSE);
            try {
                $object = $container->create_object($object_name);
            } catch (Exception $e) {
                out('container->create_object Exception: '.$e);
            }
            try {
                $object->load_from_filename($file);
            } catch (Exception $e) {
                out('object->load_from_filename $file Exception: '.$e);
            }
		try {
			$img_arr = getimagesize($file);
			$img_height = (int) $img_arr[1];
			$img_width = (int) $img_arr[0];
			$object->metadata = array("Width" => $img_width , "Height" => $img_height);
			$object->sync_metadata();
		} catch (Exception $e) {
			out('container->sync_metadata Exception: ' . $e);
	  	}
            out('Done.');
          }
         
        unset($object);          
        }
      }
      closedir($dh);
    }
  }
}

exit(0);
