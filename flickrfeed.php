<?php

/**
 * A simple ZenphotoCMS plugin to display the latest public images from a Flickr account
 * 
 * It does use the public RSS feed and therefore only covers public content.
 * 
 * ## Installation
 * 
 * Place the file `flickrfeed.php` into your `/plugins` folder, enable it and set the plugin options.
 * 
 * Add `flickrFeed::printFreed(4);` to your theme where you want to display the latest images.
 * 
 * Note the plugin does just print an unordered list with linked thumbs and does not provide any default CSS styling. 
 * 
 * @author Malte Müller (acrylian)
 * @licence GPL v3 or later
 */
$plugin_description = gettext('A simple plugin to display the latest public images from a Flickr account');
$plugin_author = 'Malte Müller (acrylian)';
$plugin_version = '1.0';
$plugin_category = gettext('Media');
$option_interface = 'flickrFeedOptions';

class flickrFeedOptions {

	function __construct() {
		setOptionDefault('flickrfeed_cachetime', 86400);
		purgeOption('flickrfeed_cache');
		purgeOption('flickrfeed_lastmod');
	}

	function getOptionsSupported() {
		return array(
				gettext('Flickr User UD') => array(
						'key' => 'flickrfeed_userid',
						'type' => OPTION_TYPE_TEXTBOX,
						'order' => 1,
						'desc' => gettext('The user id of your Flickr account to fetch')),
				gettext('Cache time') => array(
						'key' => 'flickrfeed_cachetime',
						'type' => OPTION_TYPE_TEXTBOX,
						'order' => 1,
						'desc' => gettext('The time the cache is kept until the data is fetched freshly'))
		);
	}

}

/**
 * Class to fetch latest images from a Flickr account as rss2
 */
class flickrFeed {
	
	/**
	 * Returns an array with the feed information, either freshly or from cache.
	 * 
	 * @return array
	 */
	static function getFeed() {
		require_once(SERVERPATH . '/' . ZENFOLDER . '/' . PLUGIN_FOLDER . '/zenphoto_news/rsslib.php');
		$userid = trim(getOption('flickrfeed_userid'));
		if ($userid) {
			$feedurl = 'https://api.flickr.com/services/feeds/photos_public.gne?id=' . sanitize($userid) . '&format=rss2';
			$cache = flickrFeed::getCache();
			$lastmod = flickrFeed::getLastMod();
			$cachetime = getOption('flickrfeed_cachetime');
			if (empty($cache) || (time() - $lastmod) > $cachetime) {
				$content = RSS_Retrieve($feedurl);
				flickrFeed::saveCache($content);
				flickrFeed::saveLastMod();
				return $content;
			} else {
				return $cache;
			}
		}
		return array();
	}

	/**
	 * Prints a list of images from a users flickrFeed
	 * 
	 * Notes: 
	 * The feed is always fetched compeltely as Flickr provides no limit here. The $number parameter is used internally only.
	 * Also flickr provides the images thumbnail size, there is no sizing available other than fake resizing using CSS.
	 * 
	 * @param int $number The number of images to display
	 * @param string $class default "flickrfeed" to use the default styling
	 */
	static function printFeed($number = 4, $class="flickrfeed") {
		$content = flickrFeed::getFeed();
		$count = '';
		if ($content) {
			?>
			<ul class="<?php echo html_encode($class); ?>">
				<?php
				foreach ($content as $item) {
					//echo "<pre>"; print_r($item); echo "</pre>";
					$thumb = flickrfeed::getItemLinkAndThumb($item);
					if ($thumb) {
						$count++;
						echo '<li>' .$thumb . '</li>';
						if ($count == $number) {
							break;
						}
					}
				}
				?>
						</ul>
			<?php
		}
	}
	
	/**
	 * Return <a><img></a> HTML of the image posted
	 * 
	 * @param array $item  The item array 
	 */
	static function getItemLinkAndThumb($item) {
		$expl = explode('<p>', $item['description']);
		if (array_key_exists(2, $expl)) {
			return $expl[2];
		}
	}

	/**
	 * Returns the image description wrapped in a paragraph.
	 * @param array $item  The item array 
	 */
	static function getItemDescription($item) {
		$expl = explode('<p>', $item['description']);
		if (array_key_exists(3, $expl)) {
			return $expl[3];
		}
	}

	/**
	 * Returns the image description wrapped in a paragraph.
	 * @param array $item  The item array 
	 */
	static function getItemDate($item) {
		return zpFormattedDate(DATE_FORMAT, strtotime($item['pubDate']));
	}
	
	/**
	 * Gets the content from cache if available
	 * @return array
	 */
	static function getCache() {
		$cache = query_single_row('SELECT data FROM ' . prefix('plugin_storage') . ' WHERE `type` = "flickrfeed" AND `aux` = "flickrfeed_cache"');
		if($cache) {
			return unserialize($cache['data']);
		}
		return false;
	}
	
	/**
	 * Stores the content in cache
	 * @param array $content
	 */
	static function saveCache($content) {
		$hascache = flickrfeed::getCache();
		$cache = serialize($content);
		if($hascache) {
			$sql = 'UPDATE ' . prefix('plugin_storage') . ' SET `data`=' . db_quote($cache) . ' WHERE `type`="flickrfeed" AND `aux` = "flickrfeed_cache"';
		} else {
			$sql = 'INSERT INTO ' . prefix('plugin_storage') . ' (`type`,`aux`,`data`) VALUES ("flickrfeed", "flickrfeed_cache",' . db_quote($cache) . ')';
		}
		query($sql);
	}
	
	/**
	 * Returns the time of the last caching
	 * @return int
	 */
	static function getLastMod() {
		$lastmod = query_single_row('SELECT data FROM ' . prefix('plugin_storage') . ' WHERE `type`="flickrfeed" AND `aux` = "flickrfeed_lastmod"');
		if($lastmod) {
			return $lastmod['data'];
		}
		return false;
	}
	
	/**
	 * Sets the last modification time
	 * 
	 * @param int $lastmod Time (time()) of the last caching
	 */
	static function saveLastmod() { 
		$haslastmod = flickrfeed::getLastMod();
		$lastmod = time();
		if($haslastmod) {
			$sql = 'UPDATE ' . prefix('plugin_storage') . ' SET `data` = ' . $lastmod . ' WHERE `type`="flickrfeed" AND `aux` = "flickrfeed_lastmod"';
		} else {
			$sql = 'INSERT INTO ' . prefix('plugin_storage') . ' (`type`,`aux`,`data`) VALUES ("flickrfeed", "flickrfeed_lastmod",' . $lastmod . ')';
		}
		query($sql);
	}

}
