// CONFIG
  /* AEROPORT */
  var page_id = "";
  var app_id = "";
  var app_name = "mp-reporter";
  var app_url = "//apps.facebook.com/mp-reporter/";
  var app_abs_url = "";
  var app_page_gallery = "galerie";
  var app_page_upload = "soumettre-une-photo";
  var app_page_not_connected = "comment-participer";
  var app_url_not_connected = app_url + app_page_not_connected;

  var player_fb_id;
  var player_fb_lastname;
  var player_fb_firstname;
  var player_fb_email;
  var player_fb_token;
  
  var countdown_height = 72;
  var lenght_title = 60;
  
  var popupCancelText = "Annuler";
  var popupConfirmText = "Confirmer";   
  var popupSubtitle;
  var popupQuestion;
  
  var popupPhotos = "#popup_photos";
  
  var myKey = null;
  var protocol = document.location.protocol;

// validateEmail
function validateEmail(email) {
  var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
  if(reg.test(email) == false) return true;
}


if(typeof String.prototype.trim !== 'function') {
  String.prototype.trim = function() {
    return this.replace(/^\s+|\s+$/g, '');
  }
}

// getUrlVars
function getUrlVars() {
  var vars = [], hash;
  var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');

  for(var i = 0; i < hashes.length; i++) {
    hash = hashes[i].split('=');
    vars.push(hash[0]);
    vars[hash[0]] = hash[1];
  }
  var url = String(vars);
  return url.substr(url.lastIndexOf(app_name+'/') + (app_name.length + 1));
}

// Function to redirect user on non likers/non autorized user page
function userNotKnown(){
  killSession(); 
  if(document.location.href.lastIndexOf(app_page_not_connected) < 0){
    top.location.href = app_url_not_connected;   
  }    
}

// Function to do many things :-)
function doManyThings(){
  if ($('#albums').length > 0) getAlbums();
  activeOrDesactiveButtons("active");     
}

function activeOrDesactiveButtons(action){
  $('#btn_like').unbind("click");
  $('#btn_post').unbind("click");

  if (action=="active") {
    $('#btn_like').click(function(){
      if ($(this).attr('name') != "") ask_authorize(app_url+'photo/'+$(this).attr('name'));
      else ask_authorize(app_url+'galerie');
    });
    $('#btn_post').click(function(){ ask_authorize(app_url+'soumettre-une-photo'); });
  } else {
    $('#btn_like').click(function(){ alert("Pour voter, veuillez liker la page de l'aéroport mp."); });
    $('#btn_post').click(function(){ alert("Pour proposer une photo, veuillez liker la page de l'aéroport mp."); });
  }
}

// addZeroBeforeNumber
function addZeroBeforeNumber(numb){
  numb = numb*1;
  if (numb < 10) { 
    numb = "0" + numb;
  }else numb = numb.toString();
  return numb;
}

// Time between two Dates (in seconds, minutes, hours or days ; define with parameter u)
function diffdate(d1, d2, u){
  div=1;
  switch(u){
    case 's' : div = 1000; break;
    case 'm' : div = 1000*60; break;
    case 'h' : div = 1000*60*60; break;
    case 'd' : div = 1000*60*60*24; break;
  }
  var Diff = d2.getTime() - d1.getTime();
  return Math.ceil((Diff/div))
}

// Function for the Countdown
function showTime(){
  var Date1 = gameNow; 
  var Date2 = gameEndTime;
  
  sec = diffdate(Date1,Date2,'s');
    
  if(sec > 0){
  
    min = Math.floor(sec / 60);
    sec = sec - (min * 60);
    h = Math.floor(min / 60);
    min = min - (h * 60);
    j = Math.floor(h / 24);
    h = h - (j * 24);
    
    j = addZeroBeforeNumber(j);
    h = addZeroBeforeNumber(h);
    min = addZeroBeforeNumber(min);
    sec = addZeroBeforeNumber(sec);
      
    countdown_style = "<span class='numberContainer' style='background-position:0px -";
        
    Compteur = countdown_style + (j.substr(0,1) * countdown_height) + "px;'></span>";
    Compteur += countdown_style + (j.substr(1,1)*countdown_height) + "px; margin-right:4px'></span>";
    Compteur += countdown_style + (h.substr(0,1)*countdown_height) + "px'></span>";
    Compteur += countdown_style + (h.substr(1,1)*countdown_height) + "px; margin-right:4px'></span>";
    Compteur += countdown_style + (min.substr(0,1)*countdown_height) + "px'></span>";
    Compteur += countdown_style + (min.substr(1,1)*countdown_height) + "px; margin-right:4px'></span>";
    Compteur += countdown_style + (sec.substr(0,1)*countdown_height) + "px'></span>";
    Compteur += countdown_style + (sec.substr(1,1)*countdown_height) + "px; clear:right;'></span>";    
    
    $("#compteur").html(Compteur);
    
    Date1 = Date1.setSeconds(Date1.getSeconds()+1);
    
  }else{
    $("#compteur").html("Concours terminé !");
  }
}

// sendRequestToRecipients
function sendRequestToRecipients() {

  var friendsId = "";
  var friendsIdExclude = "";
  var bannishedFriendsId = "";

  // Get the friends id and see if they're using this app
  var fql_query = 'select uid, is_app_user from user where uid in (select uid2 from friend where uid1=me())';
  FB.Data.query(fql_query).wait(function(rows) {
    for(var i = 0; i < rows.length; i++) {
      if(rows[i].is_app_user == 1) friendsIdExclude += rows[i].uid+",";
      else friendsId += rows[i].uid+",";
    }
    friendsId = friendsId.substr(0, friendsId.length-1);
    friendsIdExclude = friendsIdExclude.substr(0, friendsIdExclude.length-1); 
       
    // Get the friend id banned from application
    bannishedFriendsId = friendsToExclude(friendsId);
    bannishedFriendsId = bannishedFriendsId.replace(/ /g,"");    
    if(bannishedFriendsId!="") friendsIdExclude += "," + bannishedFriendsId;
        
    //var reg=new RegExp("(,)", "g");
    //friendsIdExclude = friendsIdExclude.replace(reg,"'$1 '");
    friendsIdExclude = "[" + friendsIdExclude + "]";
    FB.ui({
      method: "apprequests",
      exclude_ids: friendsIdExclude,
      filters: ["app_non_users"],
      message: "En participant au concours photos, on peut gagner un voyage à New-York. Mais simplement en votant, on participe aussi à un tirage au sort !"
    }, requestCallback)
  });
  
}

// requestCallback
function requestCallback(response) {
  addInvitedFriends(response["to"]);
}

// getAlbums
function getAlbums() {
  var tabAlbum = new Array();
  var albumsList = '';
  FB.api('/me/albums?access_token='+player_fb_token, function(response) {
    if (response.error) {
      alert(response.error);
    } else {
      albumsList += '<option value="">Veuillez sélectionner un album</option>';
      for (var i = 0; i < response["data"].length; i++) {
        if (response["data"][i].count) var albumLength = response["data"][i].count;
        else var albumLength = 0;

        if (albumLength > 1) var photosText = "photos";
        else var photosText = "photo";
        albumName = response["data"][i].name + ' (' + albumLength + ' '+ photosText +')';
        
        var albumId = response["data"][i].id;
        
        albumsList += '<option value="' + albumId + '" id="' + albumLength + '">' + albumName + '</option>';
      }
      $('#albums').html('<select id="f_select_album">'+albumsList+'</select>');
      $('#f_select_album').change(function(response){ getPhotos(); });
    }
  });
}
// getPhotos
function getPhotos() {
  var albumId = $('#f_select_album').val();
  
  if (albumId != "") {
    if ($(popupPhotos).length > 0) {
      var albumLength = $('#f_select_album option:selected').attr('id');
      var photosList = "";
      trashPhoto();
      FB.api('/'+albumId+'/photos?limit='+albumLength+'&access_token='+player_fb_token, function(response){
        if (response.error) {
          alert(response.error);
        } else {
          for (var i = 0; i < response.data.length; i++) {
            var photo = response.data;
            photosList = photosList + '<img src="' + photo[i].picture + '" name="' + photo[i].source + '"/>';
          }

          popupSubtitle = "Choisir une photo";
          if (photosList == "") popupQuestion = "Aucune photo dans cet album";
          else popupQuestion = "<div id=\"selected_album\">" + photosList + "</div>";
          $(popupPhotos).find($(".s_btn")).css('visibility','hidden');
          $(popupPhotos).find($(".c_btn")).attr('value','Fermer');
          popupFb(popupPhotos);
          $(popupPhotos).find($(".s_btn")).css('visibility','hidden');
          $('#selected_album').find('img').click(function() {
            $('#selected_photo').html('<img src="'+this.src+'" />');
            $('#f_selected_photo').val(this.name);
            $('#f_select_album').val('');
            disablePopup(popupPhotos);
          });
        }
      });
    }
  } else trashPhoto();
}
// trashPhoto
function trashPhoto() {
  $('#selected_album').empty();
  $('#selected_photo').empty();
  $('#f_selected_photo').val("");
}

function checkFormPhoto() {
  popupQuestion = "";
  popupSubtitle = "Attention :";
  var title = $('#f_title_photo').val();
  if (title == "") {
    popupQuestion += "- Veuillez indiquer un lieu<br/>";
  }
  if (title.length > lenght_title) {
    popupQuestion += "- Le titre ne doit dépasser "+lenght_title+" caractères<br/>";
  }
  if (!$('#privacy').attr('checked')) {
    popupQuestion += "- Vous ne pouvez pas soumettre une photo sans avoir obtenu les autorisations n&eacute;cessaires aupr&egrave;s des personnes qui y sont reconnaissables.<br/>";
  }
  if (!$('#proprio').attr('checked')) {
    popupQuestion += "- Vous devez obligatoirement &ecirc;tre le propri&eacute;taire de la photo pour pouvoir la soumettre.<br/>";
  }
  if (popupQuestion != "") {
    $(popupPhotos).find($(".s_btn")).attr('value','Ok');
    $(popupPhotos).find($(".c_btn")).attr('value','Fermer');
    popupFb(popupPhotos);
    return false;
  } else {
    $('#f_p_send_photo').html('<img src="'+protocol+app_abs_url+'img/loader-fb.gif" alt="Veuillez patienter pendant le chargement du fichier" title="Veuillez patienter pendant le chargement du fichier" />');
    return true;
  }
}


// AJAX
  // checkMessage
  function checkMessage(pHref, pId) {
    if ( (pHref != "") && (pId != "") ) {
      $.ajax({
        type: "POST",
        url: app_abs_url+"checkMessage.php",
        async: false,
        data: "href="+encodeURIComponent(pHref)+"&id="+pId
      });
    }
  }
  
  // acceptNewsletter
  function acceptNewsletter(pThis) {
    var accept = 0;
    if ($(pThis).attr("checked")) accept = 1;
    $('.newsletter').attr("checked", $(pThis).is(":checked"));
    $.ajax({
      type: "POST",
      url: app_abs_url+"acceptNewsletter.php",
      async: false,
      data: "accept="+accept
    }).done(function( msg ) {
      alert(msg.trim());
    });
  }
  // getKey
  function getKey() {
    return $.ajax({
      type: "POST",
      url: app_abs_url+"getKey.php",
      async: false
    }).responseText;
  }
  // killSession
  function killSession() {
    $.ajax({
      type: "POST",
      url: app_abs_url+"killSession.php",
      async: false
    });
    return true;
  }
  // insertShare
  function insertShare(pImgId) {
    $.ajax({
      type: "POST",
      url: app_abs_url+"insertShare.php",
      async: false,
      data: "id="+pImgId
    });
    return true;
  }
  // addInvitedFriends
  function addInvitedFriends(pResult) {
    return $.ajax({
      type: "POST",
      url: app_abs_url+"addInvitedFriends.php",
      async: false,
      data: "id="+player_fb_id+"&result="+pResult
    }).responseText;
  }
  // warnOfAbuse
  function warnOfAbuse(pPhotoId, pIdPopup){
    return $.ajax({
      type: "POST",
      url: app_abs_url+"photosWarnOfAbuse.php",
      async: false,
      data: "photo="+pPhotoId
    }).done(function(data) {
      $(pIdPopup).find($(".contactArea")).html("Votre signalement a bien &eacute;t&eacute; pris en compte. Merci de votre contribution.");
      $(pIdPopup).find($(".s_btn")).css('visibility','hidden');
      $(pIdPopup).find($(".c_btn")).attr('value','fermer');
      
      $("#abuse" + pPhotoId).html("Signal&eacute;e");
      $("#abuse"+pPhotoId).unbind("click");
      $("#abuse" + pPhotoId).attr('class','noreport');
    });
  }
  
  // deletePhoto
  function deletePhoto(pPhotoId){
    return $.ajax({
      type: "POST",
      url: app_abs_url+"photosDelete.php",
      async: false,
      data: "photo="+pPhotoId
    }).done(function(data) {
      if (data) {
        var params = getUrlVars().split('/');
        var url = '';
        for (var i in params) {
          url += params[i]+'/';
        }
        url = url.substring(0, (url.length-1));

        disablePopup('#popup_delete');
        alert('Votre photo a bien été supprimée');
        top.location.href = app_url+url;
      }
    });
  }

  // votePhoto
  function votePhoto(pPhotoId, pIdPopup){
    return $.ajax({
      type: "POST",
      url: app_abs_url+"photosVote.php",
      async: false,
      data: "photo="+pPhotoId
    }).done(function(data) {
      $("#vote"+pPhotoId).html(data);
      $("#vote"+pPhotoId).unbind("click");
      $("#vote"+pPhotoId).attr('class','novote');
      
      disableAllPopup();
      
      img = $("#p" + pPhotoId).attr('src');
      postToFeed(img, pPhotoId, "");
    });
  }
  
  // friendsToExclude
  function friendsToExclude(pFriendsId) {
    return $.ajax({
      type: "POST",
      url: app_abs_url+"friendsToExclude.php",
      async: false,
      data: "friends="+pFriendsId
    }).responseText;
  }
   
  // Load Fake FB Dialog box
  function loadPopup(pIdPopup){
    $(pIdPopup).find($(".s_btn")).css('visibility','visible');
    $(pIdPopup).find($(".popupContact")).css({"display": "block"});
  }
  // Close Fake FB Dialog box
  function disablePopup(pIdPopup){
    $(pIdPopup).find($(".popupContact")).css({"display": "none"});
    $('#overlay').fadeOut('fast');
  }
  function disableAllPopup(){
    $(".popupContact").css({"display": "none"});
    $('#overlay').fadeOut('fast');
  }
  
  

  // popupFb
  function popupFb(pIdPopup){
    var constructPopup = '<div class="popupContact"><a class="popupContactClose">Close</a>'; 
    constructPopup += '<div class="popup_head"><h1>' + app_name + '</h1></div>';
    constructPopup += '<div class="popupSubtitle">' + popupSubtitle + '</div>';
    constructPopup += '<div class="contactArea">' + popupQuestion + '</div>';
    constructPopup += '<div class="buttonArea"><div class="Sharer_btns">';
    constructPopup += '<input type="button" value="' + popupConfirmText + '" class="s_btn"/>';
    constructPopup += '<input type="button" value="' + popupCancelText + '" class="c_btn"/>';
    constructPopup += '</div></div></div>';
    $(pIdPopup).html(constructPopup);
    
   // $(pIdPopup).find($(".popupContact")).center();
    
    FB.Canvas.getPageInfo(
      function(info) {
        var largeur = info.clientWidth;
        var hauteur = info.clientHeight;
        var stop = info.scrollTop;
        var sleft = info.scrollLeft;
        $(".popupContact").css("top", Math.max(0, ((hauteur - $(".popupContact").outerHeight()) / 2)) + stop + "px");
        $(".popupContact").css("left", Math.max(0, ((largeur - $(".popupContact").outerWidth()) / 2)) + sleft + "px");
      }
    );
     
    loadPopup(pIdPopup);
    
    $(pIdPopup).find($(".popupContactClose, .c_btn")).click(function(){ 
      disablePopup(pIdPopup);
    });
    
    //Disable popup on pressing `ESC`:
    $(document).keypress(function(e){
      if(e.keyCode==27) disablePopup(pIdPopup);
    });
    
    $(pIdPopup).find($(".s_btn")).click(function(){
      if ( ($("#popup_warn").length > 0) && (pIdPopup == "#popup_warn") ) popupWarn(pIdPopup);
      if ( ($("#popup_share").length > 0) && (pIdPopup == "#popup_share") ) popupShare(pIdPopup);
      if ( ($("#popup_delete").length > 0) && (pIdPopup == "#popup_delete") ) popupDelete(pIdPopup);
      if ( ($("#popup_vote").length > 0) && (pIdPopup == "#popup_vote") ) popupVote(pIdPopup);
      if ( ($(popupPhotos).length > 0) && (pIdPopup == popupPhotos) ) disablePopup(pIdPopup);
    });        
  }
  
  function popupWarn(pIdPopup){   
    var photoId = $(pIdPopup).find($(".popParamId")).text();
    warnOfAbuse(photoId, pIdPopup);
  }
  
  function popupDelete(pIdPopup){
    var photoId = $(pIdPopup).find($(".popParamId")).text();
    deletePhoto(photoId);
  }
  
  function popupVote(pIdPopup){
    var photoId = $(pIdPopup).find($(".popParamId")).text();
    votePhoto(photoId);
  }
  
  function popupShare(){
    popupSubtitle = "Votre partage a bien été envoyé !"
    popupQuestion = "N'oubliez pas qu'en faisant venir vos amis sur le jeu mp reporter, vous augmentez vos chances d'être tiré au sort pour remporter un billet A/R pour 2 vers l'Europe !";
    popupFb("#popup_share");
    $("#popup_share").find($(".s_btn")).css('visibility','hidden');
    $("#popup_share").find($(".c_btn")).attr('value','Fermer');
  }
  
$(document).ready(function() {
  $("#invite").click(function(){
    sendRequestToRecipients();
  });
  
  if ($('#compteur').length > 0){
    showTime();
    setInterval('showTime()', 1000);
  }
  

  $('.share').each(function(i){
    $(this).click(function () {
      var info = "";
      var photoId = $(this).attr('name');
      var img = $("#p" + photoId).attr('src');
      
      var classShare = $(this).attr('class');
      if (classShare.substr(classShare.length - 2, classShare.length) == "my") info = "my";
      
      postToFeed(img,photoId,info);
    });
  });
  
  $('.abuse').each(function(){
    $(this).click(function () {
      var photoId = $(this).attr('name');
      popupQuestion = "Souhaitez-vous signaler cette photo ?";
      popupSubtitle = 'Signaler une photo <span class="popParamId">' + photoId + '</span>';
      popupFb("#popup_warn");
    });
  });
  
  $('.delete').each(function(){
    $(this).click(function () {
      var photoId = $(this).attr('name');
      popupQuestion = "Souhaitez-vous supprimer cette photo ?";
      popupSubtitle = 'Supprimer une photo <span class="popParamId">' + photoId + '</span>';
      popupFb("#popup_delete");
    });
  });
  
  $('.vote').each(function(i){
    $(this).click(function () {
      var photoId = $(this).attr('name');
      popupQuestion = "Souhaitez-vous voter pour cette photo ?";
      popupSubtitle = 'Voter pour une photo <span class="popParamId">' + photoId + '</span>';
      popupFb("#popup_vote");
    });
  });
  
  if ($('#sorting').length > 0) {
    $('#sorting').change(function(){
      if (this.value !== "") {
        var params = getUrlVars().split('/');
        var page = 1;
        if (params.length > 1) page = params[1];
        top.location.href = app_url+app_page_gallery+'/'+page+'/'+this.value;
      }
    });
  }
  
  $('.newsletter').click(function(){
    acceptNewsletter(this);
  });
  
  setformfieldsize($('#f_title_photo'), lenght_title, 'charsremain')
});