# repository_s3bucketplus

![built status](https://travis-ci.com/durzo/moodle-repository_s3bucketplus.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/durzo/moodle-repository_s3bucketplus/badge.svg?branch=master)](https://coveralls.io/github/durzo/moodle-repository_s3bucketplus?branch=master)

Instead of giving all users access to your complete S3 account, this plugin makes it
possible to give teachers and managers access to a specific S3 folder (bucket).

Multiple instances are supported, you only have to create a IAM user who has read access
to your S3 root folder, but read and write permissions to your S3 bucket.    

