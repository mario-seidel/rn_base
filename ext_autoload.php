<?php
// autoloading seems to be pretty useless for static classes... :-(
$versionParts = explode('.', TYPO3_version);
$extensionPath = (intval($versionParts[0]) >= 6) ?
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('rn_base') :
	tx_rnbase_util_Extensions::extPath('rn_base');

return array(
		'tx_rnbase' => $extensionPath . 'class.tx_rnbase.php',
);
