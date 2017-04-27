<?php
/**
 * Finish Processing Files and refetch all necessary information
 *
 * @package framework
 * @subpackage filesystem
 */
class FinishProcessingAwsVideoFiles extends BuildTask {

	protected $title = 'Finish processing aws video files';

	protected $description = 'Videofiles are set to "processing", while aws is doing the postprocessing. This task will do a manual fetch of the processed information.';

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
		$AwsVideos = AwsVideoFile::get()->filter(array('AwsProcessingStatus' => array('unprocessed', 'processing', 'updating')))->sort('ID');

		foreach($AwsVideos as $vid){
			
			$processingFiles++;
			
			if($vid->IsProcessed()) $processedFiles++;
			
			sleep(5);
		}

		echo "$processedFiles of $processingFiles processing files are now processed.";
	}

}
