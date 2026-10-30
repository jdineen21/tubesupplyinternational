<?php
// The old (md5 based) password hash. Only used to check accounts that have
// not logged in since the upgrade; see setUserPassword().
function encrypt($string) {
	$salt = sha1(md5($string));
	return md5($string.$salt);
}

// Stores a password with password_hash(). If the password column is too short
// for it (it was sized for 32-character md5 hashes) the old hash is stored
// instead, so the user can still log in, and a note goes to the error log.
// Returns what was stored.
function setUserPassword($userId, $password) {
	$hash = password_hash($password, PASSWORD_DEFAULT);
	db_query(sprintf("UPDATE a_users SET password = '%s' WHERE userId = '%s'", db_escape($hash), db_escape($userId)));

	$row = db_fetch_assoc(db_query(sprintf("SELECT password FROM a_users WHERE userId = '%s'", db_escape($userId))));
	if (!$row || $row['password'] !== $hash) {
		$hash = encrypt($password);
		db_query(sprintf("UPDATE a_users SET password = '%s' WHERE userId = '%s'", db_escape($hash), db_escape($userId)));
		error_log('Passwords are still stored with the old md5 hash: the a_users.password column is too short. '
			. 'Run: ALTER TABLE a_users MODIFY password VARCHAR(255) NOT NULL');
	}
	return $hash;
}

function login($login,$password) {
	global $dbMain;
	
	$query = sprintf("SELECT * FROM a_users WHERE email = '%s'",
	    db_escape($login));
	$result = db_query($query);
	
	if (db_num_rows($result) == 1) {
		$row = db_fetch_assoc($result);

		$legacy = hash_equals((string) $row['password'], encrypt($password));
		if (!$legacy && !password_verify($password, (string) $row['password'])) {
			return false;
		}
		// upgrade an old md5 hash (or an outdated password_hash) now we know the password
		if ($legacy || password_needs_rehash($row['password'], PASSWORD_DEFAULT)) {
			$row['password'] = setUserPassword($row['userId'], $password);
		}

		//user found now log them in
		session_regenerate_id(true); // new session id at login, so an id known before login is useless
		$_SESSION['a_email'] = $row["email"];
		$_SESSION['a_login'] = $row["password"];
		$_SESSION['a_loginType'] = $row["loginType"];
			
		//update last login in db
		$query = sprintf("UPDATE a_users SET lastLogin = '%s' WHERE userId = '%s'",
	    	db_escape( dateToMySQL(date('c'))),
    		db_escape($row["userId"]) );
		db_query($query);
		
		return true;		
	} else {
		return false;
	}
}

// Upload names a web server could run as code (or that change server settings).
// Checked for every part after a dot, so "photo.php.jpg" is refused too.
function isSafeUploadName($name) {
	$name = strtolower(basename(trim($name)));
	if ($name == '' || $name[0] == '.' || $name == 'web.config') {
		return false;
	}
	$blocked = array('php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar',
		'asp', 'aspx', 'asa', 'asax', 'ascx', 'ashx', 'asmx', 'cer', 'cgi', 'pl', 'py', 'rb', 'sh', 'jsp', 'jspx',
		'shtml', 'shtm', 'stm', 'cfm', 'htaccess');
	$parts = explode('.', $name);
	array_shift($parts);
	return !array_intersect($parts, $blocked);
}

function iif($condition, $true, $false) {
    return ($condition ? $true : $false);
}

function generateSlug($phrase, $maxLength=50) {
	
	$result = strip_tags($phrase);
	$result = iconv('UTF-8', 'ASCII//TRANSLIT', $result);
    $result = strtolower($result);
    $result = preg_replace("/[^a-z0-9\s-]/", "", $result);
    $result = trim(preg_replace("/[\s-]+/", " ", $result));
    $result = trim(substr($result, 0, $maxLength));
    $result = preg_replace("/\s/", "-", $result);

    return $result;
	
}

function cleanBadChars($string) {
	
	$badArr = array("\\");
	$clean = str_replace($badArr,"",$string);
	$clean = str_replace("\"","&quot;",$clean);
	$clean = str_replace("&","&amp;",$clean);
	$clean = str_replace("<","&lt;",$clean);
	$clean = str_replace(">","&gt;",$clean);
	return $clean;
	
}

function cleanAndStrip($string) {
	
	// removes html and cleans up characters from Word (smart quotes)
	
	return iconv('UTF-8', 'ASCII//TRANSLIT', strip_tags($string));
	
}

function stripBadHTML($string) {
	$allowedTags = "<p><a><strong><em><u><h1><h2><h3><h4><h5><h6><img><hr>";
	$allowedTags.= "<li><ol><ul><span><div><br><ins><del>";  
	
	return strip_tags(stripslashes($string),$allowedTags);
}


function deleteBranch($branchId) {
	
	// delete the branch
	$query = sprintf("DELETE FROM a_tree WHERE branchId = '%s'",
    db_escape( $branchId ));
	db_query($query);
	
	// move all children to root category
	$query = sprintf("UPDATE a_tree SET parentId = 0 WHERE parentId = '%s'",
    db_escape( $branchId ));
	db_query($query);
	
	// delete repeat content
	$query = sprintf("DELETE FROM a_repeatregionsrows WHERE branchId = '%s'",
    db_escape( $branchId ));
	db_query($query);
	
	// delete images
	$query = sprintf("SELECT a_content.*, a_regions.regionType FROM a_content INNER JOIN a_regions ON a_content.regionId = a_regions.regionId WHERE branchId = '%s' AND regionType = 'image'",
    	db_escape( $branchId ));
	$content = db_query($query);
	
	while($item = db_fetch_assoc($content)) {
		
		$thumb = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'thumb/' . $item['contentId'] . '.jpg';
		if (is_file($thumb)) { unlink($thumb); }
		
		$thumbCustom = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'thumb-custom/' . $item['contentId'] . '.jpg';
		if (is_file($thumbCustom)) { unlink($thumbCustom); }
		
		$original = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'original/' . $item['contentId'] . '.jpg';
		if (is_file($original)) { unlink($original); }
		
		$rendered = $_SERVER['DOCUMENT_ROOT'] . '/media/images/' . 'rendered/' . $item['contentId'] . '.jpg';
		if (is_file($rendered)) { unlink($rendered); }
	
	}
	
	
	// delete content
	$query = sprintf("DELETE FROM a_content WHERE branchId = '%s'",
    db_escape( $branchId ));
	db_query($query);
	
}


function generateMenuHTML($menuId,$menuName,$parentId,$depth,$ulClass,$parentLink = '') {

	global $siteSettings;

    $query = sprintf("SELECT a.*, Deriv1.Count, a_menus.menuId FROM a_tree a  LEFT OUTER JOIN (SELECT parentId, COUNT(*) AS Count FROM a_tree GROUP BY parentId) Deriv1 ON a.branchId = Deriv1.parentId INNER JOIN a_menus ON a.menuId = a_menus.menuId INNER JOIN a_branchtypes ON a.type = a_branchtypes.typeName WHERE a.parentId = '%s' AND a_menus.menuName = '%s' AND a_branchtypes.inStructure = 'Y' ORDER BY a.sortOrder",
        db_escape($parentId),
        db_escape($menuName));

    $nav = db_query($query);

    echo '<ol class="' . $ulClass . '" id="menu' . $menuId . '">';

    while($menuItem = db_fetch_assoc($nav)) {

        $link = $parentLink . '/' . $menuItem['urlSegment'];

        $liClass = '';
        //if ($this->currentBranchId == $menuItem['branchId']) { $liClass = 'current'; }
		
		
		
		if ($siteSettings['homeBranchId'] == $menuItem['branchId']) { // dont allow nesting on the homepage
			$liClass .= ' no-nest';
		}

        echo '<li id="branchId-' . $menuItem['branchId'] . '" class="' . $liClass . '"><div class="listItem"><div class="handle"></div><div class="listItemName"><strong>' . $menuItem['title'] . '</strong>&nbsp;&nbsp;<em>(Navigation title: ' . $menuItem['navTitle'] . ')</em></div><div class="listItemTools"><a href="content-edit.htm?branchId=' . $menuItem['branchId'] . '">Edit</a> ';
		if ($menuItem['allowDelete'] == 'Y') {
			echo '<a class="special Delete" href="javascript:;" onclick="$(\'.formAction\').val(\'delete\'); $(\'.formActionId\').val(' . $menuItem['branchId'] . '); changesMade = false;" name="' . $menuItem['title'] . '">Delete</a>';
		}
		echo '</div><div class="clear"></div></div>';

        if ($menuItem['Count'] > 0) {
            generateMenuHTML($menuId,$menuName,$menuItem['branchId'],$depth + 1,'sub',$link);
            echo '</li>';
        }

    }

    echo '</ol>';

}


function generateOptionListOfPages($parentId=0,$depth=0,$showAll=false,$selected=null) {

	global $siteSettings;
	
	$ignoreHome = ' AND a.branchId <> 1';
	$menuJoin = 'INNER JOIN';
	
	if ($showAll) {
		$ignoreHome = '';
		$menuJoin = 'LEFT OUTER JOIN';
	}
		

    $query = sprintf("SELECT a.*, Deriv1.Count, a_menus.menuId, a_menus.maxDepth FROM a_tree a  LEFT OUTER JOIN (SELECT parentId, COUNT(*) AS Count FROM a_tree GROUP BY parentId) Deriv1 ON a.branchId = Deriv1.parentId " . $menuJoin . " a_menus ON a.menuId = a_menus.menuId INNER JOIN a_branchtypes ON a.type = a_branchtypes.typeName WHERE a.parentId = '%s' AND a_branchtypes.inStructure = 'Y'" . $ignoreHome . " ORDER BY a.menuId, a.sortOrder",
        db_escape($parentId)
        );

    $nav = db_query($query);

   

    while($menuItem = db_fetch_assoc($nav)) {

		$indent = '';
		for ($n = 0; $n < $depth; $n++) {
			$indent = $indent . '- ';
		}

		$select = '';
		if ( $depth+1 < $menuItem['maxDepth'] || $showAll) {
			if ($selected == $menuItem['branchId']) {
				$select = 'selected="selected"';
			}
    		echo '<option value="'  . $menuItem['branchId'] . '"' . $select . '>' . $indent . $menuItem['title'] . '</option>';
		}
	
        if ($menuItem['Count'] > 0) {
            generateOptionListOfPages($menuItem['branchId'],$depth + 1,$showAll,$selected);
        }

    }


}


function generateListOfPages($currentBranchRow,$parentId=0,$depth=0) {

	global $siteSettings;

    $query = sprintf("SELECT a.*, Deriv1.Count, a_menus.menuId, a_menus.maxDepth FROM a_tree a  LEFT OUTER JOIN (SELECT parentId, COUNT(*) AS Count FROM a_tree GROUP BY parentId) Deriv1 ON a.branchId = Deriv1.parentId LEFT OUTER JOIN a_menus ON a.menuId = a_menus.menuId INNER JOIN a_branchtypes ON a.type = a_branchtypes.typeName WHERE a.parentId = '%s' AND a_branchtypes.inStructure = 'Y' ORDER BY a.menuId, a.sortOrder",
        db_escape($parentId)
        );

    $nav = db_query($query);

   

    while($menuItem = db_fetch_assoc($nav)) {

		$indent = $depth * 10;
		$indentIcon = '';
		if ($indent > 0){
			$indentIcon = '-indent';
		}
		
		$currentClass = '';
		if ($currentBranchRow == $menuItem['branchId']){
			$currentClass = 'current ';
		}
		
    	echo '<li id="item_1_' . $menuItem['branchId'] . '" class="' . $currentClass . 'ico gray document' . $indentIcon .'" style="margin-left: ' . $indent . 'px"><a href="content-edit.htm?branchId=' . $menuItem['branchId'] . '">' . $menuItem['title'] . '</a></li>';
		
	
        if ($menuItem['Count'] > 0) {
            generateListOfPages($currentBranchRow,$menuItem['branchId'],$depth + 1);
        }

    }



}



function getURL($branchId,$parent = false) {

	// set $parent to true to show url structure up to the parent:
	// i.e:
	// $parent = false: http://site.com/cars/ford/focus
	// $parent = true:  http://site.com/cars/ford
	
		
	$buildUrl = array();
	$n = 1;
	while ($branchId > 0) { 
		$query = sprintf("SELECT branchId, parentId, urlSegment FROM a_tree WHERE branchId = '%s'",
			db_escape( $branchId ) );
		$result = db_query($query);
		$branch = db_fetch_assoc($result);
		
			array_push($buildUrl,$branch['urlSegment']);	
			$branchId = $branch['parentId'];
			
		$n++;
		if ($n >= 20) { // max 20 steps just in case!
			break;
		}
		
	}
	
	$resultArray = array_reverse($buildUrl);
	
	if ($parent) {
		array_pop($resultArray);
	}
	
	return '/' . implode('/',$resultArray);
		
			
}




// Date functions
function dateToMySQL($date) {
	// converts to a format for DATETIME in MySQL
	return date( 'Y-m-d H:i:s', strtotime($date) );
}

function getUKDate($date) {
//	date_default_timezone_set('Europe/London');      //  Enables daylight savings time PHP5.1 or higher
	return date('d/m/Y', strtotime($date));
}

function getUKDateFromTS($date) {
	return date('d/m/Y', $date); // from a timestamp
}

function getUKDateTime($date) {
	return date('d/m/Y @ H:i', strtotime($date));
}

function setUSDate($date) {
	
	$dateParts = explode('/',$date);
	
	return $dateParts[2] . "-" . $dateParts[1] . "-" . $dateParts[0];
}




// Image manipulation functions
class SimpleImage {
 
   var $image;
   var $image_type;
 
   function load($filename) {
 
      $image_info = getimagesize($filename);
      $this->image_type = $image_info[2];
      if( $this->image_type == IMAGETYPE_JPEG ) {
 
         $this->image = imagecreatefromjpeg($filename);
      } elseif( $this->image_type == IMAGETYPE_GIF ) {
 
         $this->image = imagecreatefromgif($filename);
      } elseif( $this->image_type == IMAGETYPE_PNG ) {
 
         $this->image = imagecreatefrompng($filename);
      }
   }
   
   function save($filename, $image_type=IMAGETYPE_JPEG, $compression=75, $permissions=null) {
 
      if( $image_type == IMAGETYPE_JPEG ) {
         imagejpeg($this->image,$filename,$compression);
      } elseif( $image_type == IMAGETYPE_GIF ) {
 
         imagegif($this->image,$filename);
      } elseif( $image_type == IMAGETYPE_PNG ) {
 
         imagepng($this->image,$filename);
      }
      if( $permissions != null) {
 
         chmod($filename,$permissions);
      }
   }
   
   function output($image_type=IMAGETYPE_JPEG) {
 
      if( $image_type == IMAGETYPE_JPEG ) {
         imagejpeg($this->image);
      } elseif( $image_type == IMAGETYPE_GIF ) {
 
         imagegif($this->image);
      } elseif( $image_type == IMAGETYPE_PNG ) {
 
         imagepng($this->image);
      }
   }
   
   function getWidth() {
 
      return imagesx($this->image);
   }
   
   function getHeight() {
 
      return imagesy($this->image);
   }
   
   function resizeToHeight($height) {
 
      $ratio = $height / $this->getHeight();
      $width = $this->getWidth() * $ratio;
      $this->resize($width,$height);
   }
 
   function resizeToWidth($width) {
      $ratio = $width / $this->getWidth();
      $height = $this->getheight() * $ratio;
      $this->resize($width,$height);
   }
 
   function scale($scale) {
      $width = $this->getWidth() * $scale/100;
      $height = $this->getheight() * $scale/100;
      $this->resize($width,$height);
   }
 
	function resize($width,$height) {
		$new_image = imagecreatetruecolor($width, $height);
		if( $this->image_type == IMAGETYPE_GIF || $this->image_type == IMAGETYPE_PNG ) {
			$current_transparent = imagecolortransparent($this->image);
			if($current_transparent != -1) {
				$transparent_color = imagecolorsforindex($this->image, $current_transparent);
				$current_transparent = imagecolorallocate($new_image, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
				imagefill($new_image, 0, 0, $current_transparent);
				imagecolortransparent($new_image, $current_transparent);
			} elseif( $this->image_type == IMAGETYPE_PNG) {
				imagealphablending($new_image, false);
				$color = imagecolorallocatealpha($new_image, 0, 0, 0, 127);
				imagefill($new_image, 0, 0, $color);
				imagesavealpha($new_image, true);
			}
		}
		imagecopyresampled($new_image, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
		$this->image = $new_image;	
	}      
 
}


?>