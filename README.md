# Silverstripe AWS Video File - (c) Andre Lohmann 2017

## Maintainer Contact
  * Andre Lohmann (Nickname: andrelohmann)
    <lohmann dot andre at googlemail dot com>

## Requirements

Silverstripe 3.5.x


## Overview
this module offers an extended VideoFile Object with automatically upload and transcoding functionality to your aws elastic transcoding and s3 account.
the module extends andrelohmann-silverstripe/mediafiles

you need to create an account on https://aws.amazon.com/de/developers/access-keys/ and setup a groups with AmazonS3FullAccess and AmazonElasticTranscoderFullAccess permissions

## Usage

Add AWS Credentials to your _ss_environment.php
```PHP
define('AWS_KEY', 'YOUR_ACCESS_KEY_ID');
define('AWS_SECRET', 'YOUR_ACCESS_KEY_SECRET');
define('AWS_REGION', 'YOUR_AWS_REGION');
define('AWS_INPUT_BUCKET', 'YOUR_AWS_INPUT_BUCKET');
define('AWS_OUTPUT_BUCKET', 'YOUR_AWS_OUTPUT_BUCKET');
define('AWS_PIPELINE_ID', 'YOUR_AWS_PIPELINE_ID');
define('AWS_CLOUDFRONT_DOMAIN', 'YOUR_AWS_CLOUDFRONT_DOMAIN');
```
