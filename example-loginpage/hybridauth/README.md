Installation instructions for
[hybridauth](http://hybridauth.sourceforge.net/):

- Get the hybridauth core library from
  [http://hybridauth.sourceforge.net/download.html](http://hybridauth.sourceforge.net/download.html),
  (HybridAuth-2.1.2 as this file is being written). You do not need the
  additional provider packages.
- Unzip hybridauth-2.1.2.zip, you will obtain a directory named
  hybridauth-2.1.2/, containing several files and directory: 'CHANGELOG',
  'README.html', 'hybridauth/', 'examples'.
- Remove the directory containing this file (we will replace it with the
  'hybridauth' directory from the archive). Make sure you save this file
  somewhere else before doing so.
- Move all the files in 'hybridauth-2.1.2/' at the document root of your
  login page website, along with the "index.php" and "magnet.php" files.
  It should be such that the hybridauth directory replaces the one you
  just removed.
- Visit hybridauth/README.html with a browser and configure the following
  identity providers: Google, Facebook, Twitter, LinkedIn.
- Once everything works fine, remove README.html, CHANGELOG, and the
  examples directory. You should be left with 'index.php', 'magnet.php',
  'css/' and 'hybridauth/'.
