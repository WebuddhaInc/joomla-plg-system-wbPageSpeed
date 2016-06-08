#!/bin/bash

#
#
#  Package repository for installation as a ZIP file
#
#

# Repository Path

  RPATH="$2"
  if [ ! -d "$RPATH" ]; then
    if [ -d "./plugin" ]; then
      RPATH="./"
    else
      echo "Invalid Repo Path";
    fi
  fi

  if [[ ! $RPATH =~ [\/$] ]]; then
    RPATH="$RPATH/"
  fi

# Absolute Path

  ARPATH=$( cd $RPATH && pwd )/

# Repository Name

  REPONAME=$(basename `pwd`)
  BASENAME=${REPONAME/plg_content/}
  TEMPPATH="$ARPATH../$REPONAME.package/"
  PACKAGE="$ARPATH$REPONAME.package.zip"

# Echo

  echo "Repository name:"
  echo "$REPONAME"
  echo ""

  echo "Repository path:"
  echo "$ARPATH"
  echo ""

  echo "Temporary Package path:"
  echo "$TEMPPATH"
  echo ""

  echo "Final Package file:"
  echo "$PACKAGE"
  echo ""

  read -p "Press any key to continue... " -n1 -s
  echo

# Check Temp Path

	if [ -d "$TEMPPATH" ]; then
    echo "Temporary Path Already Exists"
		echo "$TEMPPATH"
		exit
	fi

# Delete Existing

	if [ -e "$PACKAGE" ]; then
		rm "$PACKAGE"
	fi

# Package

	mkdir -p "$TEMPPATH/"
	cp -fr "$ARPATH/plugin/." "$TEMPPATH"
	cd "$TEMPPATH"
	zip -ur "$PACKAGE" .
	rm -rf "$TEMPPATH/"