var debug = false;

// JavaScript Document

// Function to check if page is liked
function like_page(){
  var fql_query = "SELECT uid FROM page_fan WHERE page_id = " + page_id + " and uid = " + player_fb_id;
  FB.Data.query(fql_query).wait(function(rows) {
    if (rows.length == 1) {
      if (rows[0].uid == player_fb_id) doManyThings();
      else userNotKnown();        
    } else userNotKnown();
  });
}
    
// Function to open the auth-dialog and authorize the app (care with antipopup, fb.login opens a dialog box)
function ask_authorize(default_url){
  var this_url = app_url;
  default_url = default_url != '' ? default_url : app_url;
  FB.login(function(response) {
    if (response.authResponse) {
      FB.api('/me', function(response) {
        var sharekey = myKey;
        var json = '{"id":"'+response.id+'","first_name":"'+response.first_name+'","last_name":"'+response.last_name+'","email":"'+response.email+'", "sharekey":"'+sharekey+'"}';
        var ajaxRequest = $.ajax({
          type: "POST",
          url: "setInfosUser.php",
          async: false,
          data: { 'json': json },
          cache: false,
          success: function(data) {
            top.location.href = default_url;
          },
          error: function(jqXHR, textStatus, errorThrown) { }
    	  });
      });
    } else {
      // User cancelled login or did not fully authorize
      userNotKnown();
    }
  }, {scope: 'user_likes,email,user_photos'});
}


function postToFeed(img, id, info) {
  //img = img.replace("_n.", "_s.");
  if (info=="my") {
    var g_name = "Votez pour ma Destination Idéale sur le concours photos - mp Reporter (Aéroport Marseille Provence) !";
    var g_description = "En votant pour moi, vous participez au tirage au sort pour gagner un billet A/R pour 2 vers l'Europe ! Si, comme moi, vous voulez visiter New York, venez poster une photo de votre destination idéale et invitez vos amis à voter pour vous !";
  } else {
    var g_name = "Faites comme moi, votez pour vos photos pr&eacute;f&eacute;r&eacute;es et tentez de gagner un billet A/R pour 2 vers l'Europe !";
    var g_description = "En participant au concours photos, on peut gagner un voyage &agrave; New-York. Mais simplement en votant, on participe aussi &agrave; un tirage au sort pour gagner un billet A/R pour 2 vers l'Europe !";
  }
  $('#overlay').fadeIn('fast');
  
  var key = getKey();
  key = key.trim();
  if (key != "") {
    //var url = app_url.substr(2, app_url.length);
    var url = "https:"+app_url;
    FB.ui({
      method: 'feed',
      link: url+'redirect/'+key,
      picture: img,
      name: g_name,
      caption: 'MP Reporter - Concours photos (A&eacute;roport Marseille Provence)',
      description: g_description
    }, function(response) {
      if (response && response.post_id) {
        insertShare(id);
        popupShare();
      } else disableAllPopup();
    });
  }
}


    
// Init the SDK upon load
window.fbAsyncInit = function() {
  FB.init({
    appId      : app_id,
    channelUrl : app_abs_url+'channel.php', // Channel File
    status     : true, 
    cookie     : true, 
    xfbml      : true
  }); 
  FB.Canvas.setAutoGrow();
 

  FB.Event.subscribe('edge.create', function(response) { 
    if (debug) console.log('edge.create: '+response);
    activeOrDesactiveButtons("active");
  }); 
  FB.Event.subscribe('edge.remove', function(response) {
    if (debug) console.log('edge.remove: '+response);
    activeOrDesactiveButtons("desactive");
  });
  FB.Event.subscribe('comment.create', function(response) {
    checkMessage(response.href, response.commentID);
  });
        
  FB.getLoginStatus(function(response) {
  
    if (debug) console.log('getLoginStatus : '+response.status);
    
    // Connected user, authorized app :
    if (response.status === 'connected') {
      if (debug) console.log('response.authResponse.userID: '+response.authResponse.userID);      
      player_fb_id = parseInt(response.authResponse.userID);
      if(debug) console.log(player_fb_id);
      player_fb_token = response.authResponse.accessToken;      
      FB.api('/'+player_fb_id, function(response) {
        if (debug) console.log('player_fb_id: '+player_fb_id);
        player_fb_lastname = response.last_name;
        player_fb_firstname = response.first_name;
        player_fb_email = response.email;
        like_page();
      });
      
    // Connected user, not authorized app :  
    } else if (response.status === 'not_authorized'){
      activeOrDesactiveButtons("active"); 
      userNotKnown();
    
    // Not connected user :
    } else {
      activeOrDesactiveButtons("desactive"); 
      userNotKnown();
     }      
    
  });
};   

// Load the SDK Asynchronously
(function(d){
  var js, id = 'facebook-jssdk', ref = d.getElementsByTagName('script')[0];
  if (d.getElementById(id)) {return;}
  js = d.createElement('script');
  js.id = id;
  js.async = true;
  js.src = "//connect.facebook.net/en_US/all.js";
  ref.parentNode.insertBefore(js, ref);
}(document));