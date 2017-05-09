<?php
/**
 * Process all unprocessed/new AwsVideoFiles one by one
 *
 * @package framework
 * @subpackage filesystem
 */
class MigrateAwsVideoFiles extends BuildTask {

	protected $title = 'Process all unprocessed/new AwsVideoFiles one by one';

	protected $description = 'Fetches all "unprocessed" AwsVideoFiles and puts them into postprocessing (aws upload and transcoding) one after another (using a page refresh)';

	/**
	 * Check that the user has appropriate permissions to execute this task
	 */
	public function init() {
		if(!Director::is_cli() && !Director::isDev() && !Permission::check('ADMIN')) {
			return Security::permissionFailure();
		}

		parent::init();
	}

	/**
	 * Clear out the image manipulation cache
	 * @param SS_HTTPRequest $request
	 */
	public function run($request) {
		$processingFiles = 0;
		$processedFiles = 0;
		$AwsVideos = AwsVideoFile::get()->filter(array('AwsProcessingStatus' => array('unprocessed')))->sort('ID');

		$count = ($AwsVideos->count() - 1);

		if($AwsVideos->first()->IsProcessed()){
				sleep(1);
		}

		if($count > 0) $refresh = "<meta http-equiv=\"refresh\" content=\"0; URL=/dev/tasks/MigrateAwsVideoFiles\">";
		else $refresh = "";

		echo <<<EOL
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
		{$refresh}
    <title>MigrateAwsVideoFiles</title>

  </head>
  <body>
    <h1>MigrateAwsVideoFiles</h1>
		<a href="/dev/tasks">Back</a>
		<p>File processed. {$count} Files left.</p>
  </body>
</html>
EOL;
	}

}
