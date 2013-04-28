<?php
/*
Ajax : Releases Data
Version: 2.5.19
*/
define('DOING_AJAX', true);
$root = dirname(dirname(__FILE__));
$debug = false;
global $error_message;
$error_message = "";
if (file_exists($root.'/wp-load.php')) {
		// WP 2.6
		require_once($root.'/wp-load.php');
} else {
		// Before 2.6
		require_once($root.'/wp-config.php');
}

if($_POST['action'] == "set-favorite" || $debug) {
    $response = new WP_Ajax_Response();

    if(setFavorite($_REQUEST['title'], $_REQUEST['group'])) {
	$response->add(array(
	    'what' => 'result',
	    'data' => "successfull"
	));
    }
    else {
	$response->add(array(
	    'what' => 'error',
	    'id' => 1,
	    'data' => "Please use our page: http://telestrekoza.com \n".$error_message
	));
    }
    $response->send();

} elseif($_POST['action'] == 'get-releases') {
    $feeds = false;

    if(isset($_REQUEST['selected'])) {
	$myfeeds = getFeeds();
	//var_dump($result);

	parse_str($_REQUEST['selected']);
	//var_dump($_REQUEST['selected']);
	$feeds = array();
	$favorite = false;
	if($rel_opt_favorite == "on")
	    $favorite = true;
	foreach($myfeeds as $_label => $_id) {
	    $name = "rel_opt_".$_id;
	    if($$name == "on") {
		setcookie("rel_opt_".$_id, "true",time()+60*60*24*365, "/");
		$feeds[] = $_id;
		
	    }
	    else
		setcookie("rel_opt_".$_id, "",time()-3600, "/");
	}
    }
    $response = new WP_Ajax_Response();
    getData($response, $feeds, $favorite);
    $response->send();
}
else {
	if(isset($_REQUEST['rss'])) {
		$xmldoc = getRssData();
		echo $xmldoc->saveXML();
		return;
	}
    $response = new WP_Ajax_Response();
    $response->add(array(
	'what' => 'error',
	'id' => 1,
	'data' => "Please use our page: http://telestrekoza.com"
    ));
    $response->send();

}

function setFavorite($title, $group) {
    global $error_message;
    if(!isset($title) || !isset($group)) {
	$error_message = "Title and Group is not set.";
	return false;
    }
    global $userdata, $wpdb;
    get_currentuserinfo();
    if(($userId = $userdata->ID) <= 0) {
	$error_message = "User is not loged in";
	return false; //user not logined
    }
    $table = $wpdb->prefix."releases_favorites";
    //var_dump("SELECT * FROM ".$table." WHERE user_id=".$userId." AND `show`='".$wpdb->escape($title)."' AND `group`='".$wpdb->escape($group)."';");
    //var_dump("user_id=".$userId." AND `show`='".$wpdb->escape($title)."' AND `group`='".$wpdb->escape($group)."';");
    $result = $wpdb->get_results("SELECT user_id FROM ".$table." WHERE user_id=".$userId." AND `show`='".$wpdb->escape($title)."' AND `group`='".$wpdb->escape($group)."';");
    //var_dump("INSERT INTO ".$table." (user_id, `show`, `group`) VALUES (".$userId.",'".$wpdb->escape($title)."','".$wpdb->escape($group)."');");
    //var_dump($result);
    //insert if not exists:
    //var_dump(count($result));
    if(count($result) == 0) {
	$result = $wpdb->query("INSERT INTO ".$table." (user_id, `show`, `group`) VALUES (".$userId.",'".$wpdb->escape($title)."','".$wpdb->escape($group)."');");
	//var_dump($result);
	if(!$result) {
	    $error_message = "Cant insert new record :"; // . "INSERT INTO ".$table." (user_id, `show`, `group`) VALUES (".$userId.",'".$wpdb->escape($title)."','".$wpdb->escape($group)."');";
	    return false;
	}
    }
    //remove if exists
    else {
	$result = $wpdb->query("DELETE FROM ".$table." WHERE user_id=".$userId." AND `show`='".$wpdb->escape($title)."' AND `group`='".$wpdb->escape($group)."';");
	//var_dump($result);
	if(!$result) {
	    $error_message = "Cannt delete this record.";
	    return false;
	}
    }
    return true;
}


function getAllFavorite() {
    global $userdata, $wpdb;
    get_currentuserinfo();
    if(($userId = $userdata->ID) <= 0)
	return array(); //user not logined
    $table = $wpdb->prefix."releases_favorites";
    $result = $wpdb->get_results("SELECT `show`,`group` FROM ".$table." WHERE user_id=".$userId.";");
    $return = array();
    if(count($result) > 0)
	foreach($result as $obj)
	    $return[$obj->show][] = $obj->group;
    return $return;
}


function getFeeds() {
    global $wpdb;

    $rows =& $wpdb->get_results("SELECT t1.*, (SELECT COUNT(1) FROM `".$wpdb->prefix."lifestream_event` WHERE `feed_id` = t1.`id`) as `events` FROM `".$wpdb->prefix."lifestream_feeds` as t1 ORDER BY `id` ");

    $results = array();
    foreach ($rows as $result) {
	$options = unserialize($result->options);
	//$results[$result->id] = $options["feed_label"];
	$results[$options["feed_label"]] = $result->id;
    }
    return $results;
}

function getRssData() {
	global $lifestream;
	
	$xml = new DOMDocument('1.0', 'UTF-8');
	$xml->formatOutput = true;
	$roo = $xml->createElement('rss');
    $roo->setAttribute('version', '2.0');
    $xml->appendChild($roo);
    $cha = $xml->createElement('channel');
    $roo->appendChild($cha);
    
    //add RSS headers
    $hea = $xml->createElement('title', utf8_encode('Telestrekoza.com :: aggregated releases from different release groups'));
    $cha->appendChild($hea);

    $hea = $xml->createElement('description', 'Сборка последних релизов некоторых релиз групп.');
    $cha->appendChild($hea);

    $hea = $xml->createElement('language', utf8_encode('ru'));
    $cha->appendChild($hea);

    $hea = $xml->createElement('link', htmlentities('http://telestrekoza.com/releases/'));
    $cha->appendChild($hea);

    $hea = $xml->createElement('lastBuildDate', utf8_encode(date("D, j M Y H:i:s ").'GMT'));
    $cha->appendChild($hea);
    
    //create data items
    $events = $lifestream->get_events(array('limit' => 50, 'break_groups'=>true));
    foreach ($events as $result) {
    	$itm = $xml->createElement('item');
    	
    	$result_data = parseEventData($result, $logined);
    	
    	if(is_array($result->feed->options["feed_label"])) {
    	    $result->feed->options["feed_label"] = $result->feed->options["feed_label"][0];
    	}
    	//$dat = $xml->createElement('title', utf8_encode($result_data[1]["releaseGroup"] . ":" . $result_data[0] ." / ". $result_data[1]["showNumber"]));
    	//$dat = $xml->createElement('title', htmlentities($result_data[0] ." / " .$result_data[1]["showNumber"]. " / ". $result_data[1]["releaseGroup"]));
    	if(isset($result_data[1]["features"])) {
    		$str = $result_data[0] ." / " .$result_data[1]["showNumber"]. " / ". $result->feed->options["feed_label"] . " / " . $result_data[1]["features"];
    	} else {
    		$str = $result_data[0] ." / " .$result_data[1]["showNumber"]. " / ". $result->feed->options["feed_label"];
    	}
    	$str = seems_utf8($str)?  $str: utf8_encode( htmlentities($str));

    	$dat = $xml->createElement('title', $str);
    	$itm->appendChild($dat);
    
    //var_dump($result_data[1]);
    $str = $result_data[1]["extendedTitle"];
    $str = seems_utf8($str)? $str: utf8_encode($str);
    	$dat = $xml->createElement('description', $str);
    	$itm->appendChild($dat);   
 
    	$dat = $xml->createElement('link', htmlentities($result->data[0]["link"]));
    	$itm->appendChild($dat);
 
		$dat = $xml->createElement('pubDate', utf8_encode(date("D, j M Y H:i:s ", $result->data[0]["date"]).'GMT'));
    	$itm->appendChild($dat);
 
    	$dat = $xml->createElement('guid', htmlentities($result->data[0]["guid"]));
    	$itm->appendChild($dat);
    	
    	$cha->appendChild($itm);
    }
    return $xml;
}

function getData($resp,$feeds, $favorite = false) {

global $lifestream;

//var_dump($lifestream->feeds);
//var_dump($lifestream);
//$events = $lifestream->get_events(array('feed_types'=>array('generic'),'limit' => 100, 'break_groups'=>true));
if(is_array($feeds))
    $events = $lifestream->get_events(array('limit' => 150, 'feed_ids'=> $feeds, 'break_groups'=>true));
else
    $events = $lifestream->get_events(array('limit' => 150, 'break_groups'=>true));
//$events = lifestream_get_events(array('feed_types'=>array('generic'), 'break_groups'=>true));
$day = '';
$title_width = 80;
global $userdata;
get_currentuserinfo();
if($userdata->ID > 0) {
    $logined = true;
    $favorites =  getAllFavorite();
    //var_dump($favorites);
}
else
    $logined = false;

// old fashion
//$img_path = get_bloginfo('template_directory')."/images/rel/";
global $img_path;
$img_path = "/static/images/rel/";
if (count($events))
{
    $today = date('m d Y');
    $yesterday = date('m d Y', time()-86400);
    $prev_day = "";
    $prev_link = "";
    foreach ($events as $result)
    {
	/*
	$link = explode("://",$result->link); $link = $link[1];
	if($link == $prev_link && isset($link)) {
	    var_dump($link);
	    continue;
	}
	$prev_link = $link;
	*/
	/*
	echo "<-- debug :";
	print_r($result);
	echo "-->";
	*/
        $timestamp = $result->get_date();
        if ($today == date('m d Y', $timestamp)) $this_day = 'Сегодня';
        else if ($yesterday == date('m d Y', $timestamp)) $this_day = 'Вчера';
        else {
    	    setlocale(LC_ALL, 'ru_RU.UTF-8');
    	    //TODO: reset locale and check if rus date plugin is installed
    	    $this_day =  maxsite_the_russian_time(strftime('%d %B %Y',$timestamp));
    	    //$this_day = ucfirst(htmlentities($this_day));
    	}

        if($prev_day != $this_day) {
    	    $prev_day= $this_day;
    	    $resp->add(array(
		'what' => 'release-date',
		'id' => $result->id,
		'data' => $this_day
	    ));
        }

        $result_data = parseEventData($result,$logined);
        /*
        echo $result->id;
        echo "\n";
        //var_dump($result->data[0]);
        continue;
        */
	//var_dump($result->feed->options["feed_label"]);
	

	if(!empty($result_data)) {
	    $result_data[1]["releaseGroupIcon"] = $img_path.$result_data[1]["releaseGroup"].".png";
	    $result_data[1]["releaseGroupLabel"] = $result->feed->options["feed_label"];
	
	    //check favorite:
	    if($logined) {
		if($favorites && is_array($favorites[$result_data[0]])) {
		//echo "check ".$result_data[0]." for ".$result_data[1]["releaseGroupLabel"]."\n";
		    if(in_array($result_data[1]["releaseGroupLabel"], $favorites[$result_data[0]]))
			$result_data[1]["favorite"] = true;
		    }
		    else
			$result_data[1]["favorite"] = false;
	    }
	    //filter only favirites on show
	    if($favorite && $favorites && !is_array($favorites[$result_data[0]]))
		continue;
	    if($favorite && $favorites && is_array($favorites[$result_data[0]]))
		if(!in_array($result_data[1]["releaseGroupLabel"], $favorites[$result_data[0]]))
		    continue;
	    $resp->add(array(
		'what' => 'release',
		'id' => $result->id,
		'data' => $result_data[0],
		'supplemental' => $result_data[1]
	    ));
	}
    }
}
else
{
	$resp->add(array(
		'what' => 'error',
		'id' => 1,
		'data' => "Sorry, no releases found at this moment."
	    ));
}
}//end getData function

function parseEventData($result, $logined) {
	$data = "";
	$result_data = "";
	if($result->feed->options["feed_label"] == "Lost Film") {
	    $data = str_replace("..", ". .", $result->data[0]["title"]);
	    $data = explode(". ",$data);
	    $result_data = print_lostfilm($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "Nova Film") {
	    $data = explode("/",$result->data[0]["title"]);
	    $result_data = print_novafilm($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "FilmGate") {
	    $data = explode("/",$result->data[0]["title"]);
	    $result_data = print_filmgate($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "Oth Film") {
	    $data = explode("/",$result->data[0]["title"]);
	    //var_dump($data);
	    if(isset($data[1]))
			$result_data = print_othfilm($result, $data, $logined);
	    }
	elseif($result->feed->options["feed_label"] == "1001cinema") {
	    //var_dump($result->data[0]);
	    $data = explode(" /",$result->data[0]["title"]);
	    //var_dump($data);
	    if(isset($data[1]))
			$result_data = print_1001cinema($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "Telegurman") {
	    //var_dump($result->data[0]);
	    $data = explode(" /",$result->data[0]["title"]);
	    //var_dump($data);
	    if(isset($data[1]))
			$result_data = print_telegurman($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "Madchester") {
	    //var_dump($result->data[0]);
	    $data = explode(" – ",$result->data[0]["title"]);
	    //var_dump($data);
	    if(isset($data[1]))
			$result_data = print_madchester($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "Neosound") {
	    //var_dump($result->data[0]);
	    $data = explode("/",$result->data[0]["title"]);
	    //var_dump($data);
	    if(isset($data[1]))
			$result_data = print_neosound($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "WestFilm") {
	    //var_dump($result->data[0]);
	    $data = explode(" / ",$result->data[0]["title"]);
	    //var_dump($data);
	    if(isset($data[1]))
			$result_data = print_westfilm($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "Newstudio") {
	    $data = explode(" / ",$result->data[0]["title"]);
	    //var_dump($data);
	    if(isset($data[1]))
			$result_data = print_newstudio($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "BeeFilm") {
	    $data = explode(" / ",$result->data[0]["title"]);
	    if(count($data) < 2) {
			$data = explode(" \ ",$result->data[0]["title"]);
			$data = array_reverse($data);
	    }
	    //var_dump($data);
	    if(isset($data[1]))
			$result_data = print_beefilm($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "Кубик в кубе") {
	    $data = $result->data[0]["title"];
	    //var_dump($data);
	    if(isset($data))
		$result_data = print_kubik($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "Studdio") {
	    $data = explode( " / ",$result->data[0]["title"]);
	    //var_dump($data);
	    if(isset($data))
		$result_data = print_studdio($result, $data, $logined);
	}
	elseif($result->feed->options["feed_label"] == "TrueTranslate") {
	    $data = explode( "New:: ",$result->data[0]["title"]);
	    //var_dump($data[1]);
	    if(isset($data[1]))
		$result_data = print_trueTranslate($result, $data[1], $logined);
	}
	elseif($result->feed->options["feed_label"] == "BaibaKo") {
	    $data = explode( "Baibak: ",$result->data[0]["title"]);
	    //var_dump($data[1]);
	    if(isset($data[1]))
		$result_data = print_baibako($result, $data[1], $logined);
	}
	elseif($result->feed->options["feed_label"] == "IvnetCinema") {
	    //$data = explode( " / ",$result->data[0]["title"]);
	    $data = $result->data[0]["title"];
	    //var_dump($data);
	    if(isset($data) && substr_count($result->data[0]["link"], "ivnet-cinema") > 0 )
		$result_data = print_ivnet($result, $data, $logined);
	}
	

	return $result_data;
}

function print_subtitle($mytitle,$info =  "") {
    $result = "";
    if(!empty($mytitle) ||(empty($mytitle) && !empty($info))) {
	$result .= '<div class="episode-title">'.$mytitle;
	if(!empty($info))
	    $result .= ' <span>'.$info.'</span>';
	$result .= '</div>';
    }
    return $result;
}

function print_lostfilm($result, $data, $logined) {
    global $img_path, $title_width, $lost_down_url;
    $ret_result = array();

    //var_dump($result->data);

    $title = explode( "(", $data[0]);
    //var_dump($title[1]);
    if(empty($title[1]) || $title[1] == ")")
	return;
    $mytitle = substr($data[1],0,$title_width);
    if(preg_match("/download.php\/(\d+)\//",$result->data[0]["link"],$down_link)) {
	$det_link = "http://lostfilm.tv/details.php?id=".$down_link[1]."&hit=1";
	//var_dump($det_link);
    } elseif(preg_match("/download.php\?id=(\d+)&/",urldecode($result->data[0]["link"]),$down_link)) {
	$det_link = "http://lostfilm.tv/details.php?id=".$down_link[1]."&hit=1";
	//var_dump($det_link);
    } else {
	$det_link = $result->data[0]["link"];
    }
    if($lost_down_url == $det_link)
        return;
    $lost_down_url = $det_link;

    
    if( trim($data[2]) == "HD 720p" 
	    || substr_count($result->data[0]["link"],'.720p.')> 0
	    || substr_count($result->data[0]["title"],'720p') > 0
	)
	$ret_result[1]["features"] = "720p";
    if($logined)
	    $ret_result[1]["downloadLink"] = $result->data[0]["link"];

    
    $ret_result[1]["releaseGroup"] = "lost";

    $ret_result[1]["link"] = $det_link;
    $ret_result[1]["extendedTitle"] =trim($title[0]);
    $ret_result[0] = substr($title[1],0,-1);
    if(!empty($data[3]))
	$ret_result[1]["showNumber"] = $data[3];
    else
	$ret_result[1]["showNumber"] = $data[2];
    if(!empty($mytitle))
	$ret_result[1]["episodeTitle"]= print_subtitle($mytitle);
    return $ret_result;
}

function print_novafilm($result, $data, $logined) {
    global $img_path, $title_width, $nova_down_url;
    //var_dump($data);
    //eliminate dupps
    $down_url = str_replace('https://', 'http://', strip_tags(urldecode($result->data[0]["link"])));

    //var_dump($down_url);

    $ret_result = array();

    preg_match("/download[\.php]?\/(\d+)\//",$down_url,$down_link);
    //var_dump($down_link);
    //$det_link = "https://tracker.novafilm.tv/details.php?id=".$down_link[1]."&hit=1";
    $det_link = "http://novafilm.tv/torrent/".$down_link[1].".html";

    if($nova_down_url == $det_link) {
        //var_dump($det_link);
        return;
    }
    $nova_down_url = $det_link;


    if(trim($data[1]) == "Teaser")
	$ret_result[1]["features"] .= "Teaser,";
    if(trim($data[1]) == "Trailer")
	$ret_result[1]["features"] .= "Trailer,";

    if(trim($data[2]) == "MP3")
	$ret_result[1]["features"] .= "Podcast,";
    if(trim($data[2]) == "HDTV")
	$ret_result[1]["features"] .= "HDTV,";
    if(trim($data[2]) == "HDRip")
	$ret_result[1]["features"] .= "HDRip,";
    if(trim($data[2]) == "720p")
	$ret_result[1]["features"] .= "720p,";
    if(trim($data[3]) == "720p")
	$ret_result[1]["features"] .= "720p,";

    if($logined)
	$ret_result[1]["downloadLink"] = $down_url;

    $ret_result[1]["releaseGroup"] = "nova";

    $data[0] = strip_tags($data[0]);
    $ret_result[1]["link"] = $det_link;
    $ret_result[0] = trim($data[0]);

    //var_dump($data[1]);
    if(preg_match("/\"(?<episode>.*)\"/", $data[1], $info))
	$ret_result[1]["episodeTitle"] = htmlentities($info["episode"]);
    
    if(preg_match("/.?Preview#(?<episode>\d+).?/", $data[1], $info))
	$ret_result[1]["showNumber"] = "епизод ".$info["episode"];
    
    //var_dump($ret_result);
    if(preg_match("/^ \w+ (?<season>\d+), \w+ (?<episode>\d+) \"(?<title>[^\"].*)\" (?<data>.*).?/",$data[1],$folge_info)) {
        $ret_result[1]["showNumber"] = '(S'.str_pad((int)$folge_info["season"],2,"0",STR_PAD_LEFT).'E'.str_pad((int)$folge_info["episode"],2,"0",STR_PAD_LEFT).')';
	$mytitle = substr($folge_info["title"],0,$title_width);
	//var_dump($folge_info);
    }
    elseif(preg_match("/^ \w+ (?<season>\d+) \((?<title>[^\"].*)\).?/",$data[1],$folge_info))
	$ret_result[1]["showNumber"] = '(Полный '.$folge_info["season"].' сезон )';
    elseif(preg_match("/^ .+ (?<season>\d+), .+ (?<episode>\d+).?/",$data[1],$folge_info)) {
	$ret_result[1]["showNumber"] = '(S'.$folge_info["season"].'E'.str_pad((int)$folge_info["episode"],2,"0",STR_PAD_LEFT).')';
	//var_dump($folge_info);
    }

    if(preg_match("/(?<ru>.*)\((?<orig>.*)\)/", $ret_result[0], $title)) {
	//var_dump($title);
	$ret_result[0] = $title["orig"];
	$ret_result[1]["extendedTitle"] = $title["ru"];
    }
    if(!empty($mytitle))
        $ret_result[1]["episodeTitle"] = htmlentities($mytitle);
    return $ret_result;
}

function print_filmgate($result, $data, $logined) {
    global $img_path,$title_width;
    $ret_result = array();
    if(!isset($data[1]))
	return;
    //echo "<!-- debug2: ";var_dump($result); echo " -->";
    if(trim($data[3]) == "HD 720p")
	$ret_result[1]["features"] = '720p';
    if($logined){
	$ret_result[1]["downloadLink"]  = urldecode ($result->data[0]["link"]);
    }

    if(!isset($data[2])) {
	$tmp = explode(",", $data[1]);
	$data[1] = trim( $tmp[0] );
	array_shift($tmp);
	//var_dump($tmp[3]);
	$data[2] = " " . $tmp[0] . ", " . $tmp[1] . " ";
	if( isset($tmp[2]) && preg_match("/720p/i", $tmp[2]) )
	    $ret_result[1]["features"] = '720p';

    }
    $ret_result[1]["link"] = str_replace("&action=download", "", urldecode($result->data[0]["link"]));
    $ret_result[1]["extendedTitle"] = trim($data[0]);
    $ret_result[0] = trim($data[1]);
    $ret_result[1]["releaseGroup"] = "filmgate";

    if(preg_match("/^ .+ (?<season>\d+), .+ (?<episode>\d+).?/",$data[2],$folge_info)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$folge_info["season"],2,"0",STR_PAD_LEFT).'E'.str_pad((int)$folge_info["episode"],2,"0",STR_PAD_LEFT).')';
    }
    if(!empty($mytitle))
	$ret_result[1]["episodeTitle"] = $mytitle;
    return $ret_result;
}


function print_othfilm($result, $data, $logined) {
    global $img_path,$title_width,$othfilm_down_link;
    //echo( $result->data[0]["link"]." == ".$othfilm_down_link."\n");
    if($othfilm_down_link == $result->data[0]["link"]) {
	//echo( "DUP \n\n");
	return;
    }
    $othfilm_down_link = $result->data[0]["link"];
    $ret_result = array();
    //echo "<!-- debug2: ";var_dump($result); echo "-->";
    
    //var_dump($data[0]);
    /*
    if(preg_match("/(?<type>.*) ::/",$data[0],$episode_info)) {
	$type = $episode_info["type"];
	//echo $type;
        if(!preg_match("/^Релизы/", $episode_info["type"])){
	    return;
	}
    } else {
	return;
    }
    */
    if(preg_match("/(?<title_ru>.*) \"(?<episode_title>.*)\" (\[(?<group_info>.*)\])?.?/",$data[0],$episode_info)) {
	//var_dump($episode_info["type"]);
	//var_dump($episode_info["title_ru"]);
	$title_ru = addslashes(trim($episode_info["title_ru"]));
	if(!empty($episode_info["group_info"]))
	    $myinfo = $episode_info["type"]."/".$episode_info["group_info"];
	$mytitle = $episode_info["episode_title"];
    }
    if(preg_match("/(?<s_start>\d+)x(?<e_start>\d+)-(?<s_end>\d+)x(?<e_end>\d+)/",$data[0],$period)) {
	//var_dump($period);
	$seson = str_pad((int)$period["s_start"],2,"0",STR_PAD_LEFT);
	$episode = str_pad((int)$period["e_start"],2,"0",STR_PAD_LEFT);
	$seson_end = str_pad((int)$period["s_end"],2,"0",STR_PAD_LEFT);
	$episode_end = str_pad((int)$period["e_end"],2,"0",STR_PAD_LEFT);
    }
    elseif(preg_match("/(?<s_start>\d+)x(?<e_start>\d+)-(?<e_end>\d+)/",$data[0],$period)) {
	//var_dump($period);
	$seson = str_pad((int)$period["s_start"],2,"0",STR_PAD_LEFT);
	$episode = str_pad((int)$period["e_start"],2,"0",STR_PAD_LEFT);
	$seson_end = $seson;
	$episode_end = str_pad((int)$period["e_end"],2,"0",STR_PAD_LEFT);
    }

    if(preg_match("/(?<title>.*) (?<season>\d+)x(?<episode>\d+).?/",$data[1],$folge_info)) {
	if(empty($title_ru))
	    $title_ru = addslashes(trim($data[0]));
	$title = trim($folge_info["title"]);
	$seson = str_pad((int)$folge_info["season"],2,"0",STR_PAD_LEFT);
	$episode = str_pad((int)$folge_info["episode"],2,"0",STR_PAD_LEFT);
    }
    elseif(preg_match("/(?<title>.*).?(\[(?<details>.*)\])+/",$data[1],$folge_info)) {
	if(empty($title_ru))
	    $title_ru = addslashes(trim($data[0]));
	$title = trim($folge_info["title"]);
	if($folge_info["details"] == "HDTV 720p")
	    $ret_result[1]["feature"] = "720p";
	//var_dump($folge_info);
    }
    else {
    	if(empty($title_ru))
    	    $title_ru = addslashes(trim($data[0]));
    	$title = trim($data[1]);
    }
    
    //var_dump($data[0]);
    if(preg_match_all("/(\[(?<details>[^\]]*)\])+/",$data[0],$folge_info)) {
	$_myinfo = "";
	foreach($folge_info["details"] as $details) {
	    if($details == "HDTV 720p")
		$ret_result[1]["feature"] = "720p";
	    else
		$_myinfo .= " ".$details;
	}
	if(preg_match("/(?<type>.*) ::/",$data[0],$folge_info2))
	    if(!empty($folge_info2["type"]))
		$myinfo = $folge_info2["type"]." - ".$_myinfo;
	//var_dump($folge_info);
    }
    
    if($logined){
	//var_dump($result->data[0]["description"]);
	/*
	if(preg_match("/<a href=\"(?<url>.*)\"><b>Download/",$result->data[0]["description"],$values)) {
	    $download_url = $values["url"];
	    var_dump($values);
	}
	else */
	    $download_url = urldecode($result->data[0]["link"]);
	$ret_result[1]["downloadLink"] = $download_url;
	//echo ($download_url."\n");
    }

    $ret_result[1]["releaseGroup"] = "othfilm";

    $ret_result[1]["link"] = urldecode($result->data[0]["link"]);
    $ret_result[1]["extendedTitle"] = $title_ru;
    $ret_result[0] = $title;

    if(isset($seson) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.$seson.'E'.$episode;
	if(isset($seson_end) && isset($episode_end))
	    $ret_result[1]["showNumber"] .= ' - S'.$seson_end.'E'.$episode_end;
	$ret_result[1]["showNumber"] .= ')';
    }

    if(!empty($mytitle)) {
	$ret_result[1]["episodeTitle"] = $mytitle;
    }
    
    if(!empty($myinfo)) {
	$ret_result[1]["episodeCategory"] = $myinfo;
    }

    return $ret_result;
}

function print_1001cinema($result, $data, $logined) {
    global $img_path,$title_width,$cinema_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["link"];
    if($cinema_down_url == $down_url)
        return;
    $cinema_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";

    //var_dump($data[0]);
    $title = trim($data[1]);
    $title_ru = trim($data[0]);
    $mytitle = trim($data[4]);
    $feature = trim($data[3]);
    if($feature == "720p")
	$ret_result[1]["features"] .= '720p';

    if($logined){
	if(preg_match("/<a href=\"(?<url>.*)\"><b>Download/",$result->data[0]["description"],$values)) {
	    $download_url = $values["url"];
	}
	//a href="http://1001cinema.ru/forum/download.php?id=5514"><b>Download
	else
	    $download_url = $result->data[0]["link"];
        //var_dump($download_url);
        $ret_result[1]["downloadLink"] = $download_url;
    }

    $ret_result[1]["releaseGroup"] = '1001cinema';

    $ret_result[1]["link"] = $result->data[0]["link"];
    $ret_result[1]["extendedTitle"] = $title_ru;
    $ret_result[0] = $title;
    if(isset($data[2])) {
		$ser = trim($data[2]);
		if(strtoupper($ser[0])=="S")
		    $ser = strtoupper($ser);
		$ret_result[1]["showNumber"] = '('.$ser.')';
    }
    elseif(isset($seson) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.$seson.'E'.$episode;
	if(isset($seson_end) && isset($episode_end))
	    $ret_result[1]["showNumber"] .= ' - S'.$seson_end.'E'.$episode_end;
	$ret_result[1]["showNumber"] .= ')';
    }

    if(!empty($mytitle)) {
	$ret_result[1]["episodeTitle"] = $mytitle;
    }
    
    //if($result->id == 2048)
    //	var_dump($ret_result);

    return $ret_result;
}

function print_telegurman($result, $data, $logined) {
    global $img_path,$title_width,$telegurman_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["link"];
    if($telegurman_down_url == $down_url)
        return;
    $telegurman_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";

    //var_dump($data[0]);
    $title_ru = trim($data[1]);
    $title = trim($data[0]);
    $mytitle = trim($data[4]);
    $feature = trim($data[3]);

    if(preg_match('/^(?<title>.*) (?<show_num>S\d+?E\d+.?(\d+)?) (?<lang>\w+)$/', $title, $values)) {
	    $title = $values["title"];
	    $show_num = $values["show_num"];
    }

    if(preg_match('/^.* \«(?<mytitle>.+)\»$/', $title_ru, $values)) {
	    $mytitle = $values["mytitle"];
	    //var_dump($values);
    }
    if(preg_match('/^.* \«(?<mytitle>.+)\»( \((?<feature>.+)\))$/', $title_ru, $values)) {
	    $mytitle = $values["mytitle"];
	    $feature = "720p";
	    //var_dump($values);
    }
    /*
    if(preg_match('/^\d+.\d+$/', $mytitle)) {
	    $mytitle = null;
    }
    */

    if($logined){
	if(preg_match("/<a href=\"(?<url>.*)\"><b>Download/",$result->data[0]["description"],$values)) {
	    $download_url = $values["url"];
	}
	else
	    $download_url = $result->data[0]["link"];
	//var_dump($download_url);
        $ret_result[1]["downloadLink"] = $download_url;
    }

    $ret_result[1]["releaseGroup"] = 'telegurman';

    $ret_result[1]["link"] = $result->data[0]["link"];
    $ret_result[1]["extendedTitle"] = $title_ru;
    $ret_result[0] = $title;
    if(isset($show_num)) {
		$ser = trim($show_num);
		if(strtoupper($ser[0])=="S")
		    $ser = strtoupper($ser);
		$ret_result[1]["showNumber"] = '('.$ser.')';
    }
    elseif(isset($seson) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.$seson.'E'.$episode;
	if(isset($seson_end) && isset($episode_end))
	    $ret_result[1]["showNumber"] .= ' - S'.$seson_end.'E'.$episode_end;
	$ret_result[1]["showNumber"] .= ')';
    }

    if($feature == "720p")
	$ret_result[1]["features"] .= '720p';

    if(!empty($mytitle)) {
	$ret_result[1]["episodeTitle"] = $mytitle;
    }

    //if($result->id == 2048)
    //	var_dump($ret_result);

    return $ret_result;
}

function print_madchester($result, $data, $logined) {
    global $img_path,$title_width,$madchester_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["link"];
    if($madchester_down_url == $down_url)
        return;
    $madchester_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";

    //var_dump($data);
    $title = trim($data[0]);

    if(preg_match('/^(?<title_ru>.*) \((?<title>.+)\)$/', $title, $values)) {
	    $title = $values["title"];
	    $title_ru = $values["title_ru"];
    }

    if(preg_match('/^.*(\d+) .* (\d+)(.(\d+))?$/', $data[1], $values)) {
	    $season = $values[1];
	    $season = str_pad((int)$season,2,"0",STR_PAD_LEFT);
	    $st_ep  = str_pad((int)$values[2],2,"0",STR_PAD_LEFT);
	    $end_ep = $values[3] ? str_pad((int)$values[4],2,"0",STR_PAD_LEFT) : NULL;
	    $episode = $values[3] ? $st_ep."-".$end_ep : $st_ep;
    }
    /*
    if(preg_match('/^.* (?<episode>\d+) >.+)\”$/', $data[1], $values)) {
	    $episode = $values["episode"];
	    $mytitle = $values["mytitle"];
	    //var_dump($values);
    }
    */
    if($logined){
	if(preg_match("/<a href=\"(?<url>.*)\"><b>Download/",$result->data[0]["description"],$values)) {
	    $download_url = $values["url"];
	}
	else
	    $download_url = $result->data[0]["link"];
	//var_dump($download_url);
        $ret_result[1]["downloadLink"] = $download_url;
    }

    $ret_result[1]["releaseGroup"] = 'madchester';

    $ret_result[1]["link"] = $result->data[0]["link"];
    $ret_result[1]["extendedTitle"] = $title_ru;
    $ret_result[0] = $title;
    if(isset($season) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.$season.'E'.$episode;
	if(isset($season_end) && isset($episode_end))
	    $ret_result[1]["showNumber"] .= ' - S'.$season_end.'E'.$episode_end;
	$ret_result[1]["showNumber"] .= ')';
    }

    if($feature == "720p")
	$ret_result[1]["features"] .= '720p';

    if(!empty($mytitle)) {
	$ret_result[1]["episodeTitle"] = $mytitle;
    }

    //if($result->id == 2048)
    //	var_dump($ret_result);

    return $ret_result;
}

function print_neosound($result, $data, $logined) {
    global $img_path,$title_width,$neosound_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["link"];
    if($neosound_down_url == $down_url)
        return;
    $neosound_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";

    //var_dump($data);
    $title_ru = trim($data[0]);
    $title = trim($data[1]);

    if(preg_match('/^(?<title>.*) (\(.+\))? (?<feature>.+) (.+)?$/', $title, $values)) {
	    $title = $values["title"];
	    //$title_ru = $values["title_ru"];
    }elseif(preg_match('/^(?<title>.*) (\(.+\))? (?<feature>.+)$/', $title, $values)) {
	    $title = $values["title"];
	    //$title_ru = $values["title_ru"];
    }

    if(preg_match('/^(?<title_ru>.*) (\(.+ (?<season>\d+)[,;]? .+ (?<episode>\d+)\))$/', $title_ru, $values)) {
	    $title_ru = $values["title_ru"];
	    $season = $values["season"];
	    $episode = $values["episode"];
    }elseif(preg_match('/^(?<title_ru>.*) (\((?<season>\d+).+[,;]? .+ (?<episode>\d+)\))$/', $title_ru, $values)) {
	    $title_ru = $values["title_ru"];
	    $season = $values["season"];
	    $episode = $values["episode"];
    }
    if(preg_match("/download.php\/(?<id>\d+)\/.+\/(?<torrent>.+)$/",urldecode($result->data[0]["link"]),$values)) {
	$id = $values["id"];
	//echo "id:".$id."\n";
	if($logined){
	    $ret_result[1]["downloadLink"] = "http://www.neo-sound.ru/download.php?id=".$id."&name=".$values["torrent"];
	}
    }elseif(preg_match("/id=(?<id>\d+).+name=(?<torrent>.+)$/",urldecode($result->data[0]["link"]),$values)) {
	//var_dump($values); echo "\n";
	$id = $values["id"];
	if($logined){
	    $ret_result[1]["downloadLink"] = "http://www.neo-sound.ru/download.php?id=".$id."&name=".$values["torrent"];
	//echo "cant find id for: ".$result->data[0]["link"]."\n";
	}
    }

    $ret_result[1]["releaseGroup"] = 'neosound';

    $ret_result[1]["link"] = "http://www.neo-sound.ru/details.php?id=".$id; //$result->data[0]["link"];
    $ret_result[1]["extendedTitle"] = $title_ru;
    $ret_result[0] = $title;
    if(isset($season) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).'E'.str_pad((int)$episode,2,"0",STR_PAD_LEFT);
	if(isset($season_end) && isset($episode_end))
	    $ret_result[1]["showNumber"] .= ' - S'.$season_end.'E'.$episode_end;
	$ret_result[1]["showNumber"] .= ')';
    }

    if($feature == "720p")
	$ret_result[1]["features"] .= '720p';

    if(!empty($mytitle)) {
	$ret_result[1]["episodeTitle"] = $mytitle;
    }

    //if($result->id == 2048)
    //	var_dump($ret_result);

    return $ret_result;
}

function print_newstudio($result, $data, $logined) {
    global $img_path,$title_width,$newstudio_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["link"];
    if($newstudio_down_url == $down_url)
        return;
    $newstudio_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";

    //var_dump($data);
    $feature = $data[1];
    $title_ru = trim($data[0]);
    if(count($data) >= 3) {
	    $title = trim($data[2]);
	    $feature = $title.$data[1];
	}
    else
	$title = trim($data[1]);

    if(preg_match('/^(?<title>.*) (\(.+\))? (?<feature>.+) (.+)?$/', $title, $values)) {
	    $title = $values["title"];
	    //$title_ru = $values["title_ru"];
    }elseif(preg_match('/^(?<title>.*) (\(.+\))? (?<feature>.+)$/', $title, $values)) {
	    $title = $values["title"];
	    //$title_ru = $values["title_ru"];
    }

    if(count($data) == 3) {
	$season_data = trim($data[1]);
	if(preg_match('/(\(.+ (?<season>\d+)[,;] .+ (?<episode>\d+)\))$/', $season_data, $values)) {
	    $season = $values["season"];
	    $episode = $values["episode"];
	}elseif(preg_match('/(\((?<season>\d+).+[,;] .+ (?<episode>\d+)\))$/', $season_data, $values)) {
	    $season = $values["season"];
	    $episode = $values["episode"];
        }
    } else {
	if(preg_match('/^(?<title_ru>.*) (\(.+ (?<season>\d+)[,;] .+ (?<episode>\d+)\))$/', $title_ru, $values)) {
	    $title_ru = $values["title_ru"];
	    $season = $values["season"];
	    $episode = $values["episode"];
	}elseif(preg_match('/^(?<title_ru>.*) (\((?<season>\d+).+[,;] .+ (?<episode>\d+)\))$/', $title_ru, $values)) {
	    $title_ru = $values["title_ru"];
	    $season = $values["season"];
	    $episode = $values["episode"];
	}
    }
    if(preg_match("/details.php\?id=(?<id>\d+)&.*$/",urldecode($result->data[0]["link"]),$values)) {
	$id = $values["id"];
	//echo "id:".$id."\n";
	if($logined){
	    $ret_result[1]["downloadLink"] = "http://newstudio.tv/download.php?id=".$id;
	}
    }
    $ret_result[1]["releaseGroup"] = 'newstudio';

    if(isset($id)) {
	$ret_result[1]["link"] = "http://newstudio.tv/details.php?id=".$id; //$result->data[0]["link"];
    } else {
	$ret_result[1]["link"] = "http://newstudio.tv";
    }
    $ret_result[1]["extendedTitle"] = htmlentities($title_ru);
    //$ret_result[0] = htmlentities($title_ru);
    $ret_result[0] = preg_replace('/\x3/', '', htmlspecialchars(utf8_decode($title), ENT_QUOTES));
    if(isset($season) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).'E'.str_pad((int)$episode,2,"0",STR_PAD_LEFT);
	if(isset($season_end) && isset($episode_end))
	    $ret_result[1]["showNumber"] .= ' - S'.$season_end.'E'.$episode_end;
	$ret_result[1]["showNumber"] .= ')';
    }

    if(preg_match("/720p/",$feature))
	$ret_result[1]["features"] .= '720p';

    if(!empty($mytitle)) {
	$ret_result[1]["episodeTitle"] = $mytitle;
    }

    //if($result->id == 2048)
    //	var_dump($ret_result);

    return $ret_result;
}

function print_beefilm($result, $data, $logined) {
    global $img_path,$title_width,$beefilm_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["link"];
    if($beefilm_down_url == $down_url)
        return;
    $beefilm_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";
    
    //var_dump("START:");
    //var_dump(count($data));
    if(count($data) <= 2) {
	$title_ru = trim($data[0]);
	$title = trim($data[1]);
	$shownumber = trim($data[1]);
	$feature = trim($data[1]);
	//$mytitle = trim($data[2]);
    } else {
	$title = trim($data[1]);
	$title_ru = trim($data[0]);
	$shownumber = trim($data[2]);
	$feature = trim($data[3]);
	$mytitle = trim($data[4]);
    }

    if(preg_match('/^(?<show_title>[^(]*)[ \(](?<show_season>\d+?) [^\ ]+ (?<show_episod>\d+)/', $shownumber, $values)) {
	    $seson = str_pad((int)$values["show_season"],2,"0",STR_PAD_LEFT);
	    $episode = str_pad((int)$values["show_episod"],2,"0",STR_PAD_LEFT);
	    $title = trim($values["show_title"]);
	    //var_dump($values);
    }elseif(preg_match('/^[sS](?<show_season>\d+?)[eE](?<show_episod>\d+(-\d+)?)/', $shownumber, $values)) {
	$seson = str_pad((int)$values["show_season"],2,"0",STR_PAD_LEFT);
	$episode = str_pad((int)$values["show_episod"],2,"0",STR_PAD_LEFT);
    }
    //sanitize title
    //var_dump($title);
    if(preg_match('/^(?<show_title>.*)[sS](?<show_season>\d+?)[eE](?<show_episod>\d+(-\d+)?)/', $title, $values)) {
	    $seson = str_pad((int)$values["show_season"],2,"0",STR_PAD_LEFT);
	    $episode = str_pad((int)$values["show_episod"],2,"0",STR_PAD_LEFT);
	    $title = trim($values["show_title"]);
	    //var_dump($values);
    }elseif(preg_match('/^(?<show_title>.*)([sS]\d+.*)$/', $title, $values)) {
	$title = trim($values["show_title"]);
    }elseif(preg_match('/^(?<show_title>.*)[ ]?(\(.*\)).*$/', $title, $values)) {
	$title = trim($values["show_title"]);
    }
    if($logined){
        $download_url = urldecode($result->data[0]["link"]);
        $ret_result[1]["downloadLink"] = $download_url;
    }

    $ret_result[1]["releaseGroup"] = 'beefilm';

    $ret_result[1]["link"] = urldecode($result->data[0]["guid"]);
    $ret_result[1]["extendedTitle"] = $title_ru;
    $ret_result[0] = $title;
    if(isset($seson) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.$seson.'E'.$episode.')';
    } elseif(isset($seson) && !isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.$seson.')';
    }

    if($feature == "720p")
	$ret_result[1]["features"] .= '720p';
    if(preg_match('/720p/', $feature))
	$ret_result[1]["features"] .= '720p';
    if(preg_match('/^Трейлер/', $data[0]))
	$ret_result[1]["features"] .= 'Trailer';
    if(!empty($mytitle)) {
	$ret_result[1]["episodeTitle"] = $mytitle;
    }

    //if($result->id == 3757)
    //	var_dump($ret_result);
    //var_dump($result);

    return $ret_result;
}

function print_westfilm($result, $data, $logined) {
    global $img_path,$title_width,$westfilm_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["link"];
    if($westfilm_down_url == $down_url)
        return;
    $westfilm_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";
    //var_dump($data[1]);
    //return;
    $title_ru = trim($data[0]);
    $title = trim($data[2]);
    $shownumber = trim($data[1]);
    $feature = trim($data[3]);
    $mytitle = trim($data[4]);

    if(preg_match('/ (?<show_season>\d+?)[, ]?.* (?<show_episod>\d+?) .*$/', $shownumber, $values)) {
	    $season = $values["show_season"];
	    $episode = $values["show_episod"];
	    //var_dump($values);
	    //return;
    }
    if($logined){
	if(preg_match('/id=(\d+)/', urldecode($result->data[0]["link"]), $values)) {
	    $ret_result[1]["downloadLink"] = "http://westfilm.tv/download.php?id=".$values[1];
        }
    }

    $ret_result[1]["releaseGroup"] = 'westfilm';

    $ret_result[1]["link"] = urldecode($result->data[0]["link"]);
    $ret_result[1]["extendedTitle"] = $title_ru;
    $ret_result[0] = $title;
    if(isset($season) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).'E'.str_pad((int)$episode,2,"0",STR_PAD_LEFT).')';
    } elseif(isset($season) && !isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).')';
    }

    if(preg_match('/720p/', $feature))
	$ret_result[1]["features"] .= '720p';

    if(!empty($mytitle)) {
	$ret_result[1]["episodeTitle"] = $mytitle;
    }

    //if($result->id == 3757)
    //	var_dump($ret_result);
    //var_dump($result);

    return $ret_result;
}

function print_kubik($result, $data, $logined) {
    global $img_path,$title_width,$kubik_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["link"];
    if($kubik_down_url == $down_url)
        return;
    $kubik_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";
    //var_dump($data);
    //$data = "Eastwick / Иствик / 1 x 12";
    //$data = "Community  / Сообщество / 1 х 11";
    //$data = "Day Of The Triffids / День триффидов / 1 х 01";
    //return;
    if(preg_match('/^(?<show_title>.*) [\/|] (?<show_title_ru>.*) [\/| ]+ (?<show_season>\d+?)[ ]?([хХхХx]+[ ]?(?<show_episod>\d+?))+$/', $data, $values)) {
	    $season = $values["show_season"];
	    $episode = $values["show_episod"];
	    $title_ru = trim($values["show_title_ru"]);
	    $title = trim($values["show_title"]);
	    //var_dump($values);
	    //return;
    }
    elseif(preg_match('/^(.*[\.\.]+[ ]?)?(?<show_title>.*) (?<show_season>\d+?)[ ]?([хХ]+[ ]?(?<show_episod>\d+?))+$/', $data, $values)) {
	    $season = $values["show_season"];
	    $episode = $values["show_episod"];
	    $title_ru = $values["show_title"];
	    $title = $values["show_title"];
	    //var_dump($values);
	    //return;
    } elseif(preg_match('/^(.*[\.\.]+[ ]?)?(?<show_title>.*) [sS]+(?<show_season>\d+?)([eE]+(?<show_episod>\d+?))+$/', $data, $values)) {
	    $season = $values["show_season"];
	    $episode = $values["show_episod"];
	    $title_ru = $values["show_title"];
	    $title = $values["show_title"];
	    //var_dump($values);
	    //return;
    } else {
    

	//var_dump($data);
	return;
    }
    if($logined && preg_match('/href=\"(?<link>[^\"]*torrents\.ru[^\"]*)\"/', $result->data[0]["description"], $values)) {
	$ret_result[1]["downloadLink"] = urldecode($values["link"]);
    }
    elseif($logined && preg_match('/href=\"(?<link>[^\"]*rutracker\.org[^\"]*)\"/', $result->data[0]["description"], $values)) {
	$ret_result[1]["downloadLink"] = urldecode($values["link"]);
    }

    $ret_result[1]["releaseGroup"] = 'kubikvkube';

    $ret_result[1]["link"] = urldecode($result->data[0]["link"]);
    $ret_result[1]["extendedTitle"] = $title_ru;
    $ret_result[0] = $title;
    if(isset($season) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).'E'.str_pad((int)$episode,2,"0",STR_PAD_LEFT).')';
    } elseif(isset($season) && !isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).')';
    }
    return $ret_result;
}

function print_studdio($result, $data, $logined) {
    global $img_path,$title_width,$studdio_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["link"];
    if($studdio_down_url == $down_url)
        return;
    $studdio_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";
    //var_dump($data);
    //var_dump($result->data[0]["description"]);
    $title_ru = $data[1];
    $title = $data[0];
    if(preg_match('/[sS](?<show_season>\d+)[eE](?<show_episod>\d+)/', $data[2], $values)) {
	    $season = $values["show_season"];
	    $episode = $values["show_episod"];
    } else {
	    $ret_result[1]["showNumber"] = "(".$data[2].")";
    }
    if($logined && preg_match('/href=\"(?<link>[^\"]*torrents\.ru[^\"]*)\"/', $result->data[0]["description"], $values)) {
	$ret_result[1]["downloadLink"] = urldecode($values["link"]);
    }
    elseif($logined && preg_match('/href=\"(?<link>[^\"]*rutracker\.org[^\"]*)\"/', $result->data[0]["description"], $values)) {
	$ret_result[1]["downloadLink"] = urldecode($values["link"]);
    }

    $ret_result[1]["releaseGroup"] = 'studdio';

    $ret_result[1]["link"] = urldecode($result->data[0]["link"]);
    $ret_result[1]["extendedTitle"] = $title_ru;
    $ret_result[0] = $title;
    if(preg_match('/720p/', $data[4]))
	$ret_result[1]["features"] .= '720p';
    if(!empty($data[3])) {
	$ret_result[1]["episodeTitle"] = $data[3];
    }
    if(isset($season) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).'E'.str_pad((int)$episode,2,"0",STR_PAD_LEFT).')';
    } elseif(isset($season) && !isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).')';
    }
    return $ret_result;
}

function print_trueTranslate($result, $data, $logined) {
    global $img_path,$title_width,$trueTranslate_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["guid"];
    if($trueTranslate_down_url == $down_url)
        return;
    $trueTranslate_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";
    //var_dump($data);
    //$data = "Eastwick / Иствик / 1 x 12";
    //$data = "Community  / Сообщество / 1 х 11";
    //$data = "Day Of The Triffids / День триффидов / 1 х 01";
    //return;
    if(preg_match('/^(?<show_title>.*)[\.]? [sS]+(?<show_season>\d+?)[eE]+(?<show_episod>\d+?) [- ]?(?<episode_title>.*)[\.]? (?<url>http.*)$/', $data, $values)) {
	    $season = $values["show_season"];
	    $episode = $values["show_episod"];
	    //n$title_ru = trim($values["show_title_ru"]);
	    $title_ru = trim($values["show_title"]);
	    $title = trim($values["show_title"]);
	    $url = urldecode(trim($values["url"]));
	    $episodeTitle = trim($values["episode_title"]);
	    //var_dump($values);
	    //return;
    } else {   

	//var_dump($data);
	return;
    }
    if( $logined ) {
	$ret_result[1]["downloadLink"] = $url;
    }

    $ret_result[1]["releaseGroup"] = 'truetranslate';

    $ret_result[1]["link"] = $url;//urldecode($result->data[0]["link"]);
    $ret_result[1]["episodeTitle"] = $episodeTitle;
    $ret_result[0] = $title;
    if(isset($season) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).'E'.str_pad((int)$episode,2,"0",STR_PAD_LEFT).')';
    } elseif(isset($season) && !isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).')';
    }
    return $ret_result;
}

function print_baibako($result, $data, $logined) {
    global $img_path,$title_width,$baibako_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["guid"];
    if($baibako_down_url == $down_url)
        return;
    $baibako_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";
    //var_dump($data);
    //return;
    if(preg_match('/^(?<show_title_ru>.*) \/(?<show_title>.*) \/[sS]+(?<show_season>\d+?)[eE]+(?<show_episod>\d+?)([a-zA-Z]?-(?<show_episod_finish>\d+))? \/(?<episode_options>[^\/]*) \/(?<episode_title>[^\/]*)( \/(?<show_details>.*))? (?<url>http.*)$/', $data, $values)) {
	    $season = $values["show_season"];
	    $episode = $values["show_episod"];
	    if(trim($values["show_episod_finish"]) != "")
		$episode_end = trim($values["show_episod_finish"]);
	    if(trim($values["show_details"]) != "")
		$show_det = trim($values["show_details"]);
	    $title_ru = trim($values["show_title_ru"]);
	    $title = trim($values["show_title"]);
	    $url = urldecode(trim($values["url"]));
	    $episodeTitle = trim($values["episode_title"]);
	    $feature = trim($values["episode_options"]);
	    //var_dump($values);
	    //return;
    } elseif(preg_match('/^(?<show_title_ru>.*) \/(?<show_title>.*) \/[sS]+(?<show_season>\d+?)[eE]+(?<show_episod>\d+?)([a-zA-Z]?-(?<show_episod_finish>\d+))? \/(?<episode_options>[^\/]*) ( \/(?<show_details>.*))?(?<url>http.*)$/', $data, $values)) {
	    $season = $values["show_season"];
	    $episode = $values["show_episod"];
	    if(trim($values["show_episod_finish"]) != "")
		$episode_end = trim($values["show_episod_finish"]);
	    if(trim($values["show_details"]) != "")
		$show_det = trim($values["show_details"]);
	    $title_ru = trim($values["show_title_ru"]);
	    $title = trim($values["show_title"]);
	    $url = urldecode(trim($values["url"]));
	    $episodeTitle = trim($values["episode_title"]);
	    $feature = trim($values["episode_options"]);
	    //var_dump($values);
	    //return;
    } else {   

	//var_dump($data);
	return;
    }
    if( $logined ) {
	$ret_result[1]["downloadLink"] = $url;
    }

    if(preg_match('/720p/', $feature))
	$ret_result[1]["features"] .= '720p';

    $ret_result[1]["releaseGroup"] = 'baibako';

    $ret_result[1]["link"] = $url;//urldecode($result->data[0]["link"]);
    $ret_result[1]["episodeTitle"] = $episodeTitle;
    if(isset($show_det))
	$ret_result[1]["episodeTitle"] .= ' ( <small>'. $show_det.'</small> )';
    $ret_result[1]["extendedTitle"] = $title_ru;
    $ret_result[0] = $title;
    if(isset($season) && isset($episode) && isset($episode_end)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).'E'.str_pad((int)$episode,2,"0",STR_PAD_LEFT).'-'.str_pad((int)$episode_end,2,"0",STR_PAD_LEFT).')';
    } elseif(isset($season) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).'E'.str_pad((int)$episode,2,"0",STR_PAD_LEFT).')';
    } elseif(isset($season) && !isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).')';
    }

    return $ret_result;
}

function print_ivnet($result, $data, $logined) {
    global $img_path,$title_width,$ivnet_down_url;
    $ret_result = array();
    //eliminate dupps
    $down_url = $result->data[0]["guid"];
    if($ivnet_down_url == $down_url)
        return;
    $ivnet_down_url = $down_url;
    //echo "<!- debug2: ";var_dump($result); echo "-->";
    //var_dump($data);
    //return;
    if(preg_match('/^(?<show_title_ru>.*) \/ (?<show_title>.*) [sS]+(?<show_season>\d+?)[eE]+(?<show_episod>\d+?)([a-zA-Z]?-(?<show_episod_finish>\d+))? (?<episode_options>[^\/]*)?(\"(?<episode_title>[^\/]*)\")(?<show_details>.*)?$/', $data, $values)) {
	    $season = $values["show_season"];
	    $episode = $values["show_episod"];
	    if(trim($values["show_episod_finish"]) != "")
		$episode_end = trim($values["show_episod_finish"]);
	    if(trim($values["show_details"]) != "")
		$show_det = trim($values["show_details"]);
	    $title_ru = trim($values["show_title_ru"]);
	    $title = trim($values["show_title"]);
	    $episodeTitle = trim($values["episode_title"]);
	    $feature = trim($values["episode_options"]);
	    //var_dump($values);
	    //return;
    } elseif(preg_match('/^(?<show_title_ru>.*) \/ (?<show_title>.*) (\(.*\))? .* (?<episode_title>.* .*)?$/', $data, $values)) {
	    $title_ru = trim($values["show_title_ru"]);
	    $title = trim($values["show_title"]);
	    $episodeTitle = trim($values["episode_title"]);
	    //var_dump($values);
	    //return;
    } else {

	//var_dump($data);
	return;
    }
    if( $logined ) {
	$ret_result[1]["downloadLink"] = $result->data[0]["link"];
    }

    if(preg_match('/720p/', $feature))
	$ret_result[1]["features"] .= '720p';

    $ret_result[1]["releaseGroup"] = 'IvnetCinema';

    $ret_result[1]["link"] = $url;//urldecode($result->data[0]["link"]);
    $ret_result[1]["episodeTitle"] = $episodeTitle;
    if(isset($show_det))
	$ret_result[1]["episodeTitle"] .= ' ( <small>'. $show_det.'</small> )';
    $ret_result[1]["extendedTitle"] = $title_ru;
    $ret_result[0] = $title;
    if(isset($season) && isset($episode) && isset($episode_end)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).'E'.str_pad((int)$episode,2,"0",STR_PAD_LEFT).'-'.str_pad((int)$episode_end,2,"0",STR_PAD_LEFT).')';
    } elseif(isset($season) && isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).'E'.str_pad((int)$episode,2,"0",STR_PAD_LEFT).')';
    } elseif(isset($season) && !isset($episode)) {
	$ret_result[1]["showNumber"] = '(S'.str_pad((int)$season,2,"0",STR_PAD_LEFT).')';
    }

    return $ret_result;
}
