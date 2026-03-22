<?php
require_once 'google_config.php';

echo "<h1>🔍 Google OAuth Setup Diagnostic</h1>";

$errors = [];
if (GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID_HERE') $errors[] = "❌ <b>CLIENT_ID</b> is still using the placeholder value.";
if (GOOGLE_CLIENT_SECRET === 'YOUR_GOOGLE_CLIENT_SECRET_HERE') $errors[] = "❌ <b>CLIENT_SECRET</b> is still using the placeholder value.";
if (GOOGLE_REDIRECT_URL === 'YOUR_GOOGLE_REDIRECT_URL_HERE') $errors[] = "❌ <b>REDIRECT_URL</b> is still using the placeholder value.";

if (count($errors) > 0) {
    echo "<h3>Issues Found:</h3><ul>";
    foreach ($errors as $e) echo "<li>$e</li>";
    echo "</ul>";
} else {
    echo "<h3>✅ Configuration matches something real!</h3>";
}

echo "<h3>Your Settings (Check these against Google Console):</h3>";
echo "<ul>";
echo "<li><b>Client ID:</b> " . substr(GOOGLE_CLIENT_ID, 0, 15) . "...</li>";
echo "<li><b>Redirect URL:</b> <code style='background:#eee; padding:2px;'>".GOOGLE_REDIRECT_URL."</code></li>";
echo "<li><b>Scope:</b> " . GOOGLE_SCOPES . "</li>";
echo "</ul>";

echo "<p>💡 <b>Pro Tip:</b> Make sure the Redirect URL in your Google Cloud Console (under 'Authorized Redirect URIs') is EXACTLY:<br><code style='color:blue;'>".GOOGLE_REDIRECT_URL."</code></p>";

echo "<hr><a href='google_auth.php'>Try Connecting Now</a>";
?>
