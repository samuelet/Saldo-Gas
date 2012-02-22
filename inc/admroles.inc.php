<?php
function roles_adm_form() {
  $form['#redirect'] = array($_GET['q'],'act=admroles');
  $options =array( ROLE_ADMIN => 'Amministratore',
		   ROLE_TREASURER => 'Tesoriere',
		   );
  $form['roles'] = array(
				'#type' => 'fieldset',
				'#title' => 'Assegna ruoli',
				);
  $users=get_users();
  $form['roles']['users'] = array(
				  '#type' => 'select',
				  '#title' => 'Utente',
				  '#required' => true,
				  '#options' =>array('-')+$users,
				  '#attributes' => array('class' => 'select-filter-users'),
				  );

  $form['roles']['roles'] = array( '#type' => 'checkboxes',
				   '#title' => 'Ruoli',
				   '#description' => "Assegna uno o pi&ugrave; ruoli all'utente. Per rimuovere gli attuali ruoli di un utente, deselezionare tutte le caselle.",
				   '#options' => $options,
				   );
  $form['roles']['submit'] = array(
					  '#type' => 'submit',
					  '#value' => 'Modifica',
					  );
  foreach ($options as $key=>$value) {
    $sql_options .= "WHEN ".$key." THEN '".$value."' ";
  }
  $query="SELECT d.uid,u.unome,GROUP_CONCAT(CASE r.rrole ".$sql_options." END) FROM ".SALDO_UTENTI." u RIGHT JOIN ".SALDO_ROLES." r ON u.uid=r.ruid LEFT JOIN {users} d ON d.mail=u.email GROUP BY u.uid ORDER by u.unome";
  $form['list']=array('#value' =>saldo_table($query,array("Utente","Ruolo")));
  return $form;
  }

function roles_adm_form_validate($form, &$form_state) {
  if ($form_state['values']['users'] < 1) {
    form_set_error('users',t('field is required'));
  }
}

function roles_adm_form_submit($form, &$form_state) {
  $addroles=FALSE;
  foreach ($form_state['values']['roles'] as $role) {
    if ($form_state['values']['roles'][$role]) {
      $addroles.="(".$form_state['values']['users'].",".$role."),";
    }
  }
  $query = "DELETE FROM ".SALDO_ROLES." WHERE ruid=".$form_state['values']['users'];
  if (db_query($query)) {
    if ($addroles) {
      $query = "INSERT INTO ".SALDO_ROLES." VALUES ".rtrim($addroles,",");
      db_query($query);
    }
    log_gas("Amministratore: Modifica ruoli utente",'NULL',implode("",get_users($form_state['values']['users'])));
    drupal_set_message("Il ruolo dell'utente <em>".implode("",get_users($form_state['values']['users']))."</em> &egrave; stato modificato.");
  }
}