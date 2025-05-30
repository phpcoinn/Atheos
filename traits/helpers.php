<?php

//////////////////////////////////////////////////////////////////////////////80
// Helper trait
//////////////////////////////////////////////////////////////////////////////80
// Copyright (c) 2020 Liam Siira (liam@siira.io), distributed as-is and without
// warranty under the MIT License. See [root]/docs/LICENSE.md for more.
// This information must remain intact.
//////////////////////////////////////////////////////////////////////////////80
// Authors: Codiad Team, @Fluidbyte, Atheos Team, @hlsiira
//////////////////////////////////////////////////////////////////////////////80

trait Helpers {

	//////////////////////////////////////////////////////////////////////////80
	// Load version number from file
	//////////////////////////////////////////////////////////////////////////80
	public static function version() {
		$version = is_readable(".version") ? file_get_contents(".version") : "NaN";
		if ($version !== "NaN") {
			$version = json_decode($version, true)["version"];
		}
		return $version;
	}

	public static function compareVersions($v1, $v2) {
		// Src: https://helloacm.com/the-javascript-function-to-compare-version-number-strings/
		if (!is_string($v1) || !is_string($v2)) {
			return false;
		}

		$v1 = explode(".", $v1);
		$v2 = explode(".", $v2);

		$k = min(count($v1), count($v2));

		for ($i = 0; $i < $k; $i++) {
			$v1[$i] = (int)$v1[$i];
			$v2[$i] = (int)$v1[$i];
			if ($v1[$i] > $v2[$i]) {
				return 1;
			}
			if ($v1[$i] < $v2[$i]) {
				return -1;
			}
		}
		return count($v1) === count($v2) ? 0 : (count($v1) < count($v2) ? -1 : 1);
	}

	//////////////////////////////////////////////////////////////////////////80
	// GetBrowserName
	//////////////////////////////////////////////////////////////////////////80
	public static function getBrowser() {
		$userAgent = SERVER("HTTP_USER_AGENT");
		if (strpos($userAgent, 'Opera') || strpos($userAgent, 'OPR/')) return 'Opera';
		elseif (strpos($userAgent, 'Edge')) return 'Edge';
		elseif (strpos($userAgent, 'Chrome')) return 'Chrome';
		elseif (strpos($userAgent, 'Safari')) return 'Safari';
		elseif (strpos($userAgent, 'Firefox')) return 'Firefox';
		elseif (strpos($userAgent, 'MSIE') || strpos($userAgent, 'Trident/7')) return 'Internet Explorer';

		return 'Other';
	}

	//////////////////////////////////////////////////////////////////////////80
	// Read Content of directory
	//////////////////////////////////////////////////////////////////////////80
	public static function readDirectory($foldername) {
		$tmp = array();
		$allFiles = scandir($foldername);

		foreach ($allFiles as $fname) {
			if (in_array($fname, [".", "..", "README.md"])) {
				continue;
			}

			if (substr($fname, -9) === ".disabled") {
				continue;
			}

			// if (is_dir($foldername."/".$fname)) {
			$tmp[] = $fname;
			// }
		}
		return $tmp;
	}

	//////////////////////////////////////////////////////////////////////////80
	// Log Action
	//////////////////////////////////////////////////////////////////////////80
	public static function log($text, $name = "global") {
		$path = DATA . "/log/";
		$path = preg_replace("#\/+#", "/", $path);

		if (!is_dir($path)) mkdir($path);

		$file = "$name.log";
		$text = $text . PHP_EOL;

		if (file_exists($path . $file)) {
			$lines = file($path . $file);
			if (sizeof($lines) > 100) {
				unset($lines[0]);
			}
			$lines[] = $text;

			$write = fopen($path . $file, "w");
			fwrite($write, implode("", $lines));
			fclose($write);
		} else {
			$write = fopen($path . $file, "w");
			fwrite($write, $text);
			fclose($write);
		}
	}

	//////////////////////////////////////////////////////////////////////////80
	// Check If WIN based system
	//////////////////////////////////////////////////////////////////////////80
	public static function isWINOS() {
		return (strtoupper(substr(PHP_OS, 0, 3)) === "WIN");
	}

	//////////////////////////////////////////////////////////////////////////80
	// Check Function Availability
	//////////////////////////////////////////////////////////////////////////80
	public static function isAvailable($func) {
		if (ini_get("safe_mode")) return false;
		$disabled = ini_get("disable_functions");
		if ($disabled) {
			$disabled = explode(",", $disabled);
			$disabled = array_map("trim", $disabled);
			return !in_array($func, $disabled);
		}
		return true;
	}

	public static function rDelete($target) {
		$status = true;

		// Unnecessary, but rather be safe that sorry.
		if ($target === "." || $target === "..") return true;

        //PHPCoin
        if(is_link($target)) {
            $status = unlink($target);
        }
		else if (is_dir($target)) {

			$files = glob($target . "{*,.[!.]*,..?*}", GLOB_BRACE|GLOB_MARK); //GLOB_MARK adds a slash to directories returned
			// $files = glob($target . "/*");

			foreach ($files as $file) {
				$status = Common::rDelete($file) === false ? false : $status;
			}
			if (file_exists($target)) {
				$status = rmdir($target) === false ? false : $status;
			}
		} elseif (is_file($target)) {
			$status = unlink($target) === false ? false : $status;
		}

		return $status;
	}


	public static function rZip($folder, &$archive, $exclusiveLength) {
		$handle = opendir($folder);
		while ($file = readdir($handle)) {
			if ($file === "." || $file === "..") continue;
			$filePath = "$folder/$file";
			$localPath = substr($filePath, $exclusiveLength);
			if (is_file($filePath)) {
				$archive->addFile($filePath, $localPath);
			} elseif (is_dir($filePath)) {
				Common::rZip($filePath, $archive, $exclusiveLength);
			}

		}
		closedir($handle);
	}

	// @author umbalaconmeogia at NOSPAM dot gmail dot com
	// @link http://www.php.net/manual/de/class.ziparchive.php#110719*
	public static function zip($orig, $dest) {
		$archive = new ZipArchive();
		$archive->open($dest, ZIPARCHIVE::CREATE);

		Common::rZip($orig, $archive, strlen("$orig/"));

		$archive->close();
	}


}
