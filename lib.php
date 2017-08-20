<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This plugin is used to access s3bucket files
 *
 * @package    repository_s3bucket
 * @copyright  2017 Renaat Debleu (www.eWallah.net) (based on work by Dongsheng Cai)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

/**
 * This is a repository class used to browse a Amazon S3 bucket.
 *
 * @package    repository_s3bucket
 * @copyright  2017 Renaat Debleu (www.eWallah.net) (based on work by Dongsheng Cai)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_s3bucket extends repository {

    private $_s3client;

    /**
     * Get S3 file list
     *
     * @param string $path this parameter can a folder name, or a identification of folder
     * @param string $page the page number of file list
     * @return array the list of files, including some meta infomation
     */
    public function get_listing($path = '.', $page = '') {
        global $OUTPUT;
        $s = $this->create_s3();
        $bucket = $this->get_option('bucket_name');
        $list = [];
        $list['list'] = [];
        $list['path'] = [['name' => $bucket, 'path' => $path]];
        $list['manage'] = false;
        $list['dynload'] = true;
        $list['nologin'] = true;
        $list['nosearch'] = true;
        $files = [];
        $folders = [];
        
        try {
            $results = $s->getPaginator('ListObjects', ['Bucket' => $bucket, 'Prefix' => $path]);
        } catch (S3Exception $e) {
            throw new moodle_exception(
                'errorwhilecommunicatingwith',
                'repository',
                '',
                $this->get_name(),
                $e->getMessage()
            );
        }
        
        if ($path === '') {
            $path = '.';
        } else {
            $path .= '/';
        }
        foreach ($results as $result) {
            foreach ($result['Contents'] as $object) {
                $pathinfo = pathinfo($object['Key']);
                if ($object['Size'] == 0) {
                    if ($pathinfo['dirname'] == $path) {
                        $folders[] = [
                            'title' => $pathinfo['basename'],
                            'children' => [],
                            'thumbnail' => $OUTPUT->image_url(file_folder_icon(90))->out(false),
                            'thumbnail_height' => 64,
                            'thumbnail_width' => 64,
                            'path' =>  $object['Key']];
                    }
                } else {
                    if ($pathinfo['dirname'] == $path or $pathinfo['dirname'] . '//' == $path) {
                        $files[] = [
                            'title' => $pathinfo['basename'],
                            'size' => $object['Size'],
                            'path' => $object['Key'],
                            'datemodified' =>  date_timestamp_get($object['LastModified']),
                            'thumbnail_height' => 64,
                            'thumbnail_width' => 64,
                            'source' =>  $object['Key'],
                            'thumbnail' => $OUTPUT->image_url(file_extension_icon($object['Key'], 90))->out(false)];
                    }
                }
            }
        }
        $list['list'] = array_merge($folders, $files);
        return $list;
    }

    /**
     * Download S3 files to moodle
     *
     * @param string $filepath
     * @param string $file The file path in moodle
     * @return array The local stored path
     */
    public function get_file($filepath, $file = '') {
        $path = $this->prepare_file($file);
        $s = $this->create_s3();
        $bucket = $this->get_option('bucket_name');
        try {
            $s->getObject(['Bucket' => $bucket, 'Key' => $filepath, 'SaveAs' => $path]);
        } catch (S3Exception $e) {
            throw new moodle_exception(
                'errorwhilecommunicatingwith',
                'repository',
                '',
                $this->get_name(),
                $e->getMessage()
            );
        }
        return ['path' => $path];
    }

    /**
     * Return the source information
     *
     * @param stdClass $filepath
     * @return string
     */
    public function get_file_source_info($filepath) {
        return 'Amazon S3: ' . $this->get_option('bucket_name') . '/' . $filepath;
    }

    /**
     * S3 doesn't require login
     *
     * @return bool
     */
    public function check_login() {
        return true;
    }

    /**
     * S3 doesn't provide search
     *
     * @return bool
     */
    public function global_search() {
        return false;
    }

    /**
     * Return names of the instance options.
     * By default: no instance option name
     *
     * @return array
     */
    public static function get_instance_option_names() {
        return ['access_key', 'secret_key', 'endpoint', 'bucket_name'];
    }

    /**
     * Edit/Create Instance Settings Moodle form
     *
     * @param moodleform $mform Moodle form (passed by reference)
     */
    public static function instance_config_form($mform) {
        parent::instance_config_form($mform);
        $strrequired = get_string('required');
        $endpointselect = [
            "s3.amazonaws.com" => "s3.amazonaws.com",
            "s3-external-1.amazonaws.com" => "s3-external-1.amazonaws.com",
            "s3-us-west-2.amazonaws.com" => "s3-us-west-2.amazonaws.com",
            "s3-us-west-1.amazonaws.com" => "s3-us-west-1.amazonaws.com",
            "s3-eu-west-1.amazonaws.com" => "s3-eu-west-1.amazonaws.com",
            "s3.eu-central-1.amazonaws.com" => "s3.eu-central-1.amazonaws.com",
            "s3-eu-central-1.amazonaws.com" => "s3-eu-central-1.amazonaws.com",
            "s3-ap-southeast-1.amazonaws.com" => "s3-ap-southeast-1.amazonaws.com",
            "s3-ap-southeast-2.amazonaws.com" => "s3-ap-southeast-2.amazonaws.com",
            "s3-ap-northeast-1.amazonaws.com" => "s3-ap-northeast-1.amazonaws.com",
            "s3-sa-east-1.amazonaws.com" => "s3-sa-east-1.amazonaws.com"
        ];
        $mform->addElement('passwordunmask', 'access_key', get_string('access_key', 'repository_s3'));
        $mform->setType('access_key', PARAM_RAW_TRIMMED);
        $mform->addElement('password', 'secret_key', get_string('secret_key', 'repository_s3'));
        $mform->setType('secret_key', PARAM_RAW_TRIMMED);
        $mform->addElement('text', 'bucket_name', get_string('bucketname', 'repository_s3bucket'));
        $mform->setType('bucket_name', PARAM_RAW_TRIMMED);
        $mform->addElement('select', 'endpoint', get_string('endpoint', 'repository_s3'), $endpointselect);
        $mform->setDefault('endpoint', 's3.amazonaws.com');
        $mform->addRule('access_key', $strrequired, 'required', null, 'client');
        $mform->addRule('secret_key', $strrequired, 'required', null, 'client');
        $mform->addRule('bucket_name', $strrequired, 'required', null, 'client');

        $options = ['subdirs' => 1, 'maxfiles' => -1, 'accepted_types' => '*', 'return_types' => FILE_INTERNAL];
        $mform->addElement('filemanager', 'attachments', get_string('browse', 'editor'), null, $options);
        $mform->disabledif('attachments', 'access_key', 'eq', '');
        $mform->disabledif('attachments', 'secret_key', 'eq', '');
        $mform->disabledif('attachments', 'bucket_name', 'eq', '');
    }

    /**
     * Validate repository plugin instance form
     *
     * @param moodleform $mform moodle form
     * @param array $data form data
     * @param array $errors errors
     * @return array errors
     */
    public static function instance_form_validation($mform, $data, $errors) {
        global $DB, $USER;
        $cont = context_user::instance($USER->id);
        $params = ['contextid' => $cont->id, 'component' => 'user', 'filearea' => 'draft', 'itemid' => $data['attachments']];
        if ($files = $DB->get_records('files', $params)) {
            $s3 = new S3($data['access_key'], $data['secret_key'], false, $data['endpoint']);
            $fs = get_file_storage();
            foreach ($files as $file) {
                if ($file->filesize > 0) {
                    $src = $fs->get_file_by_hash($file->pathnamehash);
                    $path = substr($file->filepath, 1) . $file->filename;
                    $result = $s3->putObjectString($src->get_content(), $data['bucket_name'], $path);
                }
            }
        }
        return $errors;
    }

    /**
     * S3 plugins doesn't support return links of files
     *
     * @return int
     */
    public function supported_returntypes() {
        return FILE_INTERNAL;
    }

    /**
     * Is this repository accessing private data?
     *
     * @return bool
     */
    public function contains_private_data() {
        return true;
    }

    /**
     * Get S3
     *
     * @return s3
     */
    private function create_s3() {
        if ($this->_s3client == null) {
            $accesskey = $this->get_option('access_key');
            if (empty($accesskey)) {
                throw new moodle_exception('needaccesskey', 'repository_s3');
            }
            $credentials = ['key' => $accesskey, 'secret' => $this->get_option('secret_key')];
            $endpoint = $this->get_option('endpoint');
            $s = \Aws\S3\S3Client::factory(['version' => '2006-03-01', 'credentials' => $credentials, 'region' => 'eu-central-1']);
            $s->registerStreamWrapper();
            $this->_s3client = $s;
        }
        return $this->_s3client;
    }
}