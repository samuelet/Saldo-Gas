<?php
function log_adm_form() {
  $form['#redirect'] = FALSE;
  $form['explain']=array(
			 '#value'=>'<p>Log del gestionale</p>'
			 );
  $datetype = 'textfield';
  if (module_exists('date_popup')) {
    $datetype = 'date_popup';
  }
  $form['search'] = array(
				'#type' => 'fieldset',
				'#title' => 'Storico',
				'#collapsible' => TRUE,
				'#collapsed' => !($_POST['sdate'] || $_POST['edate']),
				);
  $form['search']['date_desc'] = array('#value' => '<div class="description">Inserire la data iniziale e facoltativamente quella finale del periodo cui si vuole  visualizzare i movimenti. Formato gg/mm/aaaa</div>');
  $form['search']['sdate'] = array(
					  '#type' => $datetype,
					  '#title' => 'Data Inizio',
					  '#date_format' => 'd/m/Y',
					  '#size' => 10,
					  '#maxlength' => 10,
					  );
  $form['search']['edate'] = array(
					  '#type' => $datetype,
					  '#date_format' => 'd/m/Y',
					  '#title' => 'Data Fine',
					  '#size' => 10,
					  '#maxlength' => 10,
					  );
  $form['search']['submit'] = array(
					  '#type' => 'submit',
					  '#value' => 'Cerca',
					  '#prefix' => '<div></div>',
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
  $sdate = $_POST['sdate'];
  $edate = $_POST['edate'];
  if (module_exists('date_popup')) {
    $sdate = $sdate['date'];
    $edate = $edate['date'];
  }
  if (!$sdate=datevalid($sdate)) {
    $sdate=date('Y-m-d', strtotime("-7 days"));
    $msg .= " degli ultimi 7 giorni";
  } else {
    $msg.=" dal ".datemysql($sdate,"-","/");
  }
  if ($edate=datevalid($edate)) {
    if (saldo_greaterDate($sdate,$edate)) {
      form_set_error('edate','La data di fine periodo non deve essere anteriore a quella di inizio periodo.');
      return "";
    }
    $msg.=" al ".datemysql($edate,"-","/");
  }

  $query = "SELECT DATE_FORMAT(l.ltime,'%%d-%%m-%%Y %H:%i'),u.name as unome,l.drupalid as uid,IF(l.lextra<>'',concat('<fieldset class=\'collapsible collapsed\' title=\'note\'><legend>',l.lact,'</legend><div class=\'saldo_note\'>',l.lextra,'</div></fieldset>'),l.lact) as act,DATE_FORMAT(ldate,'%%d-%%m-%%Y') FROM ".SALDO_LOG." as l LEFT JOIN users as u on u.uid=l.drupalid WHERE l.ltime > '".$sdate."'".(($edate) ? " AND l.ltime <= '".$edate."'": '')." ORDER by ltime DESC";

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
