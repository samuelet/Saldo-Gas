<?php
function admfidpay_import_form() {
  $fids=get_fids();
  $fids['0']="--Scegli--";
  $form['help'] = array('#type' => 'fieldset',
			'#title' => 'Aiuto',
			'#collapsible'=>true,
			'#collapsed'=>true,
			'#value' => "<div>Il Pagamento Fornitore influisce in modo immediato sulla Cassa del Gas e non sul salvaresti dei gasati.</div>",
			);

  $form['pay'] = array(
				'#type' => 'fieldset',
				'#title' => 'Pagamento fornitore',
				);
  $form['pay']['fid'] = array(
				'#type' => 'select',
				'#title' => 'Fornitore',
				'#description' => "Selezionare il fornitore a cui assegnare il pagamento.",
				'#default_value' => 0,
				'#required'=>TRUE,
				'#options' =>$fids,
				);
  $form['pay']['note']= array(
			     '#title' => "Note",
			     '#type' => 'textfield',
			     '#description' => "Eventuali note.",
			     '#maxlength' => 255,
			     );
  $form['pay']['saldo']= array(
			     '#title' => "Versamento",
			     '#type' => 'textfield',
			     '#description' => "Ammontare del versamento (in Euro)",
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

function admfidpay_import_form_validate($form, &$form_state) {
  if (!is_numeric($form_state['values']['saldo']) || $form_state['values']['saldo']<0 || $form_state['values']['saldo']>=10000) {
    form_set_error('saldo',$form_state['values']['saldo']. " non &egrave un valore monetario valido. Operazione non eseguita!"); 
  }
  if (!is_numeric($form_state['values']['fid']) || $form_state['values']['fid']<1) {
    form_set_error('user',"Seleziona un fornitore!");
  }
}

function admfidpay_import_form_submit($form, &$form_state) {
  global $suser;
  $query="INSERT INTO ".SALDO_FIDPAGAMENTO." (fpfid,fpsaldo,fplastduid,fpnote) VALUES (".$form_state['values']['fid'].",".$form_state['values']['saldo'].",".$suser->duid.",'".check_plain($form_state['values']['note'])."');";
  if (db_query($query)) {
    $fids=implode(",",get_fids(array($form_state['values']['fid'])));
    drupal_set_message("Inserito pagamento di ".$form_state['values']['saldo']." Euro al fornitore ".$fids);
    log_gas("Tesoriere: Pagamento fornitore","NULL",$fids);
  } else {
    drupal_set_message("Errore inserimento pagamento di ".$form_state['values']['saldo']." Euro al fornitore ".implode(",",get_fids(array($form_state['values']['fid']))),'error');
  }
  drupal_goto($_GET['q'],'act=admfidpay');
}
