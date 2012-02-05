<?php
function log_adm_form() {
  $form['#redirect'] = FALSE;
  $form['explain']=array(
			 '#value'=>'<p>Log del gestionale</p>'
			 );
  $form['search'] = array(
				'#type' => 'fieldset',
				'#title' => 'Storico',
				'#collapsible' => TRUE,
				'#collapsed' => true,
				);
  $form['search']['date'] = array(
					  '#type' => 'textfield',
					  '#title' => 'Data Inizio',
					  '#attributes' => array('class' => 'jscalendar'),
					  '#description' => 'Inserire la data di inizio di cui visualizzare i logs. Formato gg/mm/aaaa',
					  '#jscalendar_ifFormat' => '%d/%m/%Y',
					  '#jscalendar_showsTime' => 'false',
					  '#size' => 10,
					  '#maxlength' => 10,
					  );
  $form['search']['submit'] = array(
					  '#type' => 'submit',
					  '#value' => 'Cerca',
					  );
  $form['log_adm']['result'] = array('#value' =>_log_list());
  
  return $form;
  }

function _log_list() {
  global $suser;
  $msg="Logs";
  $headers= array(
		  'Data',
		  'Tipo',
		  'Saldo',
		  );
  if (!$date=datevalid($_POST['date'])) {
    $date=date('Y-m-d', strtotime("-7 days"));
    $msg .= " degli ultimi 7 giorni";
  } else {
    $msg.=" dal ".datemysql($date,"-","/");
  }

  $query = "SELECT DATE_FORMAT(l.ltime,'%%d-%%m-%%Y %H:%i'),u.name as unome,l.drupalid as uid,IF(l.lextra<>'',concat('<fieldset class=\'collapsible collapsed\' title=\'note\'><legend>',l.lact,'</legend><div class=\'saldo_note\'>',l.lextra,'</div></fieldset>'),l.lact) as act,DATE_FORMAT(ldate,'%%d-%%m-%%Y') FROM ".SALDO_LOG." as l LEFT JOIN users as u on u.uid=l.drupalid WHERE l.ltime > '".$date."' ORDER by ltime DESC";
  $headers= array(
		  'Data',
		  'Utente',
		  'Azione',
		  'Giorno',
		  );
  $out = drupal_render($form);
  $out .= saldo_table($query,$headers);
  drupal_set_message($msg);
  
  return $out;
}
