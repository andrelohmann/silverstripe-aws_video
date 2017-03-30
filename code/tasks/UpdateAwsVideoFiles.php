<?php
/**
 * update information of all processed aws video files
 *
 * @package framework
 * @subpackage filesystem
 */
class UpdateAwsVideoFiles extends BuildTask {

	protected $title = 'Update AWS Video Files';

	protected $description = 'Update information of all processed AwsVideoFile Objects. !!! This will be a time intensive task !!!';

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
		$updatedFiles = 0;
		$AwsVideos = AwsVideoFile::get()->filter(array('AwsProcessingStatus' => 'finished'))->sort('ID');

		foreach($AwsVideos as $vid){
			
			$updatedFiles++;
			
			$vid->updateAwsData();
			
			sleep(5);
		}

		echo "$updatedFiles files have been updated.";
	}

}
