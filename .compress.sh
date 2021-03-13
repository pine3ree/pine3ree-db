#!/bin/sh

curDate=`date "+%Y%m%d-%H"`;
outDir=".bak/${curDate}"

mkdir -p "${outDir}"

tar -jcf "${outDir}/git.tar.bz2" .git
tar -jcf "${outDir}/src.tar.bz2" src
tar -jcf "${outDir}/test.tar.bz2" test

tar -jcf "${outDir}/files.tar.bz2" .gitignore composer.json *.md phpcs.xml phpstan.neon phpunit.xml.dist travis.yml


