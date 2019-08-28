This is a MediaWiki extension that adds some simple functionality to help manage the multilingual nature of the ATITD Wiki.

Installation
============

  * Download and place the files in a directory called `ELanguage` in your `extensions/` folder.
  * Add the following code at the bottom of your `LocalSettings.php`:

```php
wfLoadExtension( 'Interwiki' );
wfLoadExtension( 'ELanguage' );
$wgPageLanguageUseDB = true;
$wgGroupPermissions['user']['pagelang'] = true;
$wgAlwaysShowLanguages = [ "en", "fr" ];
```

  * Feel free to add additional language codes to the `$wgAlwaysShowLanguages` configuration array. Language codes included here will *always* display links to default interlanguage pages (e.g. `Bricks` linking to `Bricks/fr`), regardless of manually defined interlanguage links.
  * The Interwiki extension is required and any language codes you'd like to use should be added as interlanguage prefixes with a URL pointing back to the Wiki, and with the Forward and Transclude (iw_local and iw_trans) flags toggled on.
  * **Done** — Navigate to `Special:Version` on your wiki to verify that the extension is successfully installed.