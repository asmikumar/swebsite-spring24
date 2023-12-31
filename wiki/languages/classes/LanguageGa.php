<?php

/** Irish (Gaeilge)
 *
 * @ingroup Language
 */
class LanguageGa extends Language {
	# Convert day names
	# Invoked with {{GRAMMAR:transformation|word}}
	function convertGrammar( $word, $case ) {
		global $wgGrammarForms;
		if ( isset($wgGrammarForms['ga'][$case][$word]) ) {
			return $wgGrammarForms['ga'][$case][$word];
		}

		switch ( $case ) {
		case 'ainmlae':
			switch ($word) {
			case 'an Domhnach':
				$word = 'Dé Domhnaigh'; break;
			case 'an Luan':
				$word = 'Dé Luain'; break;
			case 'an Mháirt':
				$word = 'Dé Mháirt'; break;
			case 'an Chéadaoin':
				$word = 'Dé Chéadaoin'; break;
			case 'an Déardaoin':
				$word = 'Déardaoin'; break;
			case 'an Aoine':
				$word = 'Dé hAoine'; break;
			case 'an Satharn':
				$word = 'Dé Sathairn'; break;
			}
		}
		return $word;
	}

	function convertPlural( $count, $forms ) {
		if ( !count($forms) ) { return ''; }

		// plural forms per http://unicode.org/repos/cldr-tmp/trunk/diff/supplemental/language_plural_rules.html#ga
		$forms = $this->preConvertPlural( $forms, 3 );

		if ( $count == 1 ) {
			$index = 0;
		} elseif( $count == 2 ) {
			$index = 1;
		} else {
			$index = 2;
		}
		return $forms[$index];
	}
}
