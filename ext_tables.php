<?php
if (!defined ('TYPO3_MODE')) {
	die('Access denied.');
}

// Retrieve the path to the extension's directory.
$extRelPath = t3lib_extMgm::extRelPath($_EXTKEY);
$extPath = t3lib_extMgm::extPath($_EXTKEY);
$extIconRelPath = $extRelPath . 'icons/';

if (TYPO3_MODE=='BE') {
	t3lib_extMgm::addModule('web', 'txseminarsM1', '', $extPath.'mod1/');
	t3lib_extMgm::addModule('web', 'txseminarsM2', '', $extPath.'mod2/');
}

t3lib_div::loadTCA('fe_users');
t3lib_div::loadTCA('tt_content');

$tempColumns = Array (
	'tx_seminars_phone_mobile' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:seminars/locallang_db.php:fe_users.tx_seminars_phone_mobile',
		'config' => Array (
			'type' => 'input',
			'size' => '30',
			'eval' => 'trim',
		)
	),
	'tx_seminars_matriculation_number' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:seminars/locallang_db.php:fe_users.tx_seminars_matriculation_number',
		'config' => Array (
			'type' => 'input',
			'size' => '10',
			'max' => '10',
			'eval' => 'int',
			'checkbox' => '0',
			'range' => Array (
				'upper' => '999999999',
				'lower' => '1'
			),
			'default' => 0
		)
	),
	'tx_seminars_planned_degree' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:seminars/locallang_db.php:fe_users.tx_seminars_planned_degree',
		'config' => Array (
			'type' => 'input',
			'size' => '30',
			'eval' => 'trim',
		)
	),
	'tx_seminars_semester' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:seminars/locallang_db.php:fe_users.tx_seminars_semester',
		'config' => Array (
			'type' => 'input',
			'size' => '3',
			'max' => '3',
			'eval' => 'int',
			'checkbox' => '0',
			'range' => Array (
				'upper' => '99',
				'lower' => '0'
			),
			'default' => 0
		)
	),
	'tx_seminars_subject' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:seminars/locallang_db.php:fe_users.tx_seminars_subject',
		'config' => Array (
			'type' => 'input',
			'size' => '30',
			'eval' => 'trim',
		)
	),
);



$TCA['tx_seminars_seminars'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:seminars/locallang_db.php:tx_seminars_seminars',
		'label' => 'title',
		'type' => 'object_type',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'sortby' => 'sorting',
		'delete' => 'deleted',
		'enablecolumns' => Array (
			'disabled' => 'hidden',
			'starttime' => 'starttime',
			'endtime' => 'endtime',
		),
		'iconfile' => $extIconRelPath.'icon_tx_seminars_seminars_complete.gif',
		'typeicon_column' => 'object_type',
		'typeicons' => array(
			'0' => $extIconRelPath.'icon_tx_seminars_seminars_complete.gif',
			'1' => $extIconRelPath.'icon_tx_seminars_seminars_topic.gif',
			'2' => $extIconRelPath.'icon_tx_seminars_seminars_date.gif'
		),
		'dynamicConfigFile' => $extPath.'tca.php',
		'dividers2tabs' => true,
		'hideAtCopy' => true,
	),
);

$TCA['tx_seminars_speakers'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:seminars/locallang_db.php:tx_seminars_speakers',
		'label' => 'title',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY title',
		'delete' => 'deleted',
		'dynamicConfigFile' => $extPath.'tca.php',
		'iconfile' => $extIconRelPath.'icon_tx_seminars_speakers.gif',
	),
);

$TCA['tx_seminars_attendances'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:seminars/locallang_db.php:tx_seminars_attendances',
		'label' => 'title',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY title',
		'delete' => 'deleted',
		'dynamicConfigFile' => $extPath.'tca.php',
		'iconfile' => $extIconRelPath.'icon_tx_seminars_attendances.gif',
	),
);

$TCA['tx_seminars_sites'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:seminars/locallang_db.php:tx_seminars_sites',
		'label' => 'title',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY title',
		'delete' => 'deleted',
		'dynamicConfigFile' => $extPath.'tca.php',
		'iconfile' => $extIconRelPath.'icon_tx_seminars_sites.gif',
	),
);

$TCA['tx_seminars_organizers'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:seminars/locallang_db.php:tx_seminars_organizers',
		'label' => 'title',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY title',
		'delete' => 'deleted',
		'dynamicConfigFile' => $extPath.'tca.php',
		'iconfile' => $extIconRelPath.'icon_tx_seminars_organizers.gif',
	),
);

$TCA['tx_seminars_payment_methods'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:seminars/locallang_db.php:tx_seminars_payment_methods',
		'label' => 'title',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY title',
		'delete' => 'deleted',
		'dynamicConfigFile' => $extPath.'tca.php',
		'iconfile' => $extIconRelPath.'icon_tx_seminars_payment_methods.gif',
	),
);

$TCA['tx_seminars_event_types'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:seminars/locallang_db.php:tx_seminars_event_types',
		'label' => 'title',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY title',
		'delete' => 'deleted',
		'dynamicConfigFile' => $extPath.'tca.php',
		'iconfile' => $extIconRelPath.'icon_tx_seminars_event_types.gif',
	),
);

$TCA['tx_seminars_checkboxes'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:seminars/locallang_db.php:tx_seminars_checkboxes',
		'label' => 'title',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY title',
		'delete' => 'deleted',
		'dynamicConfigFile' => $extPath.'tca.php',
		'iconfile' => $extIconRelPath.'icon_tx_seminars_checkboxes.gif',
	),
);

$TCA['tx_seminars_lodgings'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:seminars/locallang_db.php:tx_seminars_lodgings',
		'label' => 'title',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY title',
		'delete' => 'deleted',
		'dynamicConfigFile' => $extPath.'tca.php',
		'iconfile' => $extIconRelPath.'icon_tx_seminars_lodgings.gif',
	),
);

t3lib_extMgm::addToInsertRecords('tx_seminars_seminars');
t3lib_extMgm::addToInsertRecords('tx_seminars_speakers');

t3lib_extMgm::addTCAcolumns('fe_users',$tempColumns,1);
t3lib_extMgm::addToAllTCAtypes('fe_users','tx_seminars_phone_mobile;;;;1-1-1, tx_seminars_matriculation_number, tx_seminars_planned_degree, tx_seminars_semester, tx_seminars_subject');

t3lib_extMgm::allowTableOnStandardPages('tx_seminars_attendances');
t3lib_extMgm::allowTableOnStandardPages('tx_seminars_organizers');
t3lib_extMgm::allowTableOnStandardPages('tx_seminars_payment_methods');
t3lib_extMgm::allowTableOnStandardPages('tx_seminars_event_types');
t3lib_extMgm::allowTableOnStandardPages('tx_seminars_seminars');
t3lib_extMgm::allowTableOnStandardPages('tx_seminars_sites');
t3lib_extMgm::allowTableOnStandardPages('tx_seminars_speakers');
t3lib_extMgm::allowTableOnStandardPages('tx_seminars_checkboxes');
t3lib_extMgm::allowTableOnStandardPages('tx_seminars_lodgings');

$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1']='layout,select_key,pages,recursive';

$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_pi1'] = 'pi_flexform';

t3lib_extMgm::addPiFlexFormValue($_EXTKEY.'_pi1', 'FILE:EXT:seminars/flexform_pi1_ds.xml');

t3lib_extMgm::addStaticFile($_EXTKEY, 'static/', 'Seminars');

t3lib_extMgm::addPlugin(Array('LLL:EXT:seminars/locallang_db.php:tt_content.list_type_pi1', $_EXTKEY.'_pi1'), 'list_type');

?>
