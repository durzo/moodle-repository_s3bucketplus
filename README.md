# repository_s3bucketplus

![built status](https://travis-ci.com/durzo/moodle-repository_s3bucketplus.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/durzo/moodle-repository_s3bucketplus/badge.svg?branch=master)](https://coveralls.io/github/durzo/moodle-repository_s3bucketplus?branch=master)

Instead of giving all users access to your complete S3 account, this plugin makes it
possible to give teachers and managers access to a specific S3 folder (bucket).

Multiple instances are supported, you only have to create a IAM user who has read access
to your S3 root folder, but read and write permissions to your S3 bucket.    

**This plugin is based on repository_s3bucket with bug fixes and features that were not wanted by the original author.**

Current list of changes from the original plugin:

  * Allow use of IAM Roles by not specifying access/secret keys.

  * Rewrite most of get_listing() to better identify files & (pseudo) directories

  * Add chroot base path option to restrict to certain directories within the bucket.

  * Force files to be served as download attachments via the S3 Signed URL

  * Fixed supported return types - ability to make copy or alias (now fixed in original)

  * Changed repo icon to the official amazon S3 icon.