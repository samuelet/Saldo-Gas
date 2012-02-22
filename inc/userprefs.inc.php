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

function user_prefs_form_validate($form, &$form_state) {
}

function user_prefs_form_submit($form, &$form_state) {
  global $suser;
  unset($_SESSION['saldo_user']);
  $_SESSION['saldo_prefix']=$form_state['values']['gas'];
  if ($form_state['values']['save_pref']){
    $query="UPDATE ".SALDO_UTENTI." set ugroup='".$form_state['values']['gas']."' WHERE uid in (".implode(",",array_keys($suser->uid)).")";
    if (!$result=db_query($query)) {
      drupal_set_message('Errore connessione database. Le preferenze non sono state salvate','error');
    } else {
      log_gas("Utente: Impostazione sottogruppo preferito");
    }
  }
}
