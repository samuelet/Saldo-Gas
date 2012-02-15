<?php
global $grp_admins, $user, $suser, $saldo_req, $gas_subgroups;
if (!$user->uid) {
  drupal_set_message("Autenticazione necessaria");
  drupal_goto();
}
ini_set('memory_limit', '96M');
DEFINE('SALDO_UTENTI','{utente_gas}');
DEFINE('ROLE_AUTH',0);
DEFINE('ROLE_ADMIN',1);
DEFINE('ROLE_TREASURER',2);
DEFINE('SALDO_BASE',dirname(__FILE__));
DEFINE('SALDO_INC',SALDO_BASE.'/inc');
include_once (SALDO_INC."/utils.inc.php");
#Necessario?
//$GLOBALS['conf']['cache'] = FALSE;
$grp_admins='Saldo Admins';
$gas_subgroups=array('ms_'=>'GAS Massa','mg_'=>'GAS Montignoso');

$suser=get_user($gas_subgroups);

DEFINE('SALDO_ORDINI','{'.$_SESSION['saldo_prefix'].'ordine_gas}');
DEFINE('SALDO_FORNITORI','{'.$_SESSION['saldo_prefix'].'fornitore_gas}');
DEFINE('SALDO_VERSAMENTI','{'.$_SESSION['saldo_prefix'].'versamento_gas}');
DEFINE('SALDO_DEBITO_CREDITO','{'.$_SESSION['saldo_prefix'].'credito_gas}');
DEFINE('SALDO_FIDPAGAMENTO','{'.$_SESSION['saldo_prefix'].'fidpagamento_gas}');
DEFINE('SALDO_SUBREFERS','{'.$_SESSION['saldo_prefix'].'subreferente_gas}');
DEFINE('SALDO_ROLES','{'.$_SESSION['saldo_prefix'].'roles_gas}');
DEFINE('SALDO_PWD','{'.$_SESSION['saldo_prefix'].'pwds_gas}');
DEFINE('SALDO_LOG','{'.$_SESSION['saldo_prefix'].'log_gas}');

get_user_refers($suser);
get_user_roles($suser);

if (is_array($_SESSION['orders_import_table'])) {
  $saldo_req=(defined('SALDO_REFCANIMPORT')) ? 'fcsvorders' : 'csvorders';
 } elseif (is_array($_SESSION['users_import_table'])) {
   $saldo_req='csvusers';
   } elseif (is_array($_SESSION['pay_import_table'])) {
     $saldo_req='csvpay';
     } else {
  $saldo_req=$_REQUEST['act'];
}

function saldo_main () {
  global $suser, $saldo_req, $gas_subgroups;
  $user_gas=$gas_subgroups[$_SESSION['saldo_prefix']];
  $admtitle=" - Amministratore (".$user_gas.")";
  $tsrtitle=" - Tesoriere (".$user_gas.")";
  $reftitle=" - Referente (".$user_gas.")";
  $usrtitle=" - Utente (".$user_gas.")";
  drupal_add_js("saldo/script.js");
  drupal_add_css("saldo/style.css", "theme");
  drupal_add_css("saldo/print.css","theme",'print');

  if (saldo_check_role(ROLE_ADMIN)) {
    switch ($saldo_req) {
    case 'admlog':
      include_once (SALDO_INC.'/admlog.inc.php');
      drupal_set_title('Logs'.$admtitle);
      $output.=drupal_get_form('log_adm_form');
      break;
    case 'admroles':
      include_once (SALDO_INC.'/admroles.inc.php');
      drupal_set_title('Ruoli'.$admtitle);
      $output.=drupal_get_form('roles_adm_form');
      break;
    case 'admpwds':
      include_once (SALDO_INC.'/admpwds.inc.php');
      drupal_set_title('Password'.$admtitle);
      $output.=drupal_get_form('pwds_adm_form');
      break;
    }
  }

  if (saldo_check_role(ROLE_TREASURER)) {
    switch ($saldo_req) {
    case 'gas_users':
      $users = get_users(FALSE,$_GET['filter']);
      print drupal_to_js($users);
      exit;
      break;
    case 'csvorders':
	case 'fcsvorders':
      include_once (SALDO_INC.'/csvorders.inc.php');
      drupal_set_title('Importa Punto Consegna'.$tsrtitle);
      $output.=drupal_get_form('orders_import_form',TRUE);
      break;
    case 'csvusers':
      include_once (SALDO_INC.'/csvusers.inc.php');
      drupal_set_title('Importa Utenti'.$tsrtitle);
      $output.=drupal_get_form('users_import_form');
      break;
    case 'csvpay':
      include_once (SALDO_INC.'/csvpay.inc.php');
      drupal_set_title('Importa/Esporta Versamenti'.$tsrtitle);
      $output.=drupal_get_form('pay_impexp_form');
      break;
    case 'admorders':
      include_once (SALDO_INC.'/admorders.inc.php');
      drupal_set_title('Amministra Ordini'.$tsrtitle);
      $output.=drupal_get_form('admin_orders_form');
      break;
    case 'admfids':
      include_once (SALDO_INC.'/admfids.inc.php');
      drupal_set_title('Amministra Fornitori'.$tsrtitle);
      $output.=_admin_fids();
      break;
    case 'admcash':
      include_once (SALDO_INC.'/admcash.inc.php');
      drupal_set_title('Situazione Saldo Cassa'.$tsrtitle);
      $output.=drupal_get_form('admin_cash_form',TRUE);
      break;
    case 'fidscash':
      include_once (SALDO_INC.'/fidscash.inc.php');
      drupal_set_title('Storico fornitori'.$tsrtitle);
      $output.=drupal_get_form('fids_cash_form',true);
      break;
    case 'admdepo':
      include_once (SALDO_INC.'/admdepo.inc.php');
      drupal_set_title('Versamenti utente'.$tsrtitle);
      $output.=drupal_get_form('admdepo_import_form');
      break;
    case 'admucredit':
      include_once (SALDO_INC.'/admpay.inc.php');
      drupal_set_title('Debito/Credito utente'.$tsrtitle);
      $output.=drupal_get_form('admpay_import_form',TRUE);
      break;
    case 'admcredit':
      include_once (SALDO_INC.'/admpay.inc.php');
      drupal_set_title('Spesa/Entrata Gas'.$tsrtitle);
      $output.=drupal_get_form('admpay_import_form');
      break;
    case 'admuorder':
      include_once (SALDO_INC.'/admuorder.inc.php');
      drupal_set_title('Aggiungi ordine utente'.$tsrtitle);
      $output.=drupal_get_form('uorder_import_form');
      break;
    case 'admfidpay':
      include_once (SALDO_INC.'/admfidpay.inc.php');
      drupal_set_title('Pagamenti fornitore'.$tsrtitle);
      $output.=drupal_get_form('admfidpay_import_form');
      break;
    case 'repcash':
      include_once (SALDO_INC.'/repcash.inc.php');
      drupal_set_title('Saldo utenti'.$tsrtitle);
      $output.=drupal_get_form('rep_cash_form');
      break;
    case 'repfcash':
      include_once (SALDO_INC.'/repfcash.inc.php');
      drupal_set_title('Saldo Fornitori'.$tsrtitle);
      $output.=drupal_get_form('rep_fcash_form');
      break;
    case 'allcash':
      include_once (SALDO_INC.'/allcash.inc.php');
      drupal_set_title('Saldo movimenti'.$tsrtitle);
      $output.=drupal_get_form('all_cash_form');
      break;
    }
  }

  //Referenti e Utenti normali
  if (saldo_check_role(ROLE_AUTH)) {
    switch ($saldo_req) {
    case 'repfucash':
      if ($suser->fids) {
	include_once (SALDO_INC.'/repfcash.inc.php');
	drupal_set_title('Saldo Fornitori'.$reftitle);
	$output.=drupal_get_form('rep_fcash_form',true);
      }
      break;
    case 'fidcash':
      if ($suser->fids) {
	include_once (SALDO_INC.'/fidscash.inc.php');
	drupal_set_title('Storico fornitori'.$reftitle);
	$output.=drupal_get_form('fids_cash_form');
	break;
      }
    case 'userpaid':
      if (!empty($suser->uid)) {
	include_once (SALDO_INC.'/userpaid.inc.php');
	drupal_set_title('Situazione Saldo'.$usrtitle);
	$output.=drupal_get_form('user_paid_form');
      }
      break;
	case 'fcsvorders':
	if (defined('SALDO_REFCANIMPORT') && $suser->fids) {
      include_once (SALDO_INC.'/csvorders.inc.php');
      drupal_set_title('Importa Punto Consegna fornitore'.$tsrtitle);
      $output.=drupal_get_form('orders_import_form');
	}
      break;
    case 'reforders':
      if ($suser->fids) {
	include_once (SALDO_INC.'/reforders.inc.php');
	drupal_set_title('Gestione Ordini'.$reftitle);
	$output.=drupal_get_form('ref_orders_form');
      }
      break;
    case 'userprefs':
      include_once (SALDO_INC.'/userprefs.inc.php');
      drupal_set_title('Preferenze'.$usrtitle);
      $output=drupal_get_form('user_prefs_form');
      break;
    case 'gascash':
      include_once (SALDO_INC.'/admcash.inc.php');
      drupal_set_title('Situazione Saldo Gas'.$tsrtitle);
      $output.=drupal_get_form('admin_cash_form',FALSE);
      break;
    }
  }

  if (!$output) {
    if (isset($suser->roles[ROLE_AUTH])) {
      drupal_set_title('Saldo'.$usrtitle);
      $output .= "Questa pagina ti permette di controllare il tuo ".l("conto",$_GET['q'],array('query' => 'act=userpaid'))." economico con il <strong>".$user_gas."</strong>.<br />";
      $output .= "Per cambiare sottogruppo, utilizza il men&ugrave; ".l("preferenze",$_GET['q'],array('query' => 'act=userprefs')).".";
      $output .= "<br /><br /><p>Tale conto si basa su questi utenti registrati attualmente con l'indirizzo di posta <strong><em>".$suser->mail."</em></strong> su <a href='http://www.economia-solidale.org'>Economia Solidale</a>:</p><ul>";
      foreach ($suser->uid as $v) {
        $output .= "<li><strong>$v</strong></li>";
      }
      $output.="</ul>Utilizza il menu <strong>Saldo</strong> a lato per gestire le eventuali opzioni che ti sono messe a disposizione.<br />";
    } else {
      drupal_set_title('Saldo');
      drupal_set_message('Impossibile visualizzare il saldo utente','error');
      $output.="Questo messaggio di errore &egrave normale se non hai mai fatto ordini con uno di questi Gas:<br />".implode("<br />",$gas_subgroups)."<br /><p>In caso contrario controlla che il tuo ".l("profilo","user/".$suser->duid."/edit")." del sito del Gas e il tuo profilo su <a href='http://www.economia-solidale.org'>Economia Solidale</a> siano configurati per utilizzare lo stesso identico indirizzo di posta. Una volta impostata correttamente, il tuo saldo utente su questa pagina potrebbe comunque non essere disponibile prima della data della prossima consegna del tuo Gas. Puoi comunque richiedere che venga sincronizzata il prima possibile ".l("contattando","contact")." gli amministratori.</p>";
    }
    $output .= "<p>Altre informazioni sono disponibili alle pagine di aiuto ".l("Fare gli ordini",'node/1188')." e ".l("Il Gestionale del Gas","node/1632").".</p>";
  }
  print $output;
}  /*End saldo_main function*/

/*
 meselected:
 Quando un elemento è solo array, viene creata una lista non ordinata.
 Quando è stringa => array la lista viene inserita dentro un fieldset.
 */
function saldo_menu() {
  global $suser, $saldo_req;
  $menu=array();
  if (saldo_check_role(ROLE_TREASURER)) {
    $mselected=array(array('admorders' => 'Gestione ordini globali',
			   ),
		     'Spese/Versamenti' => array('admdepo'=>'Versamenti utente',
						 'admucredit'=>'Debito/Credito utente',
						 'admcredit'=>'Spesa/Entrata Gas',
						 'admfidpay'=>'Pagamenti fornitore',
						 'admuorder'=>'Aggiungi ordine utente',
						 ),
		     'Riepiloghi' => array('repcash' => 'Saldo utenti',
					   'repfcash' => 'Saldo fornitori',
					   'allcash' => 'Saldo movimenti',
					   ),
		     'Utilit&agrave;' => array('admcash' => 'Situazione Saldo Cassa',
					      'fidscash' => 'Storico fornitori',
					      'admfids' => 'Gestione fornitori',
					      ),
		     'Importa' => array('csvorders' => 'Importa punto consegna',
					'csvpay'=>'Importa/Esporta versamenti',
					'csvusers'=>'Importa utenti',
					),
		     );
    $output_cmd.=saldo_mfieldset($mselected,"Tesoriere");    
  }

  if (saldo_check_role(ROLE_ADMIN)) {
    $mselected=array(array('admlog' => 'Logs'), 
		     'Gestione' => array('admroles'=>'Ruoli',
					 'admpwds'=>'Passwords',
					 ),
		     );
    $output_cmd.=saldo_mfieldset($mselected,"Amministratore");
  }

  if ($suser->fids) {
    $mselected=array(array('reforders' => 'Gestione ordini','fcsvorders' => 'Importa punto consegna'),
		     'Utilit&agrave;' => array('repfucash'=>'Saldo fornitori',
					       'fidcash'=>'Storico fornitori',
					       ),
		     );
    $output_cmd.=saldo_mfieldset($mselected,"Referente");
  }
  if (saldo_check_role(ROLE_AUTH)) {
    $mselected=array(array('userpaid' => 'Situazione saldo'), 
		     'Utilit&agrave;' => array('userprefs'=>'Preferenze',
					       'gascash'=>'Situazione saldo Gas',
					       ),
		     );
    $output_cmd.=saldo_mfieldset($mselected,"Utente");
  } else {
    $output_cmd='Nessuna opzione disponibile';
  }
  print "<div class='saldo_menu'>".$output_cmd."</div>";
}  /*End saldo_main function*/

function saldo_check_role($role){
  global $suser;
  return (isset($suser->roles[$role]) || $suser->admin);
}
?>
