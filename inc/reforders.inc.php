<?php
function ref_orders_form() {
  global $suser;
  $form['#redirect'] = FALSE;
  $form['description'] = array('#value' => 'In questa pagina puoi gestire gli ordini che non sono ancora stati validati o chiusi dal tesoriere. Gli altri ordini possono essere controllati nello '. l('storico fornitore',$_GET['q'],array(),'act=fidcash').'.');
  $form['ref_orders'] = array(
				'#type' => 'fieldset',
				'#title' => 'Gestisci ordini',
				);

  $form['ref_orders']['date'] = saldo_date_list(FALSE, FALSE, $suser->fids);

  $form['ref_orders']['hide_locked'] = array(
	                                         '#type' => 'checkbox',
                                                 '#title' => 'Nascondi gli ordini chiusi',
						 '#description' => 'Se selezionata, gli ordini chiusi dai tesorieri e quindi non modificabili, non vengono visualizzati. Le righe "Saldo fornitore" e "Saldo totale" tengono conto anche di eventuali ordini non visualizzati.', 
                                                 );


  $form['ref_orders']['filters'] = array(
				'#type' => 'fieldset',
				'#title' => 'Filtri',
				'#collapsible' => TRUE,
				'#collapsed' => !$_POST['user'], 
				);
  
  $form['ref_orders']['filters']['user'] = array(
					  '#type' => 'textfield',
					  '#title' => 'Utente',
					  '#description' => 'Filtra per nome utente. &Egrave possibile inserire parte del nome.',
					  );
  
  $form['ref_orders']['act']=array(
				     '#type' => 'hidden',
				     '#value' => 'reforders',
				     );

  $form['ref_orders']['submit'] = array(
					  '#type' => 'submit',
					  '#value' => 'Cerca',
					  );

  if ($_POST['op'] == 'Cerca') {
    if ($flag=_ref_orders()) {
      $form['result'] = array('#value' =>$flag);
      $form['submit'] = array(
			      '#type' => 'submit',
			      '#value' => 'Aggiorna',
			      );
    }
  } else {
    $form['ordact'] = array(
			    '#type' => 'hidden',
			    '#value' => '1',
			    );
  }
  return $form;
  }

function ref_orders_form_validate($form_id, $form_values) {
  $asaldo=$_POST['saldo'];
  if (is_array($asaldo)) {
    foreach ($asaldo as $saldo) {
      if (!is_numeric($saldo) || $saldo < 0) {
	form_set_error($saldo,$saldo.' non &egrave un valore valido!.');
      }
    }
  }
}

function ref_orders_form_submit($form_id, $form_values) {
  global $suser;
  $aff_rows=0;
  if ($form_values['op'] == 'Aggiorna' || $form_values['ordact']) {
    if (!is_array($asaldo=$_POST['saldo'])) {
      return "";
    }
    $valid=$_POST['valid'];
    foreach ($asaldo as $k => $saldo) {
      $saldo=check_plain($saldo);
      $validv=($valid[$k] == '2')? 1 :(int) $valid[$k];
      $validv=check_plain($validv);
      $query="UPDATE ".SALDO_ORDINI." set osaldo=".$saldo.",ovalid=".$validv.",lastduid=".$suser->duid.",otime=NOW()";
      $query .=" WHERE oid=".$k." AND olock=0 AND ofid IN (".implode(',',array_keys($suser->fids)).")";
      $query .= " AND (osaldo<>".$saldo." OR ovalid<>".$validv.") AND odata='".$form_values['date']."'";
      if ($result=db_query($query)) {
	$aff_rows+=db_affected_rows();
	$ok=true;
      } else {
	drupal_set_message('Errore aggiornamento dati. id:'.$k.' saldo:'.$saldo,'error');
      }
    }
    if ($ok) {
      if ($aff_rows > 0) {
	log_gas("Referente: Aggiornamento ordini",$form_values['date']);
      }
      drupal_set_message("Aggiornati <em>".$aff_rows."</em> ordini");
    }
  }
}

function _ref_orders() {
  global $suser;
  $date=check_plain($_POST['date']);
  $user=check_plain($_POST['user']);
  $hide_locked= (int) check_plain($_POST['hide_locked']);
  $where="WHERE o.ofid in (".implode(',',array_keys($suser->fids)).")";
  if ($date) {
    $list=array();
    $msg=' in data '.datemysql($date,"-","/");
    $where.= " AND o.odata='".$date."'";
    if (!empty($user)) {
      $where .=" and u.unome like '%".$user."%'";
      $msg.=" per l'utente <em>*".$user.'*</em>';
    }
    $query="SELECT d.uid,f.fnome,o.oid,u.unome,o.osaldo,o.ovalid,o.olock,l.name as unome2,l.uid as uid2,DATE_FORMAT(otime,'%%d-%%m-%%Y %H:%i') as otime FROM ".SALDO_ORDINI." o INNER JOIN ".SALDO_UTENTI." u ON o.ouid=u.uid INNER JOIN ".SALDO_FORNITORI." f on o.ofid=f.fid LEFT JOIN users as d on u.email=d.mail LEFT JOIN users as l on (l.uid <> ".$suser->duid." AND o.lastduid=l.uid) ".$where." order by f.fnome, u.unome;";
    $result=db_query($query);
    $psaldo=0;
    $fsaldo=0;
    while ($ord=db_fetch_array($result)) {
      if ($ord['uid']) {
	$ord['unome'].="<div class='saldo_note'>".l("Profilo","user/".$ord['uid'])." ".l("Contatta","user/".$ord['uid']."/contact")."</div>";
      }
      unset($ord['uid']);
      if ($ord['fnome'] != $lasthfid) {
	if ($lasthfid) {
	  $list[]=array("<strong>Saldo Fornitore</strong>","<strong>".$psaldo."</strong>",'','');
	  $fsaldo+=$psaldo;
	  $psaldo=0;
	}
	$list[]=array('<br>','','','');
	$list[]=array('<h2>'.$ord['fnome'].'</h2>','<strong>Spesa</strong>','<strong>Valido</strong>','<strong>Ultima modifica</strong>');
	$list[]=array('','','','');
	$lasthfid=$ord['fnome'];
      }
      $psaldo+=$ord['osaldo'];
      if ($ord['olock'] && $hide_locked) continue;
      if (!$ord['olock']) {
	if ($ord['ovalid']) {
	  $form=array(
		      '#name' => 'saldo['.$ord['oid']."]",
		      '#type' => 'hidden',
		      '#id' => 'edit-saldo-'.$ord['oid'],
		      '#value' =>$ord['osaldo'],
		      '#parents' => array(''),
		      );
	  $ord['osaldo']=$ord['osaldo'].drupal_render($form);
	} else {
	  $form= array(
		       '#name' => 'saldo['.$ord['oid']."]",
		       '#type' => 'textfield',
		       '#id' => 'edit-saldo-'.$ord['oid'],
		       '#value' =>$ord['osaldo'],
		       '#size' => 6,
		       '#maxlength' => 8,
		       '#parents' => array(''),
		       );
	  $ord['osaldo']=drupal_render($form);
	}
	$form = array(
		      '#name' => 'valid['.$ord['oid']."]",
		      '#type' => 'checkbox',
		      '#id' => 'edit-valid-'.$ord['oid'],
		      '#value' =>$ord['ovalid'],
		      '#return_value' => $ord['ovalid']?1:2,
		      '#parents' => array(),
		      );
	$ord['ovalid']=drupal_render($form);
      } else {
	$ord['ovalid']=($ord['ovalid'])?'<div class="saldo_valid" />':'';
      }

      if ($ord['uid2']) {
	$ord['otime'].="<div class='saldo_note'>di ".l($ord['unome2'],"user/".$ord['uid2'])."</div>";
      }
      unset($ord['uid2']);
      unset($ord['unome2']);
      unset($ord['olock']);
      unset($ord['oid']);
      unset($ord['fnome']);
      $list[]=$ord;
    }
    if (count($list) == 0) {
      drupal_set_message('Nessuna consegna'.$msg);
    } else {
      $list[]=array("<strong>Saldo Fornitore</strong>","<strong>".$psaldo."</strong>",'','');
      $list[]=array('<br>','','','');
      $fsaldo+=$psaldo;
      $list[]=array("<strong>Saldo TOTALE</strong><em>".$msg."</em>","<strong>".$fsaldo."</strong>",'','','');

      $formv = array(
		    '#name' => 'valid_all',
		    '#type' => 'checkbox',
		    '#id' => 'valid_all',
		    '#description' => 'tutti/nessuno',
		    '#parents' => array(),
		    );
      $out .= theme('table', array('','',theme('checkbox',$formv)),$list);

      drupal_set_message("Risultati della consegna ordine ".$msg);
    }
  }
  return $out;
}
?>
