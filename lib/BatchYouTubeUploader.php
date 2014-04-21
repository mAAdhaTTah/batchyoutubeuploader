<?php

class Batch_YouTube_Uploader {

	/**
	 * Array of arrays
	 * Generated by .csv file
	 */
	var $csvArray = array();

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
		$this->csvArray = $this->csvToArray($csv);

		$this->client = new Google_Client();
		$this->client->setClientId(getenv('oauth2_client_id'));
		$this->client->setClientSecret(getenv('oauth2_client_secret'));
		$this->client->setRedirectUri(getenv('oauth2_redirect_uri'));
		$this->client->setScopes($this->scopes);

		$this->videoDir = getenv('videodir');
	}

	/**
	 * Converts .csv file to an array of arrays
	 *
	 * @link http://gist.github.com/385876
	 * @return csvArray (array of arrays)
	 */
	protected function csvToArray($filename='', $delimiter=',') {
		ini_set('auto_detect_line_endings',TRUE);
		if(!file_exists($filename) || !is_readable($filename))
			return FALSE; // @todo maybe throw Exception here instead?

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


	/**
	 * Login and authenticate with Google
	 *
	 * @access public
	 * @return void
	 */
	public function login() {
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

	public function process() {
		foreach($this->csvArray as $videoInfo) {
			// Setting the defer flag to true tells the client to return a request which can be called
			// with ->execute(); instead of making the API call immediately.
			$this->client->setDefer(true);
			print("Begin uploading videos...\n");
			$video = new YouTubeVideo($videoInfo, $this->client);
			$video->upload();
			$this->client->setDefer(false);
			file_put_contents($this->logFile, $video->logMsg(), FILE_APPEND | LOCK_EX);
		}
	}
}