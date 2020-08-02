# ttrss-plugin-more-feed-data
TT-RSS plugin to store more data about feeds.  Currently only PostgreSQL is supported.

## Install
1. Copy the more_feed_data folder into your TT-RSS install's plugins.local folder.  Git clone preferably (then will probably have to rename the folder to `more_feed_data`).
2. Go to Preferences and enable the plugin.
3. When the Preferences page reloads, this extension will check your database and create the new table.  It produces some output that can be viewed in the extension's accordion pane.

## Upgrade
1. Replace all the files with their new ones.  Git pull preferably.
2. Load the Preferences page in TT-RSS.  This will cause it to check the database and upgrade the table if necessary.  The output of this can be viewed in the extension's accordion pane.

## How to use
The additional data is parsed and stored only after a feed is processed, so you will have to wait if you just installed the extension.  Probably a few will show up after 15 minutes or so if you have ~100 feeds; it all depends on each feed's update frequency though.

Summary statistics can be viewed on the Preferences accordion pane for the extension.  The stored values for a specific feed can be viewed on the Plugins tab of the Edit Feed window.

## Why
I want to hoard some old blog posts, and I think it makes sense to do that in RSS feed-reading software.  Wordpress allows pagination of RSS feeds so this idea will work for about 1/3 of blogs, which is enough to be interest to me.  So I need to know which blogs are run on Wordpress, and fortunately Wordpress populates the optional `<generated>` element in its feed documents.  But TT-RSS does not store the data from that field, so I made this extension to do it.

## Why not patch TT-RSS
You could say that for any extension I suppose.  But particularly:
1. This feature is unlikely to be useful to anyone else.
2. A database schema change is required so I feel the patch would require better than normal justification.
3. I have freedom to do weird things if I want to.

## How it works
### Database
A `ttrss_plugin_more_feed_data` table is created to store the extra feed data.  The version of this table is tracked using a value saved into the `ttrss_plugin_storage` table, which is accessed using `get()` and `set()` functions that are provided by the plugin host.  If the database schema needs to be updated, the appropriate numbered SQL files will be run to apply updates.

### Feed processing
After the feed data is retrieved it is sent to the extension which checks if the optional `<generated>` element is described.  The contents of the element and the values of its `uri` and `version` attributes are stored.  (Although they are described in the RSS and Atom standards, I have not observed any feeds that actually provide those attributes.)  The element contents are parsed for known formats and a "clean" name and version are extracted if possible.  For example, the contents `https://wordpress.org/?v=5.4.2` are stored in additional database columns `cleanGenerator` and `cleanGeneratorVersion` as `Wordpress` and `5.4.2`.

## My dev environment
I ran the "official" TT-RSS docker-compose script to set up an environment.  I added another service to it for pgAdmin to help out with monkeying in the database.  Docker is running in a Debian WSL2 environment.  The code on the TT-RSS app container can be accessed via the convulted path `\\wsl$\docker-desktop-data\mnt\wsl\docker-desktop-data\data\docker\volumes\ttrss-docker_app\_data`.  Debugging seems like it would be pretty difficult to figure out with so many layers, so I have just been using `print` statements.  I initally write most of the code to run inside of the plugin's Preference accordion pane where that printed stuff can be seen, and move it to run elsewhere when appropriate.  A `user_error()` function can log in to the TT-RSS database so I use that sometimes.

## The code is terrible
I only have "hacking-level" PHP skills.  A brush up on PHP scoping, types/values, and object-oriented design would work wonders.
