<?php
/*
Plugin Name: My Technorati Tag Cloud
Plugin URI: http://blog.mericson.com/
Description: This will download your technorati tags and display them as a tag cloud
Author: Matt Ericson
Version: 2.00
Author URI: http://blog.mericson.com/

INSTRUCTIONS
============

This version uses wordpress sidebar widget

Just place this file in your plugins directory then enable it

Click on "Presentation" and then "Sidebar Widgets"

Drag this over to your side bar hit the configure button 
enter your user name and you are done

This version of the code will use WP 2.0 caching to use it correctly you
need to create a "wp-content/cache" directory that is writeable by the
Web user

*/


function technorati_tag_cloud_init() {
	// Check for the required API functions
	if ( !function_exists('register_sidebar_widget') || !function_exists('register_widget_control') ) {
		return;
	}


	function technorati_tag_cloud_contol () {
		$options = $newoptions = get_option('widget_technorati_tag_cloud_list');
		if ( $_POST['tag-cloud-submit'] ) {
			$newoptions['url']   = strip_tags(stripslashes($_POST['tag-cloud-url']));
			$newoptions['key']   = strip_tags(stripslashes($_POST['tag-cloud-key']));
			$newoptions['limit']   = strip_tags(stripslashes($_POST['tag-cloud-limit']));
			$newoptions['title']  = strip_tags(stripslashes($_POST['tag-cloud-title']));
		
			// Will preset the main url as the url to show
			if (strlen($newoptions['url'])  == 0) {
				$newoptions['url']  =  strip_tags(get_bloginfo('home'));
			}
		}
		
		if ( $options != $newoptions ) {
			$options = $newoptions;
			update_option('widget_technorati_tag_cloud_list', $options);
		}
		
        ?>
        <div style="text-align:right">
            <label for="tag-cloud-title" style="line-height:35px;display:block;">Title: <input type="text" id="tag-cloud-title" name="tag-cloud-title" value="<?php echo htmlspecialchars($options['title']); ?>" /></label>
            <label for="tag-cloud-url" style="line-height:35px;display:block;">Url: <input type="text" id="tag-cloud-url" name="tag-cloud-url" value="<?php echo htmlspecialchars($options['url']); ?>" /></label>
            <label for="tag-cloud-key" style="line-height:35px;display:block;"><a href="http://www.technorati.com/developers/apikey.html" target="_new">Api Key:</a> <input type="text" id="tag-cloud-key" name="tag-cloud-key" value="<?php echo htmlspecialchars($options['key']); ?>" /></label>
            <label for="tag-cloud-limit" style="line-height:35px;display:block;">Limit: <input type="text" id="tag-cloud-limit" name="tag-cloud-limit" value="<?php echo htmlspecialchars($options['limit']); ?>" /></label>
              
            <input type="hidden" name="tag-cloud-submit" id="tag-cloud-submit" value="Save" />
            <input type="submit" value="Save" />
        </div>
        <?php
	}

	function technoratiTagCloud() {

		$options = get_option('widget_technorati_tag_cloud_list');

		$cache_time = 600;
		$url    = $options['url'];
		$apiKey = $options['key'];
		$limit  = $options['limit'];
		$title  = $options['title'];

		if (!$url || !$apiKey) {
			return false;
		}
		
		if (!$limit) {
			$limit = 20;
		}
		$url = urlencode($url);
		$api_url = "http://api.technorati.com/blogposttags?url=" . $url . "&key=" . $apiKey . "&limit=" . $limit;

		$cacheKey = "techtagcloud.$url.$limit";

		wp_cache_init();
		$data = wp_cache_get($cacheKey);

		if (! $data) {
			$c = curl_init($api_url);
			curl_setopt($c, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt($c, CURLOPT_TIMEOUT, 4);
			curl_setopt($c, CURLOPT_USERAGENT, "Technorati tagcloud plugin");
			$response = curl_exec($c);
			$info = curl_getinfo($c);

			$curl_error_code = $info['http_code'];
			curl_close($c);
			if ($curl_error_code == 200) {
				$data = $response;

				wp_cache_set($cacheKey,$data,'',$cache_time);
				wp_cache_close();
			} elseif ($curl_error_code) {
				//do something here
			} else {
				//do something here
			}
		}
		//Now I parse and show the responce


		$xml = XML_unserialize($data);


		$items = $xml['tapi']['document']['item'];
		if (!isset($items)) {
			return false;
		}
		if (!isset($items[0])) {
			$tmp = $items;
			unset($items);
			$items[0] = $tmp;
			unset ($tmp);
		}


		$max = 0;
		foreach ($items as $item ) {
			$tagData[$item['tag']] = $item['posts'] ;
			$min = $item['posts'];
			if ($item['posts'] > $max) {
				$max = $item['posts'];
			}
		}

		$rangemin   = 0;  // was 9 for font-size
		$rangemax   = 15; // was 24 for font-size


		$difference = log($max) - log($min);
		$scale = $difference;

		if ($max==$min) $scale=1.0;
		$scale = $scale / ($rangemax-$rangemin);

		echo "<style type='text/css'>
.heatmap {font-size: 0.97em;}
.heatmap li {display: inline;}
.heatmap em {font-style: normal; font-size: 1.03em;}
</style>
";

		if (sizeof($tagData)) {
			ksort($tagData);
			if (strlen($title)) {
				echo "<h2 class=\"widgettitle\">$title</h2>";
			}
			echo "<ul class=\"heatmap\" id=\"bigheatmap\">\n";

			foreach($tagData as $tag=>$posts){

				$heat = min($rangemax,$rangemin+round((log($posts) - log($min)) / $scale));

				$link = urlencode($tag);
				$disp = htmlspecialchars($tag);
				$link = str_replace("%2F", "/", $link);

				echo "<li>";

				for ($i=0;$i<$heat;$i++) {
					echo "<em>";
				}

				echo "<a href=\"http://technorati.com/tag/{$link}?from={$url}\">{$disp}</a>";


				for ($i=0;$i<$heat;$i++) {
					echo "</em>";
				}

				echo "... </li>\n";
			}
			echo "</ul>\n";
		}

	}


	register_sidebar_widget('Technorati Tag Cloud', 'technoratiTagCloud');
	register_widget_control('Technorati Tag Cloud', 'technorati_tag_cloud_contol');
}

add_action('plugins_loaded', 'technorati_tag_cloud_init');
###################################################################################
# XML class: utility class to be used with PHP's XML handling functions
###################################################################################
class XML{
    var $parser;   #a reference to the XML parser
    var $document; #the entire XML structure built up so far
    var $parent;   #a pointer to the current parent - the parent will be an array
    var $stack;    #a stack of the most recent parent at each nesting level
    var $last_opened_tag; #keeps track of the last tag opened.

    function XML(){
        $this->parser = &xml_parser_create();
        xml_parser_set_option(&$this->parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_object(&$this->parser, &$this);
        xml_set_element_handler(&$this->parser, 'open','close');
        xml_set_character_data_handler(&$this->parser, 'data');
    }
    function destruct(){ xml_parser_free(&$this->parser); }
    function & parse(&$data){
        $this->document = array();
        $this->stack    = array();
        $this->parent   = &$this->document;
        return xml_parse(&$this->parser, &$data, true) ? $this->document : NULL;
    }
    function open(&$parser, $tag, $attributes){
        $this->data = ''; #stores temporary cdata
        $this->last_opened_tag = $tag;
        if(is_array($this->parent) and array_key_exists($tag,$this->parent)){ #if you've seen this tag before
            if(is_array($this->parent[$tag]) and array_key_exists(0,$this->parent[$tag])){ #if the keys are numeric
                #this is the third or later instance of $tag we've come across
                $key = count_numeric_items($this->parent[$tag]);
            }else{
                #this is the second instance of $tag that we've seen. shift around
                if(array_key_exists("$tag attr",$this->parent)){
                    $arr = array('0 attr'=>&$this->parent["$tag attr"], &$this->parent[$tag]);
                    unset($this->parent["$tag attr"]);
                }else{
                    $arr = array(&$this->parent[$tag]);
                }
                $this->parent[$tag] = &$arr;
                $key = 1;
            }
            $this->parent = &$this->parent[$tag];
        }else{
            $key = $tag;
        }
        if($attributes) $this->parent["$key attr"] = $attributes;
        $this->parent  = &$this->parent[$key];
        $this->stack[] = &$this->parent;
    }
    function data(&$parser, $data){
        if($this->last_opened_tag != NULL) #you don't need to store whitespace in between tags
        $this->data .= $data;
    }
    function close(&$parser, $tag){
        if($this->last_opened_tag == $tag){
            $this->parent = $this->data;
            $this->last_opened_tag = NULL;
        }
        array_pop($this->stack);
        if($this->stack) $this->parent = &$this->stack[count($this->stack)-1];
    }
}
function count_numeric_items(&$array){
    return is_array($array) ? count(array_filter(array_keys($array), 'is_numeric')) : 0;
}
function & XML_unserialize(&$xml){
    $xml_parser = &new XML();
    $data = &$xml_parser->parse($xml);
    $xml_parser->destruct();
    return $data;
}
?>
