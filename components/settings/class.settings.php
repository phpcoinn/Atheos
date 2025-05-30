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
	private $db = null;

	//////////////////////////////////////////////////////////////////////////80
	// METHODS
	//////////////////////////////////////////////////////////////////////////80

	// ----------------------------------||---------------------------------- //

	//////////////////////////////////////////////////////////////////////////80
	// Construct
	//////////////////////////////////////////////////////////////////////////80
	public function __construct($activeUser) {
		$this->activeUser = $activeUser;
		$this->db = Common::getKeyStore("settings", "users/" . $activeUser);
	}

	//////////////////////////////////////////////////////////////////////////80
	// Load User Settings
	//////////////////////////////////////////////////////////////////////////80
	public function load() {
        //PHPCoin - settings in session
		$settings = $_SESSION['settings'];
		if (!empty($settings)) {
			Common::send(200, $settings);
		} else {
			Common::send(404, "Settings for user not found.");
		}
	}

	//////////////////////////////////////////////////////////////////////////80
	// Save User Settings
	//////////////////////////////////////////////////////////////////////////80
	public function save($key, $value) {
        //PHPCoin - settings in session
		$_SESSION['settings'][$key]=$value;
		Common::send("success");
	}
}
