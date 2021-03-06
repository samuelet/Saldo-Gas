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
  if (!is_numeric($form_values['saldo']) || $form_values['saldo']<0 || $form_values['saldo']>=10000) {
    form_set_error('saldo',$form_values['saldo']. " non &egrave un valore monetario valido. Operazione non eseguita!"); 
  }

  if (!is_numeric($form_values['user']) || $form_values['user']<1) {
    form_set_error('user',"Seleziona un utente!");
  }
}

function admdepo_import_form_submit($form_id, $form_values) {
  global $suser;
  $query="INSERT INTO ".SALDO_VERSAMENTI." (vuid,vsaldo,vlastduid) VALUES (".$form_values['user'].",".$form_values['saldo'].",".$suser->duid.");";
  if (db_query($query)) {
    $luser=implode("",get_users($form_values['user']));
    drupal_set_message("Inserito versamento di ".$form_values['saldo']." Euro per l'utente ".$luser);
    log_gas("Tesoriere: Inserito Versamento utente",'NULL',$luser);
  } else {
    drupal_set_message("Errore inserimento versamento di ".$form_values['saldo']." Euro per l'utente ".implode("",get_users($form_values['user'])),'error');
  }
  drupal_goto($_GET['q'],'act=admdepo');
}
