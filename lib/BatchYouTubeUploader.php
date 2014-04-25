<?php

class Batch_YouTube_Uploader {

	/**
	 * Name of csv file
	 */
	var $csv;

	/**
	 * The directory the videos are stored in
	 */
	var $videoDir;
	
	var $handle;

	/**
	 * Google client
	 */
	protected $client;

	/**
	 * YouTube scopes for authorization
	 */
	protected $scopes = array(
		'https://www.googleapis.com/auth/youtube',
		'https://www.googleapis.com/auth/youtube.upload',
		'https://www.googleapis.com/auth/youtubepartner'
		);

	/**
	 * Current video object
	 */
	protected $video;

	/**
	 * Current Exception object
	 */
	protected $error;

	/**
	 * number of 5xx errors experienced
	 */
	protected $n = 0;

	public function __construct($csv) {
		$this->csv = $csv;
		$this->videoDir = getenv('videodir');
	}

	/**
	 * Begin batch uploading process
	 *
	 * @link http://gist.github.com/385876
	 */
	public function batchUpload() {
		// Login Google client and get access to YouTube
		$this->login();

		ini_set('auto_detect_line_endings',TRUE);
		// Check if we can actually work on the file
		if(!file_exists($this->csv) || !is_readable($this->csv))
			throw new Exception("Videos.csv doesn't exist or can't be found.");

		$header = NULL;
		$data = array();
		if (($this->handle = fopen($this->csv, 'r')) !== FALSE) {
			while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
				if (!$header) {
					// Set first row as header
					$header = $row;
					print("Begin uploading videos...\n");
				} else {
					if (count($header) > count($row)) {
						// Add extra values to match the header
						$difference = count($header) - count($row);
						for ($i = 1; $i <= $difference; $i++) {
							$row[count($row) + 1] = ',';
						}
					}
					// Pass final assoc array for processing
					$this->processVideo(array_combine($header, $row));
					$this->writeResults();
				}
			}
			// Finish up
			fclose($this->handle);
		}
	}

	/**
	 * Login and authenticate with Google
	 *
	 * @access public
	 */
	public function login() {
		// Set up Google client
		$this->client = new Google_Client();
		$this->client->setClientId(getenv('oauth2_client_id'));
		$this->client->setClientSecret(getenv('oauth2_client_secret'));
		$this->client->setRedirectUri(getenv('oauth2_redirect_uri'));
		$this->client->setScopes($this->scopes);
		$this->client->setAccessType('offline');
		// @todo need to check if token is written somewhere and use that if it's still active
		// For now, we're going to request a new token every time we run the script
		$authUrl = $this->client->createAuthUrl();

		// Authorize Google client
		`open '$authUrl'`;
		echo "\nPlease enter the auth code:\n";
		$authCode = trim(fgets(STDIN));

		// Save the token into to the session
		$_SESSION['token'] = json_decode($this->client->authenticate($authCode), true);
		// @todo write token somewhere so we can reuse it
		// we don't have to log in every time we run the script
	}

	/**
	 * Proces video, based on the array of info pulled from csv
	 *
	 * @access public
	 * @param array $args
	 */
	public function processVideo(array $args) {
		// Setting the defer flag to true tells the client to return a request which can be called
		// with ->execute(); instead of making the API call immediately.
		$this->client->setDefer(true);
		$this->video = new YouTubeVideo($args, $this->client);

		// Check if video file exists or if it's been uploaded already
		if ($this->video->checkStatus()) {
			print "Uploading " . $this->video->getTitle() . "\n";

			// Read the media file and upload it chunk by chunk.
			$this->video->setUp();
			$this->upload();
			$this->video->finish();
		}

		// If you want to make other calls after the file upload, set setDefer back to false
		$this->client->setDefer(false);

		// For now, we're refreshing the token after every upload. YouTube's API
		// currently has a bug, where uploads fail if the token expires in the
		// middle of the upload. It should continue until the upload finishes, and what
		// we should do is check and if necesary, refresh the token before each
		// upload. We can't do it this way because the video that's getting uploaded
		// at the 1 hour mark will fail. When this bug gets fixed, we should put this
		// at the beginning of processVideo():
		// if($this->client->isAccessTokenExpired() {
		$this->client->refreshToken($_SESSION['token']['refresh_token']);
		// }
	}
	
	protected function writeResults() {
		$results = array($this->video->getFilename(),
		                 $this->video->getTitle(),
		                 $this->video->getDescription(),
		                 $this->video->getCategoryID(),
		                 $this->video->getTags(),
		                 $this->video->getPrivacyStatus(),
		                 $this->video->getUrl(),
		                 $this->video->getUploadStatus());
		fputcsv($this->handle, $results);
	}

	/**
	 * Upload the video.
	 *
	 * @access protected
	 */
	protected function upload() {
		while (!$this->video->status && !feof($this->video->handle)) {
			try {
				$this->video->uploadChunk(true);
			} catch(Google_Exception $error) {
				$this->error = $error;
				$this->handleUploadError();
			} catch(Google_IO_Exception $error) {
				$this->error = $error;
				$this->handleIOError();
			}
		}
	}

	/**
	 * Basically a controller for responding to Exceptions from the Google API
	 *
	 * @access protected
	 */
	protected function handleUploadError() {
		switch($this->error->getCode()) {
			case 410:
				$this->handle410Error();
				break;
			case 500:
			case 501:
			case 502:
			case 503:
				$this->handle5xxError();
				break;
			default:
				$this->errorOut();
				break;
		}
	}

	/**
	 * Handles 410 errors
	 *
	 * @access protected
	 * @todo can we do anything to handle this better?
	 */
	protected function handle410Error() {
		print "A problem has occurred on YouTube's end.\n";
		print "Please submit your issue and relevant logs to https://github.com/mAAdhaTTah/batchyoutubeuploader/issues\n";
		print "Your video will still be present in your YouTube account, with the upload marked as 'failed'.\n";
		print "Delete it and restart the upload, and everything should be fine.\n";
		exit;
	}

	/**
	 * Handles all 5xx errors
	 *
	 * @access protected
	 */
	protected function handle5xxError() {
		if($this->n < 5) {
			print "\nGoogle Exception:\n";
			print $this->error->getCode();
			print "\nMessage:\n";
			print $this->error->getMessage();
			print "\nError #" . $this->n+1 . "\n";
			$sleepTime = (1 << $this->n) * 1000 + rand(0, 1000);
			print "Sleeping for " . $sleepTime . "s\n";
			usleep($sleepTime);
			$this->n++;
			try {
				print "Retrying...";
				$this->video->uploadChunk(false);
			} catch(Google_Exception $error) {
				$this->error = $error;
				$this->handleUploadError();
			} catch(Google_IO_Exception $error) {
				$this->error = $error;
				$this->handleIOError();
			}
		} else {
			$this->errorOut();;
		}
	}

	/**
	 * Handles IO errors
	 *
	 * @access protected
	 * @todo can we do anything to handle this better?
	 */
	protected function handleIOError() {
			print($this->standardErrorMsg());
			exit(print_r($this->error));
	}

	/**
	 * Displays the last message before the script dies
	 *
	 * @access protected
	 */
	protected function errorOut() {
			print($this->standardErrorMsg());
			exit();
	}

	/**
	 * Our standard error message before exiting
	 *
	 * @access protected
	 * @return void
	 */
	protected function standardErrorMsg() {
			$errorMsg = "\nGoogle Exception:\n";
			$errorMsg .= $this->error->getCode();
			$errorMsg .= "\nMessage:\n";
			$errorMsg	.= $this->error->getMessage();
			$errorMsg .= "\nStack Trace:\n";
			$errorMsg .= $this->error->getTraceAsString();
			return $errorMsg;
	}
}