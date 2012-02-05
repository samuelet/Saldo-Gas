<?php
function _admin_fids() {
  $fid=$_REQUEST['fid'];
  if (is_numeric($fid)) {
    $out=drupal_get_form('admin_fids_form',$fid);
  } else {
    $query="SELECT f.fnome,u.unome,GROUP_CONCAT((SELECT CONCAT(uu.unome,'||',IF(dd.uid>0,dd.uid,0),'||',uu.email) FROM ".SALDO_UTENTI." as uu LEFT JOIN {users} as dd on uu.email=dd.mail where uu.uid=r.ruid) SEPARATOR '&&') as refers,f.fid,d.uid FROM ".SALDO_FORNITORI." AS f";
    $query.=" LEFT JOIN ".SALDO_UTENTI." AS u on f.frefer=u.uid LEFT JOIN users as d on u.email=d.mail LEFT JOIN ".SALDO_SUBREFERS." as r on r.rfid=f.fid GROUP BY f.fnome";
    $result=db_query($query);
    $list=array();
    while ($ofid=db_fetch_array($result)) {
      if ($ofid['refers']) {
	$sarefers=explode('&&',$ofid['refers']);
	$ofid['refers']="";
	foreach ($sarefers as $arefer) {
	  $refer=explode('||',$arefer);
	  $ofid['refers'].=$refer[0]."<div class='saldo_note'>";
	  if ($refer[1] > 0) {
	    $ofid['refers'].=l("Profilo","user/".$refer[1])."&nbsp;".l("Contatta","user/".$refer[1]."/contact");
	  } else {
	    $ofid['refers'].=l("email","mailto:".$refer[2]);
	  }
	  $ofid['refers'].="</div>";
	}
      }
      if ($ofid['uid']) {
	$ofid['unome'].="<div class='saldo_note'>".l("Profilo","user/".$ofid['uid'])." ".l("Contatta","user/".$ofid['uid']."/contact")."</div>";
      }
      unset($ofid['uid']);
      $ofid['fid']=l('Modifica',$_GET['q'],array(),'act=admfids&fid='.$ofid['fid']);
      $list[]=$ofid;
    }
    if (count($list) == 0) {
      drupal_set_message("Nessun fornitore presente! I fornitori vengono importati automaticamente quando si importa un nuovo ".l('punto consegna',$_GET['q'],array(),'act=csvorders'));
    } else {
      $headers = array(
		       array('data' => 'Fornitore'),
		       array('data' => 'Referente principale'),
		       array('data' => 'Referenti secondari'),
		       array('data' => 'Azione'),
		       );
      $out = theme('table', $headers, $list);
    }
  }
  return $out;
  }

function admin_fids_form($id) {
  $form['#redirect']=FALSE;
  $form['fid'] = array(
		       '#type' => 'fieldset',
		       '#title' => 'Modifica fornitore',
		       );
  $rusers=array();
  $users=get_users();
  $query="SELECT ruid FROM ".SALDO_SUBREFERS." WHERE rfid=".$id;
  $result=db_query($query);
  while ($ruser=db_fetch_object($result)) {
    $rusers[]=$ruser->ruid;
  }
  $query="SELECT fnome,frefer,fid FROM ".SALDO_FORNITORI." WHERE fid=".$id;
  $result=db_query($query);
  while ($ofid=db_fetch_array($result)) {
    $form['fid']['name'] = array(
				 '#type' => 'textfield',
				 '#title' => 'Nome fornitore',
				 '#required' => true,
				 '#default_value' => $ofid['fnome'],
				 );
    $form['fid']['refer'] = array(
				  '#type' => 'select',
				  '#title' => 'Referente',
				  '#required' => true,
				  '#default_value' => $ofid['frefer'],
				  '#options' =>array('Nessuno')+$users,
				  '#attributes' => array('class' => 'select-filter-users'),
				  );
    $form['fid']['subrefers'] = array(
				      '#type' => 'select',
				      '#title' => 'Referenti secondari',
				      '#description' => 'Utilizzare il <strong>tasto CTRL + click mouse</strong> per selezionare e deselezionare utenti multipli.',
				      '#multiple' => true,
				      '#default_value' => $rusers,
				      '#options' =>$users,
				      );
  }
  $form['fid']['fid'] = array(
			      '#type' => 'hidden',
			      '#value' =>$id,
			      );

  $form['fid']['submit'] = array(
				 '#type' => 'submit',
				 '#value' => 'Aggiorna',
				 );

  $form['fid']['cancel'] = array(
				 '#type' => 'button',
				 '#value' => 'Annulla',
				 );
  
  return $form;
}

function admin_fids_form_validate($form_id, $form_values) {
  if ($_POST['op']=='Annulla') {
    drupal_set_message('Modifica fornitore annullata');
    drupal_goto($_GET['q'],'act=admfids');
  }
  if (!is_numeric($form_values['fid']) || !is_numeric($form_values['refer'])) {
    form_set_error('Identificatore fornitore non valido!');
  }
}

function admin_fids_form_submit($form_id, $form_values) {
  $ok=true;
  $lfid=implode(",",get_fids(array($form_values['fid'])));
  $query='UPDATE '.SALDO_FORNITORI.' SET frefer='.$form_values['refer'].",fnome='".addcslashes($form_values['name'],"'")."' WHERE fid=".$form_values['fid'];
  if (db_query($query)) {
    $msg='Il produttore <em>'.$form_values['name'].'</em> ';
    if ($form_values['refer'] > 0) {
      $msg .= "ha un nuovo referente principale. Questo referente pu&ograve; modificare e validare gli ordini del proprio produttore.";
    } else {
      $msg .= "non ha nessun referente.";
    }
    drupal_set_message($msg);
    log_gas("Tesoriere: Impostazione referente principale",'NULL',$lfid);
  } else {
    drupal_set_message("ERRORE aggiornamento referente principale.",'error');
    $ok=false;
  }

  $query="DELETE FROM ".SALDO_SUBREFERS." WHERE rfid=".$form_values['fid'];
  if (db_query($query)) {
    if (count($form_values['subrefers'])>0) {
      $qruid="";
      foreach ($form_values['subrefers'] as $ruid) {
	$qruid.="(".$ruid.",".$form_values['fid']."),";
      }
      $query="INSERT INTO ".SALDO_SUBREFERS." (ruid,rfid) VALUES ".$qruid=rtrim($qruid,",");
      if (db_query($query)) {
	$msg='Il produttore <em>'.$form_values['name'].'</em> ';
	$msg .= "ha ".count($form_values['subrefers'])." referenti secondari. Questi referenti possono modificare e validare gli ordini del proprio produttore.";
	drupal_set_message($msg);
	log_gas("Tesoriere: Impostazione referenti secondari",'NULL',$lfid);
      } else {
	drupal_set_message("ERRORE inserimento referenti secondari.",'error');
	$ok=false;
      }
    }
  } else {
    drupal_set_message("ERRORE rimozione referenti secondari.",'error');
    $ok=false;
  }

  if ($ok) {
    drupal_goto($_GET['q'],'act=admfids');
  }
}
