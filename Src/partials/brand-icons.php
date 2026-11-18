<?php

/**
 * Favicon, Apple touch icon and theme colour for every page head.
 *
 * Set $brandAssetsPath to the relative path of the static/ folder before
 * including this file when the page is not served from the app root
 * (e.g. "../../static/" for api/v1/swagger.php).
 */
$brandIconsPath = htmlspecialchars(($brandAssetsPath ?? "static/") . "brand/", ENT_QUOTES);
?>
<link rel="icon" type="image/svg+xml" href="<?php echo $brandIconsPath; ?>favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo $brandIconsPath; ?>favicon-32.png">
<link rel="apple-touch-icon" href="<?php echo $brandIconsPath; ?>apple-touch-icon.png">
<meta name="theme-color" content="#0d1117">
