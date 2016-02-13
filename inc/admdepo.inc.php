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
				'#title' => 'Versamento/Storno-Versamento Utente',
				);
  
  $form['pay']['type'] = array('#title' => "Tipo",
			       '#type' => "radios",
			       '#description' => "Tipo di movimento.",
			       '#default_value' => 0,
			       '#required' => TRUE,
			       '#options' => array('Versamento','Storno Versamento'),
			       );

  $form['pay']['user'] = array(
				'#type' => 'select',
				'#title' => 'Utente',
				'#description' => "Selezionare l'utente a cui assegnare l'importo",
				'#default_value' => 0,
				'#options' =>$users,
				'#attributes' => array('class' => 'select-filter-users'),
				);

  $form['pay']['date'] = array(
				    '#type' => 'textfield',
				    '#title' => 'Data',
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
			     '#title' => "Importo",
			     '#type' => 'textfield',
			     '#description' => "Ammontare dell' importo (in Euro).",
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
    form_set_error('date',$form_values['date']. " non &egrave una data valida!"); 
  }

  if (saldo_greaterDate($form_values['date'],date('d/m/Y'))) {
    form_set_error('date',$form_values['date']. " &egrave una data futura!"); 
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

  switch ($form_values['type']) {
  case 0:
    $versamento=$form_values['saldo'];
    $svtype="Versamento utente";
    break;
  case 1:
    $versamento="-".$form_values['saldo'];
    $svtype='Storno Versamento utente';
    break;
  default:
    return;
  }
  $query="INSERT INTO ".SALDO_VERSAMENTI." (vuid,vsaldo,vlastduid,ltime,vtype) VALUES (".$form_values['user'].",".$versamento.",".$suser->duid.",'".$date."',".$form_values['type'].");";
  if (db_query($query)) {
    $luser=implode("",get_users($form_values['user']));
    drupal_set_message("Inserito $svtype di ".$form_values['saldo']." Euro per l'utente ".$luser.' in data '.datemysql($form_values['date'],"-","/"));
    log_gas("Tesoriere: Inserito $svtype utente",$date,$luser);
  } else {
    drupal_set_message("Errore inserimento $svtype di ".$form_values['saldo']." Euro per l'utente ".implode("",get_users($form_values['user'])),'error');
  }
  drupal_goto($_GET['q'],'act=admdepo');
}
