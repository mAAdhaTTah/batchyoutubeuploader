<?php

class YouTubeVideo {
	/**
	 * YouTube client
	 */
	protected $youtube;

	/**
	 * Video attributes (from csv)
	 */
	protected $filename;
	protected $title;
	protected $description;
	protected $categoryID;
	protected $tags;
	protected $privacyStatus;
	protected $url;
	protected $uploadStatus;

	/**
	 * Chunk size per upload
	 */
	protected $chunkSizeBytes;

	/**
	 * Size of the file to upload
	 */
	protected $filesize;

	/**
	 * Path of the file to upload
	 */
	protected $path;

	/**
	 * Google_Http_MediaFileUpload object
	 */
	protected $media;

	/**
	 * Result of previous upload
	 */
	var $status;

	/**
	 * Handle for file pointer
	 */
	var $handle;

	/**
	 * Next file chunk to be uploaded
	 */
	protected $chunk;

	/**
	 * ProgressBar Manger object
	 */
	protected $progressBar;

	/**
	 * Construct the video object
	 *
	 * @access public
	 * @param array $args
	 * @param Google_Client $client
	 */
	public function __construct(array $args, Google_Client $client) {
		foreach($args as $key => $value) {
			$this->$key = $value;
		}

		$this->path = getenv('videodir') . '/' . $this->filename;
		$this->filesize = filesize($this->path);

		$this->setUpClient($client);
		
	}

	/**
	 * Set up the upload client
	 *
	 * @access protected
	 */
	protected function setUpClient(Google_Client $client) {
		$this->youtube = new Google_Service_YouTube($client);
		$snippet = new Google_Service_YouTube_VideoSnippet();
		if(isset($this->title)) {
			$snippet->setTitle($this->title);
		}
		if(isset($this->description)) {
			$snippet->setDescription($this->description);
		}
		if(isset($this->categoryID)) {
			$snippet->setCategoryId($this->categoryID);
		}
		if(isset($this->tags)) {
			$tags = array_map('trim', explode(',', $this->tags));
			$snippet->setTags($tags);
		}
		$status = new Google_Service_YouTube_VideoStatus();
		if(isset($this->privacyStatus)) {
			$status->privacyStatus = $this->privacyStatus;
		} else {
			$status->privacyStatus = "private";
		}
		// Associate the snippet and status objects with a new video resource.
		$video = new Google_Service_YouTube_Video();
		$video->setSnippet($snippet);
		$video->setStatus($status);

		// Specify the size of each chunk of data, in bytes. Set a higher value for
		// reliable connection as fewer chunks lead to faster uploads. Set a lower
		// value for better recovery on less reliable connections.
		$this->chunkSizeBytes = $this->setChuckSizeBytes();

		// Create a request for the API's videos.insert method to create and upload the video.
		$insertRequest = $this->youtube->videos->insert("status,snippet", $video);

		// Create a MediaFileUpload object for resumable uploads.
		$this->media = new Google_Http_MediaFileUpload(
			$client,
			$insertRequest,
			'video/*',
			null,
			true,
			$this->chunkSizeBytes
			);
		$this->media->setFileSize($this->filesize);
	}

	/**
	 * Set the size of the file chunk for each upload
	 * Currently static, but can be caluclated later
	 *
	 * @access protected
	 * @return int
	 */
	protected function setChuckSizeBytes() {
		// @todo How can we set the chuckSize more efficiently?
		// Is the optimum number calculable?
		return 1 * 1024 * 1024;
	}

	/**
	 * Return the filename.
	 * 
	 * @access protected
	 * @return string $filename
	 */
	protected function getFilename() {
		return $this->filename;
	}

	/**
	 * Return the title
	 *
	 * @access public
	 * @return string $title
	 */
	public function getTitle() {
		return $this->title;
	}
	
	/**
	 * Return the description
	 *
	 * @access public
	 * @return string $description
	 */
	public function getDescription() {
		return $this->description;
	}
	
	/**
	 * Return the categoryID
	 *
	 * @access public
	 * @return string $categoryID
	 */
	public function getCategoryID() {
		return $this->categoryID;
	}
	
	/**
	 * Return the tags
	 *
	 * @access public
	 * @return string $tags
	 */
	public function getTags() {
		return $this->tags;
	}
	
	/**
	 * Return the privacyStatus
	 *
	 * @access public
	 * @return string $privacyStatus
	 */
	public function getPrivacyStatus() {
		return $this->privacyStatus;
	}
	
	/**
	 * Return the url
	 *
	 * @access public
	 * @return string $url
	 */
	public function getUrl() {
		return $this->url;
	}
	
	/**
	 * Return the uploadStatus
	 *
	 * @access public
	 * @return string $uploadStatus
	 */
	public function getUploadStatus() {
		return $this->uploadStatus;
	}

	/**
	 * Check whether a file can + should be uploaded
	 *
	 * @access public
	 * @return bool
	 */
	public function checkStatus() {
		if(!file_exists($this->path)) {
			$this->uploadStatus = "File not found";
			return false;
		}
		if($this->uploadStatus == "uploaded") {
			return false;
		}
		return true;
	}

	/**
	 * Set up object before upload
	 * Open the file and set up the progress bar
	 *
	 * @access public
	 */
	public function setUp() {
		$this->handle = fopen($this->path, "rb");
		$this->progressBar = new \ProgressBar\Manager(0, $this->filesize);
	}

	/**
	 * Upload a file chunk
	 * Can be set to false to retry previous chunk
	 *
	 * @access public
	 * @param bool $nextChunk (default: true)
	 */
	public function uploadChunk($nextChunk = true) {
		if($nextChunk) {
			$this->chunk = fread($this->handle, $this->chunkSizeBytes);
		}
		$this->status = $this->media->nextChunk($this->chunk);
		$this->updateProgressBar();
	}

	/**
	 * Update the progress bar to the latest progress
	 *
	 * @access protected
	 */
	protected function updateProgressBar() {
		$this->progressBar->update($this->media->getProgress());
		sleep(1);
	}

	/**
	 * Clean up after a video has been uploaded.
	 *
	 * @access public
	 */
	public function success() {
		fclose($this->handle);
		$successMsg = "\n" . $this->getTitle() . " uploaded\n";
		$this->url = "https://www.youtube.com/watch?v={$this->status->id}";
		$this->uploadStatus = "uploaded";
		print $successMsg;
	}
	
	public function failure() {
		fclose($this->handle);
		$this->uploadStatus = "Upload failed";
	}
}