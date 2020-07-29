<?php
require 'SchemaManager.php';

class more_feed_data extends Plugin {

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
		print "<p>Installed version: " . SchemaManager::get_installed_schema_version($this->pdo, $owner_uid) . "</p>";
		print "</div>";
	}

	function api_version() {
		return 2;
	}
}
?>