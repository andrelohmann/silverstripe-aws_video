<?php

class AwsVideoFile extends VideoFile {

    
        private static $key = null;
        private static $secret = null;
        private static $region = null;
	private static $input_bucket = null;
	private static $output_bucket = null;
	private static $pipeline_id = null;
        private static $preset_1080p = "1351620000001-000001"; // generic 1080p mp4 preset
        private static $preset_720p = "1351620000001-000010"; // generic 720p mp4 preset
        private static $preset_480p = "1351620000001-000020"; // generic 480p mp4 preset
	private static $api_request_time = 900;
	
	private static $db = array(
		'AwsProcessingStatus' => "Enum(array('unprocessed','uploading','updating','processing','processingerror','error','finished'))", // uploading, processing, finished
		'AwsURI'   =>  'Varchar(255)',
                'AwsLink'  =>  'Varchar(255)',
                'AwsID' => 'Varchar(255)',
                'AwsHLSUrl' => 'Varchar(255)',
                'AwsHLSUrlSecure' => 'Varchar(255)',
		'AwsFullHDUrl' => 'Varchar(255)', // 1080p
		'AwsFullHDUrlSecure' => 'Varchar(255)', // 1080p
		'AwsHDUrl' => 'Varchar(255)', // 720p
		'AwsHDUrlSecure' => 'Varchar(255)', // 480p
		'AwsSDUrl' => 'Varchar(255)', // 480p
		'AwsSDUrlSecure' => 'Varchar(255)'
	);
	
	private static $defaults = array(
		'AwsProcessingStatus' => 'unprocessed'
        );
	
	protected function getLogFile(){
		if(!$this->log_file){
			$this->log_file = TEMP_FOLDER.'/AwsVideoFileProcessing-ID-'.$this->ID.'.log';
		}
		return $this->log_file;
	}

        public function process($LogFile = false, $runAfterProcess = true) {
        
            if(!$LogFile) $LogFile = $this->getLogFile();

            switch($this->ProcessingStatus){
                    case 'new':
                            if(parent::process($LogFile, $runAfterProcess)){
                                    $this->awsProcess($LogFile, $runAfterProcess);
                            }else{
                                    // Something went wrong
                            }
                    break;

                    case 'finished':
                            $this->awsProcess($LogFile, $runAfterProcess);
                    break;

                    case 'processing':
                            // just do nothing
                    break;

                    case 'error':
                            // just do nothing
                    break;
            }
        }
		
	protected function awsProcess($LogFile, $runAfterProcess = true){
		
		$this->appendLog($LogFile, "awsProcess() started");
		
		switch($this->AwsProcessingStatus){
			case 'processingerror':
			case 'unprocessed':
				
				// upload the Video
				$this->awsUpload($LogFile);
				
				if($this->AwsProcessingStatus == 'finished' && $runAfterProcess) $this->onAfterProcess();
				
			break;
			
			case 'uploading':
				// just do nothing
			break;
		
			case 'processing':
				// just do nothing
			break;
		
			case 'updating':
				// just do nothing
			break;
		
			case 'error':
				// just do nothing
			break;
		
			case 'finished':
				// just do nothing
			break;
		}
		
	}
	
	protected function awsUpload($LogFile){
		
		$this->AwsProcessingStatus = 'uploading';
		$this->write();
		
		try {
			
			$this->appendLog($LogFile, "awsUpload() started");
			
			// Upload implementation here
		} catch(Exception $e) {
			$this->AwsProcessingStatus = 'error';
			$this->write();
			$this->appendLog($LogFile, "AwsUploadException:\n".$e->getMessage());
			return false;
		}
         
        }
	
	public function IsProcessed(){
		if($this->AwsProcessingStatus == 'finished'){
			return true;
		}else{
			$cache = SS_Cache::factory('AwsVideoFile_ApiRequest');
			SS_Cache::set_cache_lifetime('AwsVideoFile_ApiRequest', Config::inst()->get('AwsVideoFile', 'api_request_time')); // set the waiting time
			if(!($result = $cache->load($this->ID))){
				
				switch($this->AwsProcessingStatus){
					
					case 'unprocessed':
						$this->process();
					break;
				
					case 'updating':
                                        case 'processing':
						
						$this->appendLog($this->getLogFile(), 'IsProcessed - processing');
						
						// implement processing here
					break;
				}
				
				$result = $this->AwsProcessingStatus;
				$cache->save($result, $this->ID);
			}
			return ($result == 'finished');
		}
	}
	
	public function AwsURI() {
		if(!($this->AwsProcessingStatus == 'error' || $this->AwsProcessingStatus == 'unprocessed')){
			return $this->AwsURI;
		}else{
			return false;
		}
    }
    
    public function AwsLink () {
		if(!($this->AwsProcessingStatus == 'error' || $this->AwsProcessingStatus == 'unprocessed')){
			return $this->AwsLink;
		}else{
			return false;
		}
    }
    
    public function AwsID () {
        if(!($this->AwsProcessingStatus == 'error' || $this->AwsProcessingStatus == 'unprocessed')){
			return $this->AwsID;
		}else{
			return false;
		}
    }
    
    public function getFullHDUrl() {
        if($this->AwsProcessingStatus == 'finished'){
			if($this->AwsFullHDUrl) return $this->AwsFullHDUrl;
			else return $this->getHDUrl();
		}else{
			return false;
		}
    }
    
    public function getHDUrl() {
        if($this->AwsProcessingStatus == 'finished'){
			if($this->AwsHDUrl) return $this->AwsHDUrl;
			else return $this->getSDUrl();
		}else{
			return false;
		}
    }
    
    public function getSDUrl() {
        if($this->AwsProcessingStatus == 'finished'){
			if($this->AwsSDUrl)
				return $this->AwsSDUrl;
			else
				return false;
		}else{
			return false;
		}
    }
    
    public function getHLSUrl() {
        if($this->AwsProcessingStatus == 'finished')
			if($this->AwsHLSUrl)
				return $this->AwsHLSUrl;
			
		return false;
    }
    
    public function getFullHDUrlSecure() {
        if($this->AwsProcessingStatus == 'finished'){
			if($this->AwsFullHDUrlSecure) return $this->AwsFullHDUrlSecure;
			else return $this->getHDUrlSecure();
		}else{
			return false;
		}
    }
    
    public function getHDUrlSecure() {
        if($this->AwsProcessingStatus == 'finished'){
			if($this->AwsHDUrlSecure) return $this->AwsHDUrlSecure;
			else return $this->getSDUrlSecure();
		}else{
			return false;
		}
    }
    
    public function getSDUrlSecure() {
        if($this->AwsProcessingStatus == 'finished'){
			if($this->AwsSDUrlSecure)
				return $this->AwsSDUrlSecure;
			else
				return false;
		}else{
			return false;
		}
    }
    
    public function getHLSUrlSecure() {
        if($this->AwsProcessingStatus == 'finished')
			if($this->AwsHLSUrlSecure)
				return $this->AwsHLSUrlSecure;
			
		return false;
    }
     
    
    protected function onBeforeDelete() {
        parent::onBeforeDelete();
        
        // implement onBeforeDelete
    }
	
	protected function onAfterProcess() {
		parent::onAfterProcess();
	}
	
	public function updateAwsData(){
		if($this->AwsProcessingStatus == 'finished'){
			
			// Implement update data
			
			return true;
		}else{
			return false;
		}
	}

}
