<?php
function pwds_adm_form() {
  $puser=saldo_es_pwd(1);
  $form['#redirect'] = array($_GET['q'],'act=admpwds');
  $form['imporders'] = array('#type' => 'fieldset',
			     '#title' => 'Importazioni ordini',
			     '#description' => "Inserire le credenziali dell'utente di economia solidale che ha l'accesso al riepilogo ordini. Le credenziali sono necessarie per importare gli ordini da evadere direttamente da Economia Solidale.",
			     );
  $form['imporders']['opuser'] = array('#type' => 'textfield',
				      '#title' => 'Login',
				      '#default_value' => $puser->puser,
				      '#size' => 20,
				      '#maxlength' => 255,
				      );
  
  $form['imporders']['opassword'] = array('#type' => 'password_confirm',
					 '#size' => 20,
					 );
  $puser=saldo_es_pwd(2);
  $form['impusers'] = array('#type' => 'fieldset',
			    '#title' => 'Importazioni Utenti',
			    '#description' => "Inserire le credenziali dell'utente di Economia Solidale che ha l'accesso alla lista utenti. Le credenziali sono necessarie per importare gli utenti direttamente da Economia Solidale.",
			    );
  $form['impusers']['upuser'] = array('#type' => 'textfield',
				     '#title' => 'Login',
				     '#default_value' => $puser->puser,
				     '#size' => 20,
				     '#maxlength' => 255,
				     );
  
  $form['impusers']['upassword'] = array('#type' => 'password_confirm',
					'#size' => 20,
					);
  
  $form['submit'] = array('#type' => 'submit',
			  '#value' => 'Modifica',
			  );

  return $form;
  }

function pwds_adm_form_submit($form, &$form_state) {
  $query = "INSERT INTO ".SALDO_PWD." (ptype,puser,ppwd) VALUES ";
  $addquery=FALSE;
  $logextra=array();
  if (!empty($form_state['values']['opuser']) && !empty($form_state['values']['opassword'])) {
    $addquery="(1,'".$form_state['values']['opuser']."','".$form_state['values']['opassword']."'),";
    $logextra[]="ordini";
  }
  if (!empty($form_state['values']['upuser']) && !empty($form_state['values']['upassword'])) {
    $addquery.="(2,'".$form_state['values']['upuser']."','".$form_state['values']['upassword']."')";
    $logextra[]="utenti";
  }
  if ($addquery) {
    $query .= rtrim($addquery,",");
    $query .= " ON DUPLICATE KEY UPDATE puser=VALUES(puser), ppwd=VALUES(ppwd)";
    if (db_query($query)) {
      drupal_set_message("Credenziali modificate.");
      log_gas("Amministratore: Modifica credenziali importazione","NULL",implode(",",$logextra));
    }
  } else {
    drupal_set_message("Nessuna modifica eseguita.");
  }
}