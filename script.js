/*$(document).ready(function() */
Drupal.behaviors.saldo = function (context)
        { 
            $("#valid_all").click(function() 
            { 
                var checked_status = this.checked; 
                $("input[@name^=valid]").each(function() 
                { 
                    this.checked = checked_status;
                }); 
            }); 
            $("#lock_all").click(function() 
            { 
                var checked_status = this.checked; 
                $("input[@name^=lock]").each(function() 
                { 
                    this.checked = checked_status; 
                }); 
            });
	    if ($(".select-filter-users").length > 0) {
		  var lnk,lnkt;
		  lnk = "Tutti";
		  lnkt = "Clicca per visualizzare solo gli utenti che hanno dei movimenti assegnati con il sottogruppo Gas attuale.";
		  $(".select-filter-users").prev().append("<a title='"+lnkt+"' href='#' id='filter-edit-users'>"+lnk+"</a><span>("+$(".select-filter-users")[0].length+")</span>")
		  $("a#filter-edit-users").ajaxStart(function(){
			  $(this).next().html("(Caricamento ...)");
		      });
		  $("a#filter-edit-users").click(function()
              {
		  var fgs,lnktn;
		  if ($("a#filter-edit-users").html() == "Filtrati") {
		      lnk="Tutti";
		      lnktn=lnkt;
		      fgs = 0;
		  } else {
		      lnk="Filtrati";
		      lnktn="Clicca per visualizza tutti gli utenti.";
		      fgs = 1;
		  }

		  $.getJSON("",{act: 'gas_users', filter: fgs}, function(j){
		   var options = '';
		   if ($(".select-filter-users option:first")[0].value == 0) {
		       options += '<option value=0 >' + $(".select-filter-users option:first").html() + '</option>';
		   }
	       	   for (var i in j) {
	            options += '<option value=' + i + '>' + j[i] + '</option>';
	           }
		   if ($.browser.msie) {
		       var outerH = $(".select-filter-users")[0].outerHTML;
		       $(".select-filter-users")[0].outerHTML=outerH.substring(0, outerH.indexOf('>', 0) + 1)+options+"</select>";
		   } else {
		       $(".select-filter-users").html(options);
		   }
		   $("a#filter-edit-users").html(lnk);
		   $("a#filter-edit-users").attr("title",lnktn);
		   $("a#filter-edit-users").next().html("("+$(".select-filter-users")[0].length+")");
		  });
		 return false;
              })
	    }
};
