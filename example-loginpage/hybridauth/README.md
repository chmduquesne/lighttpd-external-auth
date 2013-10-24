Installation instructions for
[hybridauth](http://hybridauth.sourceforge.net/):

- Go to their [download
  page](http://hybridauth.sourceforge.net/download.html), and pick the
  core library (HybridAuth-2.1.2 when this README is written). No need for
  the additional provider packages (at least for the login page provided
  in this project).
- Remove the directory containing this file (we will replace it with the
  'hybridauth' directory from the Hybridauth library)
- Unzip hybridauth-2.1.2.zip, you will obtain a directory named
  hybridauth-2.1.2, containing several files and directory: 'CHANGELOG',
  'README.html', 'hybridauth/', 'examples'.
- Move everything at the document root of your login page website, along
  with the "index.php" and "magnet.php" files provided with this project.
  It should be such that the hybridauth directory replaces the one you
  just removed.
- Visit the README.html page from hybridauth with a browser and configure
  the following identity providers: Google, Facebook, Twitter, LinkedIn.
- Once everything works fine, remove README.html, CHANGELOG, and the
  examples directory. You should be left with 'index.php', 'magnet.php',
  'css/' and 'hybridauth/'. The 'README.md' can stay, though it is not
  necessary.
