<?php
/**
 * Script to bulk upload videos saved in same folder
 * @todo write better header
 */

require_once('vendor/autoload.php');
Dotenv::load(__DIR__);
Dotenv::required(array('oauth2_client_id', 'oauth2_client_secret', 'oauth2_redirect_uri', 'videodir'));

session_start();

$uploader = new Batch_YouTube_Uploader('videos.csv');
$uploader->login();
$uploader->process();

session_unset();