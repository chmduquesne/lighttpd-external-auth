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

/*
 * magnet_config variables
 *
 * These must match the magnet_config in the lua script
 */
$magnet_config = array();
$magnet_config["token_validity"] = 86400;
$magnet_config["access_token"] = "access_token";
$magnet_config["identity"] = "identity";
$magnet_config["secret_file"] = "/var/run/lighttpd/openid_seed";

/*
 * /!\ CHANGE ME!
 *
 * Domain on which the cookie should be valid
 *
 * /!\ CHANGE ME!
 */
$magnet_config["domain"] = "chmd.fr";

/*
 * You should invoke this function with the user identity as soon as this
 * identity is confirmed.
 *
 * This function signs the identity with the secret, then writes this
 * information in a cookie. You should then return the user to the
 * originally requested page.
 */
function magnet_authentify($identity) {
    global $magnet_config;
    // get the timestamp
    $t = time();
    $timestamp = (string)($t - ($t % $magnet_config["token_validity"]));
    // Compute the token
    $secret = file_get_contents($magnet_config["secret_file"]) or "";
    $message = $identity . $timestamp;
    $access_token = base64_encode(hash_hmac("sha1", $message, $secret, true));
    // Set identity and access token, so that the magnet script can verify us
    $cookie_expires = $timestamp + $magnet_config["token_validity"];
    setcookie($magnet_config["identity"], $identity, $cookie_expires, "/",
        $magnet_config["domain"], true, true);
    setcookie($magnet_config["access_token"], $access_token,
        $cookie_expires, "/", $magnet_config["domain"], true, true);
}

function magnet_deauthentify() {
    global $magnet_config;
    setcookie($magnet_config["identity"], "", 1, "/",
        $magnet_config["domain"], true, true);
    setcookie($magnet_config["access_token"], "", 1, "/",
        $magnet_config["domain"], true, true);
}

/*
 * Returns true if the input url matches the domain on which the cookie is
 * valid
 */
function matches_domain($url) {
    global $magnet_config;
    $url_parts = parse_url($url);
    $host = $url_parts["host"];
    $host_parts = explode('.', $host);
    $domain = $magnet_config["domain"];
    $domain_parts = explode('.', $domain);
    $allowed = True;
    for ($i = 1; $i <= count($domain_parts); $i++) {
        $d = array_pop($domain_parts);
        $h = array_pop($host_parts);
        $allowed = $allowed && ($h == $d);
    }
    return $allowed;
}
?>
