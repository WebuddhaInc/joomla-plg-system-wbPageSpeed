#!/bin/bash

# Testing

  # echo $(pwd)
  # echo $( cd $( dirname "${BASH_SOURCE[0]}" ) && pwd )
  # exit

# Joomla Path

  JPATH="$1"
  if [ ! -d "$JPATH" ]; then
    if [ -d "../../public_html/administrator" ] && [ -d "../../public_html/components" ]; then
      JPATH="../../public_html/"
    else
      echo "Invalid Joomla Path";
    fi
  fi

  if [[ ! $JPATH =~ [\/$] ]]; then
    JPATH="$JPATH/"
  fi

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
  AJPATH=$( cd $JPATH && pwd )/

# Echo

  echo "Repository path:"
  echo "$ARPATH"
  echo ""

  echo "Joomla path:"
  echo "$AJPATH"
  echo ""

  read -p "Press any key to continue... " -n1 -s
  echo

# link function

  function doRepoLink {
    sourcePath=$( cd $RPATH && pwd )/$1
    targetPath=$( cd "$JPATH"$2 && pwd )/
    targetName=$3
    relsrcPath=$( "$ARPATH".tools/getRelativePath.sh $targetPath $sourcePath )
    if [ -e $targetPath$targetName ] && [ -L $targetPath$targetName ]; then
      echo "Removing symbolic link:"
      echo $targetPath$targetName
      rm $targetPath$targetName
    fi
    if [ ! -e $sourcePath ]; then
      echo "Source path NOT Found:"
      echo $sourcePath
      echo ""
      return
    fi
    if [ ! -e $targetPath$targetName ]; then
      echo "Creating symbolic link:"
      echo $targetPath$targetName
      $( cd $targetPath && ln -s $relsrcPath $targetName )
    else
      echo "Target exists:"
      echo $targetPath$targetName
    fi
    echo ""
  }

# link plugin

  doRepoLink plugin plugins/system wbpagespeed

