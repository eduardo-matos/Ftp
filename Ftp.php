<?php

class Cf_Ftp
{
	const FILE = 'file';

	const CONTENTS = 'contents';

	protected $_conn;

	/**
	 * @param boolean $pasv_mode Define a forma de conexão passiva desligada ou ativa.
	 */
	public function __construct($server = null, $user = null, $pass = null, $port = 21, $pasv_mode = true)
	{
		if(null !== $server && null !== $user && null !== $pass) {
			$this->connect($server, $user, $pass, $pasv_mode, $port, $pasv_mode);
		}
	}

	public function __destruct()
	{
		$this->close();
	}

	public function connect($server, $user, $pass, $port = 21, $pasv_mode = true)
	{
		$this->close();
		$this->_conn = ftp_connect($server, $port);
		ftp_login($this->_conn, $user, $pass);

		/**
		 * Define a forma de conexão passiva desligada ou ativa.
		 *
		 * @param {Boolean} $pasv_mode = true
		 */
		@ftp_pasv($this->_conn, $pasv_mode);
	}

	public function close()
	{
		if(null !== $this->_conn) {
			ftp_close($this->_conn);
			$this->_conn = null;
		}
	}

	public function put($local_path, $remote_path, $type = self::CONTENTS)
	{

		$remote_dir = substr($remote_path, 0, strrpos($remote_path, '/'));

		if(false === $this->mkdir($remote_dir)) {
			return false;
		}

		if($type === self::CONTENTS){
			// criar arquivo temporario com o conteudo
			$contents = $local_path;
			$tmp_file_name = mt_rand(10000, 99999) . time() . '.tmp';
			file_put_contents($tmp_file_name, $contents);
			$this->_putFile($remote_path, $tmp_file_name);
			unlink($tmp_file_name);
		} elseif($type === self::FILE) {
			$this->_putFile($remote_path, $local_path);
		} else {
			throw new Exception('O tipo deve ser do tipo "file" ou "contents".');
		}
	}

	public function delete($remote_path)
	{
		if($this->fileExists($remote_path)) {
			return ftp_delete($this->_conn, $remote_path);
		} elseif($this->folderExists($remote_path)) {
			$ret_delete = @ftp_rmdir($this->_conn, $remote_path);
			if(false === $ret_delete) {
				// Deletar recursivamente
				$files = $this->nlist($remote_path);
				
				foreach($files as $file) {
					$this->delete($file);
				}
				
				$ret_delete = ftp_rmdir($this->_conn, $remote_path);
			}
			
			return $ret_delete;
		}
	}

	protected function _putFile($remote_path, $local_path)
	{
		ftp_put($this->_conn, $remote_path, $local_path, FTP_BINARY);
	}

	public function fileExists($remote_path)
	{
		return in_array($remote_path, ftp_nlist($this->_conn, $remote_path));
	}

	public function folderExists($remote_path)
	{
		$result = @ftp_chdir($this->_conn, $remote_path);
		ftp_chdir($this->_conn, '/');
		return $result;
	}
	
	public function nlist($path)
	{
		return ftp_nlist($this->_conn, $path);
	}

	public function mkdir($dirpath)
	{
		// Remover última barra do caminho
		if('/' === substr($dirpath, -1)) {
			$dirpath = substr($dirpath, 0, strlen($dirpath) - 1);
		}

		// Remover primeira barra do caminho
		if('/' === substr($dirpath, 0, 1)) {
			$dirpath = substr($dirpath, 1);
		}

		$dirs = explode('/', $dirpath);
		$existing_folders = $dirs;
		$non_existing_folders = array();

		if($this->folderExists($dirpath)) {
			return true;
		}

		for($i = count($dirs) - 1; $i >= 0; $i--) {
			$current_dir_path = '/' . implode('/', $existing_folders);

			if($this->folderExists($current_dir_path)) {

				ftp_chdir($this->_conn, $current_dir_path);

				foreach($non_existing_folders as $folder) {
					ftp_mkdir($this->_conn, $folder);
					ftp_chdir($this->_conn, $folder);
				}

				ftp_chdir($this->_conn, '/');

				break;
			} else {
				array_unshift($non_existing_folders, array_pop($existing_folders));
			}

			// Se o caminho passado não contiver nenhuma pasta do servidor
			if($non_existing_folders === $dirs) {
				return false;
			}
		}
	}


}