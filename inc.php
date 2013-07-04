<?php
 /*****  checkIsFloat  *****/
  function checkIsNumeric($pVar) {
    if ( is_numeric($pVar) ) return 1;
    else return 0;
  }
  /*****  securityCheckParamInt  *****/
  function securityParamId($pId) {
    $id = mysql_real_escape_string($pId);
    if (checkIsNumeric($id)) return $id;
    else return null;
  }
  /***** parse_signed_request *****/
  function parse_signed_request($signed_request, $secret) {
    list($encoded_sig, $payload) = explode('.', $signed_request, 2);
    // decode the data
    $sig = base64_url_decode($encoded_sig);
    $data = json_decode(base64_url_decode($payload), true);
    if (strtoupper($data['algorithm']) !== 'HMAC-SHA256') {
      error_log('Unknown algorithm. Expected HMAC-SHA256');
      return null;
    }
    // check sig
    $expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
    if ($sig !== $expected_sig) {
      error_log('Bad Signed JSON signature!');
      return null;
    }
    return $data;
  }
  /***** base64_url_decode *****/
  function base64_url_decode($input) {
    return base64_decode(strtr($input, '-_', '+/'));
  }  
  /*******************************/
  /*********** SESSION ***********/
  /*******************************/
  /*****  checkSession  *****/
  function checkSession($pSignedRequest = false){
    global $_SESSION;
    global $K_APP_SECRET;
    global $K_URL_FB;
    
    if (empty($_SESSION['mpjeuid'])) {
      if (!empty($pSignedRequest)) setSession($pSignedRequest);
      else redirect($K_URL_FB);
    } else {
      // Secure multi profiles in same browser
      if (!empty($pSignedRequest)) {
        $tabUser = parse_signed_request($pSignedRequest, $K_APP_SECRET);
        if (!empty($tabUser['user_id'])) {
          if ($tabUser['user_id'] != $_SESSION['mpjeuid']) {
            killSession();
            $_SESSION['mpjeuid'] = $tabUser['user_id'];
            $_SESSION['mpjeukey'] = returnUserKey($tabUser['user_id']);
          }
        }
      }
      // End Secure
    }

    return true;
  }
  /*****  setSession  *****/
  function setSession($pSignedRequest){
    global $_SESSION;
    global $K_APP_SECRET;
    global $K_URL_HOWPLAY;
    global $K_URL_FILE_HOWPLAY;

    $tabUser = parse_signed_request($pSignedRequest, $K_APP_SECRET);

    if (!empty($tabUser['user_id'])) {
      if (!userExists($tabUser['user_id'])) {
        $user_url = "https://graph.facebook.com/".$tabUser['user_id'];
        $user = json_decode(file_get_contents($user_url), true);
        if (!empty($_SESSION['json'])) {
          $jsonPlayer = json_decode($_SESSION['json'], true);
          $userId = $jsonPlayer['id'];
          $userLastName = $jsonPlayer['last_name'];
          $userFirstName = $jsonPlayer['first_name'];
          $userEmail = $jsonPlayer['email'];
          $userInvitedBy = returnPlayerWhoInvited($jsonPlayer['sharekey']);
          addUser($userId, $userLastName, $userFirstName, $userEmail, $userInvitedBy);
        } else {
          $userId = $user['id'];
          addUser($user['id'], $user['last_name'], $user['first_name']);
        }
      } else activateUser($tabUser['user_id']);

      if ( (empty($_SESSION['mpjeuid'])) || (empty($_SESSION['mpjeukey'])) ) {
        $_SESSION['mpjeuid'] = $tabUser['user_id'];
        $_SESSION['mpjeukey'] = returnUserKey($tabUser['user_id']);
      }
    } else {
      // If page !commentParticiper -> K_URL_FILE_HOWPLAY
      $page = substr(
        $_SERVER['PHP_SELF'],
        strrpos($_SERVER['PHP_SELF'], '/') + 1, - 4
      );
      if ($page != $K_URL_FILE_HOWPLAY) redirect($K_URL_HOWPLAY);
    }
  }
  /*****  setSessionvars  *****/
  function setSessionvars($varArray){
  	global $_SESSION;
  	foreach($varArray as $key => $value){
  		$_SESSION[$key] = $value;
  	}
  }  
  /*****  killSession  *****/
  function killSession(){
    global $_SESSION;
    $_SESSION = array();
    session_destroy();
  }
  /*****  checkAdminConnected  *****/
  function checkAdminConnected() {
    if ( (isset($_SESSION['adminIdSession'])) && (!empty($_SESSION['adminIdSession'])) ) return 1;
    else return 0;
  }

  /*****  keyCreator  *****/
  function keyCreator($chars = 40, $items = 'abcdefghijklmnpqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'){
		$output = '';
		$chars = (int)$chars;
		$nbr = strlen($items);
		if($chars > 0 && $nbr > 0){
			for($i = 0; $i < $chars; $i++){
				$output	.= $items[mt_rand(0,($nbr-1))];
			}
		}
		return $output;
	}	
  /*****  redirect  *****/
	function redirect($pUrl) {
    echo "<script>top.location.href='".$pUrl."';</script>";
    exit;
  }
  /*****  checkMessage  *****/
	function checkMessage($pHref, $pId) {
    global $K_URL_FB;
    global $K_GAME_NAME;
    global $K_CONTACT_DEV;

    if ( (!empty($pHref)) && (!empty($pHref)) ) {
      $jsonComment = file_get_contents("https://graph.facebook.com/comments/?ids=".$pHref);
      $jsonComment = json_decode(stripslashes($jsonComment), true);

      // Send Mail
      $sujet = utf8_decode("[Facebook] ".$K_GAME_NAME." - Comment : ".$K_URL_FB);
      $message = utf8_decode("Message numéro : ".$pId."\n---------------------------------------\n\n".print_r($jsonComment, true));
      mail($K_CONTACT_DEV, $sujet, $message);
    }
  }


  /*****  getExtension  *****/
  function getExtension($pName) {
    $tabName = explode(".", $pName);
    $nb = count($tabName);
    return strtolower($tabName[$nb-1]);
  }
  /*****  resizeImage  *****/
  function resizeImage($pImgName, $pExt = "_s", $pWidth = "", $pHeight = "", $pUrl = "", $pDirectory = "") {
    global $K_PHOTOS_GALLERY_WIDTH;
    global $K_PHOTOS_GALLERY_HEIGHT;
    global $K_URL_BASE;
    global $K_DIR_PHOTOS;
    global $K_DOCUMENT_ROOT;
 
    if (empty($pWidth)) $pWidth = $K_PHOTOS_GALLERY_WIDTH;
    if (empty($pHeight)) $pHeight = $K_PHOTOS_GALLERY_HEIGHT;
    if (empty($pUrl)) $pUrl = $K_URL_BASE;
    if (empty($pDirectory)) $pDirectory = $K_DIR_PHOTOS;

    $pathImg = $pUrl.str_replace("../","", $pDirectory);
    $pathImgCheck = $K_DOCUMENT_ROOT.$pDirectory;
    $tabNameImg = explode(".", $pImgName);
    $imgNewName = $tabNameImg[0].$pExt.".".$tabNameImg[1];

    if (!file_exists($pathImgCheck.$imgNewName)) {
      $pObjImg = getimagesize($pathImg.$pImgName);
      $currentWidth = $pObjImg[0];
      $currentHeight = $pObjImg[1];

      $paddingWidth = 0;
      $paddingHeight = 0;

      if ($currentWidth <= $currentHeight) {
        if ($currentWidth > $pWidth) {
          if ($pExt == "_s") {
            $newWidth = $pWidth;
            $newHeight = ($currentHeight * $newWidth) / $currentWidth;
          } else {
            $newWidth = $pWidth;
            $newHeight = ($currentHeight * $newWidth) / $currentWidth;
          }
        } else {
          $newHeight = $currentHeight;
          $newWidth = $currentWidth;
        }
        $paddingHeight = ($newHeight - $pHeight) / 2;
      } else {
        if ($currentHeight > $pHeight) {
          if ($pExt == "_s") {
            $newHeight = $pHeight;
            $newWidth = ($currentWidth * $newHeight) / $currentHeight;
          } else {
            $newHeight = $pHeight;
            $newWidth = ($currentWidth * $newHeight) / $currentHeight;
          }
        } else {
          $newHeight = $currentHeight;
          $newWidth = $currentWidth;
        }
        $paddingWidth = ($newWidth - $pWidth) / 2;
      }
      if ($pExt == "_m") {
        if ($newWidth > $pWidth) {
          $newWidth = $pWidth;
          $newHeight = ($currentHeight * $pWidth) / $currentWidth;
          $paddingWidth = ($pWidth - $newWidth) / 2;
          $paddingHeight = 0;
        } else if ($newHeight > $pHeight) {
          $newHeight = $pHeight;
          $newWidth = ($currentWidth * $pHeight) / $currentHeight;
          $paddingWidth = 0;
          $paddingHeight = ($pHeight - $newHeight) / 2;
        }
      }


      /* SAVE THE PICTURE */
      // Resize the original image
      $imageResized = imagecreatetruecolor($newWidth, $newHeight);
      $imageTmp     = imagecreatefromjpeg ($pDirectory.$pImgName);
      imagecopyresampled($imageResized, $imageTmp, 0, 0, 0, 0, $newWidth, $newHeight, $currentWidth, $currentHeight);

      // Output
      imagejpeg($imageResized, $pDirectory.$imgNewName, 100);
      imageDestroy($imageResized);
      /*****************/
    } else {
      $pObjImg = getimagesize($pathImg.$imgNewName);
      $currentWidth = $pObjImg[0];
      $currentHeight = $pObjImg[1];
      $paddingWidth = 0;
      $paddingHeight = 0;
      $newWidth = $currentWidth;
      $newHeight = $currentHeight;

      if ($currentWidth < $currentHeight) {
        if ($currentHeight > $K_PHOTOS_GALLERY_HEIGHT) $paddingHeight = ($currentHeight - $K_PHOTOS_GALLERY_HEIGHT) / 2;
      } else {
        if ($currentWidth > $K_PHOTOS_GALLERY_WIDTH) $paddingWidth = ($currentWidth - $K_PHOTOS_GALLERY_WIDTH) / 2;
      }
    }

    return array(
      "url" => $pathImg.$imgNewName,
      "width" => round($newWidth),
      "height" => round($newHeight),
      "paddingWidth" => round($paddingWidth),
      "paddingHeight" => round($paddingHeight)
    );
  }
  /*******************************/
  /************* USER ************/
  /*******************************/
  /***** insertDatas *****/
  function insertDatas() {
    global $db_link;

    if (!empty($_SESSION['mpjeuid']))  {
      $query = "INSERT INTO pho_data_players (`data_player_id`, `data_ip`, `data_user_agent`, `data_date`, `data_page`) VALUES(
        ".mysql_real_escape_string(htmlspecialchars($_SESSION['mpjeuid'])).",
        '".mysql_real_escape_string(htmlspecialchars($_SERVER['REMOTE_ADDR']))."',
        '".mysql_real_escape_string(htmlspecialchars($_SERVER['HTTP_USER_AGENT']))."',
        DATE_FORMAT(NOW(),'%Y/%m/%d %H:%i:%S'),
        '".mysql_real_escape_string(htmlspecialchars($_SERVER['PHP_SELF']))."'
      )";
      $result = mysql_query($query, $db_link);
      return true;
    }
    return false;
  }
  /***** insertShare *****/
  function insertShare($pImgId) {
    global $db_link;
    global $K_GAME_ID;

    $securityParam = securityParamId($pImgId);
    if (!empty($securityParam)) {
      if ( (!empty($_SESSION['mpjeuid'])) && (!empty($_SESSION['mpjeukeyshare'])) ) {
        $query = "INSERT INTO pho_share (`share_player_id`, `share_photo_id`, `share_date`, `share_key`, `share_game_id`) VALUES(
          ".mysql_real_escape_string(htmlspecialchars($_SESSION['mpjeuid'])).",
          '".mysql_real_escape_string(htmlspecialchars($pImgId))."',
          DATE_FORMAT(NOW(),'%Y/%m/%d %H:%i:%S'),
          '".$_SESSION['mpjeukeyshare']."',
          '".$K_GAME_ID."'
        )";
        $result = mysql_query($query, $db_link);
        return true;
      }
    }
    return false;
  }


  /***** addUserKey *****/
  function addUserKey($pUserId) {
    global $db_link;

    $securityParam = securityParamId($pUserId);
    if (!empty($securityParam)) {
      $key = keyCreator();
      $query = "UPDATE pho_players SET player_key = '".$key."'
        WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($pUserId));
      $result = mysql_query($query, $db_link);
    }
  }
  /***** returnUserKey *****/
  function returnUserKey($pUserId) {
    global $db_link;

    $securityParam = securityParamId($pUserId);
    if (!empty($securityParam)) {
      $key = keyCreator();
      $query = "SELECT player_key FROM pho_players WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($pUserId));
      $result = mysql_query($query, $db_link);
      $row = mysql_fetch_array($result);
      return $row[0];
    }
  }
  /***** userExists *****/
  function userExists($pUserId) {
    global $db_link;

    $securityParam = securityParamId($pUserId);
    if (!empty($securityParam)) {
      $query = "SELECT * FROM pho_players WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($pUserId));
      $result = mysql_query($query, $db_link);
      return mysql_num_rows($result);
    } else return false;
  }
  /***** userExistsAndActivated *****/
  function userExistsAndActivated($pUserId) {
    global $db_link;

    $securityParam = securityParamId($pUserId);
    if (!empty($securityParam)) {
      $query = "SELECT * FROM pho_players WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($pUserId)). " AND player_activated = 1";
      $result = mysql_query($query, $db_link);
      return mysql_num_rows($result);
    } else return false;
  }
  /***** addUser *****/
  function addUser($pUserId, $pUserName = false, $pUserFirstName = false, $pUserEmail = false, $pInvitedBy = false) {
    global $db_link;

    $securityParam = securityParamId($pUserId);
    if (!empty($securityParam)) {
      if (!userExists($pUserId)) {
        $key = keyCreator();
        $query = "INSERT INTO pho_players (player_id, player_name, player_firstname, player_email, player_date, player_key, player_invited_by)
          VALUES (
            ".mysql_real_escape_string(htmlspecialchars($pUserId)).",
            '".mysql_real_escape_string(htmlspecialchars(utf8_decode($pUserName)))."',
            '".mysql_real_escape_string(htmlspecialchars(utf8_decode($pUserFirstName)))."',
            '".mysql_real_escape_string(htmlspecialchars(utf8_decode($pUserEmail)))."',
            DATE_FORMAT(NOW(),'%Y/%m/%d %H:%i:%S'),
            '".$key."',
            '".$pInvitedBy."'
          )";
        $result = mysql_query($query, $db_link);
      } else {
        activateUser($pUserId);
      }
    }
  }
  /***** deleteUser *****/
  function deleteUser($pId) {
    global $db_link;

    $securityParam = securityParamId($pId);

    if (!empty($securityParam)) {
      $query = "UPDATE pho_players SET player_activated = 0
        WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($pId));
      $result = mysql_query($query, $db_link);
    }
  }
  /***** activateUser *****/
  function activateUser($pId) {
    global $db_link;

    $securityParam = securityParamId($pId);

    if (!empty($securityParam)) {
      $query = "UPDATE pho_players SET player_activated = 1
        WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($pId));
      $result = mysql_query($query, $db_link);
    }
  }

  /***** returnPlayerWhoInvited *****/
  function returnPlayerWhoInvited($pKeyShare) {
    global $db_link;

    if (!empty($pKeyShare)) {
      $query = "SELECT share_player_id FROM pho_share WHERE share_key = '".mysql_real_escape_string(htmlspecialchars($pKeyShare))."'";
      $result = mysql_query($query, $db_link);
      $row = mysql_fetch_array($result);
      return $row[0];
    }
    return null;
  }
  /***** returnInfosWhoInvited *****/
  function returnInfosWhoInvited($pKeyShare) {
    global $db_link;

    if (!empty($pKeyShare)) {
      $query = "SELECT * FROM pho_share WHERE share_key = '".mysql_real_escape_string(htmlspecialchars($pKeyShare))."'";
      $result = mysql_query($query, $db_link);
      return mysql_fetch_array($result);
    }
    return null;
  }
  /***** returnBanReasonByPlayerId *****/
  function returnBanReasonByPlayerId($pPlayerId) {
    global $db_link;

    $securityParam = securityParamId($pPlayerId);

    if (!empty($securityParam)) {
      $query = "SELECT player_ban_reason FROM pho_players WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($pPlayerId));
      $result = mysql_query($query, $db_link);
      $row = mysql_fetch_array($result);
      return $row[0];
    }
    return null;
  }
  /***** checkBanPlayer *****/
  function checkBanPlayer($pPlayerId) {
    global $db_link;
    global $K_URL_BAN;
    global $K_URL_FILE_BAN;

    $securityParam = securityParamId($pPlayerId);

    if (!empty($securityParam)) {
      $query = "SELECT player_ban FROM pho_players WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($pPlayerId));
      $result = mysql_query($query, $db_link);
      $row = mysql_fetch_array($result);

      if ($row[0]) {
        // If page !banpage -> K_URL_BAN
        $page = substr(
          $_SERVER['PHP_SELF'],
          strrpos($_SERVER['PHP_SELF'], '/') + 1, - 4
        );
        if ($page != $K_URL_FILE_BAN) redirect($K_URL_BAN);
      }
    }
    return null;
  }


  /***** returnNewsletterStateByUserId *****/
  function returnNewsletterStateByUserId() {
    global $db_link;

    $query = "SELECT player_nl FROM pho_players WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($_SESSION['mpjeuid']));
    $result = mysql_query($query, $db_link);
    $row = mysql_fetch_array($result);
    return $row[0];
  }
  /***** acceptNewsletter *****/
  function acceptNewsletter($pAccept = 0) {
    global $db_link;

    $securityParamAccept = securityParamId($pAccept);
    if ( ($securityParamAccept == 0) || ($securityParamAccept == 1) ) {
      if ($pAccept) $pAccept = 1;
      else $pAccept = 0;
      $query = "UPDATE pho_players
        SET player_nl = ".mysql_real_escape_string(htmlspecialchars($pAccept))."
        WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($_SESSION['mpjeuid']));
      mysql_query($query, $db_link);
      if ($pAccept) return "Votre abonnement à notre newsletter a bien été prise en compte";
      else return "Votre désabonnement à notre newsletter a bien été prise en compte";
    }
    return "Une erreur est survenue lors de la mise à jour de vos informations. Merci de reformuler votre demande ultérieurement";
  }

   /***** returnplayers *****/
  function returnPlayers($filter= "all", $search=""){
    global $db_link;
    
    $secure_search = mysql_real_escape_string(htmlspecialchars(utf8_decode($search)));
    
    $query = "SELECT pho_players.player_id, pho_players.player_firstname, pho_players.player_name, pho_players.player_ban, COALESCE( req1.nb_votes, 0 ) AS nb_votes, COALESCE( req2.nb_photos, 0 ) AS nb_photos
      FROM pho_players
      LEFT JOIN (
      
      SELECT count( vote_id ) AS nb_votes, player_id
      FROM pho_players
      LEFT JOIN pho_votes ON player_id = vote_player_id
      WHERE player_activated =1
      OR (
        player_activated =0
        AND player_ban =1
      )
      GROUP BY player_id
      ) AS req1 ON req1.player_id = pho_players.player_id
      LEFT JOIN (
      
      SELECT count( photo_id ) AS nb_photos, player_id
      FROM pho_players
      LEFT JOIN pho_photos ON player_id = photo_player_id
      WHERE photo_activated =1
      OR (
        photo_activated =0
        AND photo_ban =1
      )
      GROUP BY player_id
      ) AS req2 ON req2.player_id = pho_players.player_id ";
      
    if($secure_search != ""){
      $query .= " 
      WHERE (  
        pho_players.player_id = '".$secure_search."'
        OR pho_players.player_name LIKE '%".$secure_search."%'
        OR pho_players.player_firstname LIKE '%".$secure_search."%'
        OR CONCAT( pho_players.player_name, ' ', pho_players.player_firstname ) LIKE '%".$secure_search."%'
        OR CONCAT( pho_players.player_firstname, ' ', pho_players.player_name ) LIKE '%".$secure_search."%'
      ) "; 
    }
    elseif($filter =="ban"){
      $query .= "
      WHERE
        pho_players.player_ban = 1
      ";
    
    }
      
      $query.=" ORDER BY pho_players.player_name";
    return mysql_query($query, $db_link);
  }

  /*******************************/
  /************* GAME ************/
  /*******************************/

  /**** infoGame ****/
  function infoGame(){
    global $db_link;
    global $K_GAME_ID;

    $query = "SELECT * FROM pho_game WHERE game_id=".$K_GAME_ID;
    $req = mysql_query($query, $db_link);
    return mysql_fetch_assoc($req);
  }

  /*******************************/
  /*********** PHOTOS ***********/
  /*****************************/

  /**** constructPhotosList ****/
  function constructPhotosList($pResultPhotosList) {
    global $K_URL_FB;
    global $K_PHOTOS_GALLERY_WIDTH;
    global $K_PHOTOS_GALLERY_HEIGHT;
    global $K_PHOTO_TITLE_MAX;
    global $K_PHOTO_TITLE_GALLERY_MAX;

    $content = "";
    $i = 0;
    while ($rowPhotosList = mysql_fetch_assoc($pResultPhotosList)) {
      $photo_title = utf8_encode(stripslashes($rowPhotosList["photo_title"]));
      if (strlen($photo_title) > $K_PHOTO_TITLE_GALLERY_MAX) $photo_title_cut = substr($photo_title, 0, $K_PHOTO_TITLE_GALLERY_MAX)."...";
      else $photo_title_cut = $photo_title;
      $player_firstname = utf8_encode($rowPhotosList["player_firstname"]);
      $player_name = utf8_encode($rowPhotosList["player_name"]);
      $tabSizeImg = resizeImage($rowPhotosList["photo_url"]);
      $marginTop = ($K_PHOTOS_GALLERY_HEIGHT - $tabSizeImg['height'])/2 ;
      $marginLeft = ($K_PHOTOS_GALLERY_WIDTH - $tabSizeImg['width'])/2 ;

      $content .= '<div class="infosContainer"';
        if(($i+1)%3 == 0) $content .=' style="margin-right:0px;"';
        if($i%3 == 0){
          $content .=' style="clear:both;"';
          $i=0;
        }
        $i++;
        $content .= '>
        <a href="'.$K_URL_FB.'photo/'.$rowPhotosList["photo_id"].'" class="photoContainer">';
          $content .= '<img src="'.$tabSizeImg['url'].'" title="'.$photo_title.'" width="'.$tabSizeImg['width'].'" height="'.$tabSizeImg['height'].'" id="p'.$rowPhotosList["photo_id"].'" style="top:'.$marginTop.'px;left:'.$marginLeft.'px;" />
        </a><h3>'.$photo_title_cut.'</h3>';

        $content .= getActionBtns($rowPhotosList);

      $content .= '</div>';
      //$i++;
    }
    $content .= "<p style='clear:both'>&nbsp;</p>";
    return $content;
  }

  /**** returnPagin ****/
  function returnPagin($pCurrentPage, $pNbPhotos, $pOrderDetail, $admin = 0, $type_liste="") {
    global $K_URL_FB;
    global $K_PHOTOS_GALLERY_PER_PAGE;
    global $K_PAGIN_SHOW_PAGES;
    if($admin != 0){
      global $K_URL_BASE;
      $lien = $K_URL_BASE.'__adm/photos/'.$type_liste;
    }else $lien = $K_URL_FB.'galerie/';

    $content = "";
    $nb_pages = ceil($pNbPhotos / $K_PHOTOS_GALLERY_PER_PAGE);
    $page = $pCurrentPage;
    $order_detail = $pOrderDetail;

    $content .= '<div id="pagin">';
    if($page > 1) {
      if (($page-2) >= 1) $content .= '<a href="'.$lien.'1/'. $order_detail .'"><<</a>&nbsp;';
      $content .= '<a href="'.$lien. ($page-1) .'/'. $order_detail .'"><</a>&nbsp;';
      if (($page-2) >= 1) $content .= ' <a href="'.$lien.($page-2).'/'.$order_detail.'">'.($page-2).'</a>&nbsp;';
      $content .= ' <a href="'.$lien.($page-1).'/'.$order_detail.'">'.($page-1).'</a>&nbsp;';
    }
    $content .= '<a href="#" onclick="return false;" class="current">'.$page.'</a>';
    if ($page < $nb_pages) {
      $content .= ' <a href="'.$lien.($page+1).'/'.$order_detail.'">'.($page+1).'</a>&nbsp;';
      if (($page+2) <= $nb_pages) $content .= ' <a href="'.$lien.($page+2).'/'.$order_detail.'">'.($page+2).'</a>&nbsp;';
      $content .= ' <a href="'.$lien.($page+1).'/'.$order_detail.'">></a>&nbsp;';
      if (($page+2) <= $nb_pages) $content .= '<a href="'.$lien. $nb_pages .'/'. $order_detail .'">>></a>&nbsp;';
    }
    $content .= '</div>';

    return $content;
  }

  /**** getActionBtns ****/ 
  function getActionBtns($pRow) {
    $content = '';
    $photoId = $pRow["photo_id"];

    if (empty($pRow["vote"])) $content .= '<span id="vote'.$photoId.'" name="'.$photoId.'" class="vote">Voter ('.$pRow["nb_votes"].')</span>';
    else $content .= '<span class="novote">'.$pRow["nb_votes"].'</span>';

    if ($pRow["player_id"] == $_SESSION['mpjeuid']){
      $content .= '<span id="delete'.$photoId.'" name="'.$photoId.'" class="delete">Supprimer</span>';
    } else {
      if (empty($pRow["photo_admin_validate"])) {
        if (empty($pRow["report"])) $content .= '<span id="abuse'.$photoId.'" name="'.$photoId.'" class="abuse">Signaler</span>';
        else $content .= '<span class="noreport">SIGNAL&Eacute;E</span>';
      }
    }

    $content .= '<span id="share'.$photoId.'" name="'.$photoId.'" class="share ';
      if ($pRow["player_id"] == $_SESSION['mpjeuid']) $content .= ' my';
    $content .= '">Partager</span>';

    return $content;
  }

  /**** returnPhotosList ****/
  /**** NB : request returns vote = 1 means that user voted for this photo (0 = not voted) ****/
  /**** NB : request returns report = 1 means that user reported this photo as abuse (0 = no report) ****/
  function returnPhotosList($player_id, $deb = 0, $fin = 30, $order="photo_date", $order_detail="ASC"){
    global $db_link;
    global $K_GAME_ID;

    $securityParam = securityParamId($player_id);
    if (!empty($securityParam)) {
      if (userExistsAndActivated($player_id)) {
        $query = "SELECT count(vote_id) AS nb_votes,
        MAX(IF(vote_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id)).", 1, 0)) AS vote,
        MAX(IF(report_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id)).", 1, 0)) AS report,
        photo_id, photo_title, photo_url, player_id, player_name, player_firstname, photo_admin_validate
        FROM pho_photos
          LEFT JOIN pho_players ON photo_player_id = player_id
          LEFT JOIN pho_votes ON vote_photo_id = photo_id
          LEFT JOIN pho_report_photos ON report_photo_id = photo_id AND report_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id))."
        WHERE
          photo_game_id = ".$K_GAME_ID."
          AND photo_activated = 1
          AND photo_ban = 0
          AND player_activated = 1
          AND player_ban = 0
        GROUP BY photo_id, report_photo_id
        ORDER BY ".mysql_real_escape_string(htmlspecialchars($order))." ".mysql_real_escape_string(htmlspecialchars($order_detail))."
        LIMIT ".mysql_real_escape_string(htmlspecialchars($deb)).", ".mysql_real_escape_string(htmlspecialchars($fin));
        return mysql_query($query, $db_link);
      }
    }
    return false;
  }

  /**** returnRandomPhotosList ****/
  function returnRandomPhotosList($player_id, $fin = 3){
    global $db_link;
    global $K_GAME_ID;

    $securityParam = securityParamId($player_id);
    if (!empty($securityParam)) {
      if (userExistsAndActivated($player_id)) {
        $query = "SELECT count(vote_id) AS nb_votes,
        MAX(IF(vote_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id)).", 1, 0)) AS vote,
        MAX(IF(report_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id)).", 1, 0)) AS report,
        photo_id, photo_title, photo_url, player_id, player_name, player_firstname
        FROM pho_photos
          LEFT JOIN pho_players ON photo_player_id = player_id
          LEFT JOIN pho_votes ON vote_photo_id = photo_id
          LEFT JOIN pho_report_photos ON report_photo_id = photo_id AND report_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id))."
        WHERE
          photo_game_id = ".$K_GAME_ID."
          AND photo_activated = 1
          AND photo_ban = 0
          AND player_activated = 1
          AND player_ban = 0
        GROUP BY photo_id
        ORDER BY rand()
        LIMIT 0, ".mysql_real_escape_string(htmlspecialchars($fin));
        return mysql_query($query, $db_link);
      }
    }
    return false;
  }

  /**** returnPhotos ****/
  function returnPhotos($deb = 0, $fin = 30, $order_detail="asc", $type_liste ="all", $search=""){
    global $db_link;
    global $K_GAME_ID;
    
    $secure_search = mysql_real_escape_string(htmlspecialchars(utf8_decode($search)));

    $query = "SELECT COUNT(report_photo_id) as count_report, report_photo_id, photo_id, photo_title, photo_url, player_id, player_name, player_firstname, photo_ban, player_activated, player_ban
    FROM pho_photos
      LEFT JOIN pho_players ON photo_player_id = player_id
      LEFT JOIN pho_report_photos ON report_photo_id = photo_id 
    WHERE
      photo_game_id = ".$K_GAME_ID;
      if($type_liste == "ban") $query .= " AND photo_ban = 1 ";
      elseif($type_liste == "report") $query .= " AND report_photo_id IS NOT NULL ";
      elseif($type_liste == "search"){
        $query .= " 
        AND (  
          photo_id = '".$secure_search."'
          OR player_name LIKE '%".$secure_search."%'
          OR player_firstname LIKE '%".$secure_search."%'
          OR photo_title LIKE '%".$secure_search."%'
          OR CONCAT( player_name, ' ', player_firstname ) LIKE '%".$secure_search."%'
          OR CONCAT( player_firstname, ' ', player_name ) LIKE '%".$secure_search."%'
        ) "; 
      }
      $query .= " AND (photo_activated = 1 OR (photo_activated= 0 AND photo_ban = 1)) ";

    $query.=" GROUP BY photo_id
    ORDER BY photo_date ".$order_detail." 
    LIMIT ".mysql_real_escape_string(htmlspecialchars($deb)).", ".mysql_real_escape_string(htmlspecialchars($fin));  
    return mysql_query($query, $db_link);
  }

  /*****  returnPhotosByPhotographerId  *****/
  function returnPhotosByPhotographerId($player_id, $photographer_id, $pCurrentPhoto = 0 ){
    global $db_link;
    global $K_GAME_ID;

    $securityParam = securityParamId($photographer_id);
    if (!empty($securityParam)) {
      if (userExistsAndActivated($player_id)) {
       $query = "SELECT count(vote_id) AS nb_votes,
        MAX(IF(vote_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id)).", 1, 0)) AS vote,
        MAX(IF(report_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id)).", 1, 0)) AS report,
        photo_id, photo_title, photo_url, player_id, player_name, player_firstname
        FROM pho_photos
          LEFT JOIN pho_players ON photo_player_id = player_id
          LEFT JOIN pho_votes ON vote_photo_id = photo_id
          LEFT JOIN pho_report_photos ON report_photo_id = photo_id AND report_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id))."
        WHERE
          photo_game_id = ".$K_GAME_ID."
          AND photo_activated = 1
          AND photo_ban = 0
          AND player_activated = 1
          AND player_ban = 0
          AND player_id = ".mysql_real_escape_string(htmlspecialchars($photographer_id));
        if(!empty($pCurrentPhoto)){
          $query .= " AND photo_id != ".mysql_real_escape_string(htmlspecialchars($pCurrentPhoto));
        }            
        $query .= " GROUP BY photo_id";
        return mysql_query($query, $db_link);        
      }
    }
  }

  /*****  returnBannishedPhotosByPlayerId  *****/
  function returnBannishedPhotosByPlayerId($player_id){
    global $db_link;
    global $K_GAME_ID;

    $securityParam = securityParamId($player_id);
    if (!empty($securityParam)) {
      if (userExistsAndActivated($player_id)) {
       $query = "SELECT photo_title, photo_ban_reason
        FROM pho_photos
          LEFT JOIN pho_players ON photo_player_id = player_id
        WHERE
          photo_game_id = ".$K_GAME_ID."
          AND photo_ban = 1
          AND player_activated = 1
          AND player_ban = 0
          AND player_id = ".mysql_real_escape_string(htmlspecialchars($player_id));
        return mysql_query($query, $db_link);        
      }
    }
    return null;
  }

  /*****  returnPhotoIdByPhotoKey  *****/
  function returnPhotoIdByPhotoKey($pKey){
    global $db_link;
    global $K_GAME_ID;

    $query = "SELECT share_photo_id FROM pho_share WHERE share_key = '".mysql_real_escape_string(htmlspecialchars($pKey))."'";
    $result = mysql_query($query, $db_link);
    $row = mysql_fetch_array($result);
    return $row[0];
  }
  /*****  returnPhotoByPhotoId  *****/
  function returnPhotoByPhotoId($pId){
    global $db_link;
    global $K_GAME_ID;

    $securityParam = securityParamId($pId);
    if (!empty($securityParam)) {
      $query = "SELECT * FROM pho_photos WHERE photo_id = ".mysql_real_escape_string(htmlspecialchars($pId))."
        AND photo_activated = 1
        AND photo_ban = 0";
      $result = mysql_query($query, $db_link);
      return mysql_fetch_assoc($result);
    }
    return null;
  }

  /*****  shareAndClic  *****/
  function shareAndClic($pKey){
    global $db_link;
    global $K_GAME_ID;

    $query = "UPDATE pho_share SET
      share_clics = (share_clics + 1)
      WHERE share_key = '".mysql_real_escape_string(htmlspecialchars($pKey))."'
      AND share_game_id = ".mysql_real_escape_string(htmlspecialchars($K_GAME_ID));
    $result = mysql_query($query, $db_link);
    return true;
  }

  /**** nbPhotos ****/
  function returnNbPhotos(){
    global $db_link;
    global $K_GAME_ID;

    $req = mysql_query("SELECT count(photo_id) FROM pho_photos LEFT JOIN pho_players ON photo_player_id = player_id
      WHERE photo_game_id = ".$K_GAME_ID."
      AND photo_activated = 1
      AND photo_ban = 0
      AND player_activated = 1
      AND player_ban = 0", $db_link);
    $req = mysql_fetch_array($req);
    return $req[0];
  }
  
  /**** nbPhotosAdmin ****/
  function returnNbPhotosAdmin($type_liste="all", $search=""){
    global $db_link;
    global $K_GAME_ID;
    
    $query = "SELECT COUNT(photo_id) FROM pho_photos
      LEFT JOIN pho_players ON photo_player_id = player_id
      LEFT JOIN pho_report_photos ON report_photo_id = photo_id 
    WHERE
      photo_game_id = ".$K_GAME_ID;
      if($type_liste == "ban") $query .= " AND photo_ban = 1 ";
      elseif($type_liste == "report") $query .= " AND report_photo_id IS NOT NULL ";
      elseif($type_liste == "search"){
        $query .= " AND (
            photo_id = '".$search."'
            OR player_name LIKE '%".$search."%'
            OR player_firstname LIKE '%".$search."%'
            OR photo_title LIKE '%".$search."%'
            OR CONCAT( player_name, ' ', player_firstname ) LIKE '%".$search."%'
            OR CONCAT( player_firstname, ' ', player_name ) LIKE '%".$search."%'
            ) ";
      }
    $query .= " AND (photo_activated = 1 OR (photo_activated= 0 AND photo_ban = 1))";    
    
    $req = mysql_query($query, $db_link);
    $req = mysql_fetch_array($req);
    return $req[0];
  }
  

  

  /**** returnNbPhotosByPlayerId ****/
  function returnNbPhotosByPlayerId($player_id){
    global $db_link;
    global $K_GAME_ID;

    $securityParam = securityParamId($player_id);
    if (!empty($securityParam)) {
      if (userExistsAndActivated($player_id)) {
        $query = "SELECT count(photo_id) FROM pho_photos
          LEFT JOIN pho_players ON photo_player_id = player_id
            AND player_activated = 1
            AND player_ban = 0
          WHERE photo_game_id = ".$K_GAME_ID."
          AND photo_activated = 1
          AND photo_ban = 0
          AND photo_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id));
        $result = mysql_query($query, $db_link);
        $row = mysql_fetch_array($result);
        return $row[0];
      }
    }
    return false;
  }

  /**** photoInfos ****/
  function photoInfos($player_id, $photoId) {
    global $db_link;
    
    $securityParamPhotoId = securityParamId($photoId);
    $securityParamPlayerId = securityParamId($player_id);

    if ( (!empty($securityParamPhotoId)) && (!empty($securityParamPlayerId)) ) {
      if (userExistsAndActivated($player_id)) {
        $query = "SELECT count(vote_id) AS nb_votes,
        MAX(IF(vote_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id)).", 1, 0)) AS vote,
        MAX(IF(report_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id)).", 1, 0)) AS report,
        photo_title, photo_id, photo_url, player_id, player_name, player_firstname
        FROM pho_photos
          LEFT JOIN pho_players ON photo_player_id = player_id
          LEFT JOIN pho_votes ON vote_photo_id = photo_id
          LEFT JOIN pho_report_photos ON report_photo_id = photo_id
        WHERE
          photo_id = ".mysql_real_escape_string(htmlspecialchars($photoId))."
          AND photo_activated = 1
          AND photo_ban = 0
          AND player_activated = 1 
          AND player_ban = 0
          GROUP BY photo_id";  
        $result = mysql_query($query, $db_link);
        if(mysql_num_rows($result)){        
          return mysql_fetch_assoc($result);
        }
      }
    }
    return false;
  }

  /**** photoReport ****/
  function photoReport($photo_id, $player_id){
    global $db_link;

    $securityParamPhotoId = securityParamId($photo_id);
    $securityParamPlayerId = securityParamId($player_id);

    if ( (!empty($securityParamPhotoId)) && (!empty($securityParamPlayerId)) ) {
      if (userExistsAndActivated($player_id)) {
        if (!photoAdminValidate($photo_id)) {
          $query = "INSERT INTO pho_report_photos(report_photo_id, report_player_id, report_date)
            VALUES(".mysql_real_escape_string(htmlspecialchars($photo_id)).",
            ".mysql_real_escape_string(htmlspecialchars($player_id)).",
            '".date("Y-m-d")."')";
          mysql_query($query, $db_link);
          return true;
        }
      }
    }
    return false;
  }
  /**** photoAdminValidate ****/
  function photoAdminValidate($photo_id){
    global $db_link;

    $securityParam = securityParamId($photo_id);
    if (!empty($securityParam)) {
      $query = "SELECT photo_admin_validate FROM pho_photos WHERE photo_id = ".mysql_real_escape_string(htmlspecialchars($photo_id));
      $result = mysql_query($query, $db_link);
      $row = mysql_fetch_array($result);
      return $row[0];
    }
    return false;
  }

  /**** photoVote ****/
  function photoVote($photo_id, $player_id){
    global $db_link;
    global $K_GAME_ID;

    $securityParamPhotoId = securityParamId($photo_id);
    $securityParamPlayerId = securityParamId($player_id);
    
    if ( (!empty($securityParamPhotoId)) && (!empty($securityParamPlayerId)) ) {
      if (userExistsAndActivated($player_id)) {
        if (photoVoteExists($photo_id, $player_id) == 0) {
          $query = "INSERT INTO pho_votes(vote_photo_id, vote_player_id, vote_date, vote_game_id)
            VALUES(".mysql_real_escape_string(htmlspecialchars($photo_id)).",
            ".mysql_real_escape_string(htmlspecialchars($player_id)).",
            DATE_FORMAT(NOW(),'%Y/%m/%d %H:%i:%S'),
            ".$K_GAME_ID.")";
          mysql_query($query, $db_link);
        }
        return photoCountVotes($photo_id);
      }
    }
    return false;
  }
  /**** photoVoteExists ****/
  function photoVoteExists($photo_id, $player_id){
    global $db_link;
    global $K_GAME_ID;

    $securityParamPhotoId = securityParamId($photo_id);
    $securityParamPlayerId = securityParamId($player_id);

    if ( (!empty($securityParamPhotoId)) && (!empty($securityParamPlayerId)) ) {
      $query = "SELECT * FROM pho_votes
        WHERE vote_photo_id = ".mysql_real_escape_string(htmlspecialchars($photo_id))."
        AND vote_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id))."
        AND vote_game_id = ".$K_GAME_ID;
      $result = mysql_query($query, $db_link);
      return mysql_num_rows($result);
    }
    return false;
  }

  /* photoCountVotes */
  function photoCountVotes($photo_id){
    global $db_link;
    global $K_GAME_ID;

    $securityParam = securityParamId($photo_id);
    if (!empty($securityParam)) {
      $query = "SELECT count(*) AS nb FROM pho_votes WHERE vote_photo_id =".mysql_real_escape_string(htmlspecialchars($photo_id))." AND vote_game_id =".$K_GAME_ID;
      $req = mysql_fetch_assoc(mysql_query($query, $db_link));
      return $req["nb"];
    }
    return false;
  }

  /**** photoDelete ****/
  function photoDelete($photo_id, $player_id){
    global $db_link;
    global $K_GAME_ID;

    $securityParamPhotoId = securityParamId($photo_id);
    $securityParamPlayerId = securityParamId($player_id);

    if ( (!empty($securityParamPhotoId)) && (!empty($securityParamPlayerId)) ) {
      if (userExistsAndActivated($player_id)) {
        if (photoPlayer($photo_id, $player_id)) {
          $query = "UPDATE pho_photos SET photo_activated = 0
            WHERE photo_id = ".mysql_real_escape_string(htmlspecialchars($photo_id))."
            AND photo_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id))."
            AND photo_game_id = ".mysql_real_escape_string(htmlspecialchars($K_GAME_ID));
          mysql_query($query, $db_link);
        }
        return true;
      }
    }
    return false;
  }
  /**** photoPlayer ****/
  function photoPlayer($photo_id, $player_id){
    global $db_link;
    global $K_GAME_ID;

    $securityParamPhotoId = securityParamId($photo_id);
    $securityParamPlayerId = securityParamId($player_id);

    if ( (!empty($securityParamPhotoId)) && (!empty($securityParamPlayerId)) ) {
      $query = "SELECT photo_id FROM pho_photos
        WHERE photo_id = ".mysql_real_escape_string(htmlspecialchars($photo_id))."
        AND photo_player_id = ".mysql_real_escape_string(htmlspecialchars($player_id))."
        AND photo_game_id = ".$K_GAME_ID;
      $result = mysql_query($query, $db_link);
      return mysql_num_rows($result);
    }
    return false;
  }

  /*****  returnPlayerByPlayerFb  *****/
  function returnPlayerByPlayerFb($pFbPlayer){
    global $db_link;
    
    if (securityParamId($pFbPlayer) != null) {
      $query = "SELECT * FROM pho_players
        WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($pFbPlayer));
      $result = mysql_query($query, $db_link);
      return mysql_fetch_assoc($result);
    }  
  }

  /***** invitedFriendExists *****/
  function invitedFriendExists($pUserId, $pInvitedFriendId) {
    global $db_link;
    global $K_GAME_ID;

    $securityParamUserId = securityParamId($pUserId);
    $securityParamInvitedFriendId = securityParamId($pInvitedFriendId);
    if ( (!empty($securityParamUserId)) && (!empty($securityParamInvitedFriendId)) ) {
      $query = "SELECT * FROM pho_guests
        WHERE guest_guest_id = ".mysql_real_escape_string(htmlspecialchars($pInvitedFriendId))."
        AND guest_player_id = ".mysql_real_escape_string(htmlspecialchars($pUserId))."
        AND guest_game_id = ".$K_GAME_ID;
      $result = mysql_query($query, $db_link);
      return mysql_num_rows($result);
    }
  }

  /***** bannishedFriends *****/
  function bannishedFriends ($pUserId, $pInvitedFriendId) {
    global $db_link;
    global $K_GAME_ID;
    
    $securityParamUserId = securityParamId($pUserId);
    $securityParamInvitedFriendId = securityParamId($pInvitedFriendId);
    
    $bannishedFriends = "";

    if (!empty($securityParamInvitedFriendId)) {
      $securityParamInvitedFriendId = str_replace(","," OR player_id=",$securityParamInvitedFriendId);
      
      $query = "SELECT player_id FROM pho_players WHERE player_ban=1 AND (player_id=".$securityParamInvitedFriendId.")";
      $result = mysql_query($query, $db_link);
      while($row = mysql_fetch_assoc($result)){
        $bannishedFriends .= "'".$row["player_id"]."',"; 
      }             
      if (!empty($bannishedFriends)) return substr($bannishedFriends, 0, -1);
      else return $bannishedFriends;            
    }else return "";
  
  };
  
   /**** photoBan ****/
  function photoBan($photo_id, $ban = 0, $ban_reason = ""){
    global $db_link;

    $securityParamPhotoId = securityParamId($photo_id);

    if (!empty($securityParamPhotoId)) {
      if($ban == 0) $active = 1;
      else $active = 0;
      $query = "UPDATE pho_photos SET photo_activated = ".$active.", photo_admin_validate = 1, photo_ban = ".$ban.", photo_ban_reason = '".$ban_reason."'
          WHERE photo_id = ".mysql_real_escape_string(htmlspecialchars($photo_id));
      mysql_query($query, $db_link);   
      return true;
    }
    return false;
  }
  
   /**** playerBan ****/
  function playerBan($player_id, $ban = 0, $ban_reason = ""){
    global $db_link;

    $securityParamPlayerId = securityParamId($player_id);

    if (!empty($securityParamPlayerId)) {
      if($ban == 0) $active = 1;
      else $active = 0;
      $query = "UPDATE pho_players SET player_activated = ".$active.", player_ban = ".$ban.", player_ban_reason = '".$ban_reason."'
          WHERE player_id = ".mysql_real_escape_string(htmlspecialchars($player_id));
      mysql_query($query, $db_link);   
      return true;
    }
    return false;
  }


  /***** addInvitedFriends *****/
  function addInvitedFriends($pUserId, $pResultIds) {
    global $db_link;
    global $K_GAME_ID;

    $securityParam = securityParamId($pUserId);
    if (!empty($securityParam)) {
      $tabInvitedFriends = explode(",", $pResultIds);

      foreach($tabInvitedFriends as $invitedFriendId) {
        if (!invitedFriendExists($pUserId, $invitedFriendId)) {
          $query = "INSERT INTO pho_guests (guest_player_id, guest_guest_id, guest_game_id)
            VALUES (
              ".mysql_real_escape_string(htmlspecialchars($pUserId)).", 
              ".mysql_real_escape_string(htmlspecialchars($invitedFriendId)).",
              ".mysql_real_escape_string(htmlspecialchars(utf8_decode($K_GAME_ID)))."
            )";
          $result = mysql_query($query, $db_link);
        }
      }
    }
  }  
?>