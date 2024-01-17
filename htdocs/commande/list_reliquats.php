<?php
/* Copyright (C) 2001-2005  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2019  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005       Marc Barilley / Ocebo   <marc@ocebo.com>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2012       Juanjo Menent           <jmenent@2byte.es>
 * Copyright (C) 2013       Christophe Battarel     <christophe.battarel@altairis.fr>
 * Copyright (C) 2013       Cédric Salvador         <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015-2018  Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2015       Marcos García           <marcosgdf@gmail.com>
 * Copyright (C) 2015       Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2016-2023  Ferran Marcet           <fmarcet@2byte.es>
 * Copyright (C) 2018       Charlene Benke	        <charlie@patas-monkey.com>
 * Copyright (C) 2021	   	Anthony Berton			<anthony.berton@bb2a.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/commande/list.php
 *	\ingroup    commande
 *	\brief      Page to list orders
 */


// Load Dolibarr environment
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
if (isModEnabled('margin')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmargin.class.php';
}
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

// Load translation files required by the page
$langs->loadLangs(array('orders', 'sendings', 'deliveries', 'companies', 'compta', 'bills', 'stocks', 'products'));

// Get Parameters
$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$show_files = GETPOST('show_files', 'int');
$confirm = GETPOST('confirm', 'alpha');
$toselect = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'orderlist';
$mode        = GETPOST('mode', 'alpha');


// Load variable for pagination
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	// If $page is not defined, or '' or -1 or if we click on clear filters
	$page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) {
	$sortfield = 'c.ref';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}

$show_shippable_command = GETPOST('show_shippable_command', 'aZ09');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$object = new Commande($db);
//$hookmanager->initHooks(array('orderlist'));
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
	'c.ref'=>'Ref',
	'c.ref_client'=>'RefCustomerOrder',
	'pd.description'=>'Description',
	's.nom'=>"ThirdParty",
	's.name_alias'=>"AliasNameShort",
	's.zip'=>"Zip",
	's.town'=>"Town",
	'c.note_public'=>'NotePublic',
);
if (empty($user->socid)) {
	$fieldstosearchall["c.note_private"] = "NotePrivate";
}

$checkedtypetiers = 0;
$arrayfields = array(
	'c.ref'=>array('label'=>"Ref", 'checked'=>1, 'position'=>5),
	'c.ref_client'=>array('label'=>"RefCustomerOrder", 'checked'=>-1, 'position'=>10),
	'p.ref'=>array('label'=>"ProjectRef", 'checked'=>-1, 'enabled'=>(!isModEnabled('project') ? 0 : 1), 'position'=>20),
	'p.title'=>array('label'=>"ProjectLabel", 'checked'=>0, 'enabled'=>(!isModEnabled('project') ? 0 : 1), 'position'=>25),
	's.nom'=>array('label'=>"ThirdParty", 'checked'=>1, 'position'=>30),
	's.name_alias'=>array('label'=>"AliasNameShort", 'checked'=>-1, 'position'=>31),
	's2.nom'=>array('label'=>'ParentCompany', 'position'=>32, 'checked'=>0),
	's.town'=>array('label'=>"Town", 'checked'=>-1, 'position'=>35),
	's.zip'=>array('label'=>"Zip", 'checked'=>-1, 'position'=>40),
	'state.nom'=>array('label'=>"StateShort", 'checked'=>0, 'position'=>45),
	'country.code_iso'=>array('label'=>"Country", 'checked'=>0, 'position'=>50),
	'typent.code'=>array('label'=>"ThirdPartyType", 'checked'=>$checkedtypetiers, 'position'=>55),
	'c.date_commande'=>array('label'=>"OrderDateShort", 'checked'=>1, 'position'=>60),
	'c.date_delivery'=>array('label'=>"DateDeliveryPlanned", 'checked'=>1, 'enabled'=>empty($conf->global->ORDER_DISABLE_DELIVERY_DATE), 'position'=>65),
	'c.fk_shipping_method'=>array('label'=>"SendingMethod", 'checked'=>-1, 'position'=>66 , 'enabled'=>isModEnabled("expedition")),
	'c.fk_cond_reglement'=>array('label'=>"PaymentConditionsShort", 'checked'=>-1, 'position'=>67),
	'c.fk_mode_reglement'=>array('label'=>"PaymentMode", 'checked'=>-1, 'position'=>68),
	'c.fk_input_reason'=>array('label'=>"Channel", 'checked'=>-1, 'position'=>69),
	'c.total_ht'=>array('label'=>"AmountHT", 'checked'=>1, 'position'=>75),
	'c.total_vat'=>array('label'=>"AmountVAT", 'checked'=>0, 'position'=>80),
	'c.total_ttc'=>array('label'=>"AmountTTC", 'checked'=>0, 'position'=>85),
	'c.multicurrency_code'=>array('label'=>'Currency', 'checked'=>0, 'enabled'=>(!isModEnabled("multicurrency") ? 0 : 1), 'position'=>90),
	'c.multicurrency_tx'=>array('label'=>'CurrencyRate', 'checked'=>0, 'enabled'=>(!isModEnabled("multicurrency") ? 0 : 1), 'position'=>95),
	'c.multicurrency_total_ht'=>array('label'=>'MulticurrencyAmountHT', 'checked'=>0, 'enabled'=>(!isModEnabled("multicurrency") ? 0 : 1), 'position'=>100),
	'c.multicurrency_total_vat'=>array('label'=>'MulticurrencyAmountVAT', 'checked'=>0, 'enabled'=>(!isModEnabled("multicurrency") ? 0 : 1), 'position'=>105),
	'c.multicurrency_total_ttc'=>array('label'=>'MulticurrencyAmountTTC', 'checked'=>0, 'enabled'=>(!isModEnabled("multicurrency") ? 0 : 1), 'position'=>110),
	'u.login'=>array('label'=>"Author", 'checked'=>1, 'position'=>115),
	'sale_representative'=>array('label'=>"SaleRepresentativesOfThirdParty", 'checked'=>0, 'position'=>116),
	'total_pa' => array('label' => (getDolGlobalString('MARGIN_TYPE') == '1' ? 'BuyingPrice' : 'CostPrice'), 'checked' => 0, 'position' => 300, 'enabled' => (!isModEnabled('margin') || !$user->hasRight("margins", "liretous") ? 0 : 1)),
	'total_margin' => array('label' => 'Margin', 'checked' => 0, 'position' => 301, 'enabled' => (!isModEnabled('margin') || !$user->hasRight("margins", "liretous") ? 0 : 1)),
	'total_margin_rate' => array('label' => 'MarginRate', 'checked' => 0, 'position' => 302, 'enabled' => (!isModEnabled('margin') || !$user->hasRight("margins", "liretous") || empty($conf->global->DISPLAY_MARGIN_RATES) ? 0 : 1)),
	'total_mark_rate' => array('label' => 'MarkRate', 'checked' => 0, 'position' => 303, 'enabled' => (!isModEnabled('margin') || !$user->hasRight("margins", "liretous") || empty($conf->global->DISPLAY_MARK_RATES) ? 0 : 1)),
	'c.datec'=>array('label'=>"DateCreation", 'checked'=>0, 'position'=>120),
	'c.tms'=>array('label'=>"DateModificationShort", 'checked'=>0, 'position'=>125),
	'c.date_cloture'=>array('label'=>"DateClosing", 'checked'=>0, 'position'=>130),
	'c.note_public'=>array('label'=>'NotePublic', 'checked'=>0, 'enabled'=>(!getDolGlobalInt('MAIN_LIST_HIDE_PUBLIC_NOTES')), 'position'=>135),
	'c.note_private'=>array('label'=>'NotePrivate', 'checked'=>0, 'enabled'=>(!getDolGlobalInt('MAIN_LIST_HIDE_PRIVATE_NOTES')), 'position'=>140),
	'shippable'=>array('label'=>"Shippable", 'checked'=>1,'enabled'=>(isModEnabled("expedition")), 'position'=>990),
	'c.facture'=>array('label'=>"Billed", 'checked'=>1, 'enabled'=>(empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)), 'position'=>995),
	'c.import_key' =>array('type'=>'varchar(14)', 'label'=>'ImportId', 'enabled'=>1, 'visible'=>-2, 'position'=>999),
	'c.fk_statut'=>array('label'=>"Status", 'checked'=>1, 'position'=>1000)
);

// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

$object->fields = dol_sort_array($object->fields, 'position');
//$arrayfields['anotherfield'] = array('type'=>'integer', 'label'=>'AnotherField', 'checked'=>1, 'enabled'=>1, 'position'=>90, 'csslist'=>'right');
$arrayfields = dol_sort_array($arrayfields, 'position');

// Security check
$id = (GETPOST('orderid') ?GETPOST('orderid', 'int') : GETPOST('id', 'int'));
if ($user->socid) {
	$socid = $user->socid;
}
$result = restrictedArea($user, 'commande', $id, '');

$error = 0;


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list'; $massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend' && $massaction != 'confirm_createbills') {
	$massaction = '';
}

$parameters = array('socid'=>$socid, 'arrayfields'=>&$arrayfields);


/*
 * View
 */


$form = new Form($db);
$formother = new FormOther($db);
$formfile = new FormFile($db);
$formmargin = null;
if (isModEnabled('margin')) {
	$formmargin = new FormMargin($db);
}
$companystatic = new Societe($db);
$company_url_list = array();
$formcompany = new FormCompany($db);
$projectstatic = new Project($db);

$now = dol_now();

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields

$title = $langs->trans("Orders");
$help_url = "EN:Module_Customers_Orders|FR:Module_Commandes_Clients|ES:Módulo_Pedidos_de_clientes";


// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object, $action); // Note that $action and $object may have been modified by hook

$sqlquery0 = "SELECT rowid FROM ".MAIN_DB_PREFIX."propal as p";
$sqlquery0 .= " WHERE p.fk_statut = ".Propal::STATUS_SIGNED;
$sqlquery0 .= " ORDER BY date_signature ASC";


$ressqlquery0 = $db->query($sqlquery0);
foreach($ressqlquery0 as $q0) {
	$sqlquery = "SELECT rowid, fk_product, rang, qty, fk_propal FROM " . MAIN_DB_PREFIX . "propaldet as pdt";
	$sqlquery.= " WHERE fk_propal = '".$db->escape($q0['rowid'])."'";

	$ressqlquery = $db->query($sqlquery);
	$qty_propal = array();
	$qty_commandes = array();
	//$refLines = array();
	foreach ($ressqlquery as $q) {
		$qty_propal[$q["rang"]] = $q["qty"];
		$sqlquery2 = "SELECT DISTINCT(cdt.rowid), cdt.fk_product, cdt.rang, cdt.qty FROM " . MAIN_DB_PREFIX . "commandedet as cdt INNER JOIN " . MAIN_DB_PREFIX . "commande as c";
		$sqlquery2 .= " WHERE cdt.rang = " . $db->escape($q['rang']) . " AND cdt.fk_commande IN(SELECT ee.fk_target FROM " . MAIN_DB_PREFIX . "element_element as ee WHERE ee.sourcetype='propal' AND ee.targettype='commande' AND ee.fk_source=" . $db->escape($q0['rowid']) . ")";

		//$sqlquerybis = "SELECT ee.fk_target FROM ".MAIN_DB_PREFIX."element_element as ee WHERE ee.sourcetype='propal' AND ee.targettype='commande' AND ee.fk_source=".$db->escape($objectsrc->id);

		$res = $db->query($sqlquery2);
		//$resbis = $db->query($sqlquerybis);
		/*var_dump($res);
		echo "<br /><br />";*/
		if ($res) {
			foreach ($res as $r) {
				/*var_dump($r);
				echo "<br /><br />";*/
				if ($qty_commandes[$r["rang"]]) {
					$qty_commandes[$r["rang"]] = $qty_commandes[$r["rang"]] + $r["qty"];
				} else {
					$qty_commandes[$r["rang"]] = $r["qty"];
				}
			}
		} else {
			$refLines[$q["rowid"]] = $q["qty"];
		}
	}
//var_dump($qty_commandes);
	foreach ($qty_propal as $rang => $qtyp) {
		if ($qtyp != $qty_commandes[$rang]) {
			$sqlquery3 = "SELECT rowid FROM " . MAIN_DB_PREFIX . "propaldet as pdt";
			$sqlquery3 .= " WHERE rang='" . $rang . "' AND fk_propal = '" . $db->escape($q0['rowid']) . "'";

			$ressqlquery3 = $db->query($sqlquery3);
			if ($ressqlquery3) {
				foreach ($ressqlquery3 as $sql3) {
					$refLines[$sql3["rowid"]] = $qtyp - $qty_commandes[$rang];
				}
			}
		}
	}

}

$nbtotalofrecords = count($refLines);


if (($page * $limit) > $nbtotalofrecords) {	// if total resultset is smaller than paging size (filtering), goto and load page 0
	$page = 0;
	$offset = 0;
}


if ($socid > 0) {
	$soc = new Societe($db);
	$soc->fetch($socid);
	$title = $langs->trans('CustomersOrders').' - '.$soc->name;
	if (empty($search_company)) {
		$search_company = $soc->name;
	}
} else {
	$title = $langs->trans('RemainingToBeDelivered');
}

// Output page
// --------------------------------------------------------------------

llxHeader('', $title, $help_url);

$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';

// Add $param from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSearchParam', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
$param .= $hookmanager->resPrint;

// List of mass actions available
$arrayofmassactions = array(
	// TODO add mass action here
);
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

$url = DOL_URL_ROOT.'/commande/card.php?action=create';
if (!empty($socid)) {
	$url .= '&socid='.$socid;
}
$newcardbutton = '';

// Lines of title fields
print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
/*print '<input type="hidden" name="search_status" value="'.$search_status.'">';
print '<input type="hidden" name="socid" value="'.$socid.'">';*/
print '<input type="hidden" name="page_y" value="">';
print '<input type="hidden" name="mode" value="'.$mode.'">';

$num = 1;
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'order', 0, $newcardbutton, '', $limit, 0, 0, 1);

$topicmail = "SendOrderRef";
$modelmail = "order_send";
$objecttmp = new Commande($db);
$trackid = 'ord'.$object->id;


$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$selectedfields = ($mode != 'kanban' ? $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN', '')) : ''); // This also change content of $arrayfields
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

if (GETPOST('autoselectall', 'int')) {
	$selectedfields .= '<script>';
	$selectedfields .= '   $(document).ready(function() {';
	$selectedfields .= '        console.log("Autoclick on checkforselects");';
	$selectedfields .= '   		$("#checkforselects").click();';
	$selectedfields .= '        $("#massaction").val("createbills").change();';
	$selectedfields .= '   });';
	$selectedfields .= '</script>';
}
$moreforfilter = "";
print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";


$totalarray = array(
	'nbfield' => 0,
	'val' => array(
		'c.total_ht' => 0,
		'c.total_tva' => 0,
		'c.total_ttc' => 0,
	),
	'pos' => array(),
);


// Fields title label
// --------------------------------------------------------------------
print '<tr class="liste_titre">';

print_liste_field_titre("Produits", $_SERVER["PHP_SELF"], '', '', $param, '', 0, 0);
$totalarray['nbfield']++;

print_liste_field_titre("Quantité commandée", $_SERVER["PHP_SELF"], '', '', $param, '', 0, 0);
$totalarray['nbfield']++;

print_liste_field_titre("Quantité déjà livrée", $_SERVER["PHP_SELF"], '', '', $param, '', 0, 0);
$totalarray['nbfield']++;

print_liste_field_titre("Quantité restant à livrer", $_SERVER["PHP_SELF"], '', '', $param, '', 0, 0);
$totalarray['nbfield']++;

print_liste_field_titre("Tiers", $_SERVER["PHP_SELF"], '', '', $param, '', 0, 0);
$totalarray['nbfield']++;

print_liste_field_titre("Devis", $_SERVER["PHP_SELF"], '', '', $param, '', 0, 0);
$totalarray['nbfield']++;

print_liste_field_titre("Date signature", $_SERVER["PHP_SELF"], '', '', $param, '', 0, 0);
$totalarray['nbfield']++;


print '</tr>'."\n";

$total = 0;
$subtotal = 0;
$productstat_cache = array();
$productstat_cachevirtual = array();
$getNomUrl_cache = array();

$generic_commande = new Commande($db);
$generic_product = new Product($db);
$userstatic = new User($db);

$with_margin_info = false;
if (isModEnabled('margin') && (
		!empty($arrayfields['total_pa']['checked'])
		|| !empty($arrayfields['total_margin']['checked'])
		|| !empty($arrayfields['total_margin_rate']['checked'])
		|| !empty($arrayfields['total_mark_rate']['checked'])
	)
) {
	$with_margin_info = true;
}

$total_ht = 0;
$total_margin = 0;

// Loop on record
// --------------------------------------------------------------------
$i = 0;
$savnbfield = $totalarray['nbfield'];
$totalarray = array();
$totalarray['nbfield'] = 0;
$imaxinloop = ($limit ? min($num, $limit) : $num);
foreach($refLines as $rowid => $qty){
	//$ol = new OrderLine($db);
	$ol = new PropaleLigne($db);
	$ol->fetch($rowid);

	$pr = new Propal($db);
	$pr->fetch($ol->fk_propal);

	$societe = new Societe($db);
	$societe->fetch($pr->socid);
	//var_dump($ol->product_label);
	print '<tr data-rowid="'.$ol->id.'" class="oddeven">';

	print '<td class="nowraponall tdoverflowmax300">';
	if($ol->fk_product){
		$p = new Product($db);
		if($p->fetch($ol->fk_product)){
			print $p->getNomUrl(1, 0, 0, 0, 0, 1, 1);
			//print "<b>".$p->label."</b>";
		}
		else{
			$s = new Service($db);
			$s->fetch($ol->fk_product);
			print $s->getNomUrl(1, 0, 0, 0, 0, 1, 1);
			//print "<b>".$s->label."</b>";
		}
	}
	else{
		//var_dump($ol);
		print $ol->desc;
	}

	print '</td>';

	if (!$i) {
		$totalarray['nbfield']++;
	}

	print '<td class="nowraponall center">';
	print $ol->qty;
	print '</td>';

	if (!$i) {
		$totalarray['nbfield']++;
	}


	print '<td class="nowraponall center">';
	print $ol->qty-$qty;
	print '</td>';

	if (!$i) {
		$totalarray['nbfield']++;
	}



	print '<td class="nowraponall center">';
	print "<b>".$qty."</b>";
	print '</td>';

	if (!$i) {
		$totalarray['nbfield']++;
	}


	print '<td class="nowraponall tdoverflowmax200">';
	//print dol_escape_htmltag($societe->ref_client);
	print $societe->getNomUrl(1, 0, 0, 0, 0, 1, "_blank");
	//print $societe->name;
	print '</td>';

	if (!$i) {
		$totalarray['nbfield']++;
	}


	print '<td class="nowraponall">';
	print $pr->getNomUrl(1, '', '', 0, 1, 1);
	print '</td>';

	if (!$i) {
		$totalarray['nbfield']++;
	}


	print '<td class="nowraponall">';
	print dol_print_date($db->jdate($pr->dsignature), 'day');
	print '</td>';

	if (!$i) {
		$totalarray['nbfield']++;
	}
	print "</tr>\n";
}
/*while ($i < $imaxinloop) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break; // Should not happen
	}

	$notshippable = 0;
	$warning = 0;
	$text_info = '';
	$text_warning = '';
	$nbprod = 0;

	$companystatic->id = $obj->socid;
	$companystatic->name = $obj->name;
	$companystatic->name_alias = $obj->alias;
	$companystatic->client = $obj->client;
	$companystatic->fournisseur = $obj->fournisseur;
	$companystatic->code_client = $obj->code_client;
	$companystatic->email = $obj->email;
	$companystatic->phone = $obj->phone;
	$companystatic->address = $obj->address;
	$companystatic->zip = $obj->zip;
	$companystatic->town = $obj->town;
	$companystatic->country_code = $obj->country_code;
	if (!isset($getNomUrl_cache[$obj->socid])) {
		$getNomUrl_cache[$obj->socid] = $companystatic->getNomUrl(1, 'customer', 100, 0, 1, empty($arrayfields['s.name_alias']['checked']) ? 0 : 1);
	}

	$generic_commande->id = $obj->rowid;
	$generic_commande->ref = $obj->ref;
	$generic_commande->statut = $obj->fk_statut;
	$generic_commande->billed = $obj->billed;
	$generic_commande->date = $db->jdate($obj->date_commande);
	$generic_commande->delivery_date = $db->jdate($obj->date_delivery);
	$generic_commande->ref_client = $obj->ref_client;
	$generic_commande->total_ht = $obj->total_ht;
	$generic_commande->total_tva = $obj->total_tva;
	$generic_commande->total_ttc = $obj->total_ttc;
	$generic_commande->note_public = $obj->note_public;
	$generic_commande->note_private = $obj->note_private;

	$generic_commande->thirdparty = $companystatic;


	$projectstatic->id = $obj->project_id;
	$projectstatic->ref = $obj->project_ref;
	$projectstatic->title = $obj->project_label;

	$marginInfo = array();
	if ($with_margin_info === true) {
		$generic_commande->fetch_lines();
		$marginInfo = $formmargin->getMarginInfosArray($generic_commande);
		$total_ht += $obj->total_ht;
		$total_margin += $marginInfo['total_margin'];
	}

	if ($mode == 'kanban') {
		if ($i == 0) {
			print '<tr class="trkanban"><td colspan="'.$savnbfield.'">';
			print '<div class="box-flex-container kanban">';
		}

		// Output Kanban
		$selected = -1;
		if ($massactionbutton || $massaction) { // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
			$selected = 0;
			if (in_array($object->id, $arrayofselected)) {
				$selected = 1;
			}
		}
		print $generic_commande->getKanbanView('', array('selected' => $selected));
		if ($i == ($imaxinloop - 1)) {
			print '</div>';
			print '</td></tr>';
		}
	} else {
		// Show line of result
		$j = 0;
		print '<tr data-rowid="'.$object->id.'" class="oddeven">';

		// Action column
		if (getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
			print '<td class="nowrap center">';
			if ($massactionbutton || $massaction) {   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
				$selected = 0;
				if (in_array($obj->rowid, $arrayofselected)) {
					$selected = 1;
				}
				print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected ? ' checked="checked"' : '').'>';
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Ref
		if (!empty($arrayfields['c.ref']['checked'])) {
			print '<td class="nowraponall">';
			print $generic_commande->getNomUrl(1, ($search_status != 2 ? 0 : $obj->fk_statut), 0, 0, 0, 1, 1);

			$filename = dol_sanitizeFileName($obj->ref);
			$filedir = $conf->commande->multidir_output[$conf->entity].'/'.dol_sanitizeFileName($obj->ref);
			$urlsource = $_SERVER['PHP_SELF'].'?id='.$obj->rowid;
			print $formfile->getDocumentsLink($generic_commande->element, $filename, $filedir);

			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Ref customer
		if (!empty($arrayfields['c.ref_client']['checked'])) {
			print '<td class="nowrap tdoverflowmax150" title="'.dol_escape_htmltag($obj->ref_client).'">';
			print dol_escape_htmltag($obj->ref_client);
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Project ref
		if (!empty($arrayfields['p.ref']['checked'])) {
			print '<td class="nowrap">';
			if ($obj->project_id > 0) {
				print $projectstatic->getNomUrl(1);
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Project label
		if (!empty($arrayfields['p.title']['checked'])) {
			print '<td class="nowrap">';
			if ($obj->project_id > 0) {
				print $projectstatic->title;
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Third party
		if (!empty($arrayfields['s.nom']['checked'])) {
			print '<td class="tdoverflowmax150">';
			if (getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')) {
				print $companystatic->getNomUrl(1, 'customer', 100, 0, 1, empty($arrayfields['s.name_alias']['checked']) ? 0 : 1);
			} else {
				print $getNomUrl_cache[$obj->socid];
			}

			// If module invoices enabled and user with invoice creation permissions
			if (isModEnabled('facture') && !empty($conf->global->ORDER_BILLING_ALL_CUSTOMER)) {
				if ($user->hasRight('facture', 'creer')) {
					if (($obj->fk_statut > 0 && $obj->fk_statut < 3) || ($obj->fk_statut == 3 && $obj->billed == 0)) {
						print '&nbsp;<a href="'.DOL_URL_ROOT.'/commande/list.php?socid='.$companystatic->id.'&search_billed=0&autoselectall=1">';
						print img_picto($langs->trans("CreateInvoiceForThisCustomer").' : '.$companystatic->name, 'object_bill', 'hideonsmartphone').'</a>';
					}
				}
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Alias name
		if (!empty($arrayfields['s.name_alias']['checked'])) {
			print '<td class="nocellnopadd">';
			print $obj->alias;
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Parent company
		if (!empty($arrayfields['s2.nom']['checked'])) {
			print '<td class="tdoverflowmax200">';
			if ($obj->fk_parent > 0) {
				if (!isset($company_url_list[$obj->fk_parent])) {
					$companyparent = new Societe($db);
					$res = $companyparent->fetch($obj->fk_parent);
					if ($res > 0) {
						$company_url_list[$obj->fk_parent] = $companyparent->getNomUrl(1);
					}
				}
				if (isset($company_url_list[$obj->fk_parent])) {
					print $company_url_list[$obj->fk_parent];
				}
			}
			print "</td>";
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Town
		if (!empty($arrayfields['s.town']['checked'])) {
			print '<td class="nocellnopadd">';
			print $obj->town;
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Zip
		if (!empty($arrayfields['s.zip']['checked'])) {
			print '<td class="nocellnopadd">';
			print $obj->zip;
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// State
		if (!empty($arrayfields['state.nom']['checked'])) {
			print "<td>".$obj->state_name."</td>\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Country
		if (!empty($arrayfields['country.code_iso']['checked'])) {
			print '<td class="center">';
			$tmparray = getCountry($obj->fk_pays, 'all');
			print $tmparray['label'];
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Type ent
		if (!empty($arrayfields['typent.code']['checked'])) {
			print '<td class="center">';
			if (empty($typenArray)) {
				$typenArray = $formcompany->typent_array(1);
			}
			print $typenArray[$obj->typent_code];
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Order date
		if (!empty($arrayfields['c.date_commande']['checked'])) {
			print '<td class="center">';
			print dol_print_date($db->jdate($obj->date_commande), 'day');
			// Warning late icon and note
			if ($generic_commande->hasDelay()) {
				print img_picto($langs->trans("Late").' : '.$generic_commande->showDelay(), "warning");
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Plannned date of delivery
		if (!empty($arrayfields['c.date_delivery']['checked'])) {
			print '<td class="center">';
			print dol_print_date($db->jdate($obj->date_delivery), 'dayhour');
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Shipping Method
		if (!empty($arrayfields['c.fk_shipping_method']['checked'])) {
			print '<td>';
			$form->formSelectShippingMethod('', $obj->fk_shipping_method, 'none', 1);
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Payment terms
		if (!empty($arrayfields['c.fk_cond_reglement']['checked'])) {
			print '<td>';
			$form->form_conditions_reglement($_SERVER['PHP_SELF'], $obj->fk_cond_reglement, 'none', 0, '', 1, $obj->deposit_percent);
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Payment mode
		if (!empty($arrayfields['c.fk_mode_reglement']['checked'])) {
			print '<td>';
			$form->form_modes_reglement($_SERVER['PHP_SELF'], $obj->fk_mode_reglement, 'none', '', -1);
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Channel
		if (!empty($arrayfields['c.fk_input_reason']['checked'])) {
			print '<td>';
			$form->formInputReason($_SERVER['PHP_SELF'], $obj->fk_input_reason, 'none', '');
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Amount HT/net
		if (!empty($arrayfields['c.total_ht']['checked'])) {
			print '<td class="nowrap right"><span class="amount">'.price($obj->total_ht)."</span></td>\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
			if (!$i) {
				$totalarray['pos'][$totalarray['nbfield']] = 'c.total_ht';
			}
			if (isset($totalarray['val']['c.total_ht'])) {
				$totalarray['val']['c.total_ht'] += $obj->total_ht;
			} else {
				$totalarray['val']['c.total_ht'] = $obj->total_ht;
			}
		}

		// Amount VAT
		if (!empty($arrayfields['c.total_vat']['checked'])) {
			print '<td class="nowrap right"><span class="amount">'.price($obj->total_tva)."</span></td>\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
			if (!$i) {
				$totalarray['pos'][$totalarray['nbfield']] = 'c.total_tva';
			}
			if (isset($totalarray['val']['c.total_tva'])) {
				$totalarray['val']['c.total_tva'] += $obj->total_tva;
			} else {
				$totalarray['val']['c.total_tva'] = $obj->total_tva;
			}
		}

		// Amount TTC / gross
		if (!empty($arrayfields['c.total_ttc']['checked'])) {
			print '<td class="nowrap right"><span class="amount">'.price($obj->total_ttc)."</span></td>\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
			if (!$i) {
				$totalarray['pos'][$totalarray['nbfield']] = 'c.total_ttc';
			}
			if (isset($totalarray['val']['c.total_ttc'])) {
				$totalarray['val']['c.total_ttc'] += $obj->total_ttc;
			} else {
				$totalarray['val']['c.total_ttc'] = $obj->total_ttc;
			}
		}

		// Currency
		if (!empty($arrayfields['c.multicurrency_code']['checked'])) {
			print '<td class="nowrap">'.$obj->multicurrency_code.' - '.$langs->trans('Currency'.$obj->multicurrency_code)."</td>\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Currency rate
		if (!empty($arrayfields['c.multicurrency_tx']['checked'])) {
			print '<td class="nowrap">';
			$form->form_multicurrency_rate($_SERVER['PHP_SELF'].'?id='.$obj->rowid, $obj->multicurrency_tx, 'none', $obj->multicurrency_code);
			print "</td>\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Amount HT/net in foreign currency
		if (!empty($arrayfields['c.multicurrency_total_ht']['checked'])) {
			print '<td class="right nowrap"><span class="amount">'.price($obj->multicurrency_total_ht)."</span></td>\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}
		// Amount VAT in foreign currency
		if (!empty($arrayfields['c.multicurrency_total_vat']['checked'])) {
			print '<td class="right nowrap"><span class="amount">'.price($obj->multicurrency_total_vat)."</span></td>\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}
		// Amount TTC / gross in foreign currency
		if (!empty($arrayfields['c.multicurrency_total_ttc']['checked'])) {
			print '<td class="right nowrap"><span class="amount">'.price($obj->multicurrency_total_ttc)."</span></td>\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		$userstatic->id = $obj->fk_user_author;
		$userstatic->login = $obj->login;
		$userstatic->lastname = $obj->lastname;
		$userstatic->firstname = $obj->firstname;
		$userstatic->email = $obj->user_email;
		$userstatic->statut = $obj->user_statut;
		$userstatic->entity = $obj->entity;
		$userstatic->photo = $obj->photo;
		$userstatic->office_phone = $obj->office_phone;
		$userstatic->office_fax = $obj->office_fax;
		$userstatic->user_mobile = $obj->user_mobile;
		$userstatic->job = $obj->job;
		$userstatic->gender = $obj->gender;

		// Author
		if (!empty($arrayfields['u.login']['checked'])) {
			print '<td class="tdoverflowmax150">';
			if ($userstatic->id) {
				print $userstatic->getNomUrl(-1);
			} else {
				print '&nbsp;';
			}
			print "</td>\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Sales representatives
		if (!empty($arrayfields['sale_representative']['checked'])) {
			print '<td>';
			if ($obj->socid > 0) {
				$listsalesrepresentatives = $companystatic->getSalesRepresentatives($user);
				if ($listsalesrepresentatives < 0) {
					dol_print_error($db);
				}
				$nbofsalesrepresentative = count($listsalesrepresentatives);
				if ($nbofsalesrepresentative > 6) {
					// We print only number
					print $nbofsalesrepresentative;
				} elseif ($nbofsalesrepresentative > 0) {
					$j = 0;
					foreach ($listsalesrepresentatives as $val) {
						$userstatic->id = $val['id'];
						$userstatic->lastname = $val['lastname'];
						$userstatic->firstname = $val['firstname'];
						$userstatic->email = $val['email'];
						$userstatic->statut = $val['statut'];
						$userstatic->entity = $val['entity'];
						$userstatic->photo = $val['photo'];
						$userstatic->login = $val['login'];
						$userstatic->office_phone = $val['office_phone'];
						$userstatic->office_fax = $val['office_fax'];
						$userstatic->user_mobile = $val['user_mobile'];
						$userstatic->job = $val['job'];
						$userstatic->gender = $val['gender'];
						//print '<div class="float">':
						print ($nbofsalesrepresentative < 2) ? $userstatic->getNomUrl(-1, '', 0, 0, 12) : $userstatic->getNomUrl(-2);
						$j++;
						if ($j < $nbofsalesrepresentative) {
							print ' ';
						}
						//print '</div>';
					}
				}
				//else print $langs->trans("NoSalesRepresentativeAffected");
			} else {
				print '&nbsp;';
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Total buying or cost price
		if (!empty($arrayfields['total_pa']['checked'])) {
			print '<td class="right nowrap">'.price($marginInfo['pa_total']).'</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Total margin
		if (!empty($arrayfields['total_margin']['checked'])) {
			print '<td class="right nowrap">'.price($marginInfo['total_margin']).'</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
			if (!$i) {
				$totalarray['pos'][$totalarray['nbfield']] = 'total_margin';
			}
			$totalarray['val']['total_margin'] += $marginInfo['total_margin'];
		}

		// Total margin rate
		if (!empty($arrayfields['total_margin_rate']['checked'])) {
			print '<td class="right nowrap">'.(($marginInfo['total_margin_rate'] == '') ? '' : price($marginInfo['total_margin_rate'], null, null, null, null, 2).'%').'</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Total mark rate
		if (!empty($arrayfields['total_mark_rate']['checked'])) {
			print '<td class="right nowrap">'.(($marginInfo['total_mark_rate'] == '') ? '' : price($marginInfo['total_mark_rate'], null, null, null, null, 2).'%').'</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
			if (!$i) {
				$totalarray['pos'][$totalarray['nbfield']] = 'total_mark_rate';
			}
			if ($i >= $imaxinloop - 1) {
				if (!empty($total_ht)) {
					$totalarray['val']['total_mark_rate'] = price2num($total_margin * 100 / $total_ht, 'MT');
				} else {
					$totalarray['val']['total_mark_rate'] = '';
				}
			}
		}

		// Extra fields
		include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';
		// Fields from hook
		$parameters = array('arrayfields'=>$arrayfields, 'obj'=>$obj, 'i'=>$i, 'totalarray'=>&$totalarray);
		$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		print $hookmanager->resPrint;

		// Date creation
		if (!empty($arrayfields['c.datec']['checked'])) {
			print '<td align="center" class="nowrap">';
			print dol_print_date($db->jdate($obj->date_creation), 'dayhour', 'tzuser');
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Date modification
		if (!empty($arrayfields['c.tms']['checked'])) {
			print '<td align="center" class="nowrap">';
			print dol_print_date($db->jdate($obj->date_update), 'dayhour', 'tzuser');
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Date cloture
		if (!empty($arrayfields['c.date_cloture']['checked'])) {
			print '<td align="center" class="nowrap">';
			print dol_print_date($db->jdate($obj->date_cloture), 'dayhour', 'tzuser');
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Note public
		if (!empty($arrayfields['c.note_public']['checked'])) {
			print '<td class="center">';
			print dol_string_nohtmltag($obj->note_public);
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Note private
		if (!empty($arrayfields['c.note_private']['checked'])) {
			print '<td class="center">';
			print dol_string_nohtmltag($obj->note_private);
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Show shippable Icon (this creates subloops, so may be slow)
		if (!empty($arrayfields['shippable']['checked'])) {
			print '<td class="center">';
			if (!empty($show_shippable_command) && isModEnabled('stock')) {
				if (($obj->fk_statut > $generic_commande::STATUS_DRAFT) && ($obj->fk_statut < $generic_commande::STATUS_CLOSED)) {
					$generic_commande->getLinesArray(); 	// Load array ->lines
					$generic_commande->loadExpeditions();	// Load array ->expeditions

					$numlines = count($generic_commande->lines); // Loop on each line of order
					for ($lig = 0; $lig < $numlines; $lig++) {
						if (isset($generic_commande->expeditions[$generic_commande->lines[$lig]->id])) {
							$reliquat =  $generic_commande->lines[$lig]->qty - $generic_commande->expeditions[$generic_commande->lines[$lig]->id];
						} else {
							$reliquat = $generic_commande->lines[$lig]->qty;
						}
						if ($generic_commande->lines[$lig]->product_type == 0 && $generic_commande->lines[$lig]->fk_product > 0) {  // If line is a product and not a service
							$nbprod++; // order contains real products
							$generic_product->id = $generic_commande->lines[$lig]->fk_product;

							// Get local and virtual stock and store it into cache
							if (empty($productstat_cache[$generic_commande->lines[$lig]->fk_product])) {
								$generic_product->load_stock('nobatch,warehouseopen'); // ->load_virtual_stock() is already included into load_stock()
								$productstat_cache[$generic_commande->lines[$lig]->fk_product]['stock_reel'] = $generic_product->stock_reel;
								$productstat_cachevirtual[$generic_commande->lines[$lig]->fk_product]['stock_reel'] = $generic_product->stock_theorique;
							} else {
								$generic_product->stock_reel = $productstat_cache[$generic_commande->lines[$lig]->fk_product]['stock_reel'];
								$generic_product->stock_theorique = $productstat_cachevirtual[$generic_commande->lines[$lig]->fk_product]['stock_reel'] = $generic_product->stock_theorique;
							}

							if ($reliquat > $generic_product->stock_reel) {
								$notshippable++;
							}
							if (empty($conf->global->SHIPPABLE_ORDER_ICON_IN_LIST)) {  // Default code. Default should be this case.
								$text_info .= $reliquat.' x '.$generic_commande->lines[$lig]->product_ref.'&nbsp;'.dol_trunc($generic_commande->lines[$lig]->product_label, 20);
								$text_info .= ' - '.$langs->trans("Stock").': <span class="'.($generic_product->stock_reel > 0 ? 'ok' : 'error').'">'.$generic_product->stock_reel.'</span>';
								$text_info .= ' - '.$langs->trans("VirtualStock").': <span class="'.($generic_product->stock_theorique > 0 ? 'ok' : 'error').'">'.$generic_product->stock_theorique.'</span>';
								$text_info .= ($reliquat != $generic_commande->lines[$lig]->qty ? ' <span class="opacitymedium">('.$langs->trans("QtyInOtherShipments").' '.($generic_commande->lines[$lig]->qty - $reliquat).')</span>' : '');
								$text_info .= '<br>';
							} else {  // BUGGED CODE.
								// DOES NOT TAKE INTO ACCOUNT MANUFACTURING. THIS CODE SHOULD BE USELESS. PREVIOUS CODE SEEMS COMPLETE.
								// COUNT STOCK WHEN WE SHOULD ALREADY HAVE VALUE
								// Detailed virtual stock, looks bugged, uncomplete and need heavy load.
								// stock order and stock order_supplier
								$stock_order = 0;
								$stock_order_supplier = 0;
								if (!empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT) || !empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT_CLOSE)) {    // What about other options ?
									if (isModEnabled('commande')) {
										if (empty($productstat_cache[$generic_commande->lines[$lig]->fk_product]['stats_order_customer'])) {
											$generic_product->load_stats_commande(0, '1,2');
											$productstat_cache[$generic_commande->lines[$lig]->fk_product]['stats_order_customer'] = $generic_product->stats_commande['qty'];
										} else {
											$generic_product->stats_commande['qty'] = $productstat_cache[$generic_commande->lines[$lig]->fk_product]['stats_order_customer'];
										}
										$stock_order = $generic_product->stats_commande['qty'];
									}
									if (isModEnabled("supplier_order")) {
										if (empty($productstat_cache[$generic_commande->lines[$lig]->fk_product]['stats_order_supplier'])) {
											$generic_product->load_stats_commande_fournisseur(0, '3');
											$productstat_cache[$generic_commande->lines[$lig]->fk_product]['stats_order_supplier'] = $generic_product->stats_commande_fournisseur['qty'];
										} else {
											$generic_product->stats_commande_fournisseur['qty'] = $productstat_cache[$generic_commande->lines[$lig]->fk_product]['stats_order_supplier'];
										}
										$stock_order_supplier = $generic_product->stats_commande_fournisseur['qty'];
									}
								}
								$text_info .= $reliquat.' x '.$generic_commande->lines[$lig]->ref.'&nbsp;'.dol_trunc($generic_commande->lines[$lig]->product_label, 20);
								$text_stock_reel = $generic_product->stock_reel.'/'.$stock_order;
								if ($stock_order > $generic_product->stock_reel && !($generic_product->stock_reel < $generic_commande->lines[$lig]->qty)) {
									$warning++;
									$text_warning .= '<span class="warning">'.$langs->trans('Available').'&nbsp;:&nbsp;'.$text_stock_reel.'</span>';
								}
								if ($reliquat > $generic_product->stock_reel) {
									$text_info .= '<span class="warning">'.$langs->trans('Available').'&nbsp;:&nbsp;'.$text_stock_reel.'</span>';
								} else {
									$text_info .= '<span class="ok">'.$langs->trans('Available').'&nbsp;:&nbsp;'.$text_stock_reel.'</span>';
								}
								if (isModEnabled("supplier_order")) {
									$text_info .= '&nbsp;'.$langs->trans('SupplierOrder').'&nbsp;:&nbsp;'.$stock_order_supplier;
								}
								$text_info .= ($reliquat != $generic_commande->lines[$lig]->qty ? ' <span class="opacitymedium">('.$langs->trans("QtyInOtherShipments").' '.($generic_commande->lines[$lig]->qty - $reliquat).')</span>' : '');
								$text_info .= '<br>';
							}
						}
					}
					if ($notshippable == 0) {
						$text_icon = img_picto('', 'dolly', '', false, 0, 0, '', 'green paddingleft');
						$text_info = $text_icon.' '.$langs->trans('Shippable').'<br>'.$text_info;
					} else {
						$text_icon = img_picto('', 'dolly', '', false, 0, 0, '', 'error paddingleft');
						$text_info = $text_icon.' '.$langs->trans('NonShippable').'<br>'.$text_info;
					}
				}

				if ($nbprod) {
					print $form->textwithtooltip('', $text_info, 2, 1, $text_icon, '', 2);
				}
				if ($warning) {     // Always false in default mode
					print $form->textwithtooltip('', $langs->trans('NotEnoughForAllOrders').'<br>'.$text_warning, 2, 1, img_picto('', 'error'), '', 2);
				}
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Billed
		if (!empty($arrayfields['c.facture']['checked'])) {
			print '<td class="center">'.yn($obj->billed).'</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Import key
		if (!empty($arrayfields['c.import_key']['checked'])) {
			print '<td class="nowrap center">'.dol_escape_htmltag($obj->import_key).'</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Status
		if (!empty($arrayfields['c.fk_statut']['checked'])) {
			print '<td class="nowrap right">'.$generic_commande->LibStatut($obj->fk_statut, $obj->billed, 5, 1).'</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Action column
		if (!getDolGlobalString('MAIN_CHECKBOX_LEFT_COLUMN')) {
			print '<td class="nowrap center">';
			if ($massactionbutton || $massaction) {   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
				$selected = 0;
				if (in_array($obj->rowid, $arrayofselected)) {
					$selected = 1;
				}
				print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected ? ' checked="checked"' : '').'>';
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		print "</tr>\n";

		$total += $obj->total_ht;
		$subtotal += $obj->total_ht;
	}
	$i++;
}

// Show total line
include DOL_DOCUMENT_ROOT.'/core/tpl/list_print_total.tpl.php';*/

// If no record found
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) {
		if (!empty($val['checked'])) {
			$colspan++;
		}
	}
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

//$parameters = array('arrayfields'=>$arrayfields, 'sql'=>$sql);
$parameters = array('arrayfields'=>$arrayfields);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print '</table>'."\n";
print '</div>'."\n";

print '</form>'."\n";


// End of page
llxFooter();
$db->close();
