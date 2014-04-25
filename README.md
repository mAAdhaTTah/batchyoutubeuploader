# Batch YouTube Uploader

## Requirements:

* Composer

## How to Use

1. `git clone` the repo
2. Run `composer install`
3. Copy the `env.example` to `.env` and fill out the required information
	* Get your client ID and secret from the [Google Developer Console][1]
4. Fill out videos.csv with the information required for each file
	* The tags should be comma-seperated
5. Place all the video files you want to upload in the `videos` folder.
6. Run `php batchyoutubeuploader.php` and login

## Bugs

Submit your bugs on [GitHub][2]
	
  [1]: https://console.developers.google.com
  [2]: https://github.com/mAAdhaTTah/batchyoutubeuploader
