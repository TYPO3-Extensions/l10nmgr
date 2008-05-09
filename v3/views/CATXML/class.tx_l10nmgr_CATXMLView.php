<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006 Kasper Skårhøj <kasperYYYY@typo3.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(t3lib_extMgm::extPath('l10nmgr').'models/tools/class.tx_l10nmgr_xmltools.php');
require_once(t3lib_extMgm::extPath('l10nmgr').'models/tools/class.tx_l10nmgr_utf8tools.php');
require_once(t3lib_extMgm::extPath('l10nmgr').'views/class.tx_l10nmgr_abstractExportView.php');

/**
 * CATXMLView: Renders the XML for the use for translation agencies
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @author	Daniel Poetzinger <development@aoemedia.de>
 * @author	Daniel Zielinski <d.zielinski@L10Ntech.de>
 * @author	Fabian Seltmann <fs@marketing-factory.de>
 * @author	Andreas Otto <andreas.otto@dkd.de>
 * @package TYPO3
 * @subpackage tx_l10nmgr
 */
class tx_l10nmgr_CATXMLView extends tx_l10nmgr_abstractExportView{

	/**
	 * @var	array		$internalMessges		Part of XML with fail logging information content elements
	 */
	var $internalMessges = array();

	/**
	 * @var	integer		$forcedSourceLanguage		Overwrite the default language uid with the desired language to export
	 */
	var $forcedSourceLanguage = false;
	
	var $exportType = '1';

	function tx_l10nmgr_CATXMLView($l10ncfgObj, $sysLang) {
		parent::__construct($l10ncfgObj, $sysLang);			
	}


	/**
	 * Render the simple XML export
	 *
	 * @param	array		Translation data for configuration
	 * @return	string		HTML content
	 */
	function render() {
		global $LANG;

		$sysLang=$this->sysLang;
		$accumObj=$this->l10ncfgObj->getL10nAccumulatedInformationsObjectForLanguage($sysLang);
		if ($this->forcedSourceLanguage) {
			$accumObj->setForcedPreviewLanguage($this->forcedSourceLanguage);
		}
		$accum=$accumObj->getInfoArray();
		$errorMessage=array();	
		$xmlTool= t3lib_div::makeInstance("tx_l10nmgr_xmltools");
		$output = array();

			// Traverse the structure and generate XML output:
		foreach($accum as $pId => $page) {
			$output[] =  "\t" . '<pageGrp id="'.$pId.'">'."\n";
			foreach($accum[$pId]['items'] as $table => $elements) {
				foreach($elements as $elementUid => $data) {
					if (!empty($data['ISOcode'])) {
						$targetIso=$data['ISOcode'];
					}

					if (is_array($data['fields'])) {
						$fieldsForRecord = array();
						foreach($data['fields'] as $key => $tData) {
							if (is_array($tData)) {
								list(,$uidString,$fieldName) = explode(':',$key); 
								list($uidValue) = explode('/',$uidString);

								$noChangeFlag = !strcmp(trim($tData['diffDefaultValue']),trim($tData['defaultValue']));

								if (!$this->modeOnlyChanged || !$noChangeFlag)	{

									// @DP: Why this check?
									if ( ($this->forcedSourceLanguage && isset($tData['previewLanguageValues'][$this->forcedSourceLanguage])) || $this->forcedSourceLanguage === false) {

										if ($this->forcedSourceLanguage) {
											$dataForTranslation = $tData['previewLanguageValues'][$this->forcedSourceLanguage];
										}
										else {
											$dataForTranslation=$tData['defaultValue'];
										}
										// Substitutions for XML conformity here
										$_isTranformedXML=FALSE;
									// Following checks are not enough! Fields that could be transformed to be XML conform are not transformed! textpic fields are not isRTE=1!!! No idea why...
									print_r($tData); 
										if ($tData['fieldType']=='text' &&  $tData['isRTE']) { 
											$dataForTranslationTranformed=$xmlTool->RTE2XML($dataForTranslation);
											if ($dataForTranslationTranformed!==false) {
												$_isTranformedXML=TRUE;
												$dataForTranslation=$dataForTranslationTranformed;
											}
										}
										if ($_isTranformedXML) {
											$output[]= "\t\t".'<data table="'.$table.'" elementUid="'.$elementUid.'" key="'.$key.'" transformations="1">'.$dataForTranslation.'</data>'."\n";
										}
										else {
											$dataForTranslation=tx_l10nmgr_utf8tools::utf8_bad_strip($dataForTranslation);
											if ($xmlTool->isValidXMLString($dataForTranslation)) {
												$output[]= "\t\t".'<data table="'.$table.'" elementUid="'.$elementUid.'" key="'.$key.'"><![CDATA['.$dataForTranslation.']]></data>'."\n";
											}
											else {
												$this->setInternalMessage($LANG->getLL('export.process.error.invalid.message'), $elementUid.'/'.$table.'/'.$key);
											}
										}

									} else {
										$this->setInternalMessage($LANG->getLL('export.process.error.empty.message'), $elementUid.'/'.$table.'/'.$key);
									}
								}
							}
						}
					}
				}
			}
			$output[] = "\t" . '</pageGrp>'."\r";
		}

			// get ISO2L code for source language
		if ($this->l10ncfgObj->getData('sourceLangStaticId') && t3lib_extMgm::isLoaded('static_info_tables'))        {
			$sourceIso2L = '';
			$staticLangArr = t3lib_BEfunc::getRecord('static_languages',$this->l10ncfgObj->getData('sourceLangStaticId'),'lg_iso_2');
			$sourceIso2L = ' sourceLang="'.$staticLangArr['lg_iso_2'].'"';
		}

		$XML  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$XML .= '<!DOCTYPE TYPO3L10N [ <!ENTITY nbsp " "> ]>'."\n".'<TYPO3L10N l10ncfg="' . $this->l10ncfgObj->getData('uid') . '" sysLang="' . $sysLang . '"' . $sourceIso2L . $targetIso2L . ' baseURL="'.t3lib_div::getIndpEnv("TYPO3_SITE_URL").'">' . "\n";
		$XML .= "\t"   . '<head>'."\n";
		$XML .= "\t\t" . '<t3_l10ncfg>'.$this->l10ncfgObj->getData('uid').'</t3_l10ncfg>'."\n";
		$XML .= "\t\t" . '<t3_sysLang>'.$sysLang.'</t3_sysLang>'."\n";
		$XML .= "\t\t" . '<t3_sourceLang>'.$staticLangArr['lg_iso_2'].'</t3_sourceLang>'."\n";
		$XML .= "\t\t" . '<t3_targetLang>'.$targetIso.'</t3_targetLang>'."\n";
		$XML .= "\t\t" . '<t3_baseURL>'.t3lib_div::getIndpEnv("TYPO3_SITE_URL").'</t3_baseURL>'."\n";
		$XML .= "\t\t" . '<t3_workspaceId>'.$GLOBALS['BE_USER']->workspace.'</t3_workspaceId>'."\n";
		$XML .= "\t\t" . '<t3_count>'.$accumObj->getFieldCount().'</t3_count>'."\n";
		$XML .= "\t\t" . '<t3_wordCount>'.$accumObj->getWordCount().'</t3_wordCount>'."\n";
		$XML .= "\t\t" . '<t3_internal>' . "\r\t" . implode("\n\t", $this->internalMessges) . "\t\t" . '</t3_internal>' . "\n";
		$XML .= "\t\t" . '<t3_formatVersion>'.L10NMGR_FILEVERSION.'</t3_formatVersion>'."\n";
		$XML .= "\t"   . '</head>'."\n";
		$XML .= implode('', $output) . "\n";
		$XML .= "</TYPO3L10N>"; 

		$this->saveExportFile($XML);
		
		return $XML;
	}

	function getFilename() {

		if ($this->l10ncfgObj->getData('sourceLangStaticId') && t3lib_extMgm::isLoaded('static_info_tables'))        {
			$sourceIso2L = '';
			$staticLangArr = t3lib_BEfunc::getRecord('static_languages',$this->l10ncfgObj->getData('sourceLangStaticId'),'lg_iso_2');
			$sourceIso2L = ' sourceLang="'.$staticLangArr['lg_iso_2'].'"';
		}

		$fileNamePrefix = (trim( $this->l10ncfgObj->getData('filenameprefix') )) ? $this->l10ncfgObj->getData('filenameprefix') : 'export_language' ;

		// Setting filename:
		$filename =  $fileNamePrefix . '_' . $staticLangArr['lg_iso_2'] . '_' . date('dmy-His').'.xml';
		return $filename;
	}

	/**
	 * Build single item of "internal" message
	 *
	 * @param	string		$message
	 * @param	string		$key
	 * @access	private
	 * @return	void
	 */
	function setInternalMessage($message, $key) {
		$this->internalMessges[] = "\t\t" . '<t3_skippedItem>' . "\n\t\t\t\t" . '<t3_description>' . $message . '</t3_description>' . "\n\t\t\t\t" . '<t3_key>' . $key . '</t3_key>' . "\n\t\t\t" . '</t3_skippedItem>' . "\r";
	}

	/**
	* Force a new source language to export the content to translate
	*
	* @param	integer		$id
	* @access	public
	* @return	void
	*/
	function setForcedSourceLanguage($id) {
		$this->forcedSourceLanguage = $id;
	}

}
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS['TYPO3_MODE']['XCLASS']['ext/l10nmgr/views/CATXML/class.tx_l10nmgr_CATXMLView.php'])	{
	include_once($TYPO3_CONF_VARS['TYPO3_MODE']['XCLASS']['ext/l10nmgr/views/CATXML/class.tx_l10nmgr_CATXMLView.php']);
}
?>