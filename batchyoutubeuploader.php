<?php
/**
 * Script to bulk upload videos saved in same folder
 */

require_once('vendor/autoload.php');

// Load the variables from the config file
Dotenv::load(__DIR__);
Dotenv::required(array('oauth2_client_id', 'oauth2_client_secret', 'oauth2_redirect_uri', 'videodir'));
$OAUTH2_CLIENT_ID = getenv('oauth2_client_id');
$OAUTH2_CLIENT_SECRET = getenv('oauth2_client_secret');
$OAUTH2_REDIRECT_URI = getenv('oauth2_redirect_uri');
$videoDir = getenv('videodir');
$logFile = 'output.log';

// Set up the Google Client
$SCOPES = array('https://www.googleapis.com/auth/youtube',
								'https://www.googleapis.com/auth/youtube.upload',
								'https://www.googleapis.com/auth/youtubepartner');
session_start();
$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);
$client->setRedirectUri($OAUTH2_REDIRECT_URI);
$client->setScopes($SCOPES);

// @todo need to check if token is written somewhere and use that if it's still active
// For now, we're going to request a new token every time we run the script
$authUrl = $client->createAuthUrl();

`open '$authUrl'`;
echo "\nPlease enter the auth code:\n";
$authCode = trim(fgets(STDIN));

$accessToken = $client->authenticate($authCode);
// @todo write token somewhere for future use

$youtube = new Google_Service_YouTube($client);

/**
 * @link http://gist.github.com/385876
 */
function csv_to_array($filename='', $delimiter=',') {

	ini_set('auto_detect_line_endings',TRUE);
	if(!file_exists($filename) || !is_readable($filename))
		return FALSE;

	$header = NULL;
	$data = array();
	if (($handle = fopen($filename, 'r')) !== FALSE) {
		while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
			if (!$header) {
				$header = $row;
			} else {
				if (count($header) > count($row)) {
					$difference = count($header) - count($row);
					for ($i = 1; $i <= $difference; $i++) {
						$row[count($row) + 1] = $delimiter;
					}
				}
				$data[] = array_combine($header, $row);
			}
		}
		fclose($handle);
	}
	return $data;
}

$csv_array = csv_to_array('videos.csv');

/**
 * Step 2: Begin looping through array
 */
foreach($csv_array as $videoInfo) {
	$videoPath = $videoDir . '/' . $videoInfo['filename'];
	if(empty($videoInfo['youtube_url']) && file_exists($videoPath)) {
		try {
			$startTime = time();
			// Create a snippet with title, description, tags and category ID
			// Create an asset resource and set its snippet metadata and type.
			print "Uploading {$videoInfo['title']}\n";
			$snippet = new Google_Service_YouTube_VideoSnippet();
			$snippet->setTitle($videoInfo['title']);
			// $snippet->setDescription($videoInfo['description']);
			$snippet->setCategoryId("28"); // @todo make this selectable in some way; 28 = Science & Technology

			// Set the video's status to "unlisted".
			$status = new Google_Service_YouTube_VideoStatus();
			$status->privacyStatus = "unlisted";

			// Associate the snippet and status objects with a new video resource.
			$video = new Google_Service_YouTube_Video();
			$video->setSnippet($snippet);
			$video->setStatus($status);

			// Specify the size of each chunk of data, in bytes. Set a higher value for
			// reliable connection as fewer chunks lead to faster uploads. Set a lower
			// value for better recovery on less reliable connections.
			$chunkSizeBytes = 1 * 1024 * 1024;

			// Setting the defer flag to true tells the client to return a request which can be called
			// with ->execute(); instead of making the API call immediately.
			$client->setDefer(true);

			// Create a request for the API's videos.insert method to create and upload the video.
			$insertRequest = $youtube->videos->insert("status,snippet", $video);

			// Create a MediaFileUpload object for resumable uploads.
			$media = new Google_Http_MediaFileUpload(
				$client,
				$insertRequest,
				'video/*',
				null,
				true,
				$chunkSizeBytes
				);
			$filesize = filesize($videoPath);
			$media->setFileSize(filesize($videoPath));

			// Read the media file and upload it chunk by chunk.
			$status = false;
			$handle = fopen($videoPath, "rb");
			while (!$status && !feof($handle)) {
				$chunk = fread($handle, $chunkSizeBytes);
				$status = $media->nextChunk($chunk); // @todo deal with exception
				print($media->getProgress() . "\n");
			}

			fclose($handle);

			// If you want to make other calls after the file upload, set setDefer back to false
			$client->setDefer(false);
			print $videoInfo['title'] . " uploaded\n";
			print "https://www.youtube.com/watch?v=" . $status->id . "\n";
			$logData = $videoInfo['filename'] . ',' . $startTime . ',' . $status->status->uploadStatus . ',' . $status->id . ',' . time() . "\n";
		} catch(Google_Exception $e) {
			$exceptionMsg = "Google service Exception: " . $e->getCode() . "; message: "	.$e->getMessage();
			print($exceptionMsg);
			$logData = $videoInfo['filename'] . ',' . $startTime . ',' . $exceptionMsg . ',' . 'NA' . ',' . time() . "\n";
			exit(print_r($media));
		}
		file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
	}
}


session_unset();