<?php

if(defined('AWS_KEY') && defined('AWS_SECRET') && defined('AWS_REGION') && defined('AWS_INPUT_BUCKET') && defined('AWS_OUTPUT_BUCKET') && defined('AWS_PIPELINE_ID') && defined('AWS_CLOUDFRONT_DOMAIN')){

    Config::inst()->update('AwsVideoFile', 'key', AWS_KEY);
    Config::inst()->update('AwsVideoFile', 'secret', AWS_SECRET);
    Config::inst()->update('AwsVideoFile', 'region', AWS_REGION);
    Config::inst()->update('AwsVideoFile', 'input_bucket', AWS_INPUT_BUCKET);
    Config::inst()->update('AwsVideoFile', 'output_bucket', AWS_OUTPUT_BUCKET);
    Config::inst()->update('AwsVideoFile', 'pipeline_id', AWS_PIPELINE_ID);
    Config::inst()->update('AwsVideoFile', 'cloudfront_domain', AWS_CLOUDFRONT_DOMAIN);

}else{
	die('Missing AWS Credentials on AwsVideoFile');
}
