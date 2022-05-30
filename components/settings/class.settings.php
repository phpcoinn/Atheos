<?php

//////////////////////////////////////////////////////////////////////////////80
// Settings Class
//////////////////////////////////////////////////////////////////////////////80
// Copyright (c) 2020 Liam Siira (liam@siira.io), distributed as-is and without
// warranty under the MIT License. See [root]/docs/LICENSE.md for more.
// This information must remain intact.
//////////////////////////////////////////////////////////////////////////////80
// Authors: Codiad Team, @Fluidbyte, Atheos Team, @hlsiira
//////////////////////////////////////////////////////////////////////////////80

class Settings {

	//////////////////////////////////////////////////////////////////////////80
	// PROPERTIES
	//////////////////////////////////////////////////////////////////////////80
	private $activeUser = null;
	//phpcoin: allow public access to db
	public $db = null;

	//////////////////////////////////////////////////////////////////////////80
	// METHODS
	//////////////////////////////////////////////////////////////////////////80

	// ----------------------------------||---------------------------------- //

	//////////////////////////////////////////////////////////////////////////80
	// Construct
	//////////////////////////////////////////////////////////////////////////80
	public function __construct($activeUser) {
		$this->activeUser = $activeUser;
		$this->db = Common::getKeyStore("settings", $activeUser);
	}

	//////////////////////////////////////////////////////////////////////////80
	// Load User Settings
	//////////////////////////////////////////////////////////////////////////80
	public function load() {
		$settings = $_SESSION['settings'];
//		$settings = $this->db->select("*");
		if (!empty($settings)) {
			Common::send("success", $settings);
		} else {
			Common::send("error", "Settings for user not found.");
		}
	}

	//////////////////////////////////////////////////////////////////////////80
	// Save User Settings
	//////////////////////////////////////////////////////////////////////////80
	public function save($key, $value) {
//		$this->db->update($key, $value, true);
		$_SESSION['settings'][$key]=$value;
		Common::send("success");
	}
}
