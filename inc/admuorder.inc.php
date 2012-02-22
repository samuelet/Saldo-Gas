<?php
function uorder_import_form() {
  $fids=get_fids();
  $users=get_users();
  $form['help'] = array('#type' => 'fieldset',
			'#title' => 'Aiuto',
			'#collapsible'=>true,
			'#collapsed'=>true,
			'#value' => "<div>L' Ordine Utente influisce in modo immediato sul salvaresti del gasato.</div>",
			);

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

function uorder_import_form_validate($form, &$form_state) {
  if (!is_numeric($form_state['values']['saldo']) || $form_state['values']['saldo']<0 || $form_state['values']['saldo']>=10000) {
    form_set_error('saldo',$form_state['values']['saldo']. " non &egrave un valore monetario valido. Operazione non eseguita!"); 
  }
  if (!is_numeric($form_state['values']['fid']) || $form_state['values']['fid']<1) {
    form_set_error('fid',"Seleziona un fornitore!");
  }
  if (!is_numeric($form_state['values']['user']) || $form_state['values']['user']<1) {
    form_set_error('user',"Seleziona un utente!");
  }
  if (!($form_state['values']['date'])) {
    form_set_error('date',"Nessun ordine aperto. Non &egrave possibile inserire ordini.");
  }
}

function uorder_import_form_submit($form, &$form_state) {
  global $suser;
  $luser=implode(",",get_users($form_state['values']['user']));
  $msg="La spesa ordine di ".$form_state['values']['saldo']." Euro in data ".datemysql($form_state['values']['date'],"-","/")." all'utente ".$luser." per il fornitore ".implode(",",get_fids(array($form_state['values']['fid'])));
  $vexist=db_fetch_object(db_query("SELECT * FROM ".SALDO_ORDINI." WHERE odata='".$form_state['values']['date']."' AND ouid=".$form_state['values']['user']." AND ofid=".$form_state['values']['fid']));
  if ($vexist) {
    drupal_set_message($msg. " non &egrave; stata inserita perch&egrave esiste gi&agrave; tale ordine con un importo di ".$vexist->osaldo." Euro. Se vuoi modificarlo, devi utilizzare la ".l('Gestione ordini globale',$_GET['q'],array('query' => 'act=admorders&date='.$form_state['values']['date'].'&op=Cerca')),'error');
  } else {
    $query="INSERT INTO ".SALDO_ORDINI." (odata,ouid,ofid,osaldo,lastduid) VALUES ";
    $query .= "('".$form_state['values']['date']."',".$form_state['values']['user'].",".$form_state['values']['fid'].",".$form_state['values']['saldo'].",".$suser->duid.");";
    if (db_query($query)) {
      drupal_set_message($msg." &egrave; stata inserita correttamente");
      log_gas("Tesoriere: Aggiunta ordine utente",$form_state['values']['date'],$luser);
    } else {
      drupal_set_message($msg." non &egrave stata inserita a causa di un errore interno",'error');
    }
  }
  drupal_goto($_GET['q'],'act=admuorder');
}
