<?php

class YouTubeVideo {

	/**
	 * Google client
	 */
	var $client;

	/**
	 * YouTube client
	 */
	var $youtube;

	var $videoPath;
	
	var $youtube_url = false;
	
	var $logMsg;
	
	var $chunkSizeBytes;
	
	var $videoInfo;
	
	var $media;

	/**
	 * __contruct function.
	 *
	 * @access public
	 * @param array $videoArgs
	 * @param obj $client Google Client
	 * @param obj $youtube YouTube Client
	 */
	public function __construct($videoArgs, $client) {
		$this->client = $client;
		$this->youtube = new Google_Service_YouTube($this->client);
		$this->videoPath = getenv('videodir') . '/' . $videoArgs['filename'];
		
		$snippet = new Google_Service_YouTube_VideoSnippet();
		if(isset($videoArgs['title'])) {
			$snippet->setTitle($videoArgs['title']);
		}
		if(isset($videoArgs['description'])) {
		// $snippet->setDescription($videoInfo['description']);
		}
		// if(isset($videoArgs['categoryID'])) {
			$snippet->setCategoryId("28"); // @todo make this selectable in some way; 28 = Science & Technology
		// }
		$status = new Google_Service_YouTube_VideoStatus();
		if(isset($videoArgs['privacyStatus'])) {
			$status->privacyStatus = $videoArgs['privacyStatus'];
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
			$this->client,
			$insertRequest,
			'video/*',
			null,
			true,
			$this->chunkSizeBytes
			);
		$this->filesize = filesize($this->videoPath);

		if(isset($videoArgs['youtube_url'])) {
			$this->youtube_url = $videoArgs['youtube_url'];
		}
		$this->videoInfo = $videoArgs;
	}
	
	protected function setChuckSizeBytes() {
		// @todo How can we set the chuckSize more efficiently?
		// Is the optimum number calculable?
		return 1 * 1024 * 1024;
	}

	public function upload() {
		if(!file_exists($this->videoPath)) {
			$this->logMsg = $this->videoInfo['entry_id'] . ',' . '"' . $this->videoInfo['title'] . '"' . ',' . $this->videoInfo['filename'] . ',' . "File does not exist\n";
			return;
		}
		if(!empty($this->youtube_url)) {
			$this->logMsg = $this->videoInfo['entry_id'] . ',' . '"' . $this->videoInfo['title'] . '"' . ',' . $this->videoInfo['filename'] . ',' . $this->videoInfo['youtube_url'] . "\n";
			return;
		}
			$startTime = time();
			// Create a snippet with title, description, tags and category ID
			// Create an asset resource and set its snippet metadata and type.
			print "Uploading " . $this->videoInfo['title'] . "\n";
			$this->media->setFileSize($this->filesize);

			// Read the media file and upload it chunk by chunk.
			$status = false;
			$handle = fopen($this->videoPath, "rb");
			while (!$status && !feof($handle)) {
				try {
					$chunk = fread($handle, $this->chunkSizeBytes);
					$status = $this->media->nextChunk($chunk); // @todo deal with exception
					print((($this->media->getProgress()/$this->filesize) * 100) . "%\n");
				} catch(Google_Exception $e) {
					$exceptionMsg = "Google Exception: " . $e->getCode() . "; message: "	. $e->getMessage() . "\n";
					print($exceptionMsg);
					$this->logMsg = $this->videoInfo['entry_id'] . ',' . '"' . $this->videoInfo['title'] . '"' . ',' . $this->videoInfo['filename'] . ',' . "File does not exist\n";
					file_put_contents('completed.csv', $this->logMsg(), FILE_APPEND | LOCK_EX);
					exit();
				}
			}

			fclose($handle);

			// If you want to make other calls after the file upload, set setDefer back to false
			print $videoInfo['title'] . " uploaded\n";
			print "https://www.youtube.com/watch?v=" . $status->id . "\n";
			$this->logMsg = $this->videoInfo['entry_id'] . ',' . '"' . $this->videoInfo['title'] . '"' . ',' . $this->videoInfo['filename'] . ',' . "https://www.youtube.com/watch?v={$status->id}\n";
	}
	
	public function logMsg() {
		return $this->logMsg;
	}
}