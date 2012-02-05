<?php
function user_prefs_form() {
  global $suser, $gas_subgroups;
  $listgas=array();
  $form['prefs'] = array(
    '#type' => 'fieldset',
    '#title' => 'Sottogruppo',
    '#collapsible' => TRUE,
			     );
  foreach ( $gas_subgroups as $key=>$gas) {
    $suser->ugroup;
    if ($key == $suser->ugroup) {
      $gas.= " (preferito)";
    }
    $listgas[$key]=$gas;
  }

  $form['prefs']['gas'] = array('#type' => 'select',
				    '#title' => 'Sottogruppo',
				    '#description' => 'Seleziona il sottogruppo di cui vuoi visualizzare il saldo.',
				    '#default_value' => $_SESSION['saldo_prefix'],
				    '#options' => $listgas,
				    );

  $form['prefs']['save_pref'] = array('#type' => 'checkbox',
				      '#title' => 'Preferito',
				      '#description' => 'Memorizza come scelta predefinita.',
				      );
  
  $form['prefs']['submit'] = array('#type' => 'submit',
				       '#value' => 'Cambia',
				       );
  
  return $form;
}

function user_prefs_form_validate($form_id, $form_values) {
}

function user_prefs_form_submit($form_id, $form_values) {
  global $suser;
  unset($_SESSION['saldo_user']);
  $_SESSION['saldo_prefix']=$form_values['gas'];
  if ($form_values['save_pref']){
    $query="UPDATE ".SALDO_UTENTI." set ugroup='".$form_values['gas']."' WHERE uid in (".implode(",",array_keys($suser->uid)).")";
    if (!$result=db_query($query)) {
      drupal_set_message('Errore connessione database. Le preferenze non sono state salvate','error');
    } else {
      log_gas("Utente: Impostazione sottogruppo preferito");
    }
  }
}
