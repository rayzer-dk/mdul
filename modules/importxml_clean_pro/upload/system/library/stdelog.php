<?php

/**
 * @category OpenCart
 * @package StdeLog
 * @description Amateur Log for OpenCart
 * @version 0.9.3
 * @copyright CodeCart PRO, https://codecartpro.com
 */

class StdeLog {
	// Default Filename
	private $filename = 'StdeLog';
	
	// It is used for SESSION mark in cases when you use AJAX-loop
	private $marker = '';
	
	private $debug = 1;
	
	private $days = 3;

	public function __construct($filename = '') {
		if ($filename) {
			$this->setFilename($filename);
		}
	}

	public function setFilename($filename) {
		$this->filename = $filename;
	}
	
	public function setMarker($marker) {
		$this->marker = $marker;
	}

	public function setDebug($debug) {
		$this->debug = $debug;
	}
	
	public function setTime($days) {
		$this->days = $days;
	}

	/*
	 * Используется следующая классификация логов:
	 * 1 (error) - логируем только ошибки (хотя также бывают еще и fatal и warning)
	 * 2 (info) - логируем только крупные операции, которые проявляются редко (нажатие на кнопку генерации к примеру...)
	 * 3 (debug) - логируем крупные операции + данные в запросах пользователя + другие доп сведения...
	 * 4 (trace) - логируем все подряд, если дебаг не помогает
	 * По мотивам https://habr.com/ru/post/135242/
	 */
	
	/*
	 * $this->stdelog->write(2, 'generateProductKeyword() is called'); // Запишет переданный текст, если debug будет >= 2
	 * $this->stdelog->write(4, $a_data, 'generateProductKeyword() : $a_data'); // Запишет переменную переданный текст, если debug будет >= 4
	 */

	// TODO
	// Q??
	// Может сделать так, чтобы в случае ERROR, они бы записывались еще и в отдельный файл??
	function write($level, $data, $title = false, $filename = false, $marker = false) {
		if ($level > $this->debug) {
			return false;
		}

		$levels = array(
			1	=>'ERROR',
			2	=>'INFO',
			3	=>'DEBUG',
			4	=>'TRACE'
		);

		// TODO
		// Тут можно дописать удаление вчерашних логов

		if (!$filename) {
			$filename = $this->filename;
		}
		
		if (!$marker) {
			$marker = $this->marker;
		}
		
		$marker = (!empty($marker)) ? '_' . $marker : '';

		if (is_scalar($data)) {
			$str = '';

			// scalar -- write in a string
			if ($title) {
				$str = "$title : ";
			}
			
			$str .= $data;

			file_put_contents(DIR_LOGS . $filename . '_' . date("Y-m-d") . $marker . '.log', $levels[$level] . ' -- [' . date("Y/m/d H:i:s") . '] -- ' . $str . "\r\n------------------------------------------------------------------------------------\r\n", FILE_APPEND | LOCK_EX);
		} else {
			ob_start();

			// NON scalar -- write from new line
			if ($title) {
				echo "$title:\r\n";
			} else {
				echo "Mixed\r\n";
			}

			print_r($data);
			$c = ob_get_contents();
			ob_clean();

			file_put_contents(DIR_LOGS . $filename . '_' . date("Y-m-d") . $marker . '.log', $levels[$level] . ' -- [' . date("Y/m/d H:i:s") . '] -- ' . "$c" . "\r\n------------------------------------------------------------------------------------\r\n", FILE_APPEND | LOCK_EX);
		}
	}
	
	public function deleteOld($days = 0) {
		if (!$days) $days = $this->days;		
		
		// Удаляем все, что старше, чем $days
		$iterator = new DirectoryIterator(DIR_LOGS);
		
		foreach ($iterator as $item) {
			if (!$item->isDot() && $item->isFile()) {
				if (time() - filemtime(DIR_LOGS . $item->getFilename()) > 60 * 60 * 24 * $days
					&& 'error.log' != $item->getFilename()
					&& 'ocmod.log' != $item->getFilename()
					&& 'openbay.log' != $item->getFilename()
				) {
					unlink(DIR_LOGS . $item->getFilename());
				}				
			}
		}
		
		return;
	}

}
