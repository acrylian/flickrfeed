# flickrfeed
A [ZenphotoCMS](http://www.zenphoto.org) plugin to display latest images from Flickr.

It does use the public RSS feed and therefore only covers public content.

## Installation

Place the file `flickrfeed.php` into your `/plugins` folder, enable it and set the plugin options. 

Add `flickrFeed::printFreed(4);` to your theme where you want to display the latest images.

Note the plugin does just print an unordered list with linked thumbs and does not provide any default CSS styling. 
