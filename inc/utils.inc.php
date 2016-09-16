<?php
function datemysql($data,$m="/",$mr='-'){
  $rsl = substr($data,0,10);
  $rsl = explode ($m,$rsl);
  $rsl = array_reverse($rsl);
  $rls=check_plain(implode($rsl,$mr));
  return $rls;
}

#TODO:da mettere in csvorders.php
function datevalid($data,$human=FALSE) {
  if (strpos($data,"/") == 2) {
    list($dd,$mm,$yy)=explode("/",$data);
  } elseif (strpos($data,"-") == 4) {
    list($yy,$mm,$dd)=explode("-",$data);
  } else {
    return false;
  }
  if (!is_numeric($mm) || !is_numeric($dd) || !is_numeric($yy) || !checkdate($mm,$dd,$yy)) {
    return false;
  }
  if ($human) {
    return check_plain($dd."/".$mm."/".$yy);
  } else {
    return check_plain($yy."-".$mm."-".$dd);
  }
}

function _utf8_rows(&$value) {
  $value = str_replace(chr(128),'',$value);
  $value = utf8_encode($value);
}

function log_gas($lact,$date="NULL",$lextra="") {
  global $user;
  ## 
  #  lact:
  #  1 Versamento, 2 Modifica ordine, 3 Inserimento fuori ordine, 
  #  4 Importazione utenti, 5 Importazione fornitori, 6 Importazione ordine (punto consegna),
  #  7 Impostazione Referente, 8 Spesa fornitore, 9 Pagamento fornitore,
  #  10 Eliminazione movimento, 11 Impostazione Referenti secondari
  #  lextra:
  #  Note extra. 
  #  In caso di assegnazione Fornitori: id del Fornitore modificato.
  # In caso di eliminazione: 1 versamento, 2 ordine, 8 Spesa, 9 Pagamento
  ##
  if ($date!="NULL") $date="'".$date."'";
  db_query("INSERT INTO ".SALDO_LOG." (drupalid,lact,ldate,lextra) VALUES (".$user->uid.",'".$lact."',".$date.",'".check_plain($lextra)."');");
}

function get_user() {
  global $user,$grp_admins, $gas_subgroups;
  if (isset($_SESSION['saldo_user']->duid)) {
    if ($_SESSION['saldo_user']->duid == $user->uid) {
      return $_SESSION['saldo_user'];
    } else {
      unset($_SESSION['saldo_user']);
      unset($_SESSION['saldo_prefix']);
    }
  }
  $query="SELECT u.uid,u.unome,u.ugroup FROM ".SALDO_UTENTI." AS u JOIN {users} as d on d.mail=u.email where d.uid=".$user->uid;
  $result=db_query($query);
  while ($property=db_fetch_object($result)) {
    //Gruppo di preferenza
    if (isset($gas_subgroups[$property->ugroup])){
      $suser->ugroup=$property->ugroup;
    }
    $suser->uid[$property->uid]=$property->unome;
  }
  if (!isset($_SESSION['saldo_prefix'])) {
    if (isset($suser->ugroup)) {
      $_SESSION['saldo_prefix'] = $suser->ugroup;
    } else {
      $_SESSION['saldo_prefix'] = key(array_slice($gas_subgroups,0,1));
    }
  }
  $suser->duid=$user->uid;
  $suser->mail=$user->mail;
  $suser->admin=in_array($grp_admins,$user->roles);
  $_SESSION['saldo_user']=$suser;
  return $suser;
}

function get_user_roles(&$suser) {
  if (empty($suser->uid)) {
    $suser->roles = array();
  }
  if (isset($suser->roles)) return;
  $suser->roles=array(ROLE_AUTH);
  $query = "SELECT rrole FROM ".SALDO_ROLES." WHERE ruid IN (".implode(",",array_keys($suser->uid)).")";
  $result=db_query($query);
  while ($role=db_fetch_object($result)) {
    $suser->roles[$role->rrole]=$role->rrole;
  }
}

function get_user_refers(&$suser) {
  if (isset($suser->fids) && isset($suser->rfid)) return;
  if (empty($suser->uid)) return;
  $suser->fids=false;
  $suser->rfid=false;
  $uids=implode(",",array_keys($suser->uid));
  $query="SELECT fid FROM ".SALDO_FORNITORI." WHERE frefer in (".$uids.")";
  $result=db_query($query);
  while ($property=db_fetch_object($result)) {
    //Referente primario
    if ($property->fid) {
      $suser->fids[$property->fid]='r';
    }
  }
  $query="SELECT rfid FROM ".SALDO_SUBREFERS." WHERE ruid in (".$uids.")";
  $result=db_query($query);
  while ($property=db_fetch_object($result)) {
    //Referente secondario
    if ($property->rfid) {
      $suser->fids[$property->rfid]='sr';
    }
  }
}

function get_users($uid=false,$gasid=false) {
  $users=array();
  $query = "SELECT ";
  if ($gasid) {
    $query .= "distinct(u) as uid,unome,email FROM ((SELECT ouid as u FROM ".SALDO_ORDINI.") UNION ALL (SELECT vuid from ".SALDO_VERSAMENTI.") UNION ALL ( SELECT suid from ".SALDO_DEBITO_CREDITO." where stype<2)) as v LEFT JOIN utente_gas on uid=u";
  } else {
    $query .= "* FROM ".SALDO_UTENTI;
    if ($uid) {
      $query .= " WHERE uid=".$uid;
    }
  }
  $query.=" ORDER BY unome";
  $result=db_query($query);
  while ($uid=db_fetch_object($result)) {
    $users[$uid->uid]=$uid->unome.' ('.$uid->email.')';
  }
  return $users;
}

function get_fids($sfids=false) {
  $fids=array();
  if ($sfids) {
    if (!is_array($sfids) || count($sfids)<1) {
      return $fids;
    }
    $where=" WHERE fid IN (".implode(",",$sfids).")";
  }
  $query="SELECT *,unome FROM ".SALDO_FORNITORI." LEFT JOIN ".SALDO_UTENTI." on frefer=uid ".$where." ORDER BY fnome";
  $result=db_query($query);
  while ($fid=db_fetch_object($result)) {
    $fids[$fid->fid]=$fid->fnome.(($fid->unome)? " (".$fid->unome.")" : '');
  }
  return $fids;
}

function saldo_table ($query,$headers,$sum=array()){
  if ($query) {
    $result=db_query($query);
    while ($tsaldo=db_fetch_array($result)) {
      if ($tsaldo['uid'] && $tsaldo['unome']) {
	$tsaldo['unome'].="<div class='saldo_note'>".l("Profilo","user/".$tsaldo['uid'])." ".l("Contatta","user/".$tsaldo['uid']."/contact")."</div>";
      }
      if ($tsaldo['mydate']) {
	$tmp_date=datemysql($tsaldo['mydate'],$m="-",$mr='/');
	$tsaldo['mydate']=$tmp_date;
      }
      if (isset($tsaldo['mylock'])) {
	$tsaldo['mylock']=($tsaldo['mylock']>0) ? "<div class='saldo_valid' title='Dato acquisito: non modificabile' />" : "<div class='saldo_novalid' title='Dato teorico: possibili modifiche' />";
      }
      if (isset($tsaldo['myextra'])) {
	$astr=explode("|",$tsaldo['myextra']);
	$tsaldo['myextra']="";
	foreach($astr as $op) {
	  $aop=explode("%",$op);
	  $tsaldo['myextra'].=l(substr($aop[0],0,1),$_GET['q'],array('title'=>$aop[0]),$aop[1])."|";
	}
	$tsaldo['myextra']=rtrim($tsaldo['myextra'],"|");
      }
      unset($tsaldo['uid']);
      $asaldo[]=$tsaldo;
    }
    //Somma finale, se richiesta
    if (count($sum)>0 && count($asaldo)>0) {
      $sum_keys=array_keys($asaldo[0]);
      //Inizializzazione riga somma.
      $sum_row=array_combine($sum_keys,array_fill(0,count($sum_keys),""));
      foreach ($sum as $field=>$desc) {
	if (isset($sum_row[$field])) {
	  //prima riga diventa descrizione
	  while(list($desc_key,$desc_value)=each($desc)){
	    if (is_numeric($desc_key)) {
	      $desc_key=key($sum_row);
	    }
	    $sum_row[$desc_key]="$desc_value";
	  }
	  foreach ($asaldo as $item=>$value) {
	    $sum_row[$field]+=$value[$field];
	  }
	}
      }
      $asaldo[]=array_combine($sum_keys,array_fill(0,count($sum_keys),""));
      $asaldo[]=$sum_row;
    }
    return theme('table',$headers,$asaldo);
  }
}

function saldo_date_list($nolock=false,$multiple=false,$fids = false) {
  $disabled=false;
  if ($fids) {
    $qfids=" WHERE ofid in (".implode(",",array_keys($fids)).")";
  }
  $query="SELECT odata from (SELECT DISTINCT odata,MIN(olock) AS lk FROM ".SALDO_ORDINI.$qfids." GROUP BY odata) AS d WHERE lk=".(int) $nolock." ORDER BY odata DESC";
  $result=db_query($query);
  while ($data=db_fetch_array($result)) {
    $ldata[$data['odata']]=datemysql($data['odata'],'-',"/");
  }
  if (!$ldata) {
    $ldata=array('Nessun ordine '.(($nolock) ? 'chiuso' : 'aperto') );
    $disabled=true;
  }
  $form= array(
	       '#type' => 'select',
	       '#multiple' => $multiple,
	       '#title' => 'Data consegna ordine',
	       '#description' => 'Selezionare '.(($multiple) ? "i giorni delle consegne. Utilizzare CTRL+mouse per selezionare pi&ugrave; date contemporaneamente." : "il giorno della consegna."),
	       '#disabled' => $disabled,
	       '#options' =>$ldata,
	       );
  return $form;
}

function get_csv($ptype=1) {
  $puser=saldo_es_pwd($ptype);
  if (!isset($puser)) {
    drupal_set_message("Le credenziali per l'accesso su Economia Solidale non sono state impostate",'error');
    return false;
  }
#  $regxfile='/public\/tmp\/'.'\w+'.$puser->puser.'.csv/i';
  $regxfile='/it\/download\.php\?do=admin_search_users_csv/';
  $headers = array('Content-Type' => 'application/x-www-form-urlencoded');
  $query="username=".$puser->puser."&password=".$puser->ppwd."&save=1&submit=Entra";
  $out=drupal_http_request('https://www.eventhia.com/it/index.php?do=login',$headers,'POST',$query,0);
  if (!$out->headers['Set-Cookie']) {
    return false;
  }
  $setcookie=explode(";",$out->headers['Set-Cookie']);
  if ($ptype == 2) {
    $headers['Cookie']=$setcookie[0];
    $out=drupal_http_request('https://www.eventhia.com/it/index.php?do=admin_search_users',array('Cookie'=>$setcookie[0]),'GET');
    if (preg_match_all('/input class="(.*?)" value="(.*?)" name="(town[0-9]*)" id="town[0-9]*" type="checkbox"/', $out->data, $arrtowns, PREG_PATTERN_ORDER)) {
      $towns=array_combine($arrtowns[2],$arrtowns[1]);
      $towns=http_build_query($towns,'', '&');
      #$out=drupal_http_request('https://www.eventhia.com/index.php?do=admin_search_users',$headers,'POST','username=&name=&factoryid=0&subgroupid=0&associate=0&min_num_vol=255&'.$towns.'&search=Cerca',0);
      $out=drupal_http_request('https://www.eventhia.com/it/index.php?do=admin_search_users&username=&name=&factoryid=0&subgroupid=0&associate=0&min_num_vol=255&'.$towns.'&search=Cerca',$headers,0);
    } else {
      return false;
    }
  } else {
    $regxfile='/it\/download.php\?do=shop_summary_orders_csv&\S+done=0/';
    $out=drupal_http_request('https://www.eventhia.com/it/index.php?do=shop_summary_orders&done=0',array('Cookie'=>$setcookie[0]),'GET');
  }
  if (!preg_match($regxfile,$out->data,$match)) {
    return false;
  }
  $out=drupal_http_request(decode_entities("https://www.eventhia.com/".$match[0]),array('Cookie'=>$setcookie[0]));
  return $out->data;
}

function saldo_mactive($sreq) {
  global $saldo_req;
  $class=array();
  if ($sreq==$saldo_req) {
    $class=array("class" => "saldo_selected");
  }
  return $class;
}


function saldo_mfieldset($mlist,$title) {
  global $saldo_req;
  $out="";
  foreach ($mlist as $key=>$value) {
    $menu=array();
    if (is_numeric($key)) {
      foreach ($value as $mreq=>$mtitle) {
	$menu[] =l($mtitle,$_GET['q'],saldo_mactive($mreq),'act='.$mreq);
      }
      $out.="<h3><strong>".$title."</strong></h3><hr />";
      $out.=theme('item_list',$menu);
    } else {
      foreach ($value as $mreq=>$mtitle) {
	$menu[] =l($mtitle,$_GET['q'],saldo_mactive($mreq),'act='.$mreq);
      }
      $form=array('#type' => 'fieldset',
		  '#title' => $key,
		  '#value'=>theme('item_list',$menu),
		  '#collapsible' => TRUE,
		  '#collapsed' => !(array_key_exists($saldo_req,$value))
		  );
      $out.=drupal_render($form);
    }
  }
    return $out;
}

function saldo_exportcsv($query, $cols=array(), $options=array()) {
    $sep = ($options['sep']) ? $options['sep'] : "\t";
    $filename = ($options['filename']) ? $options['filename'] : "gas_versamenti";
    $data=$header='';
    if (!empty($cols)) {
      $header = implode($sep,$cols);
    } else {
      if (!preg_match("/ from (\S+)/i",$query,$table)) {
        return false;
      }
      $result = db_query("SHOW COLUMNS FROM ".$table[1]);
      while ($fields = db_fetch_array($result)) {
	    $header .= $fields['Field'] . $sep;
      }
    }
    $header=trim($header);
    $export=db_query($query);
    while( $row = db_fetch_array( $export ) )
      {
	$line = '';
	foreach( $row as $value )
	  {                                            
	    if ( ( !isset( $value ) ) || ( $value == "" ) )
	      {
		$value = $sep;
	      }
	    else
	  {
            $value = str_replace( '"' , '""' , $value );
            $value = '"' . $value . '"' . $sep;
	  }
	    $line .= $value;
	  }
	$data .= trim( $line ) . "\n";
      }
    $data = str_replace( "\r" , "" , $data );
    if ($data == "") {
      return false;
    }
    drupal_set_header("Content-type: application/octet-stream");
    drupal_set_header("Content-Disposition: attachment; filename=".$filename.".xls");
    drupal_set_header("Content-Length: ".strlen($header."\n".$data));
    drupal_set_header("Pragma: no-cache");
    drupal_set_header("Expires: 0");
    print $header."\n".$data;
    exit;
}

function saldo_es_pwd($ptype) {
  $query="SELECT puser,ppwd FROM ".SALDO_PWD." WHERE ptype = ".$ptype;
  return db_fetch_object(db_query($query));
}

function saldo_greaterDate($start_date,$end_date)
{
  $start = strtotime(str_replace("/","-",$start_date));
  $end = strtotime(str_replace("/","-",$end_date));
  if ($start-$end > 0)
    return 1;
  else
    return 0;
}
