  // Bannir une photo (ou supprimer le ban)
  function banPhoto(pPhotoId, pStatus, pReason){
    return $.ajax({
      type: "POST",
      url: "http://www.mp2.aeroport.fr/bipmod/jeux/mp-reporter/__adm/photosBan.php",
      async: false,
      data: "photo=" + pPhotoId + "&ban=" + pStatus + "&ban_reason=" + pReason
    }).done(function(data) {
      if(pStatus == 1){
        $("#f" + pPhotoId).find($(".ban_photo")).css("display","none");
        $("#f" + pPhotoId).find($(".ban_photo_no")).css("display","block");
      }else{
        $("#f" + pPhotoId).find($(".ban_photo")).css("display","block");
        $("#f" + pPhotoId).find($(".ban_photo_no")).css("display","none");
      }
    });  
  }
  
  // Bannir un joueur (ou supprimer le ban)
  function banPlayer(pPlayerId, pStatus, pReason){
    return $.ajax({
      type: "POST",
      url: "http://www.mp2.aeroport.fr/bipmod/jeux/mp-reporter/__adm/playerBan.php",
      async: false,
      data: "player=" + pPlayerId + "&ban=" + pStatus + "&ban_reason=" + pReason
    }).done(function(data) {
      if(pStatus == 1){
        $(".ban_player[title='" + pPlayerId + "']").css("display","none");
        $(".ban_player_no[title='" + pPlayerId + "']").css("display","block");
      }else{
        $(".ban_player[title='" + pPlayerId + "']").css("display","block");
        $(".ban_player_no[title='" + pPlayerId + "']").css("display","none");
      }
    });  
  }
  
$(document).ready(function() {


  $(".ban_photo").each(function(i){
    $(this).click(function () {
      if(confirm("Souhaitez-vous bannir cette photo ?")){
        var photoId= $(this).attr("title");
        var Reason = prompt("Raison du bannissement :", "Photo non conforme");
        if (Reason != null) {
          banPhoto( photoId, 1, Reason );
          return false;
        }else return false;
      }else return false; 
    });
  });
  
  $(".ban_photo_no").each(function(i){
    $(this).click(function () {
      if(confirm("Souhaitez-vous retirer le bannissement sur cette photo ?")){
        var photoId = $(this).attr("title");
        var Reason = "";
        banPhoto( photoId, 0, Reason );
        return false;
      }else return false;
    });
  });
  
  $(".ban_player").each(function(i){
    $(this).click(function () {
      if(confirm("Souhaitez-vous bannir ce joueur ?")){
        var playerId= $(this).attr("title");
        var Reason = prompt("Raison du bannissement :", "Joueur abusif");
        if (Reason != null) {
          banPlayer( playerId, 1, Reason );
          return false;
        }else return false;
      }else return false; 
    });
  });
  
  $(".ban_player_no").each(function(i){
    $(this).click(function () {
      if(confirm("Souhaitez-vous autoriser de nouveau l'acc&egrave;s au jeu &agrave; ce joueur ?")){
        var playerId = $(this).attr("title");
        var Reason = "";
        banPlayer( playerId, 0, Reason );
        return false;
      }else return false;
    });
  });
  
  /* prepend menu icon */
	$('#nav-wrap').prepend('<div id="menu-icon">Menu</div>');
	
	/* toggle nav */
	$("#menu-icon").on("click", function(){
		$("#nav").slideToggle();
		$(this).toggleClass("active");
	})


});