<?php
function admin_orders_form() {
  $form['#redirect'] = FALSE;
  $form['admin_orders'] = array(
				'#type' => 'fieldset',
				'#title' => 'Gestisci ordini',
				);
  $form['admin_orders']['date'] = saldo_date_list($_REQUEST['closed']);
  if ($_REQUEST['date'] && !array_key_exists($_REQUEST['date'],$form['admin_orders']['date']['#options'])) {
    $_REQUEST['date']=$form['admin_orders']['date']['#value']= datemysql(reset($form['admin_orders']['date']['#options']));
  } else {
    $form['admin_orders']['date']['#default_value']=$_REQUEST['date'];
  }
  $form['admin_orders']['hide_locked'] = array(
	                                         '#type' => 'checkbox',
                                                 '#title' => 'Nascondi ordini chiusi',
						 '#description' => 'Se selezionata, gli ordini chiusi, non vengono visualizzati. Le righe "saldo fornitore" e "saldo totale" tengono sempre conto anche di eventuali ordini non visualizzati.', 
                                                 );

  $query = "SELECT fid,fnome from ".SALDO_FORNITORI." order by fnome;";
  $fornitori=array('0'=>'Tutti');
  $result=db_query($query);
  while ($fid=db_fetch_array($result)) {
    $fornitori[$fid['fid']]=$fid['fnome'];
  }

  $form['admin_orders']['filters'] = array(
				'#type' => 'fieldset',
				'#title' => 'Filtri',
				'#collapsible' => TRUE,
				'#collapsed' => !($_POST['fid'] || $_POST['user'] || $_REQUEST['closed']),
				);

  $form['admin_orders']['filters']['fid'] = array(
					  '#type' => 'select',
					  '#title' => 'Fornitore',
					  '#description' => 'Filtra per fornitore.',
					  '#default_value' => 0,
					  '#options' => $fornitori,
					  );
  $users=array('Tutti')+get_users();
  $form['admin_orders']['filters']['user'] = array(
					  '#type' => 'select',
					  '#title' => 'Utente',
					  '#description' => 'Filtra per utente.',
					  '#options' => $users,
					  '#attributes' => array('class' => 'select-filter-users'),
					  );

  $form['admin_orders']['filters']['closed'] = array(
					'#type' => 'checkbox',
					'#title' => 'Consegne archiviate',
					'#default_value' => $_REQUEST['closed'],
					'#description' => 'Visualizza date di consegna archiviate.',
					);

  $form['admin_orders']['act']=array(
				     '#type' => 'hidden',
				     '#value' => 'admorders',
				     );

  $form['admin_orders']['submit'] = array(
					  '#type' => 'submit',
					  '#value' => 'Cerca',
					  );
  if ($_REQUEST['op'] == 'Cerca') {
    if ($flag=_admin_orders($users)) {
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

function admin_orders_form_validate($form_id, $form_values) {
  $asaldo=$_POST['saldo'];
  if (is_array($asaldo)) {
    foreach ($asaldo as $saldo) {
      if (!is_numeric($saldo) || $saldo < 0 || $saldo >=10000) {
	form_set_error($saldo,$saldo.' non &egrave un valore monetario valido. Operazione non eseguita!');
      }
    }
  }
}

function admin_orders_form_submit($form_id, $form_values) {
  global $suser;
  $aff_rows=0;
  if ($form_values['op'] == 'Aggiorna' || $form_values['ordact']) {
    if (!is_array($asaldo=$_POST['saldo'])) {
      return "";
    }
    $lock=$_POST['lock'];
    $valid=$_POST['valid'];
    foreach ($asaldo as $k => $saldo) {
      $saldo=check_plain($saldo);
      $validv=($valid[$k] == '2')? 1 :(int) $valid[$k];
      $lockv=($lock[$k] == '2')? 1 :(int) $lock[$k];
      if ($lockv && !$validv) {
	drupal_set_message('Non &egrave possibile lockare il saldo di <em>'.$saldo.'</em> perch&egrave non validato! Aggiornamento saldi non riuscito.','error');
	return;
      } else {
	$query="UPDATE ".SALDO_ORDINI." set osaldo=".$saldo.",ovalid=".$validv.",olock=".$lockv.",lastduid=".$suser->duid.",otime=NOW()";
	$query .=" WHERE oid=".$k;
	$query .= " AND odata='".$form_values['date']."'";
	$query .= " AND (osaldo<>".$saldo." OR ovalid<>".$validv." OR olock<>".$lockv.")";
	if ($result=db_query($query)) {
	  $aff_rows+=db_affected_rows();
	  $ok=true;
	} else {
	  drupal_set_message('Errore aggiornamento dati. id:'.$k.' saldo:'.$saldo,'error');
	}
      }
    }
    if ($ok) {
      if ($aff_rows > 0) {
	log_gas("Tesoriere: Aggiornamento ordini",$form_values['date']);
      }
      drupal_set_message("Aggiornati <em>".$aff_rows."</em> ordini");
    }
  }
}

function _admin_orders($users) {
  global $suser;
  $date=check_plain($_REQUEST['date']);
  $fid=check_plain($_POST['fid']);
  $user=check_plain($_POST['user']);
  $closed_msg=(check_plain($_REQUEST['closed']) >0 ) ? ' (ordine archiviato)' : '';
  $hide_locked= (int) check_plain($_POST['hide_locked']);
  if (datevalid(datemysql($date,"-","/"))) {
    $list=array();
    $msg=' in data <strong>'.datemysql($date,"-","/")."</strong>";
    $where= "WHERE o.odata='".$date."'";
    if ($fid > 0) {
      $query="SELECT fnome FROM ".SALDO_FORNITORI." WHERE fid=".$fid;
      $fidname=db_result(db_query($query));
      $where .=" and f.fid=".$fid;
      $msg.=' del fornitore <strong>'.$fidname.'</strong>';
    }
    if ($user) {
      $where .=" and u.uid=".$user;
      $msg.=" per l'utente <strong>".$users[$user].'</strong>';
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
	  $list[]=array("<strong>Saldo Fornitore</strong>","<strong>".$psaldo."</strong>",'','','');
	  $fsaldo+=$psaldo;
	  $psaldo=0;
	}
	if ($lasthfid) $list[]=array('<br>','','','','');
	$list[]=array('<h2>'.$ord['fnome'].'</h2>','<strong>Spesa</strong>','<strong>Valido</strong>','<strong>Chiuso</strong>','<strong>Ultima modifica</strong>');
	$list[]=array('','','','','');
	$lasthfid=$ord['fnome'];
      }
      $psaldo+=$ord['osaldo'];
      if ($ord['olock'] && $hide_locked) continue;
      if ($ord['olock'] || $ord['ovalid']) {
	$form=array(
		     '#name' => 'saldo['.$ord['oid']."]",
		     '#type' => 'hidden',
		     '#id' => 'edit-saldo-'.$ord['oid'],
		     '#value' =>$ord['osaldo'],
		     '#parents' => array(),
		    );
	$ord['osaldo']=$ord['osaldo'].drupal_render($form);
	if ($ord['olock']) {
	    $form=array(
			'#name' => 'valid['.$ord['oid']."]",
			'#type' => 'hidden',
			'#id' => 'edit-valid-'.$ord['oid'],
			'#value' =>$ord['ovalid'],
			'#parents' => array(),
			);
	    $ord['ovalid']=($ord['ovalid'])?'<div class="saldo_valid" />':'';
	    $ord['ovalid'].=drupal_render($form);
	  } else {
	    $form = array(
			  '#name' => 'valid['.$ord['oid']."]",
			  '#type' => 'checkbox',
			  '#id' => 'edit-valid-'.$ord['oid'],
			  '#value' =>$ord['ovalid'],
			  '#return_value' => $ord['ovalid']?1:2,
			  '#parents' => array(),
			  );
	    $ord['ovalid']=drupal_render($form);
	  }
	$form= array(
		     '#name' => 'lock['.$ord['oid']."]",
		     '#type' => 'checkbox',
		     '#id' => 'edit-lock-'.$ord['oid'],
		     '#value' =>$ord['olock'],
		     '#return_value' => $ord['olock']?1:2,
		     '#parents' => array(),
		     );
	$ord['olock']=drupal_render($form);
      } else {
	$form= array(
		     '#name' => 'saldo['.$ord['oid']."]",
		     '#type' => 'textfield',
		     '#id' => 'edit-saldo-'.$ord['oid'],
		     '#value' =>$ord['osaldo'],
		     '#size' => 6,
		     '#maxlength' => 8,
		     '#parents' => array(),
		     );
	$ord['osaldo']=drupal_render($form);
	$form = array(
		      '#name' => 'valid['.$ord['oid']."]",
		      '#type' => 'checkbox',
		      '#id' => 'edit-valid-'.$ord['oid'],
		      '#value' =>$ord['ovalid'],
		      '#return_value' => $ord['ovalid']?1:2,
		      '#parents' => array(),
		      );
	$ord['ovalid']=drupal_render($form);
	$form=array(
		     '#name' => 'lock['.$ord['oid']."]",
		     '#type' => 'hidden',
		     '#id' => 'edit-lock-'.$ord['oid'],
		     '#value' =>$ord['lock'],
		     '#parents' => array(),
		    );
	$ord['olock']="<div class='saldo_novalid' />".drupal_render($form);
      }
      if ($ord['uid2']) {
	$ord['otime'].="<div class='saldo_note'>di ".l($ord['unome2'],"user/".$ord['uid2'])."</div>";
      }
      unset($ord['uid2']);
      unset($ord['unome2']);
      unset($ord['oid']);
      unset($ord['fnome']);
      $list[]=$ord;
    }
    if (count($list) == 0) {
      drupal_set_message('Nessuna consegna'.$msg.$closed_msg);
    } else {
      $list[]=array("<strong>Saldo Fornitore</strong>","<strong>".$psaldo."</strong>",'','','');
      $list[]=array('<br>','','','','');
      $fsaldo+=$psaldo;
      $list[]=array("<strong>Saldo TOTALE</strong><div class='saldo_note'>".$msg."</div>","<strong>".$fsaldo."</strong>",'','','');
      $formv = array(
		    '#name' => 'valid_all',
		    '#type' => 'checkbox',
		    '#id' => 'valid_all',
		    '#description' => 'tutti/nessuno',
		    '#parents' => array(),
		    );
      $forml= array(
                    '#name' => 'lock_all',
                    '#type' => 'checkbox',
                    '#id' => 'lock_all',
		    '#description' => 'tutti/nessuno',
                    '#parents' => array(),
                    );
      $out .= theme('table', array('','',theme('checkbox',$formv),theme('checkbox',$forml)),$list);
      drupal_set_message("Risultati della consegna ordine ".$msg.$closed_msg);
    }
  }
  return $out;
}
?>
