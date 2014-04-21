<?php

class Batch_YouTube_Uploader {

	var $csv;

	/**
	 * The directory the videos are stored in
	 */
	var $videoDir;

	/**
	 * Logfile output
	 * Currently, we're writing to a new file
	 * @todo write back to input file, use this as actual log
	 */
	var $logFile = 'completed.csv';

	/**
	 * Google client
	 */
	var $client;

	/**
	 * YouTube scopes for authorization
	 */
	var $scopes = array(
		'https://www.googleapis.com/auth/youtube',
		'https://www.googleapis.com/auth/youtube.upload',
		'https://www.googleapis.com/auth/youtubepartner'
		);

	public function __construct($csv) {
		$this->csv = $csv;
		$this->videoDir = getenv('videodir');
	}

	/**
	 * Converts .csv file to an array of arrays
	 *
	 * @link http://gist.github.com/385876
	 * @return csvArray (array of arrays)
	 */
	public function batchUpload() {
		$this->login();
		ini_set('auto_detect_line_endings',TRUE);
		if(!file_exists($this->csv) || !is_readable($this->csv))
			return FALSE; // @todo maybe throw Exception here instead?

		$header = NULL;
		$data = array();
		if (($handle = fopen($this->csv, 'r')) !== FALSE) {
			while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
				if (!$header) {
					$header = $row;
					print("Begin uploading videos...\n");
				} else {
					if (count($header) > count($row)) {
						$difference = count($header) - count($row);
						for ($i = 1; $i <= $difference; $i++) {
							$row[count($row) + 1] = ',';
						}
					}
					$this->processVideo(array_combine($header, $row));
				}
			}
			fclose($handle);
		}
	}

	public function processVideo($videoInfo) {
		// Setting the defer flag to true tells the client to return a request which can be called
		// with ->execute(); instead of making the API call immediately.
		$this->client->setDefer(true);
		$video = new YouTubeVideo($videoInfo, $this->client);
		$video = $this->upload($video);
		$this->client->setDefer(false);
		file_put_contents($this->logFile, $video->logMsg, FILE_APPEND | LOCK_EX);
	}

	public function upload($video) {
		if(!file_exists($video->path)) {
			$video->logMsg = $video->info['entry_id'] . ',' . '"' . $video->info['title'] . '"' . ',' . $video->info['filename'] . ',' . "File not found\n";
			return $video;
		}
		if(!empty($video->youtube_url)) {
			$video->logMsg = $video->info['entry_id'] . ',' . '"' . $video->info['title'] . '"' . ',' . $video->info['filename'] . ',' . $video->info['youtube_url'] . "\n";
			return $video;
		}
		$startTime = time();
		// Create a snippet with title, description, tags and category ID
		// Create an asset resource and set its snippet metadata and type.
		print "Uploading " . $video->info['title'] . "\n";

		// Read the media file and upload it chunk by chunk.
		$video->handle = fopen($video->path, "rb");
		$video->setUpProgressBar();
		while (!$video->status && !feof($video->handle)) {
			try {
				$video->uploadChunk();
				$video->updateProgressBar();
			} catch(Google_Exception $e) {
				switch($e->getCode()) {
					//case 503:
						//$this->login();
						//$video->status = $video->media->nextChunk($video->chunk);
						//break;
					default:
						$exceptionMsg = "Google Exception: " . $e->getCode() . "; message: "	. $e->getMessage() . "\n";
						print($exceptionMsg);
						$video->logMsg = $video->info['entry_id'] . ',' . '"' . $video->info['title'] . '"' . ',' . $video->info['filename'] . ',' . "Upload failed\n";
						file_put_contents($this->logFile, $video->logMsg, FILE_APPEND | LOCK_EX);
						exit();
				}
			}
		}

		fclose($video->handle);

		// If you want to make other calls after the file upload, set setDefer back to false
		print $video->info['title'] . " uploaded\n";
		print "https://www.youtube.com/watch?v=" . $status->id . "\n";
		$video->logMsg = $video->info['entry_id'] . ',' . '"' . $video->info['title'] . '"' . ',' . $video->info['filename'] . ',' . "https://www.youtube.com/watch?v={$status->id}\n";
		return $video;
	}

	/**
	 * Login and authenticate with Google
	 *
	 * @access public
	 * @return void
	 */
	public function login() {
		$this->client = new Google_Client();
		$this->client->setClientId(getenv('oauth2_client_id'));
		$this->client->setClientSecret(getenv('oauth2_client_secret'));
		$this->client->setRedirectUri(getenv('oauth2_redirect_uri'));
		$this->client->setScopes($this->scopes);
		// @todo need to check if token is written somewhere and use that if it's still active
		// For now, we're going to request a new token every time we run the script
		$authUrl = $this->client->createAuthUrl();

		`open '$authUrl'`;
		echo "\nPlease enter the auth code:\n";
		$authCode = trim(fgets(STDIN));

		$accessToken = $this->client->authenticate($authCode);
		// @todo write token somewhere for future use?
		// @todo get access token that doesn't expire in only an hour
	}
}