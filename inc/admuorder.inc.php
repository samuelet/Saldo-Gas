<?php
function uorder_import_form($admin=FALSE) {
  global $suser;
  if ($admin) {
    $fids = get_fids();
  } else {
    $fids = get_fids(array_keys($suser->fids));
  }
  $users=get_users();
  if ($admin) {
      $form['help'] = array('#type' => 'fieldset',
    			'#title' => 'Aiuto',
    			'#collapsible'=>true,
    			'#collapsed'=>true,
    			'#value' => "<div>L' Ordine Utente influisce in modo immediato sul salvaresti del gasato.</div>",
    			);
  }

  $form['pay'] = array(
				'#type' => 'fieldset',
				'#title' => 'Inserimento ordine',
				);
  $form['pay']['date'] = saldo_date_list(false);
  $form['pay']['fid'] = array(
				'#type' => 'select',
				'#title' => 'Fornitore',
				'#description' => "Selezionare il fornitore a cui assegnare l'ordine.",
				'#default_value' => 0,
				'#required'=>TRUE,
				'#options' =>array("--Scegli--")+$fids,
				);
  $form['pay']['user'] = array(
				'#type' => 'select',
				'#title' => 'Utente',
				'#description' => "Selezionare l'utente a cui assegnare la spesa ordine.",
				'#default_value' => 0,
				'#options' =>array("--Scegli--")+$users,
				'#attributes' => array('class' => 'select-filter-users'),
				);

  $form['pay']['saldo']= array(
			     '#title' => "Spesa",
			     '#type' => 'textfield',
			     '#description' => "Ammontare della spesa (in Euro)",
			     '#size' => 6,
			     '#maxlength' => 8,
			     '#required'=>TRUE,
			     );

  $form['pay']['submit'] = array(
				 '#type' => 'submit',
				 '#value' => 'Inserisci',
				 );  
  return $form;
  
  }

function uorder_import_form_validate($form_id, $form_values) {
  if (!is_numeric($form_values['saldo']) || $form_values['saldo']<0 || $form_values['saldo']>=10000) {
    form_set_error('saldo',$form_values['saldo']. " non &egrave un valore monetario valido. Operazione non eseguita!"); 
  }
  if (!is_numeric($form_values['fid']) || $form_values['fid']<1) {
    form_set_error('fid',"Seleziona un fornitore!");
  }
  if (!is_numeric($form_values['user']) || $form_values['user']<1) {
    form_set_error('user',"Seleziona un utente!");
  }
  if (!($form_values['date'])) {
    form_set_error('date',"Nessun ordine aperto. Non &egrave possibile inserire ordini.");
  }
}

function uorder_import_form_submit($form_id, $form_values) {
  global $suser;
  $luser=implode(",",get_users($form_values['user']));
  $msg="La spesa ordine di ".$form_values['saldo']." Euro in data ".datemysql($form_values['date'],"-","/")." all'utente ".$luser." per il fornitore ".implode(",",get_fids(array($form_values['fid'])));
  $vexist=db_fetch_object(db_query("SELECT * FROM ".SALDO_ORDINI." WHERE odata='".$form_values['date']."' AND ouid=".$form_values['user']." AND ofid=".$form_values['fid']));
  if ($vexist) {
    drupal_set_message($msg. " non &egrave; stata inserita perch&egrave esiste gi&agrave; tale ordine con un importo di ".$vexist->osaldo." Euro. Per modificarlo, devi andare nella ".l('gestione ordini.',$_GET['q'],array(),'act='.(($_GET['act'] == "admuorder") ? 'admorders' : 'reforders').'&date='.$form_values['date'].'&op=Cerca'),'error');
  } else {
    $query="INSERT INTO ".SALDO_ORDINI." (odata,ouid,ofid,osaldo,lastduid) VALUES ";
    $query .= "('".$form_values['date']."',".$form_values['user'].",".$form_values['fid'].",".$form_values['saldo'].",".$suser->duid.");";
    if (db_query($query)) {
      drupal_set_message($msg." &egrave; stata inserita correttamente");
      log_gas((($_GET['act'] == "admuorder") ? "Tesoriere" : "Referente").": Aggiunta ordine utente",$form_values['date'],$luser);
    } else {
      drupal_set_message($msg." non &egrave stata inserita a causa di un errore interno",'error');
    }
  }
  drupal_goto($_GET['q'],'act='.$_GET['act']);
}
