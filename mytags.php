<?php
/*
Plugin Name: My Technorati Tag Cloud
Plugin URI: http://blog.mericson.com/
Description: This will download your technorati tags and display them as a tag cloud 
Author: Matt Ericson
Version: 0.01
Author URI: http://blog.mericson.com/

INSTRUCTIONS
============

Add the following code to your template where you want your tag cloud to show up
It will format the data from technorati to look like your tag cloud on your technorati profile

<?php  technoratiTagCloud("url","api key"); ?>

You may also request the number of tags you want to see the default is 20.

<?php  technoratiTagCloud("url","api key", "num of tag"); ?>

*/
//include_once("wp-includes/rss-functions.php");

function technoratiTagCloud($url , $apiKey, $limit=20, $cache_time = 600, $cache_file = null) {


    $url = urlencode($url);
    $api_url = "http://api.technorati.com/blogposttags?url=" . $url . "&key=" . $apiKey . "&limit=" . $limit;
    if ($cache_file == null) {
        $cache_file  =  "/tmp/techtagcloud.$url.$cache_time.$limit.cache";
    }

    echo "<style type='text/css'>
.heatmap {font-size: 0.97em;}
.heatmap li {display: inline;}
.heatmap em {font-style: normal; font-size: 1.03em;}
</style>
";


    $cache_file_tmp = "$cache_file.tmp";

    $time = split(" ", microtime());
    srand((double)microtime()*1000000);

    $cache_time_rnd = 30 - rand(0, 60);
    if (
    !file_exists($cache_file)
    || !filesize($cache_file) > 20
    || ((filemtime($cache_file) + $cache_time - $time[1]) + $cache_time_rnd < 0)
    || (filemtime(__FILE__) > filemtime($cache_file))
    ) {
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
            $fpwrite = fopen($cache_file_tmp, 'w');
            if ($fpwrite){
                fputs($fpwrite, $response);
                fclose($fpwrite);
                rename($cache_file_tmp, $cache_file);
            }
        }
        if ((file_exists($cache_file)) && filesize($cache_file) > 20)  {
            $data = $response;

        } elseif ($curl_error_code) {
            //do something here
        } else {
            //do something here
        }
    } else {
        $data = file_get_contents($cache_file);
    }
    //Now I parse and show the responce

    $xml = XML_unserialize($data);

    $items = $xml['tapi']['document']['item'];
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

    if (sizeof($tagData)) {
        ksort($tagData);
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
