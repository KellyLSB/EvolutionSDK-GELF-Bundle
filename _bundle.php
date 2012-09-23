<?php

namespace Bundles\Gelf;
use Exception;
use e;

class Bundle {

	private $host = false;
	private $port = '12201';

	/**
	 * Load GELF Config
	 * @author Kelly Becker
	 */
	public function _on_framework_loaded() {

		// Get the gelf host
		$this->host = e::$environment->requireVar('gelf.host');

		// Get the gelf port
		$port = e::$environment->getVar('gelf.port');
		if(!empty($port)) $this->port = $port;
	}

	/**
	 * Exception handler for sending GELF Messages
	 * @author Kelly Becker
	 */
	public function _on_exception($e) {
		$traceString = "";
		$bundle_access = false;
		$trace = array_reverse($e->getTrace());
		foreach($trace as $t) {

			// If the last method was bundle access
			if($bundle_access) {
				if($bundle_access_file == $t['file']
					&& $bundle_access_line == $t['line']
					&& $t['function'] == '__call'
					&& $t['type'] == '->')
					continue;

				// Reset vars for now
				$bundle_access = false;
				$bundle_access_file = false;
				$bundle_access_line = false;
			}

			// Parse a new line or file
			if(!empty($t['line']) || !empty($t['file'])) {
				if(empty($t['file'])) $traceString .= "\n\t".$t['line'].': ';
				else $traceString .= "\n\n".$t["file"]."\n\t".$t['line'].': ';
			}

			// Parse arguments
			foreach($t['args'] as $key => &$arg) {
				if(is_object($arg)) $arg = get_class($arg);
			}
			$args = implode(', ', $t['args']);

			// Handle call_user_func_arrays
			if($t['function'] == 'call_user_func_array') {
				$traceString .= 'call_user_func_array';
				continue;
			}

			// Handle arrows
			if($t['type'] == '->' && empty($t['class'])) {
				$traceString .= "->".$t['function']."($args)";
			}

			// Handle arrows with classes
			elseif($t['type'] == '->' && !empty($t['class'])) {

				// Determine the real call
				if($t['class'] == 'e_bundle_accessor') {

					// Save for the next line
					$bundle_access = true;
					$bundle_access_file = $t['file'];
					$bundle_access_line = $t['line'];

					// Parse out which bundle was called
					$line = $this->file($t['file'], $t['line']);
					$end = strpos($line, $t['function']) - strlen($line) - 2;
					$line = substr($line, 0, $end);
					$start = strpos($line, 'e::');

					// Append the true method with the bundle name
					$traceString .= substr($line, $start).'->'.$t['function']."($args)";
				}

				// Otherwise append the default way
				else $traceString .= "->".$t['class'].'::'.$t['function']."($args)";
			}

			// New functions
			else {
				if(empty($t['class'])) $traceString .= "\t".$t['function']."($args)";
				else $traceString .= "\t".$t["class"].'::'.$t['function']."($args)";
			}
		}

		// Create the GELF Message
		$message = new GELFMessage();
		$message->setShortMessage("Uncaught (".get_class($e)."): ".$e->getMessage());
		$message->setFullMessage(trim($traceString));
		$message->setHost(gethostname());
		$message->setLevel(GELFMessage::CRITICAL);
		$message->setFacility('evolutionSDK');
		$message->setFile($e->getFile());
		$message->setLine($e->getLine());

		// Send the GELF Message
		$publisher = new GELFMessagePublisher($this->host, $this->port);
		$publisher->publish($message);
	}

	/**
	 * This is to load specific lines from files
	 * @author Kelly Becker
	 */
	private function file($file, $lines = '0') {
		$file = file($file);

		if(strpos($lines, '~') !== false)
			$lines = explode('~', $lines);

		if(is_array($lines)) {
			$start = array_shift($lines);
			$end = array_shift($lines);
		}
		else $start = $end = $lines;

		for(null;$start <= $end;$start++) {
			$return .= $file[$start-1];
		}

		return $return;
	}


}