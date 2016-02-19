<?php
function all_cash_form() {
  $form['#redirect'] = FALSE;
  $form['allcashr'] = array(
				'#type' => 'fieldset',
				'#title' => 'Data',
				'#collapsible' => TRUE,
				'#collapsed' => !$_POST['datemr'],
				);

  $form['allcashr']['datemr'] = array(
				    '#type' => 'textfield',
				    '#title' => 'Altra data riepilogo movimenti',
				    '#attributes' => array('class' => 'jscalendar'),
				    '#description' => 'Se questo campo &egrave; vuoto (default), allora il riepilogo si riferisce ad oggi. Se invece si vuole avere il riepilogo ad un determinato giorno, inserire la data nel formato gg/mm/aaaa',
				    '#jscalendar_ifFormat' => '%d/%m/%Y',
				    '#jscalendar_showsTime' => 'false',
				    '#size' => 10,
				    '#maxlength' => 10,
				    );

  $form['allcashr']['submit'] = array(
					 '#type' => 'submit',
					 '#value' => 'Invia',
					 );  

  $aquery['Ordine Utente']="SELECT IFNULL(SUM(osaldo),0) as fpsaldo FROM ".SALDO_ORDINI;
  $aquery['Versamento Utente']="SELECT IFNULL(SUM(vsaldo),0) as vssaldo FROM ".SALDO_VERSAMENTI. " WHERE vtype=0";
  $aquery['Storno Versamento Utente']="SELECT IFNULL(SUM(vsaldo),0) as vssaldo FROM ".SALDO_VERSAMENTI. " WHERE vtype=1";
  $aquery['Pagamento Fornitore']="SELECT IFNULL(SUM(fpsaldo),0) as fpsaldo FROM ".SALDO_FIDPAGAMENTO;
  $aquery['Entrata Gas']="SELECT IFNULL(SUM(ssaldo),0) as gusaldo FROM ".SALDO_DEBITO_CREDITO." WHERE stype=3";
  $aquery['Spesa Gas']="SELECT IFNULL(SUM(ssaldo),0) as gesaldo FROM ".SALDO_DEBITO_CREDITO." WHERE stype=2";
  $aquery['Debito Utente']="SELECT IFNULL(SUM(ssaldo),0) as udsaldo FROM ".SALDO_DEBITO_CREDITO." WHERE stype=0";
  $aquery['Credito Utente']="SELECT IFNULL(SUM(ssaldo),0) as ucsaldo FROM ".SALDO_DEBITO_CREDITO." WHERE stype=1";

  if ($_POST['datemr']) {
     if ($datemr=datevalid($_POST['datemr'])) {
     	$aquery['Ordine Utente'] .= " WHERE odata <= '".$datemr."'";
	$aquery['Versamento Utente'] .= " AND ltime <= '".$datemr."'";
	$aquery['Storno Versamento Utente'] .= " AND ltime <= '".$datemr."'";
	$aquery['Pagamento Fornitore'] .= " WHERE fpltime <= '".$datemr."'";
	$aquery['Entrata Gas'] .= " AND sltime <= '".$datemr."'";
	$aquery['Spesa Gas'] .= " AND sltime <= '".$datemr."'";
	$aquery['Debito Utente'] .= " AND sltime <= '".$datemr."'";
	$aquery['Credito Utente'] .= " AND sltime <= '".$datemr."'";
	drupal_set_message("Riepilogo movimenti al ".datemysql($datemr,"-","/"));
     } else {
     	form_set_error('datemr','Data non valida');
     }
  }

  foreach ($aquery as $h=>$query) {
    $headers[]=$h;
    $r=db_result(db_query($query));
    $asaldo[$h]=$r;
  }

  $asaldoh = array_map("abs", $asaldo);
  $asaldoh = array_map(create_function('$n', 'return number_format($n, 2, ".", "");'), $asaldoh);
  $out=theme('table',$headers, array($asaldoh));

  $asaldo['Saldo Fornitori'] = $asaldo["Pagamento Fornitore"] - $asaldo["Ordine Utente"];
  $asaldo['Saldo Cassa'] = $asaldo["Versamento Utente"] + $asaldo["Storno Versamento Utente"] - $asaldo["Pagamento Fornitore"] - $asaldo["Spesa Gas"] + $asaldo["Entrata Gas"];
  $asaldo['Salva Resti']= $asaldo["Credito Utente"] + $asaldo["Versamento Utente"] + $asaldo["Storno Versamento Utente"] - $asaldo["Debito Utente"] - $asaldo["Ordine Utente"];
  $asaldo['Fondo Spese'] = $asaldo["Saldo Fornitori"] + $asaldo["Saldo Cassa"] - $asaldo["Salva Resti"];

  $main[] = array("<strong>Saldo Fornitori</strong>","=","Pagamento Fornitore - Ordine Utente",$asaldo['Saldo Fornitori']);
  $main[] = array("<strong>Saldo Cassa</strong>","=","Versamento Utente - Storno Versamento Utente - Pagamento Fornitore - Spesa Gas + Entrata Gas","<strong>".$asaldo['Saldo Cassa']."</strong>");
  $main[] = array("<strong>Salva Resti</strong>","=","Credito Utente + Versamento Utente - Storno Versamento Utente - Debito Utente - Ordine Utente",$asaldo['Salva Resti']);
  $main[] = array("<strong>Fondo Spese</strong>","=","Saldo Fornitori + Saldo Cassa - Salva Resti","<strong>".$asaldo['Fondo Spese']."<strong/>");
  $out.=theme('table',array(),$main);

  $form['allcash']['result'] = array('#value' =>$out);
  return $form;
}