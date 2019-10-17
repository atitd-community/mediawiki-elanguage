<?php

class ELanguage
{
	/**
	 * HOOK (EditPage::attemptSave:after)
	 * Sets the new page content language if the language was changed.
	 *
	 * @param  EditPage  $editpage
     * @param  Status  $status
     * @param  array|bool  $resultDetails
	 */
	public static function onEditPageAttemptSaveAfter( $editpage, $status, $resultDetails ) {
		// Page language didn't change, so just return
		if($editpage->ELangNew == false) {
			return true;
		}

		self::setLanguage( $editpage, $editpage->ELangNew, $editpage->ELangOld );

		return true;
	}

	/**
	 * HOOK (EditPage::importFormData)
	 * Sets up language variables based on the submitted Edit Page form.
	 *
     * @param  EditPage  $editpage
     * @param  WebRequest  $request
	 */
	public static function onEditPageImportFormData( $editpage, $request ) {
		$editpage->ELangNew = false;

		$editpage->ELangOld = $editpage->getTitle()->getPageLanguage()->mCode;

		$newLangCode = $request->getVal('wplanguage');

		// Page language changed, flag it to be updated later
		if($editpage->ELangOld !== $newLangCode) {
			$editpage->ELangNew = $newLangCode;
		}

		return true;
	}

	/**
	 * HOOK (EditPageGetCheckboxesDefinition)
	 * Adds a language selection dropdown to the "Edit Page" forms allowing
	 * users to set the page content language for the page being edited.
	 *
     * @param  EditPage  $editPage
     * @param  array  $checkboxes
	 */
	public static function onEditPageGetCheckboxesDefinition( $editPage, &$checkboxes ) {

		$context = $editPage->getArticle()->getContext();
		$output  = $context->getOutput();
		$user    = $context->getUser();
		$title   = $editPage->getArticle()->getTitle();

		$form = HTMLForm::factory(
			'ooui',
			self::getFormFields($context, $title),
			$context
		);

		$form->prepareForm();

		$html = '<div id="elang-selector-container"><div id="elang-selector-label">Page Language </div>';

		$html .= $form->getBody();

		$html .= '</div>';

		$output->addHTML($html);

		return true;
	}

	/**
	 * HOOK (LanguageLinks)
	 * Creates and combines the list of interlanguage
	 * links to be displayed for the current page.
	 *
     * @param  Title  $title
     * @param  array  $mLanguageLinks
     * @param  array  $linkFlags
	 */
	public static function onLanguageLinks( $title, &$mLanguageLinks, &$linkFlags ) {
		global $wgAlwaysShowLanguages;

		$curPageLang  = $title->getPageLanguage()->getCode();
		$addLanguages = $wgAlwaysShowLanguages;

		// If the page doesn't exist or isn't in the main or Project namespace, don't show language links
		if( !$title->exists() || !$title->inNamespaces([0, 4])) {
			return true;
		}

		// The language of the current page shouldn't have a suffixed link
		if (($key = array_search($curPageLang, $addLanguages)) !== false) {
			unset($addLanguages[$key]);
			$newLanguageLinks[$curPageLang] = $curPageLang.':'.$title->getText();
		}

		// If any "forced" languages have proper links, don't create suffixed links
		foreach($wgAlwaysShowLanguages as $language) {
			$len = strlen($language);
			foreach($mLanguageLinks as $link) {
				if (($language . ':') == (substr($link, 0, ($len + 1)))) {
					if (($key = array_search($language, $addLanguages)) !== false) {
						unset($addLanguages[$key]);
					}
					$newLanguageLinks[$language] = $link;
					continue;
				}
			}
		}

		// Force suffixed links for any languages we're still missing
		if(!empty($addLanguages)) {
			foreach($addLanguages as $lang) {
				$name = $title->getText();
				$newLanguageLinks[$lang] = $lang.':'.$name.'/'.$lang;
			}
		}

		// Sort links by language code
		ksort($newLanguageLinks);

		// Ensure that English is always listed first
		$mLanguageLinks = array('en' => $newLanguageLinks['en']) + $newLanguageLinks;

		return true;
	}

	/**
	 * Updates the page in the database with the new page content language.
	 *
     * @param  EditPage  $editpage
     * @param  string  $newLang
     * @param  string  $oldLang
	 * @return bool
	 */
	public static function setLanguage( $editpage, $newLang, $oldLang ) {
		$connection = wfGetDB( DB_MASTER );

		$connection->onTransactionIdle( function() use ( $connection, $editpage, $newLang ) {

			$pageId = $editpage->getTitle()->getArticleID();

			$connection->update(
				'page',
				[
					'page_lang' => $newLang
				],
				[
					'page_id'   => $pageId
				],
				__METHOD__
			);

		} );

		// If it's a new page, we don't want to see a language update in Recent Changes
		// TODO: This function seems unreliable at the moment, probably due to bugs
		// if($editpage->getTitle()->isNewPage()) { return true; }

		// Force re-render so that language-based content (parser functions etc.) gets updated
		$editpage->getTitle()->invalidateCache();

		self::logLanguageChange( $editpage, $newLang, $oldLang );

		return true;
	}

	/**
	 * Adds a log entry for a page content language change.
	 *
     * @param  EditPage  $editpage
     * @param  string  $newLang
     * @param  string  $oldLang
	 * @return bool
	 */
	public static function logLanguageChange( $editpage, $newLang, $oldLang ) {
		$user  = $editpage->getContext()->getUser();
		$title = $editpage->getTitle();

		$logParams = [
			'4::oldlanguage' => $oldLang,
			'5::newlanguage' => $newLang
		];
		$entry = new ManualLogEntry( 'pagelang', 'pagelang' );
		$entry->setPerformer( $user );
		$entry->setTarget( $title );
		$entry->setParameters( $logParams );

		$logid = $entry->insert();
		$entry->publish( $logid );

		return true;
	}

	/**
	 * Builds a language selector dropdown menu.
	 *
     * @param  RequestContext  $context
     * @param  Title  $title
	 * @return array
	 */
	public static function getFormFields($context, $title) {
		$userLang = $context->getLanguage()->getCode();
		$languages = Language::fetchLanguageNames( $userLang, 'mwfile' );
		ksort( $languages );
		$options = [];
		foreach ( $languages as $code => $name ) {
			$options["$code - $name"] = $code;
		}

		$fields['language'] = [
			'id' => 'mw-pl-languageselector',
			'cssclass' => 'mw-languageselector',
			'type' => 'select',
			'options' => $options,
			'default' => $title ?
				$title->getPageLanguage()->getCode() :
				$context->getConfig()->get( 'LanguageCode' ),
		];

		return $fields;
	}
}