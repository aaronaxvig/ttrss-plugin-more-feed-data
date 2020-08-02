<?php
require 'SchemaManager.php';

class more_feed_data extends Plugin {
	static $REQUIRED_SCHEMA_VERSION = 3;
	static $KEY_SCHEMA_VERSION = "schema_version";
	static $PLUGIN_FOLDER = "plugins.local/more_feed_data";

	function drop_table() {
		print "Dropping table";
		$sth = $this->pdo->prepare("DROP TABLE IF EXISTS ttrss_plugin_more_feed_data;");
		if ($sth->execute()) {
			print "<p>Table dropped.</p>";
			return true;
		}
		else {
			print "<p>Table drop failed.</p>";
			return false;
		}
	}

	function create_table() {
		print "Creating table";
		$sth = $this->pdo->prepare(file_get_contents("plugins.local/more_feed_data/schema/more_feed_data_schema_pgsql.sql"));
		if ($sth->execute()) {
			print "<p>Table created, setting installed schema version.</p>";
			$this->host->set($this, $this::$KEY_SCHEMA_VERSION, 1);
			print "<p>Installed version set: " . $this->host->get($this, $this::$KEY_SCHEMA_VERSION) . "</p>";
			return true;
		}
		else {
			print "<p>Table creation failed.</p>";
			return false;
		}
	}

	function check_database() {
		$sth = $this->pdo->prepare("SELECT EXISTS(SELECT * FROM information_schema.tables WHERE table_name = 'ttrss_plugin_more_feed_data');");
		$sth->execute();
		$database_table_exists = $sth->fetch(PDO::FETCH_ASSOC)['exists'];

		if (!$database_table_exists) {
			$database_table_exists = $this->create_table();
		}

		if ($database_table_exists) {
			if(!$this->host->get($this, $this::$KEY_SCHEMA_VERSION)) {
				// Database exists but version key is gone.
				$this->drop_table();
				$database_table_exists = $this->create_table();
			}
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
	}

	function processFeed($feed_data, $fetch_url) {
		$doc = new DOMDocument();
		$doc->loadXML($feed_data);

		$root = $doc->firstChild;
		$xpath = new DOMXPath($doc);
		$xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
		$xpath->registerNamespace('atom03', 'http://purl.org/atom/ns#');
		$xpath->registerNamespace('media', 'http://search.yahoo.com/mrss/');
		$xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
		$xpath->registerNamespace('slash', 'http://purl.org/rss/1.0/modules/slash/');
		$xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
		$xpath->registerNamespace('content', 'http://purl.org/rss/1.0/modules/content/');
		$xpath->registerNamespace('thread', 'http://purl.org/syndication/thread/1.0');

		$root = $xpath->query("(//atom03:feed|//atom:feed|//channel|//rdf:rdf|//rdf:RDF)");

		$generator;
		$generatorUri;
		$generatorVersion;
		$cleanGenerator;
		$cleanGeneratorVersion;

		if ($root && $root->length > 0) {
			$root = $root->item(0);

			if ($root) {
				$generatorNode;

				switch (mb_strtolower($root->tagName)) {
					case "rdf:rdf":
						break;
					case "channel":
						$generatorNode = $xpath->query("//channel/generator")->item(0);
						break;
					case "feed":
					case "atom:feed":
						$generatorNode = $xpath->query("//atom:feed/atom:generator")->item(0);

						if (!$generatorNode) {
							$generatorNode = $xpath->query("//atom03:feed/atom03:generator")->item(0);
						}
						break;
					default:
						//printf("Unknown/unsupported feed type");
					return;
				}

				if ($generatorNode) {
					$generator = $generatorNode->nodeValue;
					$generatorUri = $generatorNode->getAttribute("uri");
					$generatorVersion = $generatorNode->getAttribute("version");

					// If a version is supplied, we can keep it as "clean".
					if($generatorVersion) {
						$cleanGeneratorVersion = $generatorVersion;
					}

					if(strpos($generator, "https://wordpress.org/?v=") === 0) {
						// https://wordpress.org/?v=5.4.2
						$cleanGenerator = "Wordpress";
						$cleanGeneratorVersion = str_replace("https://wordpress.org/?v=", "", $generator);
					}
					else if(strpos($generator, "http://wordpress.com/") === 0) {
						// http://wordpress.com/
						$cleanGenerator = "Wordpress";
					}
					else if(strpos($generator, "Blogger") === 0) {
						// Blogger
						$cleanGenerator = "Blogger";
					}
					else if(strpos($generator, "GatsbyJS") === 0) {
						// GatsbyJS
						$cleanGenerator = "GatsbyJS";
					}
					else if(strpos($generator, "Ghost ") === 0) {
						// Ghost 3.26
						$cleanGenerator = "Ghost";
						$cleanGeneratorVersion = str_replace("Ghost ", "", $generator);
					}
					else if(strpos($generator, "Hexo") === 0) {
						// Hexo
						$cleanGenerator = "Hexo";
					}
					else if(strpos($generator, "Hugo") === 0) {
						// Hugo -- gohugo.io
						$cleanGenerator = "Hugo";
					}
					else if(strpos($generator, "Jekyll") === 0) {
						$cleanGenerator = "Jekyll";
					}
					else if(strpos($generator, "Jekyll v") === 0) {
						$cleanGenerator = "Jekyll";
						$cleanGeneratorVersion = str_replace("Jekyll v", "", $generator);
					}
					else if(strpos($generator, "JSitemap Pro") === 0) {
						// JSitemap Pro
						$cleanGenerator = "Joomla!";
					}
					else if(strpos($generator, "Medium") === 0) {
						// Medium
						$cleanGenerator = "Medium";
					}
					else if(strpos($generator, "Movable Type Pro") === 0) {
						// Movable Type Pro
						$cleanGenerator = "Movable Type";
					}
					else if(strpos($generator, "newtelligence dasBlog ") === 0) {
						// newtelligence dasBlog 4.0.0.0
						$cleanGenerator = "dasBlog";
						$cleanGeneratorVersion = str_replace("newtelligence dasBlog ", "", $generator);
					}
					else if(strpos($generator, "oldSchool v") === 0) {
						// oldSchool v0.5.59
						$cleanGenerator = "oldSchool";
						$cleanGeneratorVersion = str_replace("oldSchool v", "", $generator);
					}
					else if(strpos($generator, "PyNITLog") === 0) {
						// PyNITLog
						$cleanGenerator = "PyNITLog";
					}
					else if(strpos($generator, "Site-Server v") === 0) {
						// Site-Server v6.0.0-25071-25071 (http://www.squarespace.com)
						$cleanGenerator = "SquareSpace Site-Server";
						$trimmedStart = str_replace("Site-Server v", "", $generator);
						$cleanGeneratorVersion = substr($trimmedStart, 0, strpos($trimmedStart, " (http://www.squarespace.com)"));
					}
					else if(strpos($generator, "Substack") === 0) {
						// Substack
						$cleanGenerator = "Substack";
					}
					else if(strpos($generator, "Svbtle.com") === 0) {
						// Svbtle.com
						$cleanGenerator = "Svbtle";
					}
					else if(strpos($generator, "TypePad") === 0) {
						// TypePad
						$cleanGenerator = "TypePad";
					}

					$sth = $this->pdo->prepare("
						WITH feed AS (
							SELECT id FROM ttrss_feeds WHERE feed_url = :feedUrl)
						INSERT INTO ttrss_plugin_more_feed_data (feedid, generator, generatoruri, generatorversion, cleanGenerator, cleanGeneratorVersion) 
							SELECT id, :generator, :generatorUri, :generatorVersion, :cleanGenerator, :cleanGeneratorVersion
							FROM feed
						ON CONFLICT (feedId) DO UPDATE SET
							generator = EXCLUDED.generator,
							generatorUri = EXCLUDED.generatorUri,
							generatorVersion = EXCLUDED.generatorVersion,
							cleanGenerator = EXCLUDED.cleanGenerator,
							cleanGeneratorVersion = EXCLUDED.cleanGeneratorVersion;");
					if ($sth->execute(array(
						':feedUrl' => $fetch_url,
						':generator' => $generator,
						':generatorUri' => $generatorUri,
						':generatorVersion' => $generatorVersion,
						':cleanGenerator' => $cleanGenerator,
						':cleanGeneratorVersion' => $cleanGeneratorVersion))) {
						//print("<p>Inserted/Updated</p>");
					}
				}
				else {
					// TODO: blank the values in the database.
					//print("<p>Generator node not found.</p>");
				}
			}

		} else {
				user_error("Unknown/unsupported feed type", E_USER_NOTICE);
		}
	}

	function about() {
		return array(1.1,
			"Stores more feed data",
			"Aaron Axvig",
			false,
			"https://github.com/aaronaxvig/ttrss-plugin-more-feed-data");
	}

	function init($host) {
		$this->host = $host;
		$host->add_hook($host::HOOK_FEED_FETCHED, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
	}

	function hook_prefs_edit_feed($feed_id) {
		printf("<h2>More feed data</h2>");

		$sth = $this->pdo->prepare("SELECT * FROM public.ttrss_plugin_more_feed_data WHERE feedId = :feedId;");
		if ($sth->execute(array(
			':feedId' => $feed_id))) {
			$row = $sth->fetch();
			printf("<ol>");
			printf("<li>Generator: %s</li>", $row['generator']);
			printf("<li>Generator URI: %s</li>", $row['generatoruri']);
			printf("<li>Generator version: %s</li>", $row['generatorversion']);
			printf("<li>Generator (clean): %s</li>", $row['cleangenerator']);
			printf("<li>Generator version (clean): %s</li>", $row['cleangeneratorversion']);
			printf("</ol>");
		}
		else {
			printf("<p>Failed to retrieve data for feedId %s</p>", $feed_id);
		}
	}

	function hook_feed_fetched($feed_data, $fetch_url, $owner_uid, $feed) {
		$this->processFeed($feed_data, $fetch_url);

		return $feed_data;
	}

	function hook_prefs_tab($args) {
		if ($args != "prefPrefs") return;

		print "<div dojoType='dijit.layout.AccordionPane' title=\"<i class='material-icons'>storage</i> More Feed Data plugin\">";

		print "<h2>Database schema</h2>";

		$this->check_database();

		// For testing...
		$fetch_url = "https://wordpress.org/feed/";
		$feed_data = file_get_contents("plugins.local/more_feed_data/sampleFeed.xml");

		//$this->processFeed($feed_data, $fetch_url);
		// End for testing...
		

		print "</div>";
	}

	function api_version() {
		return 2;
	}
}
?>