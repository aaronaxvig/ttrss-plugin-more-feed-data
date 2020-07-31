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

		$fetch_url = "https://wordpress.org/feed/";
		$sampleRss = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
		<rss version=\"2.0\">
		<channel>
		 <title>RSS Title</title>
		 <description>This is an example of an RSS feed</description>
		 <link>http://www.example.com/main.html</link>
		 <lastBuildDate>Mon, 06 Sep 2010 00:01:00 +0000 </lastBuildDate>
		 <pubDate>Sun, 06 Sep 2009 16:20:00 +0000</pubDate>
		 <ttl>1800</ttl>
		 <generator uri=\"generatorUri\" version=\"generatorVersion\">testGenerator</generator>
		
		 <item>
		  <title>Example entry</title>
		  <description>Here is some text containing an interesting description.</description>
		  <link>http://www.example.com/blog/post/1</link>
		  <guid isPermaLink=\"false\">7bd204c6-1655-4c27-aeee-53f933c5395f</guid>
		  <pubDate>Sun, 06 Sep 2009 16:20:00 +0000</pubDate>
		 </item>
		
		</channel>
		</rss>";

		$doc = new DOMDocument();
		$doc->loadXML($sampleRss);

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
						printf("Unknown/unsupported feed type");
					return;
				}

				if ($generatorNode) {
					$generator = $xpath->query("//channel/generator")->item(0)->nodeValue;
					$generatorUri = $xpath->query("//channel/generator")->item(0)->getAttribute("uri");
					$generatorVersion = $xpath->query("//channel/generator")->item(0)->getAttribute("version");
					printf("<p>Generator: %s</p>", $generator);
					printf("<p>Generator URI: %s</p>", $generatorUri);
					printf("<p>Generator version: %s</p>", $generatorVersion);
					$sth = $this->pdo->prepare("
						WITH feed AS (
							SELECT id FROM ttrss_feeds WHERE feed_url = :feedUrl)
						INSERT INTO ttrss_plugin_more_feed_data (feedid, generator, generatoruri, generatorversion) 
							SELECT id, :generator, :generatorUri, :generatorVersion
							FROM feed
						ON CONFLICT (feedId) DO UPDATE SET
							generator = EXCLUDED.generator,
							generatorUri = EXCLUDED.generatorUri,
							generatorVersion = EXCLUDED.generatorVersion;");
					if ($sth->execute(array(
						':feedUrl' => $fetch_url,
						':generator' => $generator,
						':generatorUri' => $generatorUri,
						':generatorVersion' => $generatorVersion))) {
						print("<p>Inserted/Updated</p>");
					}
				}
				else {
					// TODO: remove table row if it exists
					print("<p>Generator node not found.</p>");
				}
			}

		} else {
				user_error("Unknown/unsupported feed type", E_USER_NOTICE);
		}

		print "</div>";
	}

	function api_version() {
		return 2;
	}
}
?>