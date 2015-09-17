<?php
/**
 * Picture class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace Skeleton\File\Picture;

use Skeleton\File\File;
use Skeleton\Database\Database;

class Picture extends File {

	/**
	 * Details
	 *
	 * @var array $details
	 * @access private
	 */
	private $local_details = [];

	/**
	 * Local fields
	 *
	 * @access private
	 * @var array $fields
	 */
	private $local_fields = ['file_id', 'width', 'height'];

	/**
	 * Get the details of this file
	 *
	 * @access private
	 */
	protected function get_details() {
		parent::get_details();
		if (!isset($this->id) OR $this->id === null) {
			throw new Exception('Could not fetch file data: ID not set');
		}

		$db = Database::Get();
		$details = $db->get_row('SELECT * FROM picture WHERE file_id=?', [$this->id]);

		if ($details === null) {
			$this->save();
		}

		$this->local_details = $details;
	}

	/**
	 * Save the file
	 *
	 * @access public
	 */
	public function save($get_details = true) {
		if (!isset($this->id)) {
			parent::save(false);
		}

		$this->get_dimensions();

		$db = Database::Get();
		if (!isset($this->local_details['id']) OR $this->local_details['id'] === null) {
			if (!isset($this->local_details['file_id']) OR $this->local_details['file_id'] == 0) {
				$this->file_id = $this->id;
			} else {
				$this->id = $this->file_id;
			}
			$db->insert('picture', $this->local_details);
		} else {
			$where = 'file_id=' . $db->quote($this->id);
			$db->update('picture', $this->local_details, $where);
		}

	
		$this->get_details();
	}

	/**
	 * Set a detail
	 *
	 * @access public
	 * @param string $key
	 * @param mixex $value
	 */
	public function __set($key, $value) {
		if (in_array($key, $this->local_fields)) {
			$this->local_details[$key] = $value;
		} else {
			parent::__set($key, $value);
		}
	}

	/**
	 * Get a detail
	 *
	 * @access public
	 * @param string $key
	 * @return mixed $value
	 */
	public function __get($key) {
		if (isset($this->local_details[$key])) {
			return $this->local_details[$key];
		} else {
			return parent::__get($key);
		}
	}

	/**
	 * Isset
	 *
	 * @access public
	 * @param string $key
	 * @return bool $isset
	 */
	public function __isset($key) {
		if (isset($this->local_details[$key])) {
			return true;
		} else {
			return parent::__isset($key);
		}
	}

	/**
	 * Get the dimensions of the picture
	 *
	 * @access private
	 */
	private function get_dimensions() {
		if (isset($this->width) AND isset($this->height)) {
			return;
		}
		$path = $this->get_path();
		list($width, $height) = getimagesize($path);
		$this->width = $width;
		$this->height = $height;
	}

	/**
	 * Resize the picture
	 *
	 * @access private
	 * @param string $size
	 */
	private function resize($size) {
		if (!file_exists(Config::$tmp_dir . '/picture/' . $size)) {
			mkdir(Config::$tmp_dir . '/picture/' . $size, 0755, true);
		}

		if ($size == 'original') {
			$resize_info = ['width' => $this->width, 'height' => $this->height, 'mode' => 'exact'];
		} else {
			$resize_info = Config::get_resize_configuration($size);
		}

		$new_width = null;
		if (isset($resize_info['width'])) {
			$new_width = $resize_info['width'];
		}

		$new_height = null;
		if (isset($resize_info['height'])) {
			$new_height = $resize_info['height'];
		}

		$mode = 'auto';
		if (isset($resize_info['mode'])) {
			$mode = $resize_info['mode'];
		}

		$image = new Manipulation($this);
		$image->resize($new_width, $new_height, $mode);
		$image->output(Config::$tmp_dir . '/picture/' . $size . '/' . $this->id);
	}

	/**
	 * Output the picture to the browser
	 *
	 * @access public
	 * @param string $size
	 */
	public function show($size = 'original') {
		if(!file_exists(Config::$tmp_dir . '/picture/' . $size . '/' . $this->id)) {
			$this->resize($size);
		}

		if ($size == 'original') {
			$filename = $this->get_path();
		} else {
			$filename = Config::$tmp_dir . '/picture/' . $size . '/' . $this->id;
		}

		$gmt_mtime = gmdate('D, d M Y H:i:s', filemtime($filename)).' GMT';

		header('Cache-Control: public');
		header('Pragma: public');

		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
			if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime) {
				header('Expires: ');
				header('HTTP/1.1 304 Not Modified');
				exit;
			}
		}

		header('Last-Modified: '. $gmt_mtime);
		header('Expires: '.gmdate('D, d M Y H:i:s', strtotime('+300 minutes')).' GMT');
		header('Content-Type: ' . $this->mime_type);
		readfile($filename);
		exit();
	}

	/**
	 * Delete the image and its cache
	 *
	 * @access public
	 */
	public function delete() {
		foreach (Config::$resize_configurations as $name => $configuration) {
			if (file_exists(Config::$tmp_dir . '/picture/' . $name . '/' . $this->id)) {
				unlink(Config::$tmp_dir . '/picture/' . $name . '/' . $this->id);
			}
		}
		$db = Database::Get();
		$db->query('DELETE FROM picture WHERE file_id=?', [$this->id]);

		parent::delete();
	}

	/**
	 * Get a picture by ID
	 *
	 * @access public
	 * @param int $id
	 * @return Picture $picture
	 */
	public static function get_by_id($id) {
		return new Picture($id);
	}
}
