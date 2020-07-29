<?php

class SchemaManager {

	private $schema_version = 1;
	private $schema_version_store_key = "more_feed_data_schema_version";

	public static function check_schema_version() {
		// Get version from ttrss_plugin_storage
		

		// If matches, return true.

		// If not matches, upgrade

		// If fails to upgrade, return false.
	}

	public static function get_installed_schema_version($pdo, $owner_uid) {
		$sth = $pdo->prepare("SELECT content FROM ttrss_plugin_storage WHERE name = 'more_feed_data_schema_version' AND owner_uid = 1");
		$sth->execute();

		return $sth->fetch(PDO::FETCH_ASSOC)['content'];
	}

	public static function get_required_schema_version() {
		return $schema_version;
	}
}
?>