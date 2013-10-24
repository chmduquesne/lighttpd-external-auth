<?php
/*
 * Copyright (c) 2013 Christophe-Marie Duquesne <chmd@chmd.fr>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

$config = dirname(__FILE__) . '/hybridauth/config.php';
require_once("hybridauth/Hybrid/Auth.php");
require_once('magnet.php');

if (isset($_GET["logout"]) and $_GET["logout"]) {
    magnet_deauthentify();
    header('Location: /');
}

if (isset($_GET["provider"]) and $_GET["provider"]):
    try{
        $hybridauth = new Hybrid_Auth($config);
        $provider = @ trim(strip_tags($_GET["provider"]));
        // openid requires the identifier url as an additional parameter
        if ($provider == "Openid") {
            if (isset($_GET['openid_identifier'])) {
                $openid_identifier = $_GET['openid_identifier'];
                $adapter = $hybridauth->authenticate($provider,
                    array("openid_identifier" => $openid_identifier)
                );
            }
        } else {
            $adapter = $hybridauth->authenticate($provider);
        }
        /*
         * Username extraction:
         * - For facebook/google/linkedin, the username is the email
         *   address, since they verified for us that the user owns it.
         * - For twitter, since their oauth API does not allow us to see
         *   the email, the username is the display name
         * - For openid, since anyone can run their own, there is no
         *   reason to trust the email address. The username is then the
         *   openid url (which is the only guarantee that openid gives)
         *
         * We append the name of the provider to this username.
         */
        $user_profile = $adapter->getUserProfile();
        if ($provider == "Google" or $provider == "Facebook" or
            $provider == "LinkedIn") {
            $identity = $user_profile->email;
        } elseif ($provider == "Twitter"){
            $identity = $user_profile->displayName;
        } else {
            $identity = $user_profile->identifier;
        }
        $identity = $identity . " (" . $provider . ")";

        magnet_authentify($identity);

        // If an url was originally requested, send the user there,
        // But only if the request is in the same domain
        if (isset($_GET["orig_url"])) {
            $orig_url = $_GET["orig_url"];
            if (matches_domain($orig_url)) {
                header("Location: $orig_url");
            }
        }
    }
    catch( Exception $e ){
        switch( $e->getCode() ){
            case 0 : $error = "Unspecified error."; break;
            case 1 : $error = "Hybriauth configuration error."; break;
            case 2 : $error = "Provider not properly configured."; break;
            case 3 : $error = "Unknown or disabled provider."; break;
            case 4 : $error = "Missing provider application credentials."; break;
            case 5 : $error = "Authentication failed. The user has canceled the authentication or the provider refused the connection."; break;
            case 6 : $error = "User profile request failed. Most likely the user is not connected to the provider and he should to authenticate again.";
                        $adapter->logout();
                        break;
            case 7 : $error = "User not connected to the provider.";
                        $adapter->logout();
                        break;
        }
    }
endif;

function echo_orig_url() {
    if (isset($_GET['orig_url'])) {
        echo ("&orig_url=" . $_GET['orig_url']);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to login.chmd.fr</title>
    <link rel="stylesheet" type="text/css" href="css/zocial.css" />
</head>
<body>
    <p>You are about to access private content. Please provide your identity.</p>
    <p>You can use one of these:</p>
    <a href="?provider=Google<?php echo_orig_url() ?>"
        class="zocial icon google"></a>
    <a href="?provider=Facebook<?php echo_orig_url() ?>"
        class="zocial icon facebook"></a>
    <a href="?provider=Twitter<?php echo_orig_url() ?>"
        class="zocial icon twitter"></a>
    <a href="?provider=LinkedIn<?php echo_orig_url() ?>"
        class="zocial icon linkedin"></a>
    <a href="?provider=Openid&openid_identifier=https://me.yahoo.com<?php echo_orig_url() ?>"
        class="zocial icon yahoo"></a>
    <p>You can also use openid:</p>
    <form name="input" action="" method="get">
        <?php
        if (isset($_GET["orig_url"])) {
            echo "<input type='hidden'" .
                 " name='orig_url'" .
                 " value=" . $_GET["orig_url"] .
                 " />";
        }
        ?>
        <input type='hidden' name='provider' value='Openid' />
        <input type='text' name='openid_identifier' style='background: url(css/openid-16x16.gif) no-repeat; padding-left: 16px' />
        <input type="submit" value="Submit">
    </form>
<?php
if (isset($_COOKIE[$magnet_config["identity"]]) and !isset($identity)) {
    $identity = $_COOKIE[$magnet_config["identity"]];
}
if (isset($identity) and $identity) {
    echo '<hr />';
    echo "<p>You appear to be logged in as <code>$identity</code>.</p>";
    echo "<p><a href='?logout=true' class='zocial primary'>logout</a></p>";
}
// if we got an error then we display it here
if (isset($error)) {
    echo '<hr />';
    echo '<p><h2>Error</h2>' . $error . '</p>';
}
?>
</body>
</html>
