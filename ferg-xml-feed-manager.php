<?php
/**
 * @wordpress-plugin
 * Plugin Name: Ferg XML Feed Manager
 * Plugin URI: https://fai2.com
 * Description: A module for pulling and caching an xml feed at certain intervals
 * Version: 1.0.0
 * Author: Ferguson Advertising (Eric Sexton)
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version. (This is also a way to check if the plugin is installed at all)
 */
define( 'FXMLM', '1.0.0' );

//function to call in the templates
function ferg_get_event_xml_feed()
{
	$plugin_path = plugin_dir_path( __FILE__ );
	log_dump($plugin_path);
	if (!file_exists($plugin_path . "/xml.json"))
		do_action('ferg_cron_get_event_xml_feed');

	$strjson = file_get_contents($plugin_path . "/xml.json");
	
	$json = json_decode($strjson);
	
	return $json;
}

//cron job
add_action('ferg_cron_get_event_xml_feed', 'ferg_cron_get_event_xml_feed', 10, 0);
wp_schedule_event( 1596016800, 'hourly', 'ferg_cron_get_event_xml_feed');
function ferg_cron_get_event_xml_feed()
{
	$plugin_path = plugin_dir_path( __FILE__ );

	//grab xml feed
	$xmlstr = file_get_contents(getenv('XML_FEED_LINK'));
	
	//parse xml
	$xml = new SimpleXMLElement($xmlstr);
	
	//converts xml to just normal php array
	$jsonstr = json_encode($xml);
	$json = json_decode($jsonstr,TRUE);

	//events array
	$events = $json["events"]["event"];
	
	$filtered_events = array_filter($events, "ferg_events_category_filter");

	$jsonFile = fopen($plugin_path . "/xml.json", "w");
	fwrite($jsonFile, json_encode($filtered_events));
	fclose($jsonFile);

	//!REMOVE: THIS IS FOR TESTING!
	$log_arr = json_decode(file_get_contents($plugin_path . "/log.json"));
	array_push($log_arr, date("Y-M-d H:i:s"));
	$testFile = fopen($plugin_path . "/log.json", "w");
	fwrite($testFile, json_encode($log_arr));
	fclose($testFile);

	return $filtered_events;
}

//filter for the cron job
function ferg_events_category_filter($event)
{
	$ferg_cat_filter = array(
		91, //visual arts
		92, //music
		93, //cinema
		94, //heritage
		95, //theatre
		96, //festival
		97, //dance,
		100, //community arts
	);
	
	if (!isset($event['eventcategories']) || !isset($event['eventcategories']['eventcategory'])) return false;

	$categories = $event['eventcategories']['eventcategory'];

	if (is_iterable($categories))
	{
		foreach($categories as $cat )
		{
			if (isset($cat['categoryid']))
			{
				foreach($ferg_cat_filter as $cat_id)
				{
					if ($cat['categoryid'] == $cat_id) return true;
				}
			}
		}
	}else{ //manage weird artifact from converting from xml
		if (!isset($categories['categoryid'])) return false;
		
		foreach($ferg_cat_filter as $cat_id)
		{
			if ($categories['categoryid'] == $cat_id) return true;
		}
	}

	return false;
}
