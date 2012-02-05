<?php
function admpay_import_form($credit=FALSE) {
  $form['help'] = array('#type' => 'fieldset',
			'#title' => 'Aiuto',
			'#collapsible'=>true,
			'#collapsed'=>true,
			'#value' => "<div>La Spesa/Entrata Gas influisce in modo immediato sulla Cassa del Gas ma non sul salvaresti dei gasati.<br /><strong>Attenzione</strong>: Dato che la Cassa attuale non rappresenta necessariamente il contributo di tutti i gasati, si pu&ograve; valutare di bilanciare una Spesa Gas con dei ".l('Debiti Utenti',$_GET['q'],array(),'act=admucredit')." ripartiti fra tutti o parte dei gasati.</div>",
			);
  $form['pay'] = array('#type' => 'fieldset',
		       '#title' => "Spesa/Entrata Gas",
		       );
  $form['pay']['type'] = array('#title' => "Tipo",
			       '#type' => "radios",
			       '#description' => "Tipo di movimento.",
			       '#required' => TRUE,
			       '#options' => array(2 => 'Spesa',3 => 'Entrata'),
			       );
  
  if ($credit) {
    $form['help']['#value'] = "<div>Sia il Debito che il Credito Utente influiscono in modo immediato sul salvaresti del gasato, ma non sulla Cassa totale del Gas.</div>";

    $form['pay']['#title'] = "Debito/Credito";
    $form['pay']['type']['#options'] = array('Debito','Credito');
    $users=get_users();
    $form['pay']['users'] = array('#type' => 'select',
				  '#title' => 'Utenti',
				  '#description' => "Selezionare gli utenti a cui assegnare l'importo.",
				  '#multiple' =>TRUE,
				  '#options' =>$users,
				  '#required' => TRUE,
				  '#size' => min(12, count($users)),
				  '#attributes' => array('class' => 'select-filter-users'),
				  );
    $note_desc = "Nota sul tipo di movimento. Ad es: Spese per sede.";
    $saldo_desc = "del debito/credito";
  } else {
    $note_desc = "Nota sul tipo di Entrata o spesa. Ad es: Quote tessere o Acquisto frigorifero.";
    $saldo_desc = "dell' entrata/spesa";
  }

  $form['pay']['note']= array(
			     '#title' => "Causale",
			     '#type' => 'textfield',
			     '#description' => $note_desc,
			     '#required' => TRUE,
			     '#maxlength' => 255,
			     );

  $form['pay']['saldo']= array(
			     '#title' => "Importo",
			     '#type' => 'textfield',
			     '#description' => "Ammontare ".$saldo_desc." (in Euro).",
			     '#size' => 7,
			     '#maxlength' => 7,
			     '#required' => TRUE,
			     );

  $form['pay']['submit'] = array(
				 '#type' => 'submit',
				 '#value' => 'Inserisci',
				 );  
  return $form; 
  }

function admpay_import_form_validate($form_id, $form_values) {
  if (!is_numeric($form_values['saldo']) || $form_values['saldo']<0) {
    form_set_error('saldo',$form_values['saldo']. " non &egrave un valore monetario valido. Operazione non eseguita!"); 
  }
}

function admpay_import_form_submit($form_id, $form_values ) {
  global $suser;
  $log_extra="";
  $query="INSERT INTO ".SALDO_DEBITO_CREDITO." (suid,ssaldo,snote,slastduid,stype) VALUES ";
  switch ($form_values['type']) {
  case 0:
    $stype ='Debito utente';

  case 1:
    $msg_users=array();
    if (!$stype) {
      $stype ='Credito utente';
    }
    $users=get_users();
    foreach ($form_values['users'] as $uid) {
      $msg_users[]=$users[$uid];
      $query .= "(".$uid.",".$form_values['saldo'].",'".check_plain($form_values['note'])."',".$suser->duid.",".$form_values['type']."),";
    }
    $query = rtrim($query,',');
    $msg = $stype." di <strong>".$form_values['saldo']."</strong> Euro per <em>".$form_values['note']."</em> agli utenti ".implode("<br \>",$msg_users);
    $log = "Tesoriere: Inserito ".$stype;
    $log_extra=implode(",",$msg_users);
    break;

  case 2:
    $stype = "Spesa Gas";

  case 3:
    if (!$stype) {
      $stype = "Entrata Gas";
    }
    $msg = $stype." di <strong>".$form_values['saldo']."</strong> Euro per <em>".$form_values['note']."</em>";
    $query .= "(0,".$form_values['saldo'].",'".check_plain($form_values['note'])."',".$suser->duid.",".$form_values['type'].")";
    $log="Tesoriere: Inserita ".$stype;
    $log_extra=check_plain($form_values['note']);
    break;
  default:
    return;
  }

  if (db_query($query)) {
    drupal_set_message("Inserito ".$msg);
    log_gas($log,'NULL',$log_extra);
  } else {
    drupal_set_message("Errore inserimento ".$msg,'error');
  }
  drupal_goto($_GET['q'],'act='.$_GET['act']);
}

?>
