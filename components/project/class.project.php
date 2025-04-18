<?php

//////////////////////////////////////////////////////////////////////////////80
// Project Class
//////////////////////////////////////////////////////////////////////////////80
// Copyright (c) Atheos & Liam Siira (Atheos.io), distributed as-is and without
// warranty under the MIT License. See [root]/docs/LICENSE.md for more.
// This information must remain intact.
//////////////////////////////////////////////////////////////////////////////80
// Authors: Codiad Team, @Fluidbyte, Atheos Team, @hlsiira
//////////////////////////////////////////////////////////////////////////////80

class Project {

    //////////////////////////////////////////////////////////////////////////80
    // PROPERTIES
    //////////////////////////////////////////////////////////////////////////80
    private $activeUser = null;
    private $userData = false;
    private $db = null;

    //////////////////////////////////////////////////////////////////////////80
    // METHODS
    //////////////////////////////////////////////////////////////////////////80

    // ----------------------------------||---------------------------------- //

    //////////////////////////////////////////////////////////////////////////80
    // Construct
    //////////////////////////////////////////////////////////////////////////80
    public function __construct() {
        $this->db = Common::getKeyStore("projects");

        $this->activeUser = SESSION("user");
        $this->userData = Common::loadJSON("users")[$this->activeUser];

        if (file_exists(DATA . "/projects.json")) {
            $projects = Common::loadJSON("projects");

            foreach ($projects as $projectPath => $projectName) {
                $this->db->update($projectName, $projectPath, true);
            }
            unlink(DATA . "/projects.json");
        }

    }

    //////////////////////////////////////////////////////////////////////////80
    // Create
    //////////////////////////////////////////////////////////////////////////80
    public function create($projectName, $projectPath, $gitRepo, $gitBranch) {

        $projectPath = Common::cleanPath($projectPath);
        $projectName = htmlspecialchars($projectName);

        $results = $this->db->select($projectName);
        if (!empty($results)) {
            Common::send(409, i18n("project_exists_name"));
        }

        if (!Common::isAbsPath($projectPath)) {
            $projectPath = $this->sanitizePath($projectPath);
            $projectPath = WORKSPACE . "/" . $projectPath;
        }

        if (!file_exists($projectPath)) {
            if (!mkdir($projectPath . "/", 0755, true)) {
                Common::send(506, i18n("project_unableAbsolute"));
            }
        } else {
            if (!is_writable($projectPath) || !is_readable($projectPath)) {
                Common::send(506, i18n("project_unablePermissions"));
            }
        }


        $this->db->insert($projectName, $projectPath);

        // Pull from Git Repo?
        if ($gitRepo && filter_var($gitRepo, FILTER_VALIDATE_URL) !== false) {
            $gitBranch = $this->sanitizeGitBranch($gitBranch);
            $cmd = Common::isAbsPath($projectPath) ? "cd " . escapeshellarg($projectPath) : "cd " . escapeshellarg(WORKSPACE . "/" . $projectPath);
            $cmd .= " && git init && git remote add origin " . escapeshellarg($gitRepo) . " && git pull origin " . escapeshellarg($gitBranch);
            Common::execute($cmd);
        }

        // Log Action
        Common::log("@" . date("Y-m-d H:i:s") . ":\t{" . $this->activeUser . "} created project {$projectName}", "projects");
        Common::send(200, array("name" => $projectName, "path" => $projectPath));

    }

    //////////////////////////////////////////////////////////////////////////80
    // Delete Project
    //////////////////////////////////////////////////////////////////////////80
    public function delete($projectName, $scope) {
        if ($scope === "hard") {
            $path = $this->db->select($projectName);
            if (Common::rDelete($path) !== true) {
                Common::send(500, "Project could not be deleted.");
            }
        }

        $this->db->delete($projectName);

        // Log Action
        Common::log("@" . date("Y-m-d H:i:s") . ":\t{" . $this->activeUser . "} deleted project {$projectName}", "projects");
        Common::send(200, "Project deleted.");
    }

    public function listProjects() {
        $projects = $this->db->select("*");
        $userACL = $this->userData["userACL"];
        asort($projects);

        if ($userACL !== "full") {
            foreach ($projects as $projectName => $projectPath) {
                if (!in_array($projectPath, $userACL)) unset($projects[$projectName]);
            }
        }

        return $projects;
    }

    //////////////////////////////////////////////////////////////////////////80
    // Load Active Project, or Default
    //////////////////////////////////////////////////////////////////////////80
    public function load($activeName, $activePath) {
        if ($activeName && $activePath) {
            // Load currently active project in session, pulled from cache data in user class
            $projectName = $activeName;
            $projectPath = $activePath;
        } else {
            // Load default/first project

            $projects = $this->db->select("*");

            if ($this->userData["userACL"] !== "full") {
                $projectPath = reset($this->userData["userACL"]);
            } else {
                $projectPath = reset($projects);
            }
            $projectName = array_search($projectPath, $projects);

            // Set Session Project
            SESSION("projectPath", $projectPath);
            SESSION("projectName", $projectName);

        }

        if (is_null($projectName) && $projectPath === BASE_PATH) {
            $projectName = "Atheos IDE";
        }



        $reply = array(
            "name" => $projectName,
            "path" => $projectPath,
            "repo" => is_dir($projectPath . "/.git"),
            "text" => $projectName . " Loaded.",
            // While I don"t approve of user information being passed through the
            // project class, it seems significantly more effective to do so as
            // opposed to creating an entire process to pass lastLogin data to
            // the client when I can accomplish it by adding this line here.
            //			- Liam Siira
            "lastLogin" => $this->userData["lastLogin"]
        );

        Common::send(200, $reply);
    }

    //////////////////////////////////////////////////////////////////////////80
    // Open Project
    //////////////////////////////////////////////////////////////////////////80
    public function open($projectName, $projectPath) {
        $isAtheosIDE = ($projectName === "Atheos IDE" && $projectPath === BASE_PATH);
        $isWebRoot = ($projectName === "Web Root" && $projectPath === WEBROOT);

        if (!$isAtheosIDE && !$isWebRoot) {
            $projectPath = $this->db->select($projectName);
        }

        if ($projectName && $projectPath) {

            //PHPCoin - symlink examples
            $folder = $_SESSION['projectPath'];
            if(!file_exists($folder."/examples")) {
                $res = symlink(BASE_PATH . "/workspace/examples/demo", $folder."/examples");
            }

			SESSION("projectName", "PHPCoin Smart Contracts");
			SESSION("projectPath", $folder);
            //PHPCoin

            Common::send(200, array(
                "name" => $projectName,
                "path" => $projectPath,
                "repo" => is_dir($projectPath . "/.git"),
                "text" => $projectName . " Loaded."
            ));
        } else {
            Common::send(404, i18n("project_missing"));
        }
    }

    //////////////////////////////////////////////////////////////////////////80
    // Rename
    //////////////////////////////////////////////////////////////////////////80
    public function rename($oldName, $newName, $projectPath) {
        $newName = htmlspecialchars($newName);

        if (!empty($this->db->select($newName))) {
            Common::send(409, i18n("project_exists_name"));
        }

        $projectPath = $this->db->select($oldName);

        $this->db->insert($newName, $projectPath);
        $this->db->delete($oldName);

        // Log Action
        Common::log("@" . date("Y-m-d H:i:s") . ":\t{" . $this->activeUser . "} renamed project {$oldName} to {$newName}", "projects");
        Common::send(200);
    }

    //////////////////////////////////////////////////////////////////////////80
    // Sanitize Path
    //////////////////////////////////////////////////////////////////////////80
    public function sanitizePath($projectPath) {
        $projectPath = str_replace(" ", "_", $projectPath);
        return preg_replace("/[^\w-]/", "", $projectPath);
    }

    //////////////////////////////////////////////////////////////////////////80
    // Sanitize gitBranch
    //////////////////////////////////////////////////////////////////////////80
    public function sanitizeGitBranch($gitBranch) {
        return str_replace(array(
            "..",
            chr(40),
            chr(177),
            "~",
            "^",
            ":",
            "?",
            "*",
            "[",
            "@{",
            "\\"
        ), array(""), $gitBranch);
    }
}
