<?php

use Aws\Common\Credentials\Credentials;
use Aws\S3\S3Client;
use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
use Aws\ElasticTranscoder\ElasticTranscoderClient;


class AwsVideoFile extends VideoFile {

    private static $aws_credentials = null;
    private static $aws_s3client = null;
    private static $aws_etclient = null;

    private static $key = null;
    private static $secret = null;
    private static $region = null;
    private static $input_bucket = null;
    private static $output_bucket = null;
    private static $cloudfront_domain = null;
    private static $pipeline_id = null;
    private static $preset_1080p = "1351620000001-000001"; // generic 1080p mp4 preset
    private static $preset_720p = "1351620000001-000010"; // generic 720p mp4 preset
    private static $preset_480p = "1351620000001-000020"; // generic 480p mp4 preset
    private static $api_request_time = 900;

    private static $db = [
        'AwsProcessingStatus' => "Enum(array('unprocessed','uploading','updating','processing','processingerror','error','finished'))", // uploading, processing, finished
        'AwsInputURI'   =>  'Varchar(255)',
        'AwsJobId'  =>  'Varchar(255)',
        'AwsOutputURI1080p' => 'Varchar(255)',
        'AwsOutputURI720p' => 'Varchar(255)',
        'AwsOutputURI480p' => 'Varchar(255)'
    ];

    private static $defaults = [
        'AwsProcessingStatus' => 'unprocessed'
    ];

    protected function creds(){
        if(!self::$aws_credentials) self::$aws_credentials = new Credentials(Config::inst()->get('AwsVideoFile', 'key'), Config::inst()->get('AwsVideoFile', 'secret'));

        return self::$aws_credentials;
    }

    protected function s3client(){
        // Instantiate the S3 client with your AWS credentials
        if(!self::$aws_s3client) self::$aws_s3client = S3Client::factory([
            'credentials' => self::creds(),
            'region' => Config::inst()->get('AwsVideoFile', 'region')
        ]);

        return self::$aws_s3client;
    }

    protected function etclient(){
        // Instantiate the S3 client with your AWS credentials
        if(!self::$aws_etclient) self::$aws_etclient = ElasticTranscoderClient::factory([
            'credentials' => self::creds(),
            'region' => Config::inst()->get('AwsVideoFile', 'region')
        ]);

        return self::$aws_etclient;
    }

    protected static function videoDomain(){
        return Config::inst()->get('AwsVideoFile', 'cloudfront_domain');
    }

    protected function jobStatus(){
        $etClient = self::etclient();

        $job = $etClient->readJob(array('Id' => $this->AwsJobId));
        $jobData = $job->get('Job');

        return $jobData['Status'];
    }

    protected function getLogFile(){
        if(!$this->log_file){
            $this->log_file = TEMP_FOLDER.'/AwsVideoFileProcessing-ID-'.$this->ID.'.log';
        }
        return $this->log_file;
    }

    public function process($LogFile = false, $runAfterProcess = true){

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

            // Instantiate the S3 client with your AWS credentials
            $s3Client = self::s3client();

            // upload the file to the input bucket
            $upload = $s3Client->upload(Config::inst()->get('AwsVideoFile', 'input_bucket'), this->ID.'/'.basename($this->getFullPath()), file_get_contents($this->getFullPath());

            $this->appendLog($LogFile, "Aws Video Upload Data returned", print_r($upload, true));

            if(isset($upload['Location'])){

                // Upload was successfull
                $this->appendLog($LogFile, "File uploaded to input bucket");

                $this->AwsInputURI = $upload['Location'];
                $this->write();

                // initiate transcoding jobs
                $etClient = self::etclient();

                // prevent Job errors by deleting existing files previously
                $s3Client->deleteObject(['Bucket' => Config::inst()->get('AwsVideoFile', 'output_bucket'), 'Key' => $this->ID.'/'.'1080p/'.basename($this->getFullPath())]);
                $s3Client->deleteObject(['Bucket' => Config::inst()->get('AwsVideoFile', 'output_bucket'), 'Key' => $this->ID.'/'.'720p/'.basename($this->getFullPath())]);
                $s3Client->deleteObject(['Bucket' => Config::inst()->get('AwsVideoFile', 'output_bucket'), 'Key' => $this->ID.'/'.'480p/'.basename($this->getFullPath())]);

                // start processing
                $job = $etClient->createJob([
                    'PipelineId' => Config::inst()->get('AwsVideoFile', 'pipeline_id'),
                    'OutputKeyPrefix' => $this->ID.'/',
                    'Input' => [
                        'Key' => $this->AwsInputURI,
                        'FrameRate' => 'auto',
                        'Resolution' => 'auto',
                        'AspectRatio' => 'auto',
                        'Interlaced' => 'auto',
                        'Container' => 'auto',
                    ],
                    'Outputs' => [
                        [
                            'Key' => '1080p/'.basename($this->getFullPath()),
                            'Rotate' => 'auto',
                            'PresetId' => Config::inst()->get('AwsVideoFile', 'preset_1080p')
                        ],
                        [
                            'Key' => '720p/'.basename($this->getFullPath()),
                            'Rotate' => 'auto',
                            'PresetId' => Config::inst()->get('AwsVideoFile', 'preset_720p')
                        ],
                        [
                            'Key' => '480p/'.basename($this->getFullPath()),
                            'Rotate' => 'auto',
                            'PresetId' => Config::inst()->get('AwsVideoFile', 'preset_480p')
                        ]
                    ]
                ]);

                // get the job data as array
                $jobData = $job->get('Job');

                $this->AwsJobId = $jobData['Id'];
                $this->write();

                return $this->finish();

            }else{

                $this->appendLog($LogFile, "Error on Upload", print_r($upload, true));

                $this->AwsProcessingStatus = 'unprocessed';
                $this->write();

                return false;
            }

        } catch(Exception $e) {

            $this->AwsProcessingStatus = 'error';
            $this->write();

            $this->appendLog($LogFile, "AwsUploadException:\n".$e->getMessage());

            return false;
        }
    }

    protected function finish(){

        switch($this->jobStatus()){
            case 'Submitted':
            case 'Progressing':
                $this->AwsProcessingStatus = 'processing';
                $this->write();

                return false;
            break;

            case 'Complete':
                // Delete origin from input bucket
                $s3Client->deleteObject(['Bucket' => Config::inst()->get('AwsVideoFile', 'input_bucket'), 'Key' => $this->AwsInputURI]);
                $this->AwsOutputURI1080p = $this->ID.'/'.'1080p/'.basename($this->getFullPath());
                $this->AwsOutputURI720p = $this->ID.'/'.'720p/'.basename($this->getFullPath());
                $this->AwsOutputURI480p = $this->ID.'/'.'480p/'.basename($this->getFullPath());
                $this->AwsProcessingStatus = 'finished';
                $this->write();

                return true;
            break;

            default:
                $this->AwsProcessingStatus = 'processingerror';
                $this->write();

                return false;
            break;
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

                        // update transcoding job info
                        $this->finish();
                    break;
                }

                $result = $this->AwsProcessingStatus;
                $cache->save($result, $this->ID);
            }

            return ($result == 'finished');
        }
    }

    public function getFullHDUrl() {
        if($this->AwsProcessingStatus == 'finished')
            return 'http://'.self::videoDomain().'/'.$this->AwsOutputURI1080p;
        else
            return false;
    }

    public function getHDUrl() {
        if($this->AwsProcessingStatus == 'finished')
            return 'http://'.self::videoDomain().'/'.$this->AwsOutputURI720p;
        else
            return false;
    }

    public function getSDUrl() {
        if($this->AwsProcessingStatus == 'finished')
            return 'http://'.self::videoDomain().'/'.$this->AwsOutputURI480p;
        else
            return false;
    }

    public function getFullHDUrlSecure() {
        if($this->AwsProcessingStatus == 'finished')
            return 'https://'.self::videoDomain().'/'.$this->AwsOutputURI1080p;
        else
            return false;
    }

    public function getHDUrlSecure() {
        if($this->AwsProcessingStatus == 'finished')
            return 'https://'.self::videoDomain().'/'.$this->AwsOutputURI720p;
        else
            return false;
    }

    public function getSDUrlSecure() {
        if($this->AwsProcessingStatus == 'finished')
            return 'https://'.self::videoDomain().'/'.$this->AwsOutputURI480p;
        else
            return false;
    }

    protected function onBeforeDelete() {
        parent::onBeforeDelete();

        // Delete origin from input bucket
        $s3Client->deleteObject(['Bucket' => Config::inst()->get('AwsVideoFile', 'input_bucket'), 'Key' => $this->AwsInputURI]);
        // delete encoded files from output bucket
        $s3Client->deleteObject(['Bucket' => Config::inst()->get('AwsVideoFile', 'output_bucket'), 'Key' => $this->AwsOutputURI1080p]);
        $s3Client->deleteObject(['Bucket' => Config::inst()->get('AwsVideoFile', 'output_bucket'), 'Key' => $this->AwsOutputURI720p]);
        $s3Client->deleteObject(['Bucket' => Config::inst()->get('AwsVideoFile', 'output_bucket'), 'Key' => $this->AwsOutputURI480p]);
    }

    protected function onAfterProcess() {
        parent::onAfterProcess();
    }

}
