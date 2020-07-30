CREATE TABLE ttrss_plugin_more_feed_data (
    id SERIAL NOT NULL PRIMARY KEY,
	feedId INTEGER NOT NULL REFERENCES ttrss_feeds(id) ON DELETE CASCADE,
    generator TEXT,
    generatorUri TEXT,
    UNIQUE(feedId));