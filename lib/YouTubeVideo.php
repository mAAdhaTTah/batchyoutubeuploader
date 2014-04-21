<?php

class YouTubeVideo {
	/**
	 * YouTube client
	 */
	var $youtube;

	var $path;
	
	var $youtube_url = false;
	
	var $logMsg;
	
	var $chunkSizeBytes;
	
	var $info;
	
	var $media;
	
	var $status = false;
	
	var $handle;
	
	var $chunk;
	
	var $progressBar;

	/**
	 * __contruct function.
	 *
	 * @access public
	 * @param array $args
	 * @param obj $client Google Client
	 * @param obj $youtube YouTube Client
	 */
	public function __construct($args, $client) {
		$this->youtube = new Google_Service_YouTube($client);
		$this->path = getenv('videodir') . '/' . $args['filename'];
		
		$snippet = new Google_Service_YouTube_VideoSnippet();
		if(isset($args['title'])) {
			$snippet->setTitle($args['title']);
		}
		if(isset($args['description'])) {
		// $snippet->setDescription($videoArg['description']);
		}
		// if(isset($args['categoryID'])) {
			$snippet->setCategoryId("28"); // @todo make this selectable in some way; 28 = Science & Technology
		// }
		$status = new Google_Service_YouTube_VideoStatus();
		if(isset($args['privacyStatus'])) {
			$status->privacyStatus = $args['privacyStatus'];
		} else {
			$status->privacyStatus = "unlisted";
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
		$this->filesize = filesize($this->path);
		$this->media->setFileSize($this->filesize);

		if(isset($args['youtube_url'])) {
			$this->youtube_url = $args['youtube_url'];
		}
		$this->info = $args;
	}
	
	protected function setChuckSizeBytes() {
		// @todo How can we set the chuckSize more efficiently?
		// Is the optimum number calculable?
		return 1 * 1024 * 1024;
	}
	
	public function uploadChunk() {
		$this->chunk = fread($this->handle, $this->chunkSizeBytes);
		$this->status = $this->media->nextChunk($this->chunk); // @todo deal with exception
	}
	
	public function setUpProgressBar() {
		$this->progressBar = new \ProgressBar\Manager(0, $this->filesize);
	}
	
	public function updateProgressBar() {
		$this->progressBar->update($this->media->getProgress());
		sleep(1);
	}
}