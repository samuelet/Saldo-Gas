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

function admdepo_import_form_validate($form_id, $form_values) {
  if (!datevalid($form_values['date'])) {
    form_set_error('saldo',$form_values['date']. " non &egrave una data valida!"); 
  }

  if (saldo_greaterDate($form_values['date'],date('d/m/Y'))) {
    form_set_error('saldo',$form_values['date']. " &egrave una data futura!"); 
  }

  if (!is_numeric($form_values['saldo']) || $form_values['saldo']<0 || $form_values['saldo']>=10000) {
    form_set_error('saldo',$form_values['saldo']. " non &egrave un valore monetario valido. Operazione non eseguita!"); 
  }

  if (!is_numeric($form_values['user']) || $form_values['user']<1) {
    form_set_error('user',"Seleziona un utente!");
  }
}

function admdepo_import_form_submit($form_id, $form_values) {
  global $suser;
  $date = datevalid($form_values['date']);
  $query="INSERT INTO ".SALDO_VERSAMENTI." (vuid,vsaldo,vlastduid,ltime) VALUES (".$form_values['user'].",".$form_values['saldo'].",".$suser->duid.",'".$date."');";
  if (db_query($query)) {
    $luser=implode("",get_users($form_values['user']));
    drupal_set_message("Inserito versamento di ".$form_values['saldo']." Euro per l'utente ".$luser.' in data '.datemysql($form_values['date'],"-","/"));
    log_gas("Tesoriere: Inserito Versamento utente",$date,$luser);
  } else {
    drupal_set_message("Errore inserimento versamento di ".$form_values['saldo']." Euro per l'utente ".implode("",get_users($form_values['user'])),'error');
  }
  drupal_goto($_GET['q'],'act=admdepo');
}
