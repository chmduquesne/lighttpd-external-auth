This is the actual php code running
[login.chmd.fr](https://login.chmd.fr). It uses
[hybridauth](http://hybridauth.sourceforge.net/) and
[css-social-buttons](http://zocial.smcllns.com/).

Installation instructions:

- Follow the instructions of css/README.md
- Follow the instructions of hybridauth/README.md
- In `magnet.php`, set `$magnet_config["domain"]` to your own domain
  name.

        $magnet_config["domain"] = "example.com";

Done!
