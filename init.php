<?php
require 'SchemaManager.php';

class more_feed_data extends Plugin {
	static $REQUIRED_SCHEMA_VERSION = 2;
	static $KEY_SCHEMA_VERSION = "schema_version";
	static $PLUGIN_FOLDER = "plugins.local/more_feed_data";

	function about() {
		return array(1.0,
			"Stores more feed data",
			"Aaron Axvig",
			false,
			"https://github.com/aaronaxvig/ttrss-plugin-more-feed-data");
	}

	function init($host) {
		$this->host = $host;
		$host->add_hook($host::HOOK_FEED_FETCHED, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
	}

	function hook_feed_fetched($feed_data, $fetch_url, $owner_uid, $feed) {
		
	}

	function hook_prefs_tab($args) {
		if ($args != "prefPrefs") return;

		print "<div dojoType='dijit.layout.AccordionPane' title=\"<i class='material-icons'>storage</i> More Feed Data plugin\">";

		print "<h2>Database schema</h2>";

		$sth = $this->pdo->prepare("SELECT EXISTS(SELECT * FROM information_schema.tables WHERE table_name = 'ttrss_plugin_more_feed_data');");
		$sth->execute();
		$database_table_exists = $sth->fetch(PDO::FETCH_ASSOC)['exists'];

		if ($database_table_exists) {
			print "<p>Database table exists.</p>";
			$installed_version = $this->host->get($this, $this::$KEY_SCHEMA_VERSION);
			print "<p>Installed version: " . $installed_version . "</p>";
			printf("<p>Required version: %d</p>", $this::$REQUIRED_SCHEMA_VERSION);

			while ($installed_version < $this::$REQUIRED_SCHEMA_VERSION) {
				$version_to_install = $installed_version + 1;
				$upgrade_file = "plugins.local/more_feed_data/schema/versions/pgsql/" . $version_to_install . ".sql";

				printf("<p>Running upgrade %d -> %d: %s</p>", $installed_version, $version_to_install, $upgrade_file);
				$sth = $this->pdo->prepare(file_get_contents($upgrade_file));
				if ($sth->execute()) {
					$installed_version = $version_to_install;
					$this->host->set($this, $this::$KEY_SCHEMA_VERSION, $installed_version);
					printf("Schema upgraded to %d", $this->host->get($this, $this::$KEY_SCHEMA_VERSION));
				}
				else {
					printf("<p>Schema failed to upgrade to %d.", $version_to_install);
				}
			}
		}
		else {
			print "Creating table";
			$sth = $this->pdo->prepare(file_get_contents("plugins.local/more_feed_data/schema/more_feed_data_schema_pgsql.sql"));
			if ($sth->execute()) {
				print "<p>Table created, setting installed schema version.</p>";
				$this->host->set($this, $this::$KEY_SCHEMA_VERSION, 1);
				print "<p>Installed version set: " . $this->host->get($this, $this::$KEY_SCHEMA_VERSION) . "</p>";
			}
			else {
				print "<p>Table creation failed.</p>";
			}
		}
		print "</div>";
	}

	function api_version() {
		return 2;
	}
}
?>