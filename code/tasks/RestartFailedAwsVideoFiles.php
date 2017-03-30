<?php
/**
 * Restart processing of all failed aws video files
 *
 * @package framework
 * @subpackage filesystem
 */
class RestartFailedAwsVideoFiles extends BuildTask {

	protected $title = 'Restart processing of all failed aws video files';

	protected $description = 'Restart processing of all failed AwsVideoFile objects';

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
		$failedFiles = 0;
		$Videos = AwsVideoFile::get()->filter(array('AwsProcessingStatus' => array('error', 'processingerror')))->sort('ID');

		foreach($Videos as $vid){
			
			$failedFiles++;
			
			if($vid->ProcessingStatus == 'error') $vid->ProcessingStatus = 'new';
			$vid->AwsProcessingStatus = 'unprocessed';
			$vid->write();
			
			$vid->onAfterLoad();
			
			sleep(5);
		}

		echo "$failedFiles failed AwsVideoFile objects have reinitiated the processing.";
	}

}
