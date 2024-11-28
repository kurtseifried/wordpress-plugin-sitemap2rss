# Cutting a new release

Update the files:

```
README.md
trunk/readme.txt
trunk/sitemap2rss.php
trunk/sitemap2rss.php SITEMAP2RSS_VERSION
```

Then cut a new .pot file and update .po files as needed

# Translating

```
wp i18n make-pot . languages/sitemap2rss.pot
```

# SVN Synchronizing from Github repo

```
rsync -av ~GitHub/wordpress-plugin-sitemap2rss/assets/ ./assets/
rsync -av ~GitHub/wordpress-plugin-sitemap2rss/tags/ ./tags/
rsync -av ~GitHub/wordpress-plugin-sitemap2rss/trunk/ ./trunk/
```

# SVN Adding all files

```
svn add *
svn add */*
```

# SVN Commit

```
svn ci -m 'message' --username kurtseifried
```

# Notes on tech used

## wp-cli

https://wp-cli.org/

