#! /bin/bash
# A modification of deploy script as found here: https://raw.githubusercontent.com/thenbrent/multisite-user-management/master/deploy.sh
# Would be great if WordPress Plugin Directory gets git support, so we do not have to have deploy scripts like this...

# main config
PLUGINSLUG="$(basename `pwd`)"
CURRENTDIR=`pwd`
MAINFILE="$PLUGINSLUG.php"

# git config
GITPATH="$CURRENTDIR/" # this file should be in the base of your git repository

# svn config
SVNPATH="/tmp/$PLUGINSLUG" # path to a temp SVN repo. No trailing slash required and don't add trunk.
SVNURL="http://plugins.svn.wordpress.org/$PLUGINSLUG/" # Remote SVN repo on wordpress.org, with no trailing slash
SVNUSER="alfreddatakillen" # your svn username

# Run unit tests just to be sure
make test || exit 1;

# Let's begin...
echo "Preparing to deploy wordpress plugin..."

# Testing git repo state:
if [ "$(git branch)" != "* master" ]; then
	echo "You must be in your master bransch to deploy. (Tests should run from master branch, to make sure you merged everything into it.)"
	exit 1;
fi

if [ "$(git status | grep "Your branch is ahead of")" != "" ]; then
	echo "Commit and push all your changes before doing the deploy (just to check there is no unmerged code at your remote).";
	exit 1;
fi

if [ "$(git status | grep "Changes not staged for commit")" != "" ]; then
	echo "Everything is not staged and committed to Git.";
	exit 1;
fi

if [ "$(git status | grep "Changes to be committed")" != "" ]; then
	echo "Everything is not committed to Git.";
	exit 1;
fi

# Test license:
if [ "$(cat $MAINFILE | grep ^License: | sed 's/^[^ ]* //')" != "$(cat composer.json | grep '"license": "' | sed 's/^[^:]*: "//' | sed 's/",$//')" ]; then
	echo "License in $MAINFILE does not match license in composer.json."
	exit 1;
fi

# Make sure the plugin URL matches the slug, so nothing is fucked up.
if [ "$(cat $MAINFILE | grep "Plugin URI: http://wordpress.org/extend/plugins/$PLUGINSLUG/")" = "" ]; then
	echo "The plugin URI in $MAINFILE does not match the plugin slug.";
	exit 1;
fi

# Check if subversion is installed before getting all worked up
if [ $(dpkg-query -W -f='${Status}' subversion 2>/dev/null | grep -c "ok installed") != "1" ]
then
	echo "You'll need to install subversion before proceeding. Exiting.";
	exit 1;
fi

# Check version in readme.txt is the same as plugin file after translating both to unix line breaks to work around grep's failure to identify mac line breaks
NEWVERSION1=`grep "^Stable tag:" $GITPATH/readme.txt | awk -F' ' '{print $NF}'`
echo "readme.txt version: $NEWVERSION1"
NEWVERSION2=`grep "^Version:" $GITPATH/$MAINFILE | awk -F' ' '{print $NF}'`
echo "$MAINFILE version: $NEWVERSION2"

if [ "$NEWVERSION1" != "$NEWVERSION2" ]; then echo "Version in readme.txt & $MAINFILE don't match. Exiting."; exit 1; fi

echo "Versions match in readme.txt and $MAINFILE. Let's proceed..."

if git show-ref --tags --quiet --verify -- "refs/tags/$NEWVERSION1"
	then 
		echo "Version $NEWVERSION1 already exists as git tag. Exiting...."; 
		exit 1; 
	else
		echo "Git version does not exist. Let's proceed..."
fi

cd $GITPATH
echo -e "Enter a commit message for this new version: \c"
read COMMITMSG
git commit -am "$COMMITMSG"

echo "Tagging new version in git"
git tag -a "$NEWVERSION1" -m "Tagging version $NEWVERSION1"

echo "Pushing latest commit to origin, with tags"
git push origin master
git push origin master --tags

echo "remove old svn repo if there is one:"
rm -Rf $SVNPATH

echo 
echo "Creating local copy of SVN repo ..."
svn co $SVNURL $SVNPATH

echo "Clearing svn repo so we can overwrite it"
rm -Rf $SVNPATH/trunk/*

echo "Exporting the HEAD of master from git to the trunk of SVN"
git checkout-index -a -f --prefix=$SVNPATH/trunk/

echo "Remove git stuff from svd repo"
rm -Rf $SVNPATH/trunk/README.md # The WordPress Plugin Directory seems to get confused when this is included.
rm -Rf $SVNPATH/trunk/.git
rm -Rf $SVNPATH/trunk/.gitignore

echo "Changing directory to SVN and committing to trunk"
cd $SVNPATH/trunk/
# Delete what is missing:
svn delete $( svn status | sed -e '/^!/!d' -e 's/^!//' )
# Add all that is there:
svn add . --force
# commit it:
svn commit --username=$SVNUSER -m "$COMMITMSG"

echo "Creating new SVN tag & committing it"
cd $SVNPATH
svn copy trunk/ tags/$NEWVERSION1/
cd $SVNPATH/tags/$NEWVERSION1
svn commit --username=$SVNUSER -m "Tagging version $NEWVERSION1"

echo "Removing temporary directory $SVNPATH"
rm -fr $SVNPATH/

echo "*** FIN ***"
