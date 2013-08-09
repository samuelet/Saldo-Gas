<?php
function admdepo_import_form() {
  $users=array("--Scegli--")+get_users();

  $form['help'] = array('#type' => 'fieldset',
			       '#title' => 'Aiuto',
			       '#collapsible'=>true,
			       '#collapsed'=>true,
			       '#value' => '<div>Il Versamento Utente influisce in modo immediato sia sul salvaresti utente che sulla cassa del Gas.</div>',
			       );

  $form['pay'] = array(
				'#type' => 'fieldset',
				'#title' => 'Versamento Utente',
				);
  
  $form['pay']['user'] = array(
				'#type' => 'select',
				'#title' => 'Utente',
				'#description' => "Selezionare l'utente che ha eseguito il versamento",
				'#default_value' => 0,
				'#options' =>$users,
				'#attributes' => array('class' => 'select-filter-users'),
				);

  $form['pay']['date'] = array(
				    '#type' => 'textfield',
				    '#title' => 'Data del versamento',
				    '#attributes' => array('class' => 'jscalendar'),
				    '#description' => 'Inserire la data nel formato gg/mm/aaaa',
				    '#jscalendar_ifFormat' => '%d/%m/%Y',
				    '#jscalendar_showsTime' => 'false',
				    '#default_value' => date("d/m/Y"),
				    '#size' => 10,
				    '#maxlength' => 10,
				    '#required' => TRUE,
				    );

  $form['pay']['saldo']= array(
			     '#title' => "Versamento",
			     '#type' => 'textfield',
			     '#description' => "Ammontare del versamento (in Euro).",
			     '#size' => 6,
			     '#maxlength' => 8,
			     );

  $form['pay']['submit'] = array(
				 '#type' => 'submit',
				 '#value' => 'Inserisci',
				 );  
  return $form;
  
  }

function admdepo_import_form_validate($form, &$form_state) {
  if (!datevalid($form_state['values']['date'])) {
    form_set_error('date',$form_state['values']['date']. " non &egrave una data valida!"); 
  }

  if (saldo_greaterDate($form_state['values']['date'],date('d/m/Y'))) {
    form_set_error('date',$form_state['values']['date']. " &egrave una data futura!"); 
  }

  if (!is_numeric($form_state['values']['saldo']) || $form_state['values']['saldo']<0 || $form_state['values']['saldo']>=10000) {
    form_set_error('saldo',$form_state['values']['saldo']. " non &egrave un valore monetario valido. Operazione non eseguita!"); 
  }

  if (!is_numeric($form_state['values']['user']) || $form_state['values']['user']<1) {
    form_set_error('user',"Seleziona un utente!");
  }
}

function admdepo_import_form_submit($form, &$form_state) {
  global $suser;
  $date = datevalid($form_state['values']['date']);
  $query="INSERT INTO ".SALDO_VERSAMENTI." (vuid,vsaldo,vlastduid,ltime) VALUES (".$form_state['values']['user'].",".$form_state['values']['saldo'].",".$suser->duid.",'".$date."');";
  if (db_query($query)) {
    $luser=implode("",get_users($form_state['values']['user']));
    drupal_set_message("Inserito versamento di ".$form_state['values']['saldo']." Euro per l'utente ".$luser.' in data '.datemysql($form_state['values']['date'],"-","/"));
    log_gas("Tesoriere: Inserito Versamento utente",$date,$luser);
  } else {
    drupal_set_message("Errore inserimento versamento di ".$form_state['values']['saldo']." Euro per l'utente ".implode("",get_users($form_state['values']['user'])),'error');
  }
  drupal_goto($_GET['q'],'act=admdepo');
}
