<?php
namespace CakephpBlueimpUpload\Controller\Component;

use Shim\Controller\Component\Component;
use Cake\Controller\ComponentRegistry;
use Tools\Utility\Mime;

/**
 * Uploader component
 */
class UploaderComponent extends Component
{

	/**
	 * @var bool
	 */
	public $realUpload = true;

	/**
	 * Contains last upload errors
	 *
	 * @var array
	 */
	protected $_upload_errors = [];

	/**
	 * @var \Tools\Utility\Mime;
	 */
	protected $_Mime;

	/**
	 * @var \Tools\Model\Table\Table;
	 */
	protected $_Table;


	/**
	 * @param array $config
	 * @return void
	 */
	public function initialize(array $config) {
		parent::initialize($config);

		$this->_Mime = new Mime();
		$this->_Table = $this->Controller->loadModel('CakephpBlueimpUpload.Uploads');
	}

	public function upload($upload_folder, array $options = array())
	{
		$this->request->allowMethod(['post', 'put']);

		$defaultOptions = [
			'auto_subfolder'     => true,
			'override_by_name'   => false,
			'accepted_mimetypes' => []
		];

		$options += $defaultOptions;

		/*
		 * Reset errors
		 */
		$this->_upload_errors = [];

		$upload_id      = $this->request->header('X-Upload-id');
		$content_length = $this->request->header('Content-Length');
		$content_range  = $this->request->header('Content-Range');
		$content_type   = $this->request->contentType(); // this is insecure !

		if (empty($this->request->data['files'])) {
			$this->_upload_errors[] = __d('cakephp_blueimp_upload', 'No data to upload found. Check that the max chunk size is not to high.');
			return false;
		}

		foreach ($this->request->data['files'] as $uploaded_file) {
			if (!$this->isUploaded($uploaded_file['tmp_name'])) {
				$this->_upload_errors[] = __d('cakephp_blueimp_upload', 'upload file not found');
				continue;
			}

			$valid_mimetype = true;
			$mimetype = null;
			if (!empty($options['accepted_mimetypes'])) {
				$mimetype = $uploaded_file['type'];

				if (!in_array($mimetype, $options['accepted_mimetypes'])) {
					$valid_mimetype = false;
				}
			}

			if (!$valid_mimetype) {
				$this->_upload_errors[] = sprintf(__d('cakephp_blueimp_upload', 'this file type (%s) can not be uploaded'), $mimetype);
				continue;
			}

			$original_filename = $uploaded_file['name'];

			$total_filesize = null;
			if(stripos($content_range, '/') !== false) {
				$total_filesize = substr($content_range, stripos($content_range, '/') + 1);
			}

			$chunk_size = $uploaded_file['size'];

			if (empty($upload_id) || empty($original_filename) || empty($chunk_size)) {
				$this->_upload_errors[] = __d('cakephp_blueimp_upload', 'some upload metadata are missing');
				continue;
			}

			/*
			 * Check if a file belonging to the same upload already exists in database
			 */
			$existingUpload = $this->_Table->find()->where(['upload_id' => $upload_id])->first();
			if (!empty($existingUpload)) {
				/*
				 * This POST is a new file part
				 */
				$upload_resume = true;
				$new_file      = false;
			} else {
				if ($options['override_by_name']) {
					/*
					 * Check if a file with the same name already exists
					 * -> the upload will override the record in the database with the new file metadata
					 */
					$existingUpload = $this->_Table->find()->where(['original_filename' => $original_filename])->first();
				}

				/*
				 * This POST is a brand new file upload
				 */
				$upload_resume = false;
				$new_file      = true;
			}

			$unique_filename   = $upload_id . '_' . $original_filename;

			if ($options['auto_subfolder']) {
				$subfolder = date('Y-m-d');
				if(!is_dir($upload_folder . $subfolder)) {
					mkdir($upload_folder . $subfolder);
					chmod($upload_folder . $subfolder, 0770);
				}

				$uploaded_filepath = $upload_folder . DS . $subfolder . DS . $unique_filename;
			} else {
				$subfolder = null;
				$uploaded_filepath = $upload_folder . DS . $unique_filename;
			}

			$uploaded = false;

			if ($new_file) {
				/*
				 * Move the first part of the file
				 */
				if($this->_move($uploaded_file['tmp_name'], $uploaded_filepath)) {
					$uploaded = true;
				}
			} elseif ($upload_resume) {
				/*
				 * -> append the uploaded data to the already existing file part
				 */
				if(file_put_contents($uploaded_filepath, fopen($uploaded_file['tmp_name'], 'r'), FILE_APPEND)) {
					$uploaded = true;
				}
			}

			if (!$uploaded) {
				$this->_upload_errors[] = __d('cakephp_blueimp_upload', 'some part of the file could not be saved');
				continue;
			}

			$upload = $this->_Table->newEntity();

			$upload->original_filename    = $original_filename;
			$upload->unique_filename      = $unique_filename;
			$upload->subfolder            = $subfolder;
			$upload->mimetype             = $this->_Mime->detectMimeType($uploaded_filepath);
			$upload->size                 = filesize($uploaded_filepath);
			$upload->upload_id            = $upload_id;
			$upload->label                = null;

			if ((!empty($total_filesize) && filesize($uploaded_filepath) == $total_filesize) || empty($total_filesize)) {
				/*
				 * Note: it seems that when $total_filesize is not set, the file is uploaded in only one POST request -> it is always complete
				 */
				$upload->complete = true;
				$upload->hash     = sha1_file($uploaded_filepath);
			} else {
				$upload->complete = false;
			}

			if (!empty($existingUpload)) {
				$upload->id = $existingUpload->id;
			}

			if (!$this->_Table->save($upload)) {
				$this->_upload_errors = array_merge($this->_upload_errors, $upload->validationErrors);
			}
		}

		if (!empty($this->_upload_errors)) {
			return false;
		}

		return $upload;
	}

	/**
	 * @return array
	 */
	public function getUploadErrors()
	{
		return $this->_upload_errors;
	}

	/**
	 * @return \Tools\Model\Table\Table
     */
	public function getTable() {
		return $this->_Table;
	}

	/**
	 * Wrapper
	 *
	 * @see http://stackoverflow.com/questions/3402765/how-can-i-write-tests-for-file-upload-in-php
	 */
	public function isUploaded($filename) {
		if (!$this->realUpload) {
			return file_exists($filename);
		}
		return is_uploaded_file($filename);
	}

	/**
	 * @param string $from
	 * @param string $to
	 *
	 * @return bool
	 */
	protected function _move($from, $to) {
		if ($this->realUpload) {
			return move_uploaded_file($from, $to);
		}
		if (copy($from, $to)) {
			unlink($from);
			return true;
		}
		return false;
	}

}
