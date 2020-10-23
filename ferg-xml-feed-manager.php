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



//main class
class FERG_XML_MANAGER
{
	const DEBUG_MODE = false;
	const CACHE_SAVE_PATH = __DIR__ . "/xml.json";
	const LOG_PATH = __DIR__ . "/log.json";


	public function __construct()
	{
		$this->register_hooks();
	}

	private function register_hooks()
	{
		add_action('ferg_cron_get_event_xml_feed', [$this, 'cron_cache_xml_feed'], 10, 0);
		wp_schedule_event( 1596016800, 'hourly', 'ferg_cron_get_event_xml_feed');
	}

	public function cron_cache_xml_feed()
	{
		$json = $this->get_xml_feed_as_json(getenv('XML_FEED_LINK'));

		//events array (this is for artsunited)
		$events = $json["events"]["event"];
		
		//filter events array (this is for artsunited)
		$filtered_events = array_filter($events, [$this, "events_category_filter"]);
	
		//write it to file
		$this->save_feed_to_cache($filtered_events);

		if (FERG_XML_MANAGER::DEBUG_MODE)
		{
			$this->log_time();
		}
	}


	private function get_xml_feed_as_json($xml_link)
	{
		//grab xml feed
		$xmlstr = $this->query_xml_feed($xml_link);
		
		//parse xml
		$xml = new SimpleXMLElement($xmlstr);
		
		//converts xml to just normal php array
		$jsonstr = json_encode($xml);


		//This pattern limits the character set to only ascii
		$re = '/[^\x00-\x7F]+/m';
		$jsonstr = preg_replace($re, "", $jsonstr);

		//this pattern removes hardcoded unicode characters
		$re = '/\\\\u[^\ ]+/m';
		$jsonstr = preg_replace($re, "", $jsonstr);


		$json = json_decode($jsonstr, true);



		return $json;
	}

	public function query_xml_feed($xml_link)
	{
		$method = "GET";
		$ch = curl_init();

		curl_setopt_array($ch, [
			CURLOPT_URL => $xml_link,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_RETURNTRANSFER => 1,
		]);

		$response = curl_exec($ch);
		curl_close($ch);

		return $response;
	}

	private function save_feed_to_cache($jsonObject)
	{
		//open file
		$jsonFile = fopen(FERG_XML_MANAGER::CACHE_SAVE_PATH, "w");
		//write file
		fwrite($jsonFile, json_encode($jsonObject));
		//close file
		fclose($jsonFile);
	}
	
	private function log_time()
	{
		//check if log file exist, if so grab content, if not, create empty content
		if (file_exists(FERG_XML_MANAGER::LOG_PATH))
		{
			$log_arr = json_decode(file_get_contents( FERG_XML_MANAGER::LOG_PATH ));
		}else{
			$log_arr = array();
		}
		//add current time to log
		array_push($log_arr, date("Y-M-d H:i:s"));
		//open log file
		$logFile = fopen(FERG_XML_MANAGER::LOG_PATH, "w");
		//write to log file
		fwrite($logFile, json_encode($log_arr));
		//close log file
		fclose($logFile);
	}

	//php array filter
	private function events_category_filter($event)
	{
		$ferg_cat_filter = array(
			90, //arts united
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
	
		if (isset($categories[0]) && is_array($categories[0]))
		{
			// log_dump($categories);
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

			log_dump("MADE IT");
			foreach($ferg_cat_filter as $cat_id)
			{
				if ($categories['categoryid'] == $cat_id) return true;
			}
		}
	
		return false;
	}
}

$FERG_XML_MANAGER = new FERG_XML_MANAGER();

//function to call in the templates
function ferg_get_event_xml_feed()
{
	if (!file_exists(FERG_XML_MANAGER::CACHE_SAVE_PATH))
		do_action('ferg_cron_get_event_xml_feed');

	$strjson = file_get_contents(FERG_XML_MANAGER::CACHE_SAVE_PATH);
	$json = json_decode($strjson, true);
	
	return $json;
}
