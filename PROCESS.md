# Cutting a new release

## Updating the code and data in GitHub

### Update the files:

```
README.md
trunk/readme.txt
trunk/sitemap2rss.php
trunk/sitemap2rss.php SITEMAP2RSS_VERSION
```

Then cut a new .pot file and update .po files as needed

### Translating

```
wp i18n make-pot . languages/sitemap2rss.pot

for i in *; do sed 's/sitemap2rss 1\.0\.9/sitemap2rss 1.0.10/' "$i" > "$i.2"; mv -f "$i.2" "$i"; done

rm *.mo

../../utils/convert-translation-files-po-to-mo.sh
```

### Cut a new release in GitHub

1. https://github.com/kurtseifried/wordpress-plugin-sitemap2rss/releases
2. Click "Draft a new release"
3. Click "Choose a tag"
4. Type in the new version e.g. "v1.2.3"
5. Click "Create new tag"
6. Describe the release in title and notes
7. Click "Publish Release"


# SVN Synchronizing from Github repo

## Make a new tag directory and synch the files over:

```
mkdir mkdir ~/GitHub/sitemap2rss/tags/NEW-RELEASE-NUMBER
rsync -av ~/GitHub/wordpress-plugin-sitemap2rss/assets/ ~/GitHub/sitemap2rss/assets/
rsync -av ~/GitHub/wordpress-plugin-sitemap2rss/trunk ~/GitHub/sitemap2rss/tags/NEW-RELEASE-NUMBER
rsync -av ~/GitHub/wordpress-plugin-sitemap2rss/trunk/ ~/GitHub/sitemap2rss/trunk/
```

## SVN Adding all files

```
svn stat
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

