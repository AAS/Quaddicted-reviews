<?php
ob_start();
//error_reporting(E_ALL);
/*$time_start = microtime(true);*/
date_default_timezone_set('Europe/Berlin');

define('PUN_ROOT', '/srv/http/forum/');
include PUN_ROOT.'include/common.php';

include_once "markdown.php";
$parser = new Markdown_Parser; // or MarkdownExtra_Parser
$parser->no_markup = true; //disables the option for HTML in comments

require_once 'htmlpurifier/library/HTMLPurifier.auto.php';
$config = HTMLPurifier_Config::createDefault();
$purifier = new HTMLPurifier($config);


$html_header = <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
EOT;

$html_header2 = <<<EOT
<link rel="stylesheet" type="text/css" href="/static/style.css" />
<link rel="stylesheet" type="text/css" href="starrating.css" />
<script type="text/javascript" src="rating.js"></script>
<link rel="icon" href="/favicon.ico" type="image/x-icon" />
<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon" />
<link href="/reviews/atom.php" type="application/atom+xml" rel="alternate" title="The latest Quake singleplayer releases at Quaddicted.com (Atom feed)" />
</head>
EOT;

echo $html_header;

if ($_GET['map']) {

	// check if the requested map string can be an actual map
	if (!preg_match('/^[a-z0-9-_\.!]*$/', $_GET['map'])) {
		header('HTTP/1.0 404 Not Found');
		echo "<h1>yummyumm!</h1>";
		require("_footer.php");
		die();
	} else {
		$zipname = $_GET["map"];
	}

	$dbq = new PDO('sqlite:/srv/http/quaddicted.sqlite');
	$dbq->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

	// if tags were added, add them to the db
	if (isset($_POST['progress'])) {
		if($_POST['tags']) {
			if (!preg_match('/^[a-z0-9, ]*$/', $_POST['tags'])) {
				echo "<h1>only lowercase a-z and 0-9 are allowed in tags. please go back and try again.</h1>";
				require("_footer.php");
				die();
			}

			if (!$pun_user['is_guest']) {
				$username = pun_htmlspecialchars($pun_user['username']);
			} else {echo "wouldn't you like an username with your tags?";}

			$tags = explode(",", ($_POST["tags"]));
			$zipname = $_POST["zipname"];
			$tag_count = 0;
			foreach ($tags as $tag) {
				#$tag = sqlite_escape_string($tag);
				$tag = trim($tag);
				if ($tag) { // do not enter empty tags
					$tag_count++;

					$stmt = $dbq->prepare("INSERT INTO tags (zipname,tag,username) VALUES (:zipname, :tag, :username)");
					$stmt->bindParam(':zipname', $zipname);
					$stmt->bindParam(':tag', $tag);
					$stmt->bindParam(':username', $username);
					$stmt->execute();
					} else { echo "dropped some empty tag(s)";}
			}
			$stmt->closeCursor();
			$stmt = $dbq->prepare("UPDATE users SET num_tags = num_tags + :tag_count WHERE username = :username");
			$stmt->bindParam(':username', $username);
			$stmt->bindParam(':tag_count', $tag_count);
			$stmt->execute();
			$stmt->closeCursor();
		}
	}//end tag

	$preparedStatement = $dbq->prepare('SELECT * FROM maps WHERE zipname = :zipname');
	$preparedStatement->execute(array(':zipname' => $zipname));
	$result = $preparedStatement->fetch();

	if (!$result) {
		header('HTTP/1.0 404 Not Found');
		echo $zipname." is not in the database.";
		require("_footer.php");
		die();
	}
	
	$mapid = $result['id']; //praktisch

	echo "<title>".$result['zipname'].".zip - ".$result['title']." by ".$result['author']." in the Quake map archive at Quaddicted.com</title>";
	echo "<meta name=\"keywords\" content=\"quake, quake map, quake level, quake singleplayer, quake download, ".$result['zipname'].", ".$result['title'].", ".$result['author'],"\" />";
	echo "<meta name=\"description\" content=\"Screenshot, description, tags, comments for the Quake map ".$result['zipname'].".zip - ".$result['title']." by ".$result['author']."\" />";
	echo $html_header2;

	//echo "<body style=\"background: url(/reviews/screenshots/".$zipname.".jpg) no-repeat center center fixed; -webkit-background-size: cover; -moz-background-size: cover; -o-background-size: cover; background-size: cover;\">";
	echo "<body>";

	require("_header.php");
	echo '<div id="content" class="review" itemscope itemtype="http://schema.org/CreativeWork">';
	$redirect_url = "/reviews/".$zipname.".html";
	include("userbar.php"); // include the top login bar, provides $loggedin = true/false

	$authorised_users = array('Spirit','negke','Drew');
	if (isset($pun_user['username']) && in_array($pun_user['username'], $authorised_users)) {
		echo "<a href=\"/reviews/editor/edit.php?zipname=".$zipname."\">edit</a>\n"; // editor is also protected by a separate authentication
	}

echo "<div class=\"left\">";

	// display the screenshot only if we have it
	if (file_exists("/srv/http/reviews/screenshots/".$zipname.".jpg")) {
		echo "<a href=\"/reviews/screenshots/".$zipname.".jpg\"><img src=\"/reviews/screenshots/".$zipname."_thumb.jpg\" alt=\"Screenshot of ".$zipname."\" class=\"screenshot\" /></a>\n";
	}

	/* ===== START INFO TABLE =====*/

	echo "<table id=\"infos\">\n";
	echo "<tr class=\"light\"><td>Author:</td><td><a href=\"/reviews/?filtered=".$result['author']."\" rel=\"nofollow\">".$result['author']."</a></td></tr>\n";
	echo "<tr class=\"dark\"><td>Title:</td><td>".$result['title']."</td></tr>\n";
	echo "<tr class=\"light\"><td>Download:</td><td><a href=\"/filebase/".$zipname.".zip\">".$zipname.".zip</a><small> (".$result['md5sum'].")</small></td></tr>\n";
	echo "<tr class=\"dark\"><td>Filesize:</td><td>".$result['size']." Kilobytes</td></tr>\n";
	echo "<tr class=\"light\"><td>Releasedate:</td><td>".$result['date']."</td></tr>\n";
	if ($result['url']) {
		echo "<tr class=\"dark\"><td>Homepage:</td><td><a href=\"".$result['url']."\">".$result['url']."</a></td></tr>\n";
	} else {
		echo "<tr class=\"dark\"><td>Homepage:</td><td></td></tr>\n";
	}
	echo "<tr class=\"light\"><td>Additional Links:</td><td>\n";

	$preparedStatement = $dbq->prepare('SELECT url,title FROM externallinks WHERE zipname = :zipname');
	$preparedStatement->execute(array(':zipname' => $zipname));
	$externallinks = $preparedStatement->fetchAll();

	if ($externallinks) {
		foreach ($externallinks as $externallink){
			echo "<a href=\"".$externallink['url']."\">".$externallink['title']."</a> &bull; ";
		}
	}
	echo "</td>";
	echo "</tr>\n";
	echo "<tr class=\"dark\"><td>Type:</td><td>";
		switch ($result['type']) {
			case 1:
				echo "Single BSP File(s)";
				break;
			case 2:
				echo "Partial conversion";
				break;
			case 3:
				echo "Total conversion";
				break;
			case 4:
				echo "Speedmapping";
				break;
			case 5:
				echo "Misc. Files";
				break;
			default:
				echo "undefined, please tell Spirit";
				break;
		}
	echo "</td></tr>\n";
	echo "<tr class=\"light\"><td colspan=\"2\">";

	if ($result['hasbsp']==="1") { echo "BSP: <img src=\"/static/tick.png\" class=\"ticks\" alt=\"&#x2714;\" /> • ";}
	else { echo "BSP: <img src=\"/static/cross.png\" class=\"ticks\" alt=\"&#x2718;\" /> • ";}
	if ($result['haspak']==="1") { echo "PAK: <img src=\"/static/tick.png\" class=\"ticks\" alt=\"&#x2714;\" /> • ";}
	else { echo "PAK: <img src=\"/static/cross.png\" class=\"ticks\" alt=\"&#x2718;\" /> • ";}
	if ($result['hasprogs']==="1") { echo "PROGS.DAT: <img src=\"/static/tick.png\" class=\"ticks\" alt=\"&#x2714;\" /> • ";}
	else { echo "PROGS.DAT: <img src=\"/static/cross.png\" class=\"ticks\" alt=\"&#x2718;\" /> • ";}
	if ($result['hascustomstuff']==="1") { echo "Custom Models/Sounds: <img src=\"/static/tick.png\" class=\"ticks\" alt=\"&#x2714;\" />";}
	else { echo "Custom Models/Sounds: <img src=\"/static/cross.png\" class=\"ticks\" alt=\"&#x2718;\" />";}
	echo "</td></tr>\n";

	$preparedStatement = $dbq->prepare('SELECT dependency FROM dependencies WHERE zipname = :zipname');
	$preparedStatement->execute(array(':zipname' => $zipname));
	$dependencies = $preparedStatement->fetchAll();

	if ($dependencies) {
		echo "<tr class=\"dark\"><td>Dependencies:</td>";
		echo "<td>";
		foreach ($dependencies as $dependency){
			echo "<a href=\"".$dependency['dependency'].".html\">".$dependency['dependency']."</a> &bull; ";
		}
		echo "</td></tr>\n";
	}
	echo "</table>\n";

	/* ===== END INFO TABLE =====*/

	// included files
	echo "<br /><table id=\"includedfileslist\" cellpadding=\"1\" cellspacing=\"1\" border=\"1\" rules=\"all\">\n<caption>Files in the ZIP archive:</caption>\n<tr>\n<th>File</th>\n<th>Size</th>\n<th>Date</th>\n</tr>";

	$preparedStatement = $dbq->prepare('SELECT size,date,filename FROM includedfiles WHERE zipname = :zipname');
	$preparedStatement->execute(array(':zipname' => $zipname));
	$includedfiles = $preparedStatement->fetchAll();

	if ($includedfiles) {
		foreach ($includedfiles as $includedfile){
			$filesize = ceil($includedfile['size']/1024);
			echo "<tr><td>".$includedfile['filename']."</td><td align=\"right\">".$filesize." KB</td><td>".$includedfile['date']."</td></tr>";
		}
	}
	echo "</table>";

echo "</div> <!--left-->";

echo "<div class=\"right\">";
	echo "<h2 class=\"title\" itemprop=\"name\">".$result['zipname'].".zip - ".$result['title']."</h2>";
	echo "<span>".$result['description']."</span>\n";

	/* Tags */
	$preparedStatement = $dbq->prepare('SELECT DISTINCT tag FROM tags WHERE zipname = :zipname');
	$preparedStatement->execute(array(':zipname' => $zipname));
	$tags = $preparedStatement->fetchAll();

	if ($tags) {
		echo "<br /><br /><strong>Tags: </strong>";
		foreach ($tags as $tag){
			$tagout = $tagout.", ".$tag[0];
		}
		echo trim($tagout," ,");
	}

	if ($loggedin) {
		echo "<form enctype=\"multipart/form-data\" method=\"post\" action=\"".$zipname.".html\"><div><input type=\"hidden\" name=\"progress\" value=\"1\" /><input type=\"hidden\" name=\"zipname\" value=\"".$zipname."\" />\n"; // zipname.html hat einen htaccess redirect auf details.php
		echo '<br />Add comma-separated tags: <input type="text" name="tags" />
		<input type="submit" value="Submit" /></div></form>';
	} /*else {
		echo "\n<br /><br />You could add tags if you logged in.";
	}*/

	if ($result['type'] != "4") {
		echo "<br /><br /><strong><a href=\"/help/maps#rating\">Editor's Rating</a>: ";
		switch ($result['rating']) {
			case 1:
				echo "Crap";
				break;
			case 2:
				echo "Poor";
				break;
			case 3:
				echo "Average";
				break;
			case 4:
				echo "Nice";
				break;
			case 5:
				echo "Excellent";
				break;
			default:    
				echo "no rating (yet)";
				break;
		}
		echo "</strong><br />\n";
	}

	/* user ratings */
	if($result['sum_ratings']){
			$rating = round($result['sum_ratings'] / $result['num_ratings'],1); // rounds to one decimal point
	}else{
		$rating = 0;
	}

	echo "<div itemprop=\"aggregateRating\" itemscope itemtype=\"http://schema.org/AggregateRating\"><strong>User Rating: </strong>";
	if ($loggedin) {
		echo "<ul class=\"star-rating\">\n<li class=\"current-rating\" id=\"current-rating\" style=\"width: ".($rating *25)."px\">Currently: ".$rating."/5 Stars.</li>\n";
		echo "<li><a href=\"javascript:rateImg(1,'".$zipname."')\" title=\"1 star out of 5\" class=\"one-star\">1</a></li>\n";
		echo "<li><a href=\"javascript:rateImg(2,'".$zipname."')\" title=\"2 stars out of 5\" class=\"two-stars\">2</a></li>\n";
		echo "<li><a href=\"javascript:rateImg(3,'".$zipname."')\" title=\"3 stars out of 5\" class=\"three-stars\">3</a></li>\n";
		echo "<li><a href=\"javascript:rateImg(4,'".$zipname."')\" title=\"4 stars out of 5\" class=\"four-stars\">4</a></li>\n";
    		echo "<li><a href=\"javascript:rateImg(5,'".$zipname."')\" title=\"5 stars out of 5\" class=\"five-stars\">5</a></li>\n";
		echo "</ul>\n";
	}

	echo "<span itemprop=\"ratingValue\">".$rating."</span>/<span itemprop=\"bestRating\">5</span> with <span itemprop=\"ratingCount\">".$result['num_ratings']."</span> ratings";

	// todo would be nicer like "if ($loggedin && ($row['username'] === $username))"
	if ($loggedin) {
              	$preparedStatement = $dbq->prepare('SELECT rating_value FROM ratings WHERE username = :username AND zipname = :zipname');
		$preparedStatement->execute(array(':zipname' => $zipname, ':username' => $username));
		$userrating = $preparedStatement->fetch();

		if ($userrating) {
			echo ", you gave it: <span class=\"userrating\">";
			for ($i=0;$i<$userrating['rating_value'];$i++){
				echo "&hearts;";
			}
			echo "</span>";
		}
	} else {
		echo "\n<br />You can NOT add ratings if you are not logged in.";
	}
	echo "</div>"; //ratings div for schema.org

	//comments
	$preparedStatement = $dbq->prepare('SELECT comment,comments.zipname,time,comments.username,registered,rating_value FROM comments 
					    LEFT OUTER JOIN ratings ON comments.username = ratings.username AND comments.zipname = ratings.zipname 
					    WHERE comments.zipname= :zipname ORDER BY time');
	$preparedStatement->execute(array(':zipname' => $zipname));
	$comments = $preparedStatement->fetchAll();
	echo "<div id=\"comments\"><!--<h2 class=\"title\">Comments</h2>-->\n";
	foreach ($comments as $row){
		if ($loggedin && ($row['username'] === $username)) {
			echo "<div class=\"comment_own\">";
		} else {
			echo "<div class=\"comment\">";
		}
		echo "<strong>".htmlspecialchars($row['username'])."</strong>";
		echo "<small>";
		if (preg_match('/^(negke|Spirit)$/', $row['username'])) {
			echo "<span style=\"color:gold;\" title=\"Premium user\">★</span>";
		}


		if ($row['registered'] === "1" )
		{
			echo " Registered";
		} elseif ($row['registered'] === "0" ) {
			echo " Guest";
		}
		if ($row['rating_value']) { echo ", rated this a ".$row['rating_value'];}
		echo "</small> ";
		echo "<small class=\"commentdate\">".date("j F Y, G:i",$row['time'])."</small>";

		if ($row['time'] > 1363384265) { // markdown was installed afterwards
			$html = $parser->transform($row['comment']); // markdown
			$html = $purifier->purify($html); // html purifier
		} else {
			$html = nl2br(htmlspecialchars($row['comment'])); // before markdown
		}

		echo "<div class=\"commenttext\">".$html."</div>";
		echo "</div><!-- comment -->\n";

	}

	echo "<div id=\"commentform\"><h3>Post a Comment</h3><small>Your comment will be parsed with <a href=\"http://daringfireball.net/projects/markdown/dingus\">Markdown</a>!<br />Keep the comments on topic and do not post nonsense. <br />Did you read the file's readme?</small>";
	echo "<form method=\"post\" action=\"comment.php\">";
	echo "<input type=\"hidden\" name=\"zipname\" value=\"".$zipname."\" />";
	echo "<textarea name=\"comment_text\" cols=\"40\" rows=\"13\"></textarea><br />";
	echo "<div id=\"commentinputfloater\" style=\"text-align:right;\">"; //to align the inputs on the right
	if ($loggedin)
	{
		echo "<input type=\"hidden\" name=\"comment_user\" value=\"".htmlspecialchars($username)."\" />";
	} else {
		echo "Name <input type=\"text\" name=\"comment_user\" maxlength=\"40\" size=\"20\" />";
		echo "<br />Quake was released in <input type=\"text\" name=\"fhtagn\" maxlength=\"4\" size=\"4\" />";
	}

	echo "<br /><input type=\"submit\" name=\"Submit\" value=\"Submit\" /></div></form></div>";
	if (!$loggedin) { echo "</div>"; } // commentinputfloater
	echo "</div>\n"; // commentform
	echo "</div> <!--right-->";
	echo "<div style=\"clear:both;\"></div>";

	$dbq = NULL;
}
else { echo "no map requested";}

/*
$time_end = microtime(true);
$time = $time_end - $time_start;
echo "Rendered in ".($time*1000)." ms\n";*/

require("_footer.php");
ob_end_flush();
?>
